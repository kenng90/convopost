/**
 * Buffers PCM16 and feeds 10 ms frames to RTCAudioSource at the correct sample rate.
 */
export class AudioOutputQueue {
  constructor(audioSource, sampleRate = 48000) {
    this.audioSource = audioSource;
    this.sampleRate = sampleRate;
    this.frameSamples = Math.round(sampleRate / 100);
    /** @type {Int16Array} */
    this.buffer = new Int16Array(0);
    this.stopped = false;
    this.timer = setInterval(() => this.tick(), 10);
  }

  push(samples) {
    if (this.stopped || !samples?.length) return;
    this.buffer = concatInternal(this.buffer, samples);
  }

  tick() {
    if (this.stopped) return;

    if (this.buffer.length >= this.frameSamples) {
      const frame = this.buffer.slice(0, this.frameSamples);
      this.buffer = this.buffer.slice(this.frameSamples);
      this.audioSource.onData({
        samples: frame,
        sampleRate: this.sampleRate,
        bitsPerSample: 16,
        channelCount: 1,
        numberOfFrames: frame.length,
      });
    }
  }

  stop() {
    this.stopped = true;
    clearInterval(this.timer);
    this.buffer = new Int16Array(0);
  }
}

function concatInternal(a, b) {
  const out = new Int16Array(a.length + b.length);
  out.set(a, 0);
  out.set(b, a.length);
  return out;
}
