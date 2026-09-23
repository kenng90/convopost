<?php

namespace Modules\Social\Livewire;

use App\Models\ListCatalog;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Modules\Social\Enums\SocialProvider;
use Modules\Social\Http\Requests\StoreSocialPostRequest;
use Modules\Social\Models\SocialAccount;
use Modules\Social\Models\SocialHashtagGroup;
use Modules\Social\Models\SocialLabel;
use Modules\Social\Models\SocialMediaAsset;
use Modules\Social\Models\SocialPost;
use Modules\Social\Models\SocialTemplate;
use Modules\Social\Services\SocialPostApprovalService;
use Modules\Social\Services\SocialPostComposerService;

class PostComposer extends Component
{
    public string $content = '';

    /** @var list<int> */
    public array $selectedAccountIds = [];

    /** @var list<int> */
    public array $selectedMediaIds = [];

    /** @var list<int> */
    public array $selectedLabelIds = [];

    /** @var array<string, string> */
    public array $networkVersions = [
        'facebook' => '',
        'instagram' => '',
        'linkedin' => '',
    ];

    public bool $showNetworkOverrides = false;

    public ?string $scheduledAt = null;

    public string $offerType = 'none';

    public string $offerUrl = '';

    public ?int $offerTargetId = null;

    public function mount(): void
    {
        $accounts = $this->availableAccounts();
        if ($accounts->count() === 1) {
            $this->selectedAccountIds = [(int) $accounts->first()->id];
        }
    }

    public function toggleNetworkOverrides(): void
    {
        $this->showNetworkOverrides = ! $this->showNetworkOverrides;
    }

    public function toggleAccount(int $accountId): void
    {
        if (in_array($accountId, $this->selectedAccountIds, true)) {
            $this->selectedAccountIds = array_values(array_filter(
                $this->selectedAccountIds,
                fn (int $id) => $id !== $accountId
            ));
        } else {
            $this->selectedAccountIds[] = $accountId;
        }
    }

    public function toggleMedia(int $mediaId): void
    {
        if (in_array($mediaId, $this->selectedMediaIds, true)) {
            $this->selectedMediaIds = array_values(array_filter(
                $this->selectedMediaIds,
                fn (int $id) => $id !== $mediaId
            ));
        } else {
            $this->selectedMediaIds[] = $mediaId;
        }
    }

    public function applyTemplate(int $templateId): void
    {
        $company = Auth::user()?->currentCompany();

        $template = SocialTemplate::query()
            ->active()
            ->when($company, fn ($query) => $query->where('company_id', $company->id))
            ->whereKey($templateId)
            ->first();

        if (! $template) {
            return;
        }

        $this->content = $template->content;
    }

    public function insertHashtagGroup(int $groupId): void
    {
        $company = Auth::user()?->currentCompany();

        $group = SocialHashtagGroup::query()
            ->when($company, fn ($query) => $query->where('company_id', $company->id))
            ->whereKey($groupId)
            ->first();

        if (! $group) {
            return;
        }

        $tags = $group->formattedTags();

        if ($tags === '') {
            return;
        }

        $this->content = trim($this->content) === ''
            ? $tags
            : rtrim($this->content)."\n\n".$tags;
    }

    public function toggleLabel(int $labelId): void
    {
        if (in_array($labelId, $this->selectedLabelIds, true)) {
            $this->selectedLabelIds = array_values(array_filter(
                $this->selectedLabelIds,
                fn (int $id) => $id !== $labelId
            ));
        } else {
            $this->selectedLabelIds[] = $labelId;
        }
    }

    public function saveDraft(): void
    {
        $post = $this->persist('draft');

        if ($post) {
            $this->redirect(route('social.posts.index', ['status' => 'draft']), navigate: false);
        }
    }

