<?php

namespace Modules\Whatsappcall\Services;

use App\Models\Company;

class VoiceSpokenLanguageService
{
    public const CONFIG_KEY = 'whatsapp_ai_spoken_language';

    public const DEFAULT_CODE = 'en';

    public const AUTO_CODE = 'auto';

    /**
     * ISO-639-1 codes accepted by Whisper transcription (intersected with config/languages.php).
     *
     * @var array<int, string>
     */
    public const WHISPER_CODES = [
        'af', 'am', 'ar', 'as', 'az', 'be', 'bg', 'bn', 'bo', 'bs', 'ca', 'cs', 'cy', 'da',
        'de', 'el', 'en', 'es', 'et', 'eu', 'fa', 'fi', 'fo', 'fr', 'gl', 'gu', 'ha', 'he',
        'hi', 'hr', 'hu', 'hy', 'id', 'is', 'it', 'ja', 'ka', 'kk', 'km', 'kn', 'ko', 'lt',
        'lv', 'mg', 'ml', 'mr', 'ms', 'mt', 'my', 'nb', 'ne', 'nl', 'nn', 'pa', 'pl', 'ps',
        'pt', 'ro', 'ru', 'si', 'sk', 'sl', 'sn', 'so', 'sq', 'sr', 'sv', 'sw', 'ta', 'te',
        'th', 'tr', 'uk', 'ur', 'uz', 'vi', 'yo', 'zh',
    ];

    /**
     * Whisper uses slightly different codes for a few locales.
     *
     * @var array<string, string>
     */
    private const TRANSCRIPTION_ALIASES = [
        'nb' => 'no',
        'nn' => 'no',
    ];

    /**
     * @return array<string, string>
     */
    public function options(): array
    {
        $labels = config('languages', []);
        $pinned = [];

        foreach (self::WHISPER_CODES as $code) {
            if (isset($labels[$code])) {
                $pinned[$code] = $labels[$code];
            }
        }

        asort($pinned);

        return array_merge([
            self::AUTO_CODE => __('Match the caller (can switch languages)'),
        ], $pinned);
    }

    /**
     * @return array<int, string>
     */
    public function allowedCodes(): array
    {
        return array_merge([self::AUTO_CODE], self::WHISPER_CODES);
    }

    public function resolveCode(Company $company): string
    {
        $code = strtolower(trim((string) $company->getConfig(self::CONFIG_KEY, self::DEFAULT_CODE)));

        if ($code === '') {
            return self::DEFAULT_CODE;
        }

        if (in_array($code, $this->allowedCodes(), true)) {
            return $code;
        }

        return self::DEFAULT_CODE;
    }

    public function resolveName(Company $company): string
    {
        $code = $this->resolveCode($company);

        if ($code === self::AUTO_CODE) {
            return "the caller's language";
        }

        return (string) (config('languages.'.$code) ?: 'English');
    }

    public function isPinned(Company $company): bool
    {
        return $this->resolveCode($company) !== self::AUTO_CODE;
    }

    public function transcriptionCode(Company $company): ?string
    {
        if (! $this->isPinned($company)) {
            return null;
        }

        $code = $this->resolveCode($company);

        return self::TRANSCRIPTION_ALIASES[$code] ?? $code;
    }

    public function instructionText(Company $company): string
    {
        if (! $this->isPinned($company)) {
            return "Language: Detect the caller's language from their first clear sentence and stay in that language for the rest of the call. Do not switch languages because of background noise, accents, product names, or mixed knowledge-base text.";
        }

        $name = $this->resolveName($company);

        return "Language: Always speak {$name}. Do not switch to another language unless the caller explicitly asks you to. If audio is noisy or unclear, continue in {$name} — never guess a different language.";
    }

    /**
     * @return array{code: string, name: string, pinned: bool, transcription_code: ?string, instruction: string}
     */
    public function forDispatch(Company $company): array
    {
        return [
            'code' => $this->resolveCode($company),
            'name' => $this->resolveName($company),
            'pinned' => $this->isPinned($company),
            'transcription_code' => $this->transcriptionCode($company),
            'instruction' => $this->instructionText($company),
        ];
    }
}
