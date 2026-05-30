/**
 * Linear PCM16 resampling for telephony bridges (e.g. 48 kHz WebRTC ↔ 24 kHz OpenAI Realtime).
 */
export function resamplePcm16(samples, fromRate, toRate) {
  if (!samples?.length || fromRate === toRate) {
    return samples ?? new Int16Array(0);
  }

  const ratio = toRate / fromRate;
  const outLength = Math.max(1, Math.round(samples.length * ratio));
  const out = new Int16Array(outLength);

  for (let i = 0; i < outLength; i++) {
    const srcIdx = i / ratio;
    const idx0 = Math.floor(srcIdx);
    const idx1 = Math.min(idx0 + 1, samples.length - 1);
    const frac = srcIdx - idx0;
    out[i] = Math.round(samples[idx0] * (1 - frac) + samples[idx1] * frac);
  }

  return out;
}

export function pcm16ToBase64(samples) {
  return Buffer.from(samples.buffer, samples.byteOffset, samples.byteLength).toString('base64');
}

export function base64ToPcm16(base64) {
  const buf = Buffer.from(base64, 'base64');
  return new Int16Array(buf.buffer, buf.byteOffset, buf.byteLength / 2);
}

export function concatPcm16(a, b) {
  if (!a?.length) return b ?? new Int16Array(0);
  if (!b?.length) return a;
  const out = new Int16Array(a.length + b.length);
  out.set(a, 0);
  out.set(b, a.length);
  return out;
}
