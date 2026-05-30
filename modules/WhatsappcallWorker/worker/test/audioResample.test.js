import test from 'node:test';
import assert from 'node:assert/strict';
import { resamplePcm16, pcm16ToBase64, base64ToPcm16 } from '../src/audioResample.js';

test('resamplePcm16 doubles length when upsampling 24k to 48k', () => {
  const input = Int16Array.from([0, 1000, -1000, 500]);
  const out = resamplePcm16(input, 24000, 48000);
  assert.equal(out.length, 8);
});

test('pcm16 base64 roundtrip', () => {
  const original = Int16Array.from([0, 1234, -5678]);
  const restored = base64ToPcm16(pcm16ToBase64(original));
  assert.deepEqual(restored, original);
});
