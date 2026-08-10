<?php

namespace Modules\Flowmaker\Models\Nodes;

use App\Services\Flowmaker\FlowOutboundService;
use Illuminate\Support\Facades\Log;
use Modules\Flowmaker\Models\Contact;

class Buttons extends Node
{
    public function listenForReply($message, $data)
    {
        Log::info('Listening for reply in buttons node', ['nodeId' => $this->id, 'flowId' => $this->flow_id]);

        $extraData = $data->extra ?? '';
        $node = null;
        $elseNode = $this->getNextNodeId('else');
        $settings = $this->getDataAsArray()['settings'] ?? [];
        $activeButtons = (int) ($settings['activeButtons'] ?? 0);

        if ($extraData === null || $extraData === '') {
            $extraData = $this->matchChoiceFromText((string) $message, $settings, $activeButtons);
        }

        if ($extraData != null && $extraData != '') {
            Log::info('Extra data found', ['extraData' => $extraData]);

            for ($i = 1; $i <= $activeButtons; $i++) {
                $buttonKey = "button{$i}";
                if (isset($settings[$buttonKey]) && $settings[$buttonKey] !== null) {
                    $btnID = "button-{$i}_id{$this->id}_flow{$this->flow_id}";

                    if ($btnID == $extraData) {
                        $btnIDsub = substr($btnID, 0, 8);
                        $node = $this->getNextNodeId($btnIDsub) ?? $elseNode;
                        break;
                    }
                }
            }
        } else {
            Log::info('No button click detected (no extra data) - keeping current_node state');

            return;
        }

        $contactId = is_object($data) ? $data->contact_id : $data['contact_id'];
        $contact = Contact::find($contactId);
        $contact->clearContactState($this->flow_id, 'current_node');

        if ($node != null) {
            $node->process($message, $data);
        } elseif ($elseNode != null) {
            $elseNode->process($message, $data);
        }
    }

    public function process($message, $data)
    {
        Log::info('Processing message in buttons node', ['message' => $message, 'data' => $data]);
        if ($this->isStartNode) {
            $this->listenForReply($message, $data);

            return [
                'success' => true,
            ];
        }

        $contactId = is_object($data) ? $data->contact_id : $data['contact_id'];
        $contact = Contact::find($contactId);

        $settings = $this->getDataAsArray()['settings'];
        $header = $contact->changeVariables($settings['header'] ?? '');
        $body = $contact->changeVariables($settings['body'] ?? '');
        $footer = $contact->changeVariables($settings['footer'] ?? '');
        $activeButtons = $settings['activeButtons'] ?? 0;
        $isListMode = $activeButtons > 3;

        $choices = [];
        for ($i = 1; $i <= $activeButtons; $i++) {
            $buttonKey = "button{$i}";
            if (isset($settings[$buttonKey]) && $settings[$buttonKey] !== null) {
                $choices[] = [
                    'id' => "button-{$i}_id{$this->id}_flow{$this->flow_id}",
                    'title' => $contact->changeVariables($settings[$buttonKey]),
                ];
            }
        }

        $contact->setContactState($this->flow_id, 'current_node', $this->id);

        $sent = app(FlowOutboundService::class)->sendChoices(
            $contact,
            $body,
            $choices,
            $header !== '' ? $header : null,
            $footer !== '' ? $footer : null,
            $isListMode ? $contact->changeVariables($settings['listButtonName'] ?? __('Choose an option')) : null,
        );

        if ($sent && (int) $sent->status === 5) {
            $this->clearWaitOnSendFailure($contact, $sent);
        }

        return [
            'success' => true,
        ];
    }

    protected function getNextNodeId($handleId = null)
    {
        foreach ($this->outgoingEdges as $edge) {
            if (str_contains($edge->getSourceHandle(), $handleId)) {
                return $edge->getTarget();
            }
        }

        return null;
    }

    /**
     * Match numbered / title replies used on Instagram & Messenger text menus.
     */
    private function matchChoiceFromText(string $message, array $settings, int $activeButtons): string
    {
        $trimmed = trim($message);
        if ($trimmed === '') {
            return '';
        }

        if (ctype_digit($trimmed)) {
            $index = (int) $trimmed;
            if ($index >= 1 && $index <= $activeButtons && isset($settings["button{$index}"])) {
                return "button-{$index}_id{$this->id}_flow{$this->flow_id}";
            }
        }

        for ($i = 1; $i <= $activeButtons; $i++) {
            $title = trim((string) ($settings["button{$i}"] ?? ''));
            if ($title !== '' && strcasecmp($title, $trimmed) === 0) {
                return "button-{$i}_id{$this->id}_flow{$this->flow_id}";
            }
        }

        return '';
    }

    private function clearWaitOnSendFailure(Contact $contact, $sent): void
    {
        $error = strtolower((string) ($sent->error ?? ''));
        if (str_contains($error, 'window')) {
            Log::info('Clearing flow wait after messaging window expiry', [
                'contact_id' => $contact->id,
                'flow_id' => $this->flow_id,
                'node_id' => $this->id,
            ]);
            $contact->clearContactState($this->flow_id, 'current_node');
        }
    }
}
