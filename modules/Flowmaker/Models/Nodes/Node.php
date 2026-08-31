<?php

namespace Modules\Flowmaker\Models\Nodes;

use App\Enums\MessagingChannelType;
use App\Services\Flowmaker\FlowOutboundService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Modules\Flowmaker\Models\Contact;

class Node
{
    public $edges;

    public $incomingEdges;

    public $outgoingEdges;

    public $data;

    public $id;

    public $type;

    public $isStartNode;

    public $flow_id;

    public function __construct($nodeData, $edges)
    {
        //Log::info('Node data to construct', ['nodeData' => $nodeData]);
        $this->data = $nodeData['data'] ?? [];
        $this->edges = $edges;
        $this->id = $nodeData['id'];
        $this->type = $nodeData['type'];
        $this->incomingEdges = [];
        $this->outgoingEdges = [];
        $this->isStartNode = false;
        //Log::info('Node data to construct set', ['nodeData' => $this->data]);
    }

    public function getDataAsArray()
    {
        //Std to array
        return json_decode(json_encode($this->data), true);
    }

    public function addIncomingEdge($edge)
    {
        $this->incomingEdges[] = $edge;
    }

    public function addOutgoingEdge($edge)
    {
        $this->outgoingEdges[] = $edge;
    }

    /**
     * Process the message and return next node information
     *
     * @param  string  $message  The message to process
     * @param  array  $data  Additional data needed for processing
     * @return array Processing result with success status and next node ID
     */
    public function process($message, $data)
    {
        Log::info('Processing message in normal node', ['message' => $message, 'data' => $data]);
    }

    /**
     * Get the next connected node ID
     */
    protected function getNextNodeId($param = null)
    {
        if (empty($this->edges)) {
            return null;
        }

        // Find edge that connects from this node
        foreach ($this->edges as $edge) {
            if ($edge['source'] === $this->id) {
                return $edge['target'];
            }
        }

        return null;
    }

    //Serialize the node
    public function serialize()
    {
        return json_encode([
            'id' => $this->id,
            'type' => $this->type,
        ]);
    }

    public function getMediaUrl($mediaUrl)
    {
        // Check if the mediaUrl already has /storage/ in it
        if (strpos($mediaUrl, '/storage/') === 0) {
            // If it starts with /storage/, just add the base URL
            return url($mediaUrl);
        } else {
            // Otherwise use Storage::url which adds /storage/ prefix
            return url(Storage::url($mediaUrl));
        }
    }

    /**
     * Skip WhatsApp-native nodes on Instagram/Messenger/TikTok with an optional text fallback,
     * then continue via else / completed / first outgoing edge.
     *
     * @return array{success: bool}|null Null when the node should continue normally.
     */
    protected function skipIfNotWhatsappChannel($message, $data, string $fallbackText = ''): ?array
    {
        $contactId = is_object($data) ? ($data->contact_id ?? null) : ($data['contact_id'] ?? null);
        if (! $contactId) {
            return null;
        }

        $contact = Contact::find($contactId);
        if (! $contact || $contact->messagingChannel() === MessagingChannelType::Whatsapp) {
            return null;
        }

        Log::info('Skipping WhatsApp-only flow node on non-WhatsApp channel', [
            'node_id' => $this->id,
            'type' => $this->type,
            'channel' => $contact->messagingChannel()->value,
            'flow_id' => $this->flow_id,
        ]);

        if ($fallbackText !== '') {
            app(FlowOutboundService::class)->sendText($contact, $fallbackText);
        }

        $contact->clearContactState($this->flow_id, 'current_node');

        $next = null;
        foreach (['else', 'onFlowCompleted', 'onAbandoned'] as $handle) {
            try {
                $next = $this->getNextNodeId($handle);
            } catch (\Throwable $e) {
                $next = null;
            }
            if ($next) {
                break;
            }
        }

        if (! $next) {
            try {
                $next = $this->getNextNodeId();
            } catch (\Throwable $e) {
                $next = null;
            }
        }

        if ($next) {
            $next->process($message, $data);
        }

        return ['success' => true];
    }
}
