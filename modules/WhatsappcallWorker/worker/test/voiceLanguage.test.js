import test from 'node:test';
import assert from 'node:assert/strict';
import {
  buildTranscriptionConfig,
  resolveSpokenLanguage,
  spokenLanguageGreetingHint,
  spokenLanguageInstructionLines,
} from '../src/voiceLanguage.js';

test('defaults to pinned English when payload omits spoken_language', () => {
  const resolved = resolveSpokenLanguage({});

  assert.equal(resolved.code, 'en');
  assert.equal(resolved.name, 'English');
  assert.equal(resolved.pinned, true);
  assert.equal(resolved.transcriptionLanguage, 'en');
});

test('pins Swahili transcription and instructions', () => {
  const payload = { spoken_language: 'sw', spoken_language_name: 'Swahili' };
  const resolved = resolveSpokenLanguage(payload);
  const lines = spokenLanguageInstructionLines(payload);
  const transcription = buildTranscriptionConfig('whisper-1', payload);

  assert.equal(resolved.transcriptionLanguage, 'sw');
  assert.match(lines.join(' '), /Always speak Swahili/);
  assert.equal(transcription.language, 'sw');
  assert.match(spokenLanguageGreetingHint(payload), /Swahili only/);
});

test('auto leaves transcription unpinned', () => {
  const payload = { spoken_language: 'auto' };
  const resolved = resolveSpokenLanguage(payload);
  const transcription = buildTranscriptionConfig('whisper-1', payload);

  assert.equal(resolved.pinned, false);
  assert.equal(resolved.transcriptionLanguage, null);
  assert.equal(transcription.language, undefined);
  assert.match(spokenLanguageInstructionLines(payload).join(' '), /Detect the caller's language/);
});

test('maps Norwegian Bokmål to Whisper no', () => {
  const resolved = resolveSpokenLanguage({ spoken_language: 'nb', spoken_language_name: 'Norwegian Bokmål' });

  assert.equal(resolved.transcriptionLanguage, 'no');
});
