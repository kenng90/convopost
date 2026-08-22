<?php

namespace App\Services\Workspace;

use App\Models\Company;
use App\Models\User;
use Modules\Wpbox\Models\Contact;

class InboxRoutingService
{
    public const MODE_MANUAL = 'manual';

    public const MODE_ROUND_ROBIN = 'round_robin';

    public const MODE_LEAST_BUSY = 'least_busy';

    public function mode(Company $company): string
    {
        $mode = (string) $company->getConfig('inbox_routing_mode', self::MODE_ROUND_ROBIN);

        return in_array($mode, [self::MODE_MANUAL, self::MODE_ROUND_ROBIN, self::MODE_LEAST_BUSY], true)
            ? $mode
            : self::MODE_ROUND_ROBIN;
    }

    public function nextAgentId(Company $company): ?int
    {
        $agents = $this->eligibleAgents($company);
        if ($agents->isEmpty()) {
            return null;
        }

        return match ($this->mode($company)) {
            self::MODE_LEAST_BUSY => $this->leastBusy($company, $agents),
            self::MODE_MANUAL => null,
            default => $this->roundRobin($company, $agents),
        };
    }

    /**
     * @return \Illuminate\Support\Collection<int, User>
     */
    public function eligibleAgents(Company $company)
    {
        return User::query()
            ->where('company_id', $company->id)
            ->where(function ($q) use ($company) {
                $q->whereHas('roles', fn ($roles) => $roles->whereIn('name', ['staff', 'owner']))
                    ->orWhere('id', $company->user_id);
            })
            ->orderBy('id')
            ->get();
    }

    /**
     * @param  \Illuminate\Support\Collection<int, User>  $agents
     */
    private function roundRobin(Company $company, $agents): ?int
    {
        $ids = $agents->pluck('id')->map(fn ($id) => (int) $id)->all();
        $last = (int) $company->getConfig('inbox_routing_last_agent_id', '0');
        $next = $ids[0];

        foreach ($ids as $index => $id) {
            if ($id === $last) {
                $next = $ids[($index + 1) % count($ids)];
                break;
            }
        }

        $company->setConfig('inbox_routing_last_agent_id', (string) $next);

        return $next;
    }

    /**
     * @param  \Illuminate\Support\Collection<int, User>  $agents
     */
    private function leastBusy(Company $company, $agents): ?int
    {
        $openCounts = Contact::withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->where('resolved_chat', 0)
            ->whereIn('user_id', $agents->pluck('id'))
            ->selectRaw('user_id, count(*) as open_count')
            ->groupBy('user_id')
            ->pluck('open_count', 'user_id');

        return $agents
            ->sortBy(fn (User $user) => (int) ($openCounts[$user->id] ?? 0))
            ->first()
            ?->id;
    }
}
