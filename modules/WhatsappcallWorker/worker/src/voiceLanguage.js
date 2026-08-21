/**
 * Pin (or auto-detect) the spoken language for OpenAI Realtime voice calls.
 */

const TRANSCRIPTION_ALIASES = {
  nb: 'no',
  nn: 'no',
};

export function resolveSpokenLanguage(payload) {
  const raw = String(payload?.spoken_language ?? 'en').trim().toLowerCase();
  const auto = raw === 'auto';
  const code = auto ? 'auto' : (raw || 'en');
  const name = auto
    ? "the caller's language"
    : (String(payload?.spoken_language_name || '').trim() || 'English');
  const transcriptionLanguage = auto ? null : (TRANSCRIPTION_ALIASES[code] || code);

  return {
    code,
    name,
    pinned: !auto,
    transcriptionLanguage,
  };
}

export function spokenLanguageInstructionLines(payload) {
  const { pinned, name } = resolveSpokenLanguage(payload);

  if (!pinned) {
    return [
      "Language: Detect the caller's language from their first clear sentence and stay in that language for the rest of the call.",
      'Do not switch languages because of background noise, accents, product names, or mixed knowledge-base text.',
    ];
  }

  return [
    `Language: Always speak ${name}. Do not switch to another language unless the caller explicitly asks you to.`,
    `If audio is noisy or unclear, continue in ${name} — never guess a different language.`,
  ];
}

export function spokenLanguageGreetingHint(payload) {
  const { pinned, name } = resolveSpokenLanguage(payload);

  if (!pinned) {
    return 'If the caller has not spoken yet, greet in English. If they later speak clearly in another language, switch to that language and stay there.';
  }

  return `Speak this greeting in ${name} only. Do not use any other language.`;
}

export function buildTranscriptionConfig(model, payload) {
  const { transcriptionLanguage } = resolveSpokenLanguage(payload);
  const transcription = { model };

  if (transcriptionLanguage) {
    transcription.language = transcriptionLanguage;
  }

  return transcription;
}
