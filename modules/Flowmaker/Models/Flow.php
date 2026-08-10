<?php

namespace Modules\Flowmaker\Models;

use App\Models\Company;
use App\Scopes\CompanyScope;
use App\Services\Catalog\CatalogCheckoutPendingService;
use App\Services\Catalog\CatalogFlowCallbackService;
use App\Services\Flowmaker\FlowRunLogger;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Modules\Flowmaker\Models\Nodes\AssignAgent;
use Modules\Flowmaker\Models\Nodes\AssignGroup;
use Modules\Flowmaker\Models\Nodes\AssignJourneyStage;
use Modules\Flowmaker\Models\Nodes\BookAppointment;
use Modules\Flowmaker\Models\Nodes\BookingEventRegister;
use Modules\Flowmaker\Models\Nodes\BookingEventsList;
use Modules\Flowmaker\Models\Nodes\Branch;
use Modules\Flowmaker\Models\Nodes\Buttons;
use Modules\Flowmaker\Models\Nodes\CatalogSearch;
use Modules\Flowmaker\Models\Nodes\CheckPricing;
use Modules\Flowmaker\Models\Nodes\Counter;
use Modules\Flowmaker\Models\Nodes\Edge;
use Modules\Flowmaker\Models\Nodes\End;
use Modules\Flowmaker\Models\Nodes\Every;
use Modules\Flowmaker\Models\Nodes\FlowHTTPNode;
use Modules\Flowmaker\Models\Nodes\Keyword;
use Modules\Flowmaker\Models\Nodes\ListingInquiry;
use Modules\Flowmaker\Models\Nodes\ListMessage;
use Modules\Flowmaker\Models\Nodes\LLM;
use Modules\Flowmaker\Models\Nodes\ManageBooking;
use Modules\Flowmaker\Models\Nodes\ManageEventRegistration;
use Modules\Flowmaker\Models\Nodes\Media;
use Modules\Flowmaker\Models\Nodes\Message;
use Modules\Flowmaker\Models\Nodes\MpesaStkPush;
use Modules\Flowmaker\Models\Nodes\Node;
use Modules\Flowmaker\Models\Nodes\OrderStatus;
use Modules\Flowmaker\Models\Nodes\RequestPayment;
use Modules\Flowmaker\Models\Nodes\SendBookingLink;
use Modules\Flowmaker\Models\Nodes\SetVariable;
use Modules\Flowmaker\Models\Nodes\Template;
use Modules\Flowmaker\Models\Nodes\UserReply;
use Modules\Flowmaker\Models\Nodes\WhatsAppCatalog;
use Modules\Flowmaker\Models\Nodes\WhatsAppFlow;
use Modules\Reminders\Models\EventRegistration;
use Modules\Reminders\Models\Reservation;

class Flow extends Model
{
    protected $table = 'flows';

    public $guarded = [];

    protected $casts = [
        'exclusive_on_match' => 'boolean',
        'is_active' => 'boolean',
        'has_unpublished_changes' => 'boolean',
        'priority' => 'integer',
    ];

    // Define any custom methods or scopes here
    protected static function booted()
    {
        static::addGlobalScope(new CompanyScope);

        static::creating(function ($model) {
            $company_id = session('company_id', null);
            if ($company_id) {
                $model->company_id = $company_id;
            }
        });
    }

    public function documents()
    {
        return $this->hasMany(Flowdocument::class);
    }

