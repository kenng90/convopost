/**
 * Build chat-friendly summary bullets from a Realtime call transcript.
 */
export function buildSummaryBulletsFromTranscript(transcript) {
  if (!transcript || typeof transcript !== 'string') {
    return [];
  }

  const bullets = [];

  for (const line of transcript.split('\n')) {
    const trimmed = line.trim();
    if (!trimmed || trimmed.startsWith('[STUB MODE')) {
      continue;
    }

    if (trimmed.startsWith('[AI] ')) {
      bullets.push(`Agent: ${trimmed.slice(5).trim()}`);
      continue;
    }

    if (trimmed.startsWith('[Caller] ')) {
      bullets.push(`Caller: ${trimmed.slice(9).trim()}`);
    }
  }

  if (bullets.length > 0) {
    return bullets.slice(-10);
  }

  const plain = transcript.trim();
  if (plain && !plain.startsWith('Call ended (')) {
    return [plain.slice(0, 500)];
  }

  return [];
}

export function buildCallBriefStructured({ transcript, debug, reason, payload }) {
  const summaryBullets = buildSummaryBulletsFromTranscript(transcript);

  if (summaryBullets.length === 0) {
    summaryBullets.push(`AI voice call completed (${reason}).`);
    if (debug?.audio_out_chunks > 0) {
      summaryBullets.push(`Duration included ${debug.audio_out_chunks} audio segments from the agent.`);
    }
  }

  return {
    intent: 'voice_call',
    summary_bullets: summaryBullets,
    summary: summaryBullets[0] ?? null,
    transcript_excerpt: transcript ? transcript.slice(0, 2000) : null,
    fields: buildFieldsFromPayload(payload),
    missing_required: [],
    handled_by: 'ai',
  };
}

function buildFieldsFromPayload(payload) {
  const required = payload?.required_field_keys ?? [];
  const fields = [];

  if (payload?.wa_user_id) {
    fields.push({
      key: 'phone',
      label: 'Phone',
      value: `+${String(payload.wa_user_id).replace(/^\+/, '')}`,
      status: 'confirmed',
    });
  }

  const contactName = (payload?.contact_name || '').trim();
  if (required.includes('name') && contactName) {
    fields.push({
      key: 'name',
      label: 'Name',
      value: contactName,
      status: 'inferred',
    });
  }

  return fields;
}
