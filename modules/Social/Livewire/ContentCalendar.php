<?php

namespace Modules\Social\Livewire;

use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Modules\Social\Models\SocialPost;

class ContentCalendar extends Component
{
    public string $mode = 'month';

    public string $cursorDate;

    public ?int $reschedulingPostId = null;

    public string $rescheduleAt = '';

    public function mount(?string $date = null): void
    {
        $this->cursorDate = $date
            ? Carbon::parse($date)->toDateString()
            : now()->toDateString();
    }

    public function previousPeriod(): void
    {
        $cursor = Carbon::parse($this->cursorDate);

        $this->cursorDate = $this->mode === 'week'
            ? $cursor->subWeek()->toDateString()
            : $cursor->subMonthNoOverflow()->toDateString();
    }

    public function nextPeriod(): void
    {
        $cursor = Carbon::parse($this->cursorDate);

        $this->cursorDate = $this->mode === 'week'
            ? $cursor->addWeek()->toDateString()
            : $cursor->addMonthNoOverflow()->toDateString();
    }

    public function goToday(): void
    {
        $this->cursorDate = now()->toDateString();
    }

    public function setMode(string $mode): void
    {
        if (! in_array($mode, ['month', 'week'], true)) {
            return;
        }

        $this->mode = $mode;
    }

    public function startReschedule(int $postId): void
    {
        $post = $this->findOwnedPost($postId);

        if (! $post || ! in_array($post->status, ['draft', 'scheduled'], true)) {
            return;
        }

        $this->reschedulingPostId = $post->id;
        $this->rescheduleAt = ($post->scheduled_at ?? now()->addHour())->format('Y-m-d\TH:i');
        $this->resetErrorBag();
    }

    public function cancelReschedule(): void
    {
        $this->reschedulingPostId = null;
        $this->rescheduleAt = '';
        $this->resetErrorBag();
    }

    public function saveReschedule(): void
    {
        $this->validate([
            'rescheduleAt' => ['required', 'date', 'after:now'],
        ]);

        $post = $this->findOwnedPost((int) $this->reschedulingPostId);

        if (! $post || ! in_array($post->status, ['draft', 'scheduled'], true)) {
            $this->addError('rescheduleAt', __('Only draft or scheduled posts can be rescheduled.'));

            return;
        }

        $scheduledAt = Carbon::parse($this->rescheduleAt);

        $post->forceFill([
            'scheduled_at' => $scheduledAt,
            'status' => 'scheduled',
        ])->save();

        $this->cursorDate = $scheduledAt->toDateString();
        $this->cancelReschedule();
    }

    protected function findOwnedPost(int $postId): ?SocialPost
    {
        $company = Auth::user()?->currentCompany();

        if (! $company) {
            return null;
        }

        return SocialPost::query()
            ->where('company_id', $company->id)
            ->whereKey($postId)
            ->first();
    }

    public function render(): View
    {
        $company = Auth::user()?->currentCompany();
        $cursor = Carbon::parse($this->cursorDate)->startOfDay();
        $range = $this->rangeFor($cursor);
        $postsByDay = $this->postsGroupedByDay($company?->id, $range['start'], $range['end']);

        return view('social::livewire.content-calendar', [
            'workspaceName' => $company?->name,
            'cursor' => $cursor,
            'rangeStart' => $range['start'],
            'rangeEnd' => $range['end'],
            'days' => $this->buildDays($range['start'], $range['end'], $cursor, $postsByDay),
            'title' => $this->titleFor($cursor),
            'weekdays' => ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'],
        ]);
    }

    /**
     * @return array{start: Carbon, end: Carbon}
     */
    protected function rangeFor(Carbon $cursor): array
    {
        if ($this->mode === 'week') {
            return [
                'start' => $cursor->copy()->startOfWeek(Carbon::MONDAY)->startOfDay(),
                'end' => $cursor->copy()->endOfWeek(Carbon::SUNDAY)->endOfDay(),
            ];
        }

        return [
            'start' => $cursor->copy()->startOfMonth()->startOfWeek(Carbon::MONDAY)->startOfDay(),
            'end' => $cursor->copy()->endOfMonth()->endOfWeek(Carbon::SUNDAY)->endOfDay(),
        ];
    }

    protected function titleFor(Carbon $cursor): string
    {
        if ($this->mode === 'week') {
            $start = $cursor->copy()->startOfWeek(Carbon::MONDAY);
            $end = $cursor->copy()->endOfWeek(Carbon::SUNDAY);

            return $start->format('M j').' – '.$end->format('M j, Y');
        }

        return $cursor->format('F Y');
    }

    /**
     * @return Collection<string, Collection<int, SocialPost>>
     */
    protected function postsGroupedByDay(?int $companyId, Carbon $start, Carbon $end): Collection
    {
        if (! $companyId) {
            return collect();
        }

        $posts = SocialPost::query()
            ->with(['defaultVersion', 'accounts'])
            ->where('company_id', $companyId)
            ->where(function ($query) use ($start, $end) {
                $query->whereBetween('scheduled_at', [$start, $end])
                    ->orWhereBetween('published_at', [$start, $end]);
            })
            ->whereIn('status', ['draft', 'scheduled', 'published', 'failed'])
            ->orderBy('scheduled_at')
            ->orderBy('id')
            ->get();

        return $posts->groupBy(function (SocialPost $post) {
            $at = $post->scheduled_at ?? $post->published_at;

            return $at?->toDateString() ?? 'unknown';
        });
    }

    /**
     * @param  Collection<string, Collection<int, SocialPost>>  $postsByDay
     * @return list<array{date: Carbon, inPeriod: bool, isToday: bool, posts: Collection<int, SocialPost>}>
     */
    protected function buildDays(Carbon $start, Carbon $end, Carbon $cursor, Collection $postsByDay): array
    {
        $days = [];
        $day = $start->copy();

        while ($day->lte($end)) {
            $key = $day->toDateString();
            $inPeriod = $this->mode === 'week'
                ? true
                : $day->isSameMonth($cursor);

            $days[] = [
                'date' => $day->copy(),
                'inPeriod' => $inPeriod,
                'isToday' => $day->isToday(),
                'posts' => $postsByDay->get($key, collect()),
            ];

            $day->addDay();
        }

        return $days;
    }
}
