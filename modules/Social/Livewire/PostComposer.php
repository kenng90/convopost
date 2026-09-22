<?php

namespace Modules\Social\Livewire;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Modules\Social\Enums\SocialProvider;
use Modules\Social\Models\SocialAccount;
use Modules\Social\Models\SocialMediaAsset;
use Modules\Social\Services\SocialPostComposerService;

class PostComposer extends Component
{
    public string $content = '';

    /** @var list<int> */
    public array $selectedAccountIds = [];

    /** @var list<int> */
    public array $selectedMediaIds = [];

    /** @var array<string, string> */
    public array $networkVersions = [
        'facebook' => '',
        'instagram' => '',
        'linkedin' => '',
    ];

    public bool $showNetworkOverrides = false;

    public ?string $scheduledAt = null;

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

    public function saveDraft(): void
    {
        $this->persist('draft');
    }

    public function schedule(): void
    {
        $this->persist('scheduled');
    }

    protected function persist(string $status): void
    {
        $rules = [
            'content' => ['required', 'string', 'max:5000'],
            'selectedAccountIds' => ['required', 'array', 'min:1'],
            'selectedAccountIds.*' => ['integer'],
            'selectedMediaIds' => ['array'],
            'selectedMediaIds.*' => ['integer'],
            'networkVersions.facebook' => ['nullable', 'string', 'max:5000'],
            'networkVersions.instagram' => ['nullable', 'string', 'max:5000'],
            'networkVersions.linkedin' => ['nullable', 'string', 'max:5000'],
            'scheduledAt' => [$status === 'scheduled' ? 'required' : 'nullable', 'date', 'after:now'],
        ];

        $this->validate($rules);

        $company = Auth::user()->currentCompany();

        if (! $company) {
            $this->addError('content', __('Select a company before composing.'));

            return;
        }

        app(SocialPostComposerService::class)->create($company, Auth::user(), [
            'content' => $this->content,
            'account_ids' => $this->selectedAccountIds,
            'media_ids' => $this->selectedMediaIds,
            'versions' => $this->networkVersions,
            'status' => $status,
            'scheduled_at' => $this->scheduledAt,
        ]);

        $this->redirect(route('social.posts.index', ['status' => $status]), navigate: false);
    }

    public function render(): View
    {
        return view('social::livewire.post-composer', [
            'accounts' => $this->availableAccounts(),
            'mediaAssets' => $this->availableMedia(),
            'providers' => SocialProvider::publishable(),
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
}