    public function processMessage($data)
    {
        try {
            $message = $data->value;

            FlowRunLogger::log($this->id, $data->contact_id ?? null, 'message_received', null, mb_substr((string) $message, 0, 500));

            $flowData = $this->getDecodedFlowData();
            if (! $flowData || ! isset($flowData->nodes) || ! isset($flowData->edges)) {
                Log::error('Invalid flow data structure - missing nodes or edges', ['flowId' => $this->id]);

                return;
            }

            $contact = Contact::with(['fields', 'country'])->findOrFail($data->contact_id);
            $contact->primeFlowStateCache($this->id);
            $startNode = $contact->getContactStateValue($this->id, 'current_node');

            // Expire stuck waits after Meta messaging window (~24h).
            if ($startNode) {
                $waitingState = ContactState::query()
                    ->where('contact_id', $contact->id)
                    ->where('flow_id', $this->id)
                    ->where('state', 'current_node')
                    ->first();

                if ($waitingState && $waitingState->updated_at && $waitingState->updated_at->lt(now()->subHours(24))) {
                    Log::info('Flow wait expired after 24h', [
                        'flow_id' => $this->id,
                        'contact_id' => $contact->id,
                        'node_id' => $startNode,
                    ]);
                    $contact->clearContactState($this->id, 'current_node');
                    $startNode = null;
                }
            }

            $pendingService = app(CatalogCheckoutPendingService::class);
            $extra = is_object($data) ? ($data->extra ?? '') : ($data['extra'] ?? '');
            $skipKeywordRestart = $pendingService->isOrderConfirmationMessage($contact, $this->id, $message)
                || $pendingService->hasPending($contact, $this->id)
                || ($extra !== '' && (
                    str_starts_with((string) $extra, 'catalog_')
                    || str_starts_with((string) $extra, 'listing_')
                    || str_starts_with((string) $extra, 'listing:')
                ));

            if (! $skipKeywordRestart && $this->messageMatchesKeywordTrigger($flowData->nodes, $message)) {
                $contact->clearContactState($this->id, 'current_node');
                $this->processAllKeywordTriggers($flowData->nodes, $flowData->edges, $message, $data);

                return;
            }

            $graph = null;
            try {
                $graph = $this->makeGraph($flowData->nodes, $flowData->edges, $startNode);

                if ($startNode && $graph->id !== $startNode) {
                    $contact->clearContactState($this->id, 'current_node');
                }
            } catch (\Exception $e) {
                Log::error('Error making graph', ['error' => $e->getMessage()]);
            }

            if ($graph) {
                $graph->process($message, $data);
            }
        } catch (\Exception $e) {
            Log::error('Error processing message in flow', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Process all keyword_trigger nodes in turn.
     * Used when there are multiple keyword triggers in a single flow —
     * each one gets to evaluate the message and route accordingly.
     */
    private function processAllKeywordTriggers($rawNodes, $rawEdges, string $message, $data): void
    {
        $nodes = $this->getWiredNodes($rawNodes, $rawEdges);

        foreach ($nodes as $node) {
            if ($node->type !== 'keyword_trigger') {
                continue;
            }

            $node->isStartNode = true;
            $result = $node->process($message, $data);

            if (is_array($result) && ($result['success'] ?? false)) {
                return;
            }
        }
    }

    private function getDecodedFlowData(): ?object
    {
        $timestamp = $this->updated_at?->timestamp ?? 0;
        $cacheKey = "flow:data:{$this->id}:{$timestamp}";

        return Cache::remember($cacheKey, now()->addDay(), function () {
            return json_decode($this->flow_data, false);
        });
    }

    /**
     * @return array<string, Node>
     */
    private function getWiredNodes($rawNodes, $rawEdgesArray): array
    {
        $timestamp = $this->updated_at?->timestamp ?? 0;
        $cacheKey = "flow:wired:{$this->id}:{$timestamp}";

        $cached = Cache::get($cacheKey);
        if (is_string($cached)) {
            $nodes = @unserialize($cached);
            if (is_array($nodes)) {
                return $nodes;
            }
        }

        $nodes = $this->buildWiredNodes($rawNodes, $rawEdgesArray);
        Cache::put($cacheKey, serialize($nodes), now()->addDay());

        return $nodes;
    }

    private function makeGraph($nodes, $edgesArray, $startNode)
    {
        $nodes = $this->getWiredNodes($nodes, $edgesArray);

        //Return the graph, it is the first node
        if ($startNode && isset($nodes[$startNode])) {
            $nodes[$startNode]->isStartNode = true;

            return $nodes[$startNode];
        } else {
            $foundStartNode = $this->findStartNode($nodes);
            $foundStartNode->isStartNode = true;

            return $foundStartNode;
        }
    }

    /**
     * Build the full node map with all edges wired up.
     * Shared by makeGraph() and processAllKeywordTriggers().
     */
    private function buildWiredNodes($rawNodes, $rawEdgesArray): array
    {
        //Convert the nodes to objects
        $nodes = array_reduce($rawNodes, function ($carry, $node) {
            //Convert the node to an array
            $nodeArray = (array) $node;
            if ($nodeArray['type'] === 'keyword_trigger') {
                $theNewNode = new Keyword($nodeArray, []);
            } elseif ($nodeArray['type'] === 'message') {
                $theNewNode = new Message($nodeArray, []);
            } elseif ($nodeArray['type'] === 'incomingMessage') {
                $theNewNode = new Every($nodeArray, []);
            } elseif ($nodeArray['type'] === 'end') {
                $theNewNode = new End($nodeArray, []);
            } elseif ($nodeArray['type'] === 'image' || $nodeArray['type'] === 'video' || $nodeArray['type'] === 'pdf') {
                $theNewNode = new Media($nodeArray, []);
            } elseif ($nodeArray['type'] === 'template') {
                $theNewNode = new Template($nodeArray, []);
            } elseif ($nodeArray['type'] === 'branch') {
                $theNewNode = new Branch($nodeArray, []);
            } elseif ($nodeArray['type'] === 'quick_replies') {
                $theNewNode = new Buttons($nodeArray, []);
            } elseif ($nodeArray['type'] === 'list_message') {
                $theNewNode = new ListMessage($nodeArray, []);
            } elseif ($nodeArray['type'] === 'openai') {
                $theNewNode = new LLM($nodeArray, []);
            } elseif ($nodeArray['type'] === 'question') {
                $theNewNode = new UserReply($nodeArray, []);
            } elseif ($nodeArray['type'] === 'http') {
                Log::info('Creating HTTP node', ['nodeArray' => $nodeArray]);
                try {
                    $theNewNode = new FlowHTTPNode($nodeArray, []);
                } catch (\Exception $e) {
                    Log::error('Error creating HTTP node', ['error' => $e->getMessage()]);
                }
                Log::info('HTTP node created', ['theNewNode' => $theNewNode]);
            } elseif ($nodeArray['type'] === 'datastore') {
                $theNewNode = new SetVariable($nodeArray, []);
            } elseif ($nodeArray['type'] === 'assign_agent') {
                $theNewNode = new AssignAgent($nodeArray, []);
            } elseif ($nodeArray['type'] === 'assign_group') {
                $theNewNode = new AssignGroup($nodeArray, []);
            } elseif ($nodeArray['type'] === 'assign_journey_stage') {
                $theNewNode = new AssignJourneyStage($nodeArray, []);
            } elseif ($nodeArray['type'] === 'mpesa_stk_push') {
                $theNewNode = new MpesaStkPush($nodeArray, []);
            } elseif ($nodeArray['type'] === 'request_payment') {
                $theNewNode = new RequestPayment($nodeArray, []);
            } elseif ($nodeArray['type'] === 'catalog_search') {
                $theNewNode = new CatalogSearch($nodeArray, []);
            } elseif ($nodeArray['type'] === 'whatsapp_catalog') {
                $theNewNode = new WhatsAppCatalog($nodeArray, []);
            } elseif ($nodeArray['type'] === 'listing_inquiry') {
                $theNewNode = new ListingInquiry($nodeArray, []);
            } elseif ($nodeArray['type'] === 'whatsapp_flow') {
                $theNewNode = new WhatsAppFlow($nodeArray, []);
            } elseif ($nodeArray['type'] === 'booking_events_list') {
                $theNewNode = new BookingEventsList($nodeArray, []);
            } elseif ($nodeArray['type'] === 'booking_event_register') {
                $theNewNode = new BookingEventRegister($nodeArray, []);
            } elseif ($nodeArray['type'] === 'book_appointment') {
                $theNewNode = new BookAppointment($nodeArray, []);
            } elseif ($nodeArray['type'] === 'send_booking_link') {
                $theNewNode = new SendBookingLink($nodeArray, []);
            } elseif ($nodeArray['type'] === 'manage_booking') {
                $theNewNode = new ManageBooking($nodeArray, []);
            } elseif ($nodeArray['type'] === 'manage_event_registration') {
                $theNewNode = new ManageEventRegistration($nodeArray, []);
            } elseif ($nodeArray['type'] === 'order_status') {
                $theNewNode = new OrderStatus($nodeArray, []);
            } elseif ($nodeArray['type'] === 'counter') {
                $theNewNode = new Counter($nodeArray, []);
            } elseif ($nodeArray['type'] === 'check_pricing') {
                $theNewNode = new CheckPricing($nodeArray, []);
            } else {
                $theNewNode = new Node($nodeArray, []);
            }
            $theNewNode->flow_id = $this->id;
            $carry[$nodeArray['id']] = $theNewNode;

            return $carry;
        }, []);

        //Convert the edges to objects
        $edges = array_reduce($rawEdgesArray, function ($carry, $edge) {
            $edgeArray = (array) $edge;
            $carry[$edgeArray['id']] = new Edge($edgeArray);

            return $carry;
        }, []);

        //Wire edges to nodes
        foreach ($edges as $edge) {
            try {
                $source = $nodes[$edge->getSourceId()];
                $target = $nodes[$edge->getTargetId()];
                $source->addOutgoingEdge($edge);
                $target->addIncomingEdge($edge);
                $edge->setSource($nodes[$edge->getSourceId()]);
                $edge->setTarget($nodes[$edge->getTargetId()]);
            } catch (\Exception $e) {
                Log::error('Error adding edge to nodes', ['error' => $e->getMessage()]);
            }
        }

        return $nodes;
    }

    /**
     * Find the start node based on position and type
     * The start node should be the node with the lowest x position
     * and should be one of these types: keyword_trigger, incoming_message, opening_hours, template
     *
     * @param  array  $nodes  Array of nodes
     * @return Node The start node
     */
    private function findStartNode(array $nodes)
    {
        $validTypes = ['keyword_trigger', 'incoming_message', 'incomingMessage'];
        $startNode = null;
        $lowestX = PHP_FLOAT_MAX;

        foreach ($nodes as $node) {
            // Skip if not a valid start node type
            if (! in_array($node->type, $validTypes)) {
                continue;
            }

            // Get the x position from the node's data
            $position = $node->position ?? (object) ['x' => PHP_FLOAT_MAX];
            $x = $position->x ?? PHP_FLOAT_MAX;

            Log::info('Checking potential start node', [
                'id' => $node->id,
                'type' => $node->type,
                'x' => $x,
                'current_lowest_x' => $lowestX,
            ]);

            if ($x < $lowestX || $x === $lowestX) {
                $lowestX = $x;
                $startNode = $node;
            }
        }

        if (! $startNode) {
            Log::error('No valid start node found! Using first node as fallback.');
            $startNode = reset($nodes);
        }

        return $startNode;
    }

    /**
     * Check whether the incoming message matches any keyword_trigger node in this flow.
     * Used to decide whether to reset the saved contact state or resume from it.
     */
    private function messageMatchesKeywordTrigger(array $nodes, string $message): bool
    {
        return self::nodesMatchKeywordMessage($nodes, $message);
    }

    /**
     * Public helper for dispatch selection (exclusive keyword match).
     */
    public function matchesKeywordMessage(string $message): bool
    {
        $flowData = json_decode($this->flow_data ?: '{}');
        $nodes = is_object($flowData) && isset($flowData->nodes) ? (array) $flowData->nodes : [];

        return self::nodesMatchKeywordMessage($nodes, $message);
    }

    /**
     * @param  array<int, mixed>  $nodes
     */
    public static function nodesMatchKeywordMessage(array $nodes, string $message): bool
    {
        foreach ($nodes as $node) {
            $nodeArray = (array) $node;
            if (($nodeArray['type'] ?? '') !== 'keyword_trigger') {
                continue;
            }

            $data = (array) ($nodeArray['data'] ?? []);
            $keywords = $data['keywords'] ?? [];

            foreach ($keywords as $kw) {
                $kw = (array) $kw;
                $value = $kw['value'] ?? '';
                $matchType = $kw['matchType'] ?? 'exact';

                if ($matchType === 'exact' && strtolower(trim($message)) === strtolower(trim($value))) {
                    return true;
                }
                if ($matchType === 'contains' && str_contains(strtolower($message), strtolower($value))) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Resume a flow after an MPesa STK Push callback arrives.
     * The contact's current_node is still set to the MPesa node,
     * so makeGraph will set it as isStartNode and call listenForReply.
     */
    public function resumeFromMpesaCallback(Contact $contact)
    {
        $this->resumeWaitingNode($contact, null);
    }

    public function resumeFromPaymentCallback(Contact $contact, string $status = 'success'): void
    {
        $contact->setContactState($this->id, 'payment_result_status', $status);
        $this->resumeWaitingNode($contact, 'payment_'.$status);
    }

    public function resumeBookingPaymentSuccess(Contact $contact, string $nodeId, int $reservationId): void
    {
        try {
            $reservation = Reservation::withoutGlobalScopes()->find($reservationId);

            if (! $reservation) {
                Log::error('Flow booking resume: reservation not found', ['reservationId' => $reservationId]);

                return;
            }

            $flowData = $this->getDecodedFlowData();

            if (! $flowData || ! isset($flowData->nodes, $flowData->edges)) {
                return;
            }

            $nodes = $this->buildWiredNodes($flowData->nodes, $flowData->edges);
            $node = $nodes[$nodeId] ?? null;

            if (! $node instanceof BookAppointment) {
                BookAppointment::notifyPaymentOutcome($this->id, $contact->id, $nodeId, 'success');

                return;
            }

            $node->isStartNode = true;
            $mockData = new \stdClass();
            $mockData->contact_id = $contact->id;
            $mockData->company_id = $contact->company_id;
            $mockData->value = '';
            $mockData->extra = null;

            $node->completeSuccess($contact, $reservation, '', $mockData);
        } catch (\Exception $e) {
            Log::error('Flow booking resume: exception', [
                'error' => $e->getMessage(),
                'flowId' => $this->id,
                'nodeId' => $nodeId,
            ]);
        }
    }

    public function resumeEventRegistrationPaymentSuccess(Contact $contact, string $nodeId, int $registrationId): void
    {
        try {
            $registration = EventRegistration::withoutGlobalScopes()->find($registrationId);

            if (! $registration) {
                Log::error('Flow event registration resume: registration not found', ['registrationId' => $registrationId]);

                return;
            }

            $flowData = $this->getDecodedFlowData();

            if (! $flowData || ! isset($flowData->nodes, $flowData->edges)) {
                return;
            }

            $nodes = $this->buildWiredNodes($flowData->nodes, $flowData->edges);
            $node = $nodes[$nodeId] ?? null;

            if (! $node instanceof BookingEventRegister) {
                BookingEventRegister::notifyPaymentOutcome($this->id, $contact->id, $nodeId, 'success');

                return;
            }

            $node->isStartNode = true;
            $mockData = new \stdClass();
            $mockData->contact_id = $contact->id;
            $mockData->company_id = $contact->company_id;
            $mockData->value = '';
            $mockData->extra = null;

            $node->completeSuccess($contact, $registration, '', $mockData);
        } catch (\Exception $e) {
            Log::error('Flow event registration resume: exception', [
                'error' => $e->getMessage(),
                'flowId' => $this->id,
                'nodeId' => $nodeId,
            ]);
        }
    }

    /**
     * Resume a flow after a catalog web checkout completes.
     *
     * @param  list<array<string, mixed>>  $cartItems
     */
    public function resumeFromCatalogCheckout(Contact $contact, string $productId, array $cartItems = [])
    {
        $extra = $cartItems !== []
            ? CatalogFlowCallbackService::CHECKOUT_COMPLETE_EXTRA
            : $productId;

        $this->resumeWaitingNode($contact, $extra);
    }

    /**
     * Resume a flow after a listing catalog web inquiry is submitted.
     */
    public function resumeFromListingInquiry(Contact $contact, string $itemId): void
    {
        $extra = app(CatalogFlowCallbackService::class)->listingInquiryExtra($itemId);
        $this->resumeWaitingNode($contact, $extra);
    }

    /**
     * Resume automation from the onAbandoned handle when a WhatsApp Form was abandoned.
     * Falls back to else for legacy graphs.
     */
    public function resumeFromFormAbandonment(Contact $contact, string $whatsappFlowNodeId): bool
    {
        try {
            $flowData = $this->getDecodedFlowData();
            if (! $flowData || ! isset($flowData->edges)) {
                return false;
            }

            $targetId = null;
            $fallbackElse = null;
            foreach ($flowData->edges as $edge) {
                $edgeArray = is_array($edge) ? $edge : (array) $edge;
                $source = $edgeArray['source'] ?? null;
                if ($source !== $whatsappFlowNodeId) {
                    continue;
                }

                $handle = (string) ($edgeArray['sourceHandle'] ?? '');
                if ($handle === 'onAbandoned' || str_contains($handle, 'onAbandoned')) {
                    $targetId = $edgeArray['target'] ?? null;
                    break;
                }
                if (($handle === 'else' || str_contains($handle, 'else')) && ! $fallbackElse) {
                    $fallbackElse = $edgeArray['target'] ?? null;
                }
            }

            $targetId = $targetId ?: $fallbackElse;
            if (! $targetId) {
                return false;
            }

            $contact->clearContactState($this->id, 'current_node');
            $contact->primeFlowStateCache($this->id);

            $nodes = $this->getWiredNodes($flowData->nodes, $flowData->edges);
            if (! isset($nodes[$targetId])) {
                return false;
            }

            $nodes[$targetId]->isStartNode = true;

            $mockData = new \stdClass();
            $mockData->contact_id = $contact->id;
            $mockData->company_id = $contact->company_id;
            $mockData->value = '';
            $mockData->extra = json_encode(['abandoned' => true]);

            $nodes[$targetId]->process('', $mockData);

            return true;
        } catch (\Exception $e) {
            Log::error('Flow form abandonment resume: exception', [
                'error' => $e->getMessage(),
                'flowId' => $this->id,
                'nodeId' => $whatsappFlowNodeId,
            ]);

            return false;
        }
    }

    private function resumeWaitingNode(Contact $contact, ?string $extra): void
    {
        try {
            $flowData = $this->getDecodedFlowData();
            $startNode = $contact->getContactStateValue($this->id, 'current_node');

            if (! $startNode || ! isset($flowData->nodes) || ! isset($flowData->edges)) {
                Log::error('Flow resume: missing flow data or current_node', ['flowId' => $this->id]);

                return;
            }

            $contact->primeFlowStateCache($this->id);
            $graph = $this->makeGraph($flowData->nodes, $flowData->edges, $startNode);

            $mockData = new \stdClass();
            $mockData->contact_id = $contact->id;
            $mockData->company_id = $contact->company_id;
            $mockData->value = '';
            $mockData->extra = $extra;

            $graph->process('', $mockData);

        } catch (\Exception $e) {
            Log::error('Flow resume: exception', ['error' => $e->getMessage(), 'flowId' => $this->id]);
        }
    }

    //Company
    public function company()
    {
        return $this->belongsTo(Company::class);
    }
}
