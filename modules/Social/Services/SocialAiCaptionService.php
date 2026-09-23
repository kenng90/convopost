<?php

namespace Modules\Social\Services;

use App\Models\Company;
use App\Services\PlanEntitlementResolver;
use App\Services\Platform\ManagedAiService;
use App\Services\Platform\OpenRouterService;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class SocialAiCaptionService
{
    public const ACTION_GENERATE = 'social_ai_caption';

    public const ACTION_REWRITE = 'social_ai_rewrite';

    /** @var array<string, string> */
    public const TONES = [
        'professional' => 'Professional and trustworthy',
        'casual' => 'Casual and friendly',
        'punchy' => 'Short, punchy, and high-energy',
        'shorter' => 'Shorter and clearer (cut fluff)',
    ];

    public function __construct(
        private readonly ManagedAiService $managedAi,
        private readonly OpenRouterService $openRouter,
        private readonly PlanEntitlementResolver $entitlements,
    ) {
    }

    public function generate(Company $company, string $brief, ?string $offerHint = null): string
    {
        $brief = trim($brief);

        if ($brief === '') {
            throw ValidationException::withMessages([
                'aiPrompt' => __('Describe what the post should be about.'),
            ]);
        }

        $userPrompt = "Write one social media caption for this brief:\n{$brief}";

        if ($offerHint) {
            $userPrompt .= "\nInclude a soft call-to-action related to: {$offerHint}";
        }

        $system = 'You write social commerce captions for Facebook, Instagram, and LinkedIn. '
            .'Return ONLY the caption text — no quotes, no markdown, no hashtag walls unless brief asks. '
            .'Keep it under 400 characters when possible. Use a tone suitable for Kenya/Africa SMB commerce.';

        return $this->run($company, self::ACTION_GENERATE, $system, $userPrompt);
    }

    public function rewrite(Company $company, string $content, string $tone): string
    {
        $content = trim($content);

        if ($content === '') {
            throw ValidationException::withMessages([
                'content' => __('Write a caption before rewriting.'),
            ]);
        }

        if (! array_key_exists($tone, self::TONES)) {
            throw ValidationException::withMessages([
                'aiTone' => __('Choose a valid rewrite tone.'),
            ]);
        }

        $toneLabel = self::TONES[$tone];
        $system = 'You rewrite social media captions. Return ONLY the rewritten caption — no quotes or markdown. '
            ."Apply this tone: {$toneLabel}. Keep commerce intent and any URLs or product mentions.";

        return $this->run(
            $company,
            self::ACTION_REWRITE,
            $system,
            "Rewrite this caption:\n{$content}"
        );
    }

    public function companyCanUseAi(Company $company): bool
    {
        $owner = $company->user;

        if (! $owner) {
            return false;
        }

        return $this->entitlements->userHasCapability($owner, 'social_ai');
    }

    protected function run(Company $company, string $action, string $system, string $userPrompt): string
    {
        if (! $this->companyCanUseAi($company)) {
            throw new RuntimeException(__('Social AI captions are not included in your plan.'));
        }

        $keyInfo = $this->managedAi->resolveOpenRouterKey($company);
        $cost = $this->managedAi->actionCost($action);

        if ($keyInfo['key'] === null) {
            throw new RuntimeException(__('No OpenRouter API key is configured. Add your key in workspace settings or upgrade for managed AI.'));
        }

        if ($keyInfo['should_meter'] && ! $this->managedAi->canConsume($company, $cost)) {
            throw new RuntimeException($this->managedAi->exhaustionMessage($company));
        }

        $model = (string) config('social.ai.model', config('managed-ai.flow_generate_model', 'openai/gpt-4o-mini'));

        try {
            $result = $this->openRouter->chatCompletion(
                $keyInfo['key'],
                [
                    ['role' => 'system', 'content' => $system],
                    ['role' => 'user', 'content' => $userPrompt],
                ],
                $model,
                0.7,
                600,
            );
        } catch (\Throwable $e) {
            Log::warning('Social AI caption request failed', [
                'action' => $action,
                'company_id' => $company->id,
                'error' => $e->getMessage(),
            ]);

            throw new RuntimeException(__('Could not generate a caption right now. Please try again.'));
        }

        $caption = trim((string) ($result['content'] ?? ''));
        $caption = trim($caption, " \t\n\r\0\x0B\"'");

        if ($caption === '') {
            throw new RuntimeException(__('The AI returned an empty caption. Please try again.'));
        }

        if ($keyInfo['should_meter']) {
            $this->managedAi->consume($company, $cost, $action, [
                'model' => $result['model'] ?? $model,
                'source' => 'social',
            ]);
        }

        return $caption;
    }
}
