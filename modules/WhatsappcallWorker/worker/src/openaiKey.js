/**
 * OpenAI key for this call — supplied by Laravel from company settings only.
 */
export function resolveOpenAiApiKey(payload) {
  const fromPayload = typeof payload?.openai_api_key === 'string' ? payload.openai_api_key.trim() : '';

  return fromPayload;
}
