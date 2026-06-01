import test from 'node:test';
import assert from 'node:assert/strict';

const { resolveOpenAiApiKey } = await import('../src/openaiKey.js');

test('resolveOpenAiApiKey uses company key from payload', () => {
  const key = resolveOpenAiApiKey({ openai_api_key: 'sk-company' });
  assert.equal(key, 'sk-company');
});

test('resolveOpenAiApiKey returns empty when payload has no key', () => {
  assert.equal(resolveOpenAiApiKey({}), '');
  assert.equal(resolveOpenAiApiKey({ openai_api_key: '  ' }), '');
});
