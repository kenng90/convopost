<?php

namespace Modules\Flowmaker\Models\Nodes;

use App\Services\Flowmaker\FlowOutboundService;
use Illuminate\Support\Facades\Log;
use Modules\Flowmaker\Models\Contact;

class ListMessage extends Node
{
    public function listenForReply($message, $data)
    {
        Log::info('Listening for reply in list message node');

        $extraData = $data->extra ?? '';
        $node = null;
        $elseNode = $this->getNextNodeId('else');
        $settings = $this->getDataAsArray()['settings'] ?? [];
        $sections = $settings['sections'] ?? [];

        if ($extraData === null || $extraData === '') {
            $extraData = $this->matchChoiceFromText((string) $message, $sections);
        }

        if ($extraData != null && $extraData != '') {
            foreach ($sections as $section) {
                $rows = $section['rows'] ?? [];
                foreach ($rows as $row) {
                    $listItemId = "{$section['id']}-{$row['id']}_id{$this->id}_flow{$this->flow_id}";
                    if ($listItemId == $extraData) {
                        $handleId = "{$section['id']}-{$row['id']}";
                        $node = $this->getNextNodeId($handleId) ?? $elseNode;
                        break 2;
                    }
                }
            }
        } else {
            Log::info('No list selection detected (no extra data) - keeping current_node state');

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
        Log::info('Processing message in list message node', ['message' => $message, 'data' => $data]);

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
        $buttonText = $contact->changeVariables($settings['buttonText'] ?? 'Choose an option');

        $choices = [];
        foreach ($settings['sections'] ?? [] as $section) {
            foreach ($section['rows'] ?? [] as $row) {
                $choices[] = [
                    'id' => "{$section['id']}-{$row['id']}_id{$this->id}_flow{$this->flow_id}",
                    'title' => $contact->changeVariables($row['title'] ?? ''),
                    'description' => $contact->changeVariables($row['description'] ?? ''),
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
            $buttonText,
        );

        if ($sent && (int) $sent->status === 5) {
            $error = strtolower((string) ($sent->error ?? ''));
            if (str_contains($error, 'window')) {
                $contact->clearContactState($this->flow_id, 'current_node');
            }
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
     * @param  array<int, array<string, mixed>>  $sections
     */
    private function matchChoiceFromText(string $message, array $sections): string
    {
        $trimmed = trim($message);
        if ($trimmed === '') {
            return '';
        }

        $flat = [];
        foreach ($sections as $section) {
            foreach ($section['rows'] ?? [] as $row) {
                $flat[] = [
                    'id' => "{$section['id']}-{$row['id']}_id{$this->id}_flow{$this->flow_id}",
                    'title' => trim((string) ($row['title'] ?? '')),
                ];
            }
        }

        if (ctype_digit($trimmed)) {
            $index = (int) $trimmed - 1;
            if (isset($flat[$index])) {
                return $flat[$index]['id'];
            }
        }

        foreach ($flat as $item) {
            if ($item['title'] !== '' && strcasecmp($item['title'], $trimmed) === 0) {
                return $item['id'];
            }
        }

        return '';
    }
}
