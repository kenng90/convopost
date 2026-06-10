import { nonstandard } from './webrtcSession.js';
import { resamplePcm16, pcm16ToBase64 } from './audioResample.js';
import { AudioOutputQueue } from './audioOutputQueue.js';
import { config } from './config.js';
import { logInfo, logWarn, logError, logDebug } from './logger.js';
import { addError } from './sessionDebug.js';

const { RTCAudioSink } = nonstandard;

/**
 * Bridges WhatsApp WebRTC audio ↔ OpenAI Realtime (24 kHz PCM16).
 */
export class AudioBridge {
  constructor({ peerConnection, audioSource, realtimeClient, debug }) {
    this.peerConnection = peerConnection;
    this.audioSource = audioSource;
    this.realtimeClient = realtimeClient;
    this.debug = debug;
    this.sink = null;
    this.outputQueue = new AudioOutputQueue(audioSource, config.webrtcAudioRate);
    this.openAiRate = config.openaiAudioRate;
    this.webrtcRate = config.webrtcAudioRate;
    this.stopped = false;
    this.loggedFirstIn = false;
    this.loggedFirstOut = false;
  }

  async start() {
    logInfo('Waiting for remote WhatsApp audio track', {
      connection_state: this.peerConnection.connectionState,
      ice_state: this.peerConnection.iceConnectionState,
    });

    const track = await waitForRemoteAudioTrack(this.peerConnection, config.remoteTrackTimeoutMs);

    if (this.debug) this.debug.remote_track_received = true;

    logInfo('Remote audio track attached', {
      track_id: track.id,
      ready_state: track.readyState,
      enabled: track.enabled,
    });

    this.sink = new RTCAudioSink(track);
    this.sink.ondata = (data) => {
      if (this.stopped) return;
      const samples = data.samples;
      const rate = data.sampleRate || this.webrtcRate;
      if (!samples?.length) return;

      if (this.debug) this.debug.audio_in_frames += 1;

      if (!this.loggedFirstIn) {
        this.loggedFirstIn = true;
        logInfo('First caller audio frame received', {
          sample_rate: rate,
          samples: samples.length,
          channel_count: data.channelCount ?? 1,
        });
      }

      const mono = downmixToMono(samples, data.channelCount ?? 1);
      const pcm24 = resamplePcm16(mono, rate, this.openAiRate);
      if (pcm24.length > 0) {
        this.realtimeClient.appendAudioPcm16Base64(pcm16ToBase64(pcm24));
      }
    };

    this.realtimeClient.onAudioDelta = (base64Delta) => {
      if (this.stopped) return;
      try {
        if (this.debug) this.debug.audio_out_chunks += 1;

        if (!this.loggedFirstOut) {
          this.loggedFirstOut = true;
          logInfo('First OpenAI audio chunk received — playing to caller');
        }

        const buf = Buffer.from(base64Delta, 'base64');
        const pcm24 = new Int16Array(buf.buffer, buf.byteOffset, buf.byteLength / 2);
        const pcmWebrtc = resamplePcm16(pcm24, this.openAiRate, this.webrtcRate);
        this.outputQueue.push(pcmWebrtc);
      } catch (e) {
        logWarn('Failed to decode OpenAI audio delta', { error: e.message });
        if (this.debug) addError(this.debug, `audio decode: ${e.message}`);
      }
    };

    this.peerConnection.addEventListener('connectionstatechange', () => {
      const state = this.peerConnection.connectionState;
      if (this.debug) this.debug.peer_connection_state = state;
      logInfo('WebRTC connection state', { state, ice: this.peerConnection.iceConnectionState });
    });

    return track;
  }

  stop() {
    this.stopped = true;
    logInfo('Audio bridge stopped', {
      audio_in_frames: this.debug?.audio_in_frames,
      audio_out_chunks: this.debug?.audio_out_chunks,
    });
    try {
      this.sink?.stop();
    } catch {
      /* ignore */
    }
    this.outputQueue?.stop();
  }
}

function waitForRemoteAudioTrack(pc, timeoutMs) {
  return new Promise((resolve, reject) => {
    const existing = pc
      .getReceivers?.()
      .map((r) => r.track)
      .find((t) => t?.kind === 'audio' && t.readyState === 'live');
    if (existing) {
      logDebug('Found existing remote audio track');
      resolve(existing);
      return;
    }

    const timer = setTimeout(() => {
      logError('Remote audio track timeout', {
        timeout_ms: timeoutMs,
        connection_state: pc.connectionState,
        receivers: pc.getReceivers?.().length,
      });
      reject(new Error(`Timed out waiting for remote audio track after ${timeoutMs}ms`));
    }, timeoutMs);

    pc.addEventListener('track', (event) => {
      logDebug('WebRTC track event', { kind: event.track?.kind, id: event.track?.id });
      if (event.track?.kind === 'audio') {
        clearTimeout(timer);
        resolve(event.track);
      }
    });
  });
}

function downmixToMono(samples, channelCount) {
  if (channelCount <= 1) return samples;
  const frames = Math.floor(samples.length / channelCount);
  const mono = new Int16Array(frames);
  for (let i = 0; i < frames; i++) {
    let sum = 0;
    for (let c = 0; c < channelCount; c++) {
      sum += samples[i * channelCount + c];
    }
    mono[i] = Math.round(sum / channelCount);
  }
  return mono;
}
