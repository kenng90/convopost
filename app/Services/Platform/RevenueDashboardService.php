<?php

namespace App\Services\Platform;

use App\Models\Company;
use Illuminate\Support\Facades\DB;
use Modules\Invoice\Models\Invoice;
use Modules\Journies\Models\JourneyStage;
use Modules\Wpbox\Models\Campaign;
use Modules\Wpbox\Models\Contact;
use Modules\Wpbox\Models\Message;

class RevenueDashboardService
{
    /**
     * @return array<string, array{title: string, icon: string, icon_color: string, main_value: mixed, sub_value: mixed, sub_value_color: string, sub_title: string, href?: string}>
     */
    public function widgets(Company $company): array
    {
        $paidRevenue = $this->paidRevenue($company);
        $conversationOrders = $this->conversationsToOrders($company);
        $campaignRoi = $this->campaignRoi($company);
        $pipelineValue = $this->pipelineValue($company);
        $agentConversion = $this->agentConversion($company);

        return [
            'revenue_total' => [
                'title' => __('WhatsApp revenue'),
                'icon' => 'ni-money-coins',
                'icon_color' => 'bg-gradient-success',
                'main_value' => $company->currency.' '.number_format($paidRevenue, 0),
                'sub_value' => $this->paidInvoiceCount($company),
                'sub_value_color' => 'text-success',
                'sub_title' => __('paid invoices'),
            ],
            'conversation_orders' => [
                'title' => __('Conversations → orders'),
                'icon' => 'ni-basket',
                'icon_color' => 'bg-gradient-info',
                'main_value' => $conversationOrders['rate'].'%',
                'sub_value' => $conversationOrders['orders'],
                'sub_value_color' => 'text-info',
                'sub_title' => __('orders from chats'),
            ],
            'campaign_roi' => [
                'title' => __('Campaign ROI'),
                'icon' => 'ni-notification-70',
                'icon_color' => 'bg-gradient-warning',
                'main_value' => $campaignRoi['replies'],
                'sub_value' => $campaignRoi['read_rate'].'%',
                'sub_value_color' => 'text-warning',
                'sub_title' => __('reply rate'),
                'href' => route('campaigns.index'),
            ],
            'pipeline_value' => [
                'title' => __('Pipeline value'),
                'icon' => 'ni-chart-bar-32',
                'icon_color' => 'bg-gradient-primary',
                'main_value' => $company->currency.' '.number_format($pipelineValue['value'], 0),
                'sub_value' => $pipelineValue['deals'],
                'sub_value_color' => 'text-primary',
                'sub_title' => __('open deals'),
                'href' => class_exists(\Modules\Journies\Models\Journey::class) ? route('journies.index') : null,
            ],
            'agent_conversion' => [
                'title' => __('Agent conversion'),
                'icon' => 'ni-single-02',
                'icon_color' => 'bg-gradient-danger',
                'main_value' => $agentConversion['rate'].'%',
                'sub_value' => $agentConversion['resolved'],
                'sub_value_color' => 'text-danger',
                'sub_title' => __('resolved chats'),
                'href' => route('chat.index'),
            ],
        ];
    }

    private function paidRevenue(Company $company): float
    {
        if (! class_exists(Invoice::class)) {
            return 0;
        }

        return (float) Invoice::where('company_id', $company->id)
            ->where('status', 'paid')
            ->sum('amount');
    }

    private function paidInvoiceCount(Company $company): int
    {
        if (! class_exists(Invoice::class)) {
            return 0;
        }

        return Invoice::where('company_id', $company->id)->where('status', 'paid')->count();
    }

    private function conversationsToOrders(Company $company): array
    {
        $chats = Contact::where('company_id', $company->id)->where('has_chat', 1)->count();
        $orders = class_exists(Invoice::class)
            ? Invoice::where('company_id', $company->id)->whereIn('status', ['paid', 'sent', 'pending'])->count()
            : 0;

        $rate = $chats > 0 ? round(($orders / $chats) * 100, 1) : 0;

        return ['chats' => $chats, 'orders' => $orders, 'rate' => $rate];
    }

    private function campaignRoi(Company $company): array
    {
        $campaigns = Campaign::where('company_id', $company->id)->where('send_to', '>', 1);
        $delivered = (int) $campaigns->sum('delivered_to');
        $read = (int) $campaigns->sum('read_by');
        $replies = Message::where('company_id', $company->id)
            ->where('is_message_by_contact', true)
            ->where('created_at', '>=', now()->subDays(30))
            ->count();

        return [
            'read_rate' => $delivered > 0 ? round(($read / $delivered) * 100, 1) : 0,
            'replies' => $replies,
        ];
    }

    private function pipelineValue(Company $company): array
    {
        if (! class_exists(JourneyStage::class) || ! DB::getSchemaBuilder()->hasTable('journey_stage_contacts')) {
            return ['value' => 0, 'deals' => 0];
        }

        $deals = DB::table('journey_stage_contacts')
            ->join('journey_stages', 'journey_stages.id', '=', 'journey_stage_contacts.stage_id')
            ->join('journeys', 'journeys.id', '=', 'journey_stages.journey_id')
            ->where('journeys.company_id', $company->id)
            ->count();

        $avgDeal = class_exists(Invoice::class)
            ? (float) Invoice::where('company_id', $company->id)->where('status', 'paid')->avg('amount') ?: 0
            : 0;

        return [
            'deals' => $deals,
            'value' => $deals * $avgDeal,
        ];
    }

    private function agentConversion(Company $company): array
    {
        $withChat = Contact::where('company_id', $company->id)->where('has_chat', 1)->count();
        $resolved = Contact::where('company_id', $company->id)->where('resolved_chat', 1)->count();

        return [
            'resolved' => $resolved,
            'rate' => $withChat > 0 ? round(($resolved / $withChat) * 100, 1) : 0,
        ];
    }
}
