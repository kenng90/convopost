import test from 'node:test';
import assert from 'node:assert/strict';
import { buildSummaryBulletsFromTranscript } from '../src/callBriefSummary.js';

test('buildSummaryBulletsFromTranscript extracts agent and caller lines', () => {
  const transcript = [
    '[AI] Hello, thanks for calling. How can I help?',
    '[Caller] I need help with my order.',
    '[AI] Sure, I can help with that.',
  ].join('\n');

  const bullets = buildSummaryBulletsFromTranscript(transcript);

  assert.equal(bullets.length, 3);
  assert.match(bullets[0], /^Agent:/);
  assert.match(bullets[1], /^Caller:/);
});

test('buildSummaryBulletsFromTranscript returns empty for placeholder transcript', () => {
  assert.deepEqual(buildSummaryBulletsFromTranscript('Call ended (completed).'), []);
});
