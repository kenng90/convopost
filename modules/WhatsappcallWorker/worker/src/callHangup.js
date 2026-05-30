import { config } from './config.js';
import { logInfo, logDebug } from './logger.js';

const TERMINAL_PC_STATES = new Set(['closed', 'failed', 'disconnected']);
const TERMINAL_ICE_STATES = new Set(['closed', 'failed', 'disconnected']);
const TERMINAL_CALL_STATUSES = new Set(['terminate', 'terminated', 'completed', 'failed', 'ended']);

/**
 * Resolve when the WhatsApp caller hangs up — multiple signals because node-wrtc
 * often never fires connectionState=closed when Meta ends the call.
 */
export function waitForCallEnd({ peerConnection, laravel, remoteTrack = null }) {
  const cleanup = createCleanupRegistry();

  return new Promise((resolve) => {
    const finish = (reason) => {
      cleanup.runAll();
      logInfo('Call end detected', { reason });
      resolve();
    };

    waitForPeerDisconnect(peerConnection, finish, cleanup);
    waitForRemoteTrackEnded(remoteTrack, peerConnection, finish, cleanup);
    waitForLaravelCallEnded(laravel, finish, cleanup);
    cleanup.addTimer(setTimeout(() => finish('max_call_duration'), config.maxCallDurationMs));
  });
}

function createCleanupRegistry() {
  const fns = [];

  return {
    add(fn) {
      fns.push(fn);
    },
    addTimer(timer) {
      fns.push(() => clearTimeout(timer));
    },
    addInterval(timer) {
      fns.push(() => clearInterval(timer));
    },
    runAll() {
      while (fns.length) {
        try {
          fns.pop()();
        } catch {
          /* ignore */
        }
      }
    },
  };
}

function waitForPeerDisconnect(pc, finish, cleanup) {
  let finished = false;

  const done = (reason) => {
    if (finished) return;
    finished = true;
    pc.removeEventListener('connectionstatechange', onConnectionChange);
    pc.removeEventListener('iceconnectionstatechange', onIceChange);
    pc.removeEventListener('signalingstatechange', onSignalingChange);
    finish(reason);
  };

  const checkStates = () => {
    if (TERMINAL_PC_STATES.has(pc.connectionState)) {
      done(`webrtc:connectionState=${pc.connectionState}`);
      return;
    }
    if (TERMINAL_ICE_STATES.has(pc.iceConnectionState)) {
      done(`webrtc:iceConnectionState=${pc.iceConnectionState}`);
    }
  };

  const onConnectionChange = () => {
    logInfo('WebRTC connection state', { state: pc.connectionState });
    checkStates();
  };

  const onIceChange = () => {
    logInfo('WebRTC ICE state', { state: pc.iceConnectionState });
    checkStates();
  };

  const onSignalingChange = () => {
    logDebug('WebRTC signaling state', { state: pc.signalingState });
    if (pc.signalingState === 'closed') {
      done('webrtc:signalingState=closed');
    }
  };

  cleanup.addInterval(setInterval(checkStates, 1500));
  cleanup.add(() => {
    pc.removeEventListener('connectionstatechange', onConnectionChange);
    pc.removeEventListener('iceconnectionstatechange', onIceChange);
    pc.removeEventListener('signalingstatechange', onSignalingChange);
  });

  checkStates();
  if (finished) return;

  pc.addEventListener('connectionstatechange', onConnectionChange);
  pc.addEventListener('iceconnectionstatechange', onIceChange);
  pc.addEventListener('signalingstatechange', onSignalingChange);
}

function waitForRemoteTrackEnded(initialTrack, pc, finish, cleanup) {
  let finished = false;

  const done = (reason) => {
    if (finished) return;
    finished = true;
    finish(`media:${reason}`);
  };

  const attach = (track) => {
    if (!track || track.kind !== 'audio') return;

    if (track.readyState === 'ended') {
      done('track already ended');
      return;
    }

    const onEnded = () => done('track ended event');
    track.addEventListener('ended', onEnded);
    cleanup.add(() => track.removeEventListener('ended', onEnded));
    track.addEventListener('mute', () => logDebug('Remote audio track muted'));
  };

  attach(initialTrack);

  if (!initialTrack) {
    const onTrack = (event) => {
      if (event.track?.kind === 'audio') {
        attach(event.track);
      }
    };
    pc.addEventListener('track', onTrack);
    cleanup.add(() => pc.removeEventListener('track', onTrack));
  }

  cleanup.addInterval(setInterval(() => {
    const track = initialTrack
      ?? pc.getReceivers?.().map((r) => r.track).find((t) => t?.kind === 'audio');
    if (track?.readyState === 'ended') {
      done('track readyState=ended');
    }
  }, 1500));
}

function waitForLaravelCallEnded(laravel, finish, cleanup) {
  const pollMs = parseInt(process.env.CALL_STATUS_POLL_MS || '2000', 10);
  let finished = false;

  const poll = async () => {
    if (finished) return;
    try {
      const res = await laravel.show();
      const status = String(res?.call?.status || '').toLowerCase();
      if (TERMINAL_CALL_STATUSES.has(status)) {
        finished = true;
        finish(`laravel:status=${status}`);
      }
    } catch (e) {
      logDebug('Laravel call status poll failed', { error: e.message });
    }
  };

  poll();
  cleanup.addInterval(setInterval(poll, pollMs));
}