    public function schedule(): void
    {
        if ($this->requiresApprovalGate()) {
            $post = $this->persist('draft');

            if ($post) {
                app(SocialPostApprovalService::class)->submit($post, Auth::user());
                $this->redirect(route('social.posts.show', $post), navigate: false);
            }

            return;
        }

        $post = $this->persist('scheduled');

        if ($post) {
            $this->redirect(route('social.posts.index', ['status' => 'scheduled']), navigate: false);
        }
    }

    public function submitForApproval(): void
    {
        $post = $this->persist('draft');

        if ($post) {
            app(SocialPostApprovalService::class)->submit($post, Auth::user());
            $this->redirect(route('social.posts.show', $post), navigate: false);
        }
    }

    protected function requiresApprovalGate(): bool
    {
        $user = Auth::user();

        if (! $user) {
            return false;
        }

        return ! app(SocialPostApprovalService::class)->canReview($user);
    }

    protected function persist(string $status): ?SocialPost
    {
        $this->validate(StoreSocialPostRequest::livewireRules($status, $this->offerType));

        $company = Auth::user()->currentCompany();

        if (! $company) {
            $this->addError('content', __('Select a company before composing.'));

            return null;
        }

        return app(SocialPostComposerService::class)->create($company, Auth::user(), [
            'content' => $this->content,
            'account_ids' => $this->selectedAccountIds,
            'media_ids' => $this->selectedMediaIds,
            'label_ids' => $this->selectedLabelIds,
            'versions' => $this->networkVersions,
            'status' => $status,
            'scheduled_at' => $this->scheduledAt,
            'offer_type' => $this->offerType,
            'offer_url' => $this->offerUrl ?: null,
            'offer_target_id' => $this->offerTargetId,
        ]);
    }

    public function render(): View
    {
        return view('social::livewire.post-composer', [
            'accounts' => $this->availableAccounts(),
            'mediaAssets' => $this->availableMedia(),
            'catalogs' => $this->availableCatalogs(),
            'templates' => $this->availableTemplates(),
            'hashtagGroups' => $this->availableHashtagGroups(),
            'labels' => $this->availableLabels(),
            'providers' => SocialProvider::publishable(),
            'requiresApproval' => $this->requiresApprovalGate(),
        ]);
    }

    protected function availableAccounts()
    {
        $company = Auth::user()?->currentCompany();

        return SocialAccount::query()
            ->when($company, fn ($query) => $query->where('company_id', $company->id))
            ->where('status', 'active')
            ->orderBy('provider')
            ->orderBy('name')
            ->get();
    }

    protected function availableMedia()
    {
        $company = Auth::user()?->currentCompany();

        return SocialMediaAsset::query()
            ->when($company, fn ($query) => $query->where('company_id', $company->id))
            ->orderByDesc('id')
            ->limit(24)
            ->get();
    }

    protected function availableCatalogs()
    {
        $company = Auth::user()?->currentCompany();

        return ListCatalog::query()
            ->when($company, fn ($query) => $query->where('company_id', $company->id))
            ->orderBy('name')
            ->limit(50)
            ->get(['id', 'name']);
    }

    protected function availableTemplates()
    {
        $company = Auth::user()?->currentCompany();

        return SocialTemplate::query()
            ->active()
            ->when($company, fn ($query) => $query->where('company_id', $company->id))
            ->orderBy('name')
            ->limit(50)
            ->get(['id', 'name', 'category']);
    }

    protected function availableHashtagGroups()
    {
        $company = Auth::user()?->currentCompany();

        return SocialHashtagGroup::query()
            ->when($company, fn ($query) => $query->where('company_id', $company->id))
            ->orderBy('name')
            ->limit(50)
            ->get(['id', 'name', 'tags']);
    }

    protected function availableLabels()
    {
        $company = Auth::user()?->currentCompany();

        return SocialLabel::query()
            ->when($company, fn ($query) => $query->where('company_id', $company->id))
            ->orderBy('name')
            ->limit(50)
            ->get(['id', 'name', 'color']);
    }
}
