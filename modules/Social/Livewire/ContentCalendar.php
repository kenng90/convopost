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

    public function render(): View
    {
        $cursor = Carbon::parse($this->cursorDate)->startOfDay();
        $range = $this->rangeFor($cursor);
        $postsByDay = $this->postsGroupedByDay($range['start'], $range['end']);

        return view('social::livewire.content-calendar', [
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
    protected function postsGroupedByDay(Carbon $start, Carbon $end): Collection
    {
        $company = Auth::user()?->currentCompany();

        $posts = SocialPost::query()
            ->with(['defaultVersion', 'accounts'])
            ->when($company, fn ($query) => $query->where('company_id', $company->id))
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
