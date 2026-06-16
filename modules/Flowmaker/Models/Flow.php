<?php

namespace Modules\Flowmaker\Models;

use App\Models\Company;
use App\Scopes\CompanyScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Modules\Flowmaker\Models\Nodes\AssignAgent;
use Modules\Flowmaker\Models\Nodes\AssignGroup;
use Modules\Flowmaker\Models\Nodes\AssignJourneyStage;
use Modules\Flowmaker\Models\Nodes\BookingEventRegister;
use Modules\Flowmaker\Models\Nodes\BookingEventsList;
use Modules\Flowmaker\Models\Nodes\Branch;
use Modules\Flowmaker\Models\Nodes\Buttons;
use Modules\Flowmaker\Models\Nodes\Edge;
use Modules\Flowmaker\Models\Nodes\End;
use Modules\Flowmaker\Models\Nodes\Every;
use Modules\Flowmaker\Models\Nodes\FlowHTTPNode;
use Modules\Flowmaker\Models\Nodes\Keyword;
use Modules\Flowmaker\Models\Nodes\ListMessage;
use Modules\Flowmaker\Models\Nodes\LLM;
use Modules\Flowmaker\Models\Nodes\Media;
use Modules\Flowmaker\Models\Nodes\Message;
use Modules\Flowmaker\Models\Nodes\MpesaStkPush;
use Modules\Flowmaker\Models\Nodes\Node;
use Modules\Flowmaker\Models\Nodes\SetVariable;
use Modules\Flowmaker\Models\Nodes\Template;
use Modules\Flowmaker\Models\Nodes\UserReply;
use Modules\Flowmaker\Models\Nodes\WhatsAppCatalog;
use Modules\Flowmaker\Models\Nodes\WhatsAppFlow;

class Flow extends Model
{
    protected $table = 'flows';

    public $guarded = [];

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

            $flowData = $this->getDecodedFlowData();
            if (! $flowData || ! isset($flowData->nodes) || ! isset($flowData->edges)) {
                Log::error('Invalid flow data structure - missing nodes or edges', ['flowId' => $this->id]);

                return;
            }

            $contact = Contact::with(['fields', 'country'])->findOrFail($data->contact_id);
            $contact->primeFlowStateCache($this->id);
            $startNode = $contact->getContactStateValue($this->id, 'current_node');

            if ($this->messageMatchesKeywordTrigger($flowData->nodes, $message)) {
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
            } elseif ($nodeArray['type'] === 'whatsapp_catalog') {
                $theNewNode = new WhatsAppCatalog($nodeArray, []);
            } elseif ($nodeArray['type'] === 'whatsapp_flow') {
                $theNewNode = new WhatsAppFlow($nodeArray, []);
            } elseif ($nodeArray['type'] === 'booking_events_list') {
                $theNewNode = new BookingEventsList($nodeArray, []);
            } elseif ($nodeArray['type'] === 'booking_event_register') {
                $theNewNode = new BookingEventRegister($nodeArray, []);
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
        try {
            $flowData = $this->getDecodedFlowData();
            $startNode = $contact->getContactStateValue($this->id, 'current_node');

            if (! $startNode || ! isset($flowData->nodes) || ! isset($flowData->edges)) {
                Log::error('MPesa resume: missing flow data or current_node');

                return;
            }

            $contact->primeFlowStateCache($this->id);
            $graph = $this->makeGraph($flowData->nodes, $flowData->edges, $startNode);

            // Create a minimal data object so the node can find the contact
            $mockData = new \stdClass();
            $mockData->contact_id = $contact->id;
            $mockData->company_id = $contact->company_id;
            $mockData->value = '';
            $mockData->extra = null;

            $graph->process('', $mockData);

        } catch (\Exception $e) {
            Log::error('MPesa resume: exception', ['error' => $e->getMessage()]);
        }
    }

    //Company
    public function company()
    {
        return $this->belongsTo(Company::class);
    }
}
