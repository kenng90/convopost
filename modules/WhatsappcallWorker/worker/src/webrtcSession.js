import wrtc from '@roamhq/wrtc';

const { RTCPeerConnection, nonstandard } = wrtc;

/**
 * Answer a WhatsApp WebRTC offer and return { sdp, peerConnection, cleanup }.
 */
export async function createWhatsAppAnswer(offerSdp, offerType = 'offer') {
  const pc = new RTCPeerConnection({
    iceServers: [{ urls: 'stun:stun.l.google.com:19302' }],
  });

  const audioSource = new nonstandard.RTCAudioSource();
  const outboundTrack = audioSource.createTrack();

  try {
    pc.addTrack(outboundTrack);

    const sanitized = sanitizeSdp(offerSdp);
    await pc.setRemoteDescription({ type: offerType, sdp: sanitized });

    const answer = await pc.createAnswer({ offerToReceiveAudio: true });
    await pc.setLocalDescription(answer);

    await waitForIceGathering(pc);

    const sdp = normalizeLineEndings(pc.localDescription?.sdp || '');
    if (!sdp) {
      throw new Error('Empty local SDP after answer');
    }

    return {
      sdp,
      peerConnection: pc,
      audioSource,
      outboundTrack,
      cleanup: () => {
        try {
          outboundTrack.stop();
        } catch {
          /* ignore */
        }
        try {
          pc.close();
        } catch {
          /* ignore */
        }
      },
    };
  } catch (err) {
    try {
      outboundTrack.stop();
    } catch {
      /* ignore */
    }
    try {
      pc.close();
    } catch {
      /* ignore */
    }
    throw err;
  }
}

function waitForIceGathering(pc, timeoutMs = 8000) {
  return new Promise((resolve, reject) => {
    if (pc.iceGatheringState === 'complete') {
      resolve();
      return;
    }
    const timer = setTimeout(() => {
      reject(new Error('ICE gathering timeout'));
    }, timeoutMs);
    pc.addEventListener('icegatheringstatechange', () => {
      if (pc.iceGatheringState === 'complete') {
        clearTimeout(timer);
        resolve();
      }
    });
  });
}

function normalizeLineEndings(text) {
  return (text || '').replace(/\r?\n/g, '\r\n');
}

/** Strip codecs Meta often rejects (aligned with Convocon browser sanitizer). */
function sanitizeSdp(sdp) {
  const original = normalizeLineEndings(sdp || '');
  const lines = original.split('\r\n');
  const removePayloads = new Set();

  for (const line of lines) {
    const m = line.match(/^a=rtpmap:(\d+)\s+([^/]+)\//i);
    if (m) {
      const codec = (m[2] || '').toLowerCase();
      if (codec === 'telephone-event' || codec === 'cn') {
        removePayloads.add(m[1]);
      }
    }
  }

  const filtered = [];
  let audioMLineIndex = -1;

  for (const line of lines) {
    if (!line) {
      filtered.push(line);
      continue;
    }
    if (/^m=audio /i.test(line)) {
      audioMLineIndex = filtered.length;
      filtered.push(line);
      continue;
    }
    if (/^a=ssrc:\d+\s+/i.test(line)) continue;
    if (/^a=ptime:\d+/i.test(line)) continue;
    if (/^a=maxptime:\d+/i.test(line)) continue;
    const rtp = line.match(/^a=(rtpmap|fmtp|rtcp-fb):(\d+)/i);
    if (rtp && removePayloads.has(rtp[2])) continue;
    filtered.push(line);
  }

  if (audioMLineIndex >= 0) {
    const parts = filtered[audioMLineIndex].split(' ');
    if (parts.length >= 4) {
      const header = parts.slice(0, 3);
      const pts = parts.slice(3).filter((pt) => !removePayloads.has(pt));
      filtered[audioMLineIndex] = [...header, ...pts].join(' ');
    }
  }

  let out = filtered.join('\r\n');
  if (!out.endsWith('\r\n')) out += '\r\n';
  return out;
}

export { nonstandard };
