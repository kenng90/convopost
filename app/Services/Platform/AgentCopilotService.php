<?php

namespace App\Services\Platform;

use App\Models\Company;
use Illuminate\Support\Str;
use Modules\Knowledge\Models\KnowledgeArticle;
use Modules\Wpbox\Models\Contact;
use Modules\Wpbox\Models\Reply;
use Modules\Wpbox\Models\Template;

class AgentCopilotService
{
    /**
     * @return array{suggestions: array<int, array{text: string, source: string, confidence: float}>, tone_variants: array<string, string>}
     */
    public function suggest(Company $company, Contact $contact, ?string $draft = null): array
    {
        $suggestions = [];

        $kbSuggestion = $this->fromKnowledgeBase($company, $draft);
        if ($kbSuggestion) {
            $suggestions[] = $kbSuggestion;
        }

        $templateSuggestion = $this->fromQuickReplies($company);
        if ($templateSuggestion) {
            $suggestions[] = $templateSuggestion;
        }

        $contextSuggestion = $this->fromContactContext($contact);
        if ($contextSuggestion) {
            $suggestions[] = $contextSuggestion;
        }

        $base = $draft ?: ($suggestions[0]['text'] ?? __('Hello! How can I help you today?'));

        return [
            'suggestions' => array_slice($suggestions, 0, 3),
            'tone_variants' => [
                'professional' => $this->adjustTone($base, 'professional'),
                'friendly' => $this->adjustTone($base, 'friendly'),
                'concise' => $this->adjustTone($base, 'concise'),
            ],
        ];
    }

    private function fromKnowledgeBase(Company $company, ?string $draft): ?array
    {
        if (! class_exists(KnowledgeArticle::class)) {
            return null;
        }

        $query = $draft ?: __('help');
        $article = KnowledgeArticle::where('company_id', $company->id)
            ->where(function ($q) use ($query) {
                $q->where('title', 'like', '%'.$query.'%')
                    ->orWhere('content', 'like', '%'.$query.'%');
            })
            ->first();

        if (! $article) {
            return null;
        }

        $excerpt = Str::limit(strip_tags($article->content), 200);

        return [
            'text' => $excerpt,
            'source' => __('Knowledge: :title', ['title' => $article->title]),
            'confidence' => 0.75,
        ];
    }

    private function fromQuickReplies(Company $company): ?array
    {
        $reply = Reply::where('type', 1)->whereNull('flow_id')->first();
        if (! $reply) {
            return null;
        }

        return [
            'text' => $reply->text,
            'source' => __('Quick reply: :name', ['name' => $reply->name]),
            'confidence' => 0.6,
        ];
    }

    private function fromContactContext(Contact $contact): ?array
    {
        $name = $contact->name ?: __('there');

        return [
            'text' => __('Hi :name, thanks for reaching out. I am reviewing your request and will get back to you shortly.', ['name' => $name]),
            'source' => __('Context-aware greeting'),
            'confidence' => 0.5,
        ];
    }

    private function adjustTone(string $text, string $tone): string
    {
        return match ($tone) {
            'friendly' => '👋 '.$text,
            'concise' => Str::limit($text, 100),
            default => $text,
        };
    }

    public function approvedTemplates(Company $company): array
    {
        return Template::where('status', 'APPROVED')
            ->select('id', 'name', 'language')
            ->limit(10)
            ->get()
            ->map(fn (Template $t) => ['id' => $t->id, 'name' => $t->name, 'language' => $t->language])
            ->all();
    }
}
