<?php

namespace App\Models;

use App\Traits\DelegatesSharedCreditsToOwner;
use App\Traits\HasConfig;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Company extends MyModel
{
    use DelegatesSharedCreditsToOwner;
    use HasConfig;
    use HasFactory;
    use SoftDeletes;

    protected $modelName = "App\Models\Company";

    protected $guarded = [];

    protected $imagePath = '/uploads/companies/';

    /**
     * Get the user that owns the company.
     */
    public function user()
    {
        return $this->belongsTo(\App\Models\User::class);
    }

    public function getAliasAttribute()
    {
        return $this->subdomain;
    }

    public function getPlanAttribute()
    {
        $planInfo = [
            'plan' => null,
            'canMakeNewOrder' => false,
            'canAddNewItems' => false,
            'itemsMessage' => '',
            'itemsAlertType' => 'success',
            'ordersMessage' => '',
            'ordersAlertType' => 'success',
        ];

        //Find the plan
        $currentPlan = Plans::withTrashed()->find($this->user->mplanid());
        if ($currentPlan == null) {
            //Make artificial plan - usefull when migrating the system  - or wrong free plan id
            $currentPlan = new Plans();
            $currentPlan->name = __('No plan found');
            $currentPlan->price = 0;
            $currentPlan->limit_items = 0;
            $currentPlan->enable_ordering = 1;
            $currentPlan->limit_orders = 0;
            $currentPlan->limit_catalog_items = 0;
            $currentPlan->limit_agents = 0;
            $currentPlan->limit_companies = 0;
            $currentPlan->limit_integrations = 0;
            $currentPlan->period = 1;
        }
        $planInfo['plan'] = $currentPlan->toArray();

        //Pure SaaS
        $planInfo['ordersMessage'] = $currentPlan->name.' - '.rtrim(money($currentPlan['price'], config('settings.cashier_currency'), config('settings.do_convertion', true))->format(), '.00').'/'.($currentPlan['period'] == 1 ? __('m') : __('y'));
        $planInfo['itemsMessage'] = $currentPlan->features;

        $catalogLimit = (int) ($currentPlan->limit_catalog_items ?? 0);
        $planInfo['usageSummary'] = app(\App\Services\PlanUsageLimit::class)->getUsageSummary($this);
        $planInfo['creditWallets'] = $this->buildCreditWalletsSummary();

        if (config('settings.enable_per_seat_billing', false)) {
            $owner = $this->user;
            if ($owner) {
                $planInfo['seatBillingSummary'] = app(\App\Services\PlanSeatBillingService::class)->getBillingSummary($owner);
            }
        }

        if ($catalogLimit > 0) {
            $catalogUsage = app(\App\Services\CatalogItemPlanLimit::class)->getUsageSummary($this);
            $planInfo['catalogItemsMessage'] = __('Catalog items: :used of :limit', [
                'used' => $catalogUsage['used'],
                'limit' => $catalogUsage['limit'],
            ]);
            $planInfo['catalogItemsAlertType'] = $catalogUsage['remaining'] === 0 ? 'warning' : 'info';
        } else {
            $planInfo['catalogItemsMessage'] = __('Catalog items: unlimited');
            $planInfo['catalogItemsAlertType'] = 'success';
        }

        $plugins = $currentPlan->getConfig('plugins', null);

        if ($plugins) {
            $planInfo['allowedPluginsPerPlan'] = json_decode($plugins, false);
        } else {
            $planInfo['allowedPluginsPerPlan'] = null;
        }

        return $planInfo;

    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function buildCreditWalletsSummary(): array
    {
        $wallets = [];

        if (config('settings.enable_credits', false) && $this->user) {
            $messagingStats = $this->user->getMessagingCreditWalletStats();

            $wallets[] = array_merge($messagingStats, [
                'key' => 'messaging',
                'label' => __('Messaging credits'),
                'has_own_key' => false,
                'alert' => $messagingStats['percent_used'] >= 90 ? 'warning' : 'info',
            ]);
        }

        $managedAiStatus = app(\App\Services\Platform\ManagedAiService::class)->status($this);

        if ($managedAiStatus['enabled'] && $managedAiStatus['monthly_allowance'] > 0) {
            $allowance = (int) $managedAiStatus['monthly_allowance'];
            $used = (int) $managedAiStatus['used'];
            $remaining = (int) $managedAiStatus['remaining'];
            $percentUsed = 0;

            if ($allowance > 0) {
                $percentUsed = (int) round(($used / $allowance) * 100);
                if ($used > 0 && $percentUsed === 0) {
                    $percentUsed = 1;
                }
            }

            $wallets[] = [
                'key' => 'ai',
                'label' => __('AI credits'),
                'available' => $remaining,
                'used' => $used,
                'total' => $allowance,
                'percent_used' => $percentUsed,
                'has_own_key' => (bool) $managedAiStatus['has_own_key'],
                'alert' => $remaining <= 0 && ! $managedAiStatus['has_own_key'] ? 'warning' : 'info',
            ];
        }

        return $wallets;
    }

    /**
     * Whether the company's plan includes a plugin/module alias.
     * When the plan has no plugin restriction (null), all plugins are allowed.
     */
    public function hasPlanPlugin(string $alias): bool
    {
        $allowed = $this->getPlanAttribute()['allowedPluginsPerPlan'];

        return $allowed === null || in_array($alias, $allowed, true);
    }

    public function getLinkAttribute()
    {
        if (config('settings.wildcard_domain_ready')) {
            //As subdomain
            return str_replace('://', '://'.$this->subdomain.'.', config('app.url', ''));
        } elseif (strlen($this->getConfig('domain')) > 3) {
            //As domain
            return 'https://'.explode(' ', $this->getConfig('domain'))[0];
        } else {
            //As link
            return route('static-page', $this->subdomain);
        }
    }

    public function getLogomAttribute()
    {
        return $this->getImage($this->logo, config('global.company_details_image'));
    }

    public function getLogowideAttribute()
    {
        return $this->getImage($this->getConfig('resto_wide_logo', null), '/default/company_wide.png', '_original.png');
    }

    public function getLogowidedarkAttribute()
    {
        return $this->getImage($this->getConfig('resto_wide_logo_dark', null), '/default/company_wide_dark.png', '_original.png');
    }

    public function getIconAttribute()
    {
        return $this->getImage($this->logo, str_replace('_large.jpg', '_thumbnail.jpg', config('global.company_details_image')), '_thumbnail.jpg');
    }

    public function getCovermAttribute()
    {
        return $this->getImage($this->cover, config('global.company_details_cover_image'), '_cover.jpg');
    }

    public function managers()
    {
        return $this->hasMany(CompanyMembership::class, 'company_id', 'id')
            ->where('role', CompanyMembership::ROLE_MANAGER);
    }

    public function staff(): HasMany
    {
        return $this->hasMany(\App\Models\User::class, 'company_id', 'id')->role('staff');
    }

    public function users(): HasMany
    {
        return $this->hasMany(\App\Models\User::class, 'company_id', 'id');
    }

    public function whatsappWidget()
    {
        return $this->hasOne(\Modules\Embedwhatsapp\Models\Whatsappwidget::class, 'company_id', 'id');
    }

    public function supportWidget()
    {
        return $this->hasOne(\Modules\Websupportwidget\Models\WebsupportWidget::class, 'company_id', 'id');
    }
}
