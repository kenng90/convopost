<?php

namespace Modules\Flowmaker\Models\Nodes;

use App\Models\WhatsappFlow as WhatsappFlowModel;
use App\Models\WhatsappFlowResponse;
use App\Services\WhatsappFlowSendService;
use App\Services\WhatsappFlowSubmissionService;
use Illuminate\Support\Facades\Log;

class WhatsAppFlow extends Node
{
    // public function listenForReply($message, $data)
    // {
    //     Log::info('WhatsApp Flow: listening for flow completion', ['nodeId' => $this->id]);

    //     $extraData = $data->extra ?? null;
    //     $contactId = is_object($data) ? $data->contact_id : $data['contact_id'];
    //     $contact = \Modules\Flowmaker\Models\Contact::find($contactId);

    //     if ($extraData == null || empty($extraData)) {
    //         Log::info('WhatsApp Flow: no response data received');
    //         return;
    //     }

    //     // Extract response data (should be an array of form responses)
    //     $rawData = is_string($extraData) ? json_decode($extraData, true) : $extraData;

    //     // Strip internal Meta keys — keep only user-submitted field values
    //     $internalKeys = ['flow_token', 'version', 'action', 'screen', 'name'];
    //     $responseData = is_array($rawData)
    //         ? array_filter($rawData, fn ($k) => ! in_array($k, $internalKeys, true), ARRAY_FILTER_USE_KEY)
    //         : [];

    //     Log::info('WhatsApp Flow: response data received', [
    //         'contactId'    => $contactId,
    //         'rawData'      => $rawData,
    //         'responseData' => $responseData,
    //     ]);

    //     // Get the settings to find the flow response record
    //     $settings = $this->getDataAsArray()['settings'] ?? [];
    //     $flowId = $settings['whatsappFlowId'] ?? null;

    //     if (!$flowId) {
    //         Log::error('WhatsApp Flow: no WhatsApp Flow configured');
    //         return;
    //     }

    //     $whatsappFlow = WhatsappFlowModel::find($flowId);
    //     if (!$whatsappFlow) {
    //         Log::error('WhatsApp Flow: flow not found', ['flowId' => $flowId]);
    //         return;
    //     }

    //     // Find the response record — prefer the ID stored in contact state (set when the
    //     // flow was sent), then fall back to a DB query.
    //     $responseId = $contact->getContactStateValue($this->flow_id, 'whatsapp_flow_response_id');
    //     $flowResponse = $responseId ? WhatsappFlowResponse::find($responseId) : null;

    //     if (!$flowResponse) {
    //         $flowResponse = WhatsappFlowResponse::where([
    //             'whatsapp_flow_id' => $flowId,
    //             'flow_id'          => $this->flow_id,
    //             'flow_node_id'     => $this->id,
    //             'contact_id'       => $contactId,
    //         ])->latest()->first();
    //     }

    //     if (!$flowResponse) {
    //         Log::warning('WhatsApp Flow: flow response record not found, creating one');
    //         $flowResponse = WhatsappFlowResponse::create([
    //             'company_id'       => $contact->company_id,
    //             'whatsapp_flow_id' => $flowId,
    //             'flow_id'          => $this->flow_id,
    //             'flow_node_id'     => $this->id,
    //             'contact_id'       => $contactId,
    //             'contact_phone'    => $contact->phone,
    //             'contact_name'     => $contact->name,
    //             'status'           => 'pending',
    //         ]);
    //     }

    //     // Only call markCompleted if there are actual form field values.
    //     // The nfm_reply webhook handler runs first and already marked the record
    //     // as completed with whatever Meta sent. If responseData is empty here it
    //     // means Meta didn't include any form fields — don't overwrite with empty data.
    //     if (!empty($responseData)) {
    //         $flowResponse->markCompleted($responseData);
    //         Log::info('WhatsApp Flow: updated response with form data', [
    //             'flowResponseId' => $flowResponse->id,
    //             'fieldCount'     => count($responseData),
    //         ]);
    //     } elseif ($flowResponse->status !== 'completed') {
    //         // No form fields but record still pending — mark as completed
    //         $flowResponse->markCompleted([]);
    //         Log::info('WhatsApp Flow: marked as completed (no form fields)', [
    //             'flowResponseId' => $flowResponse->id,
    //         ]);
    //     } else {
    //         Log::info('WhatsApp Flow: response already completed by webhook handler', [
    //             'flowResponseId' => $flowResponse->id,
    //             'existingFields' => count($flowResponse->responses ?? []),
    //         ]);
    //     }

    //     // Clear the waiting state
    //     $contact->clearContactState($this->flow_id, 'current_node');

    //     // Store responses in contact state for downstream nodes to access
    //     $contact->setContactState($this->flow_id, 'whatsapp_flow_responses', json_encode($responseData));

    //     // Check for conditional routing
    //     $settings = $this->getDataAsArray()['settings'] ?? [];
    //     $conditions = $settings['conditions'] ?? [];

    //     if (!empty($conditions)) {
    //         Log::info('WhatsApp Flow: evaluating conditions', ['conditionCount' => count($conditions)]);

    //         // Evaluate each condition in order
    //         foreach ($conditions as $conditionIndex => $condition) {
    //             if ($this->evaluateCondition($condition, $responseData)) {
    //                 Log::info('WhatsApp Flow: condition matched', ['conditionIndex' => $conditionIndex]);
    //                 $nextNode = $this->getNextNodeId("condition_{$conditionIndex}");
    //                 if ($nextNode) {
    //                     Log::info('WhatsApp Flow: routing to condition match node', ['conditionIndex' => $conditionIndex]);
    //                     $nextNode->process($message, $data);
    //                     return;
    //                 }
    //             }
    //         }

    //         // No condition matched - route to else
    //         Log::info('WhatsApp Flow: no conditions matched, routing to else');
    //         $nextNode = $this->getNextNodeId('else');
    //         if ($nextNode) {
    //             $nextNode->process($message, $data);
    //         }
    //     } else {
    //         // No conditions - route to onFlowCompleted handle
    //         $nextNode = $this->getNextNodeId('onFlowCompleted');
    //         if ($nextNode) {
    //             Log::info('WhatsApp Flow: routing to onFlowCompleted node');
    //             $nextNode->process($message, $data);
    //         } else {
    //             Log::info('WhatsApp Flow: no onFlowCompleted node connected');
    //         }
    //     }
    // }

    public function listenForReply($message, $data)
    {
        Log::info('WhatsApp Flow: listening for flow completion', ['nodeId' => $this->id]);

        $extraData = $data->extra ?? null;
        $contactId = is_object($data) ? $data->contact_id : $data['contact_id'];
        $contact = \Modules\Flowmaker\Models\Contact::find($contactId);

        if ($extraData == null || empty($extraData)) {
            Log::info('WhatsApp Flow: no response data received');

            return;
        }

        // Decode the raw payload from extra (always the full Meta nfm_reply response_json)
        $rawData = is_string($extraData) ? json_decode($extraData, true) : $extraData;
        $flowToken = $rawData['flow_token'] ?? null;

        // Strip internal Meta keys — keep only user-submitted field values
        $internalKeys = ['flow_token', 'version', 'action', 'screen', 'name'];
        $responseData = is_array($rawData)
            ? array_filter($rawData, fn ($k) => ! in_array($k, $internalKeys, true), ARRAY_FILTER_USE_KEY)
            : [];

        Log::info('WhatsApp Flow: response data received', [
            'contactId' => $contactId,
            'rawData' => $rawData,
            'responseData' => $responseData,
            'flowToken' => $flowToken,
        ]);

        // Get settings
        $settings = $this->getDataAsArray()['settings'] ?? [];
        $flowId = $settings['whatsappFlowId'] ?? null;

        if (! $flowId) {
            Log::error('WhatsApp Flow: no WhatsApp Flow configured');

            return;
        }

        $whatsappFlow = WhatsappFlowModel::find($flowId);
        if (! $whatsappFlow) {
            Log::error('WhatsApp Flow: flow not found', ['flowId' => $flowId]);

            return;
        }

        // ── Locate the response record ────────────────────────────────────────────
        // Priority 1: contact state (most reliable — set when the flow was sent)
        $responseId = $contact->getContactStateValue($this->flow_id, 'whatsapp_flow_response_id');
        $flowResponse = $responseId ? WhatsappFlowResponse::find($responseId) : null;

        // Priority 2: match by flow_token stored in the DB record
        if (! $flowResponse && $flowToken) {
            $flowResponse = WhatsappFlowResponse::where('flow_token', $flowToken)->first();
        }

        // Priority 3: latest pending record for this contact+flow
        if (! $flowResponse) {
            $flowResponse = WhatsappFlowResponse::where([
                'whatsapp_flow_id' => $flowId,
                'flow_id' => $this->flow_id,
                'flow_node_id' => $this->id,
                'contact_id' => $contactId,
            ])->latest()->first();
        }

        if (! $flowResponse) {
            Log::warning('WhatsApp Flow: flow response record not found, creating one');
            $flowResponse = WhatsappFlowResponse::create([
                'company_id' => $contact->company_id,
                'whatsapp_flow_id' => $flowId,
                'flow_id' => $this->flow_id,
                'flow_node_id' => $this->id,
                'contact_id' => $contactId,
                'contact_phone' => $contact->phone,
                'contact_name' => $contact->name,
                'status' => 'pending',
            ]);
        }

        // ── Resolve the actual field data ─────────────────────────────────────────
        // If nfm_reply had no fields in extra, load from DB — the webhook handler
        // may have already stored them via markCompleted() before this node ran.
        if (empty($responseData) && $flowResponse->status === 'completed') {
            $dbResponses = $flowResponse->responses ?? [];
            if (! empty($dbResponses)) {
                $responseData = $dbResponses;
                Log::info('WhatsApp Flow: loaded response data from DB record (extra had no fields)', [
                    'flowResponseId' => $flowResponse->id,
                    'fieldCount' => count($responseData),
                    'fields' => array_keys($responseData),
                ]);
            }
        }

        // ── Persist the response data ─────────────────────────────────────────────
        $submissionService = app(WhatsappFlowSubmissionService::class);

        if (! empty($responseData) || $flowResponse->status !== 'completed') {
            $submissionService->handleCompleted(
                $flowResponse,
                $responseData,
                $contact,
                $this->flow_id
            );
            $responseData = $flowResponse->fresh()->responses ?? $responseData;
        } else {
            $submissionService->syncResponseToContactState($contact, $this->flow_id, $flowResponse->responses ?? [], $whatsappFlow);
        }

        $this->applyCrmMappings($contact, $responseData, $settings);
        $this->applyOnCompleteActions($contact, $settings);

        // Clear the waiting state
        $contact->clearContactState($this->flow_id, 'current_node');

        Log::info('WhatsApp Flow: final responseData going to routing', [
            'fieldCount' => count($responseData),
            'fields' => array_keys($responseData),
            'values' => $responseData,
        ]);

        // ── Route to next node ────────────────────────────────────────────────────
        $conditions = $settings['conditions'] ?? [];
        $scoreRules = $settings['scoreRules'] ?? [];
        $scoreThreshold = $settings['scoreThreshold'] ?? null;

        if (is_array($scoreRules) && $scoreRules !== [] && $scoreThreshold !== null && $scoreThreshold !== '') {
            $score = $this->computeResponseScore($scoreRules, $responseData);
            $contact->setContactState($this->flow_id, 'whatsapp_flow_score', (string) $score);
            Log::info('WhatsApp Flow: score evaluated', ['score' => $score, 'threshold' => $scoreThreshold]);

            if ($score >= (float) $scoreThreshold) {
                $nextNode = $this->getNextNodeId('score_pass');
                if ($nextNode) {
                    $nextNode->process($message, $data);

                    return;
                }
            } else {
                $nextNode = $this->getNextNodeId('score_fail');
                if ($nextNode) {
                    $nextNode->process($message, $data);

                    return;
                }
            }
        }

        if (! empty($conditions)) {
            Log::info('WhatsApp Flow: evaluating conditions', ['conditionCount' => count($conditions)]);

            foreach ($conditions as $conditionIndex => $condition) {
                if ($this->evaluateCondition($condition, $responseData)) {
                    Log::info('WhatsApp Flow: condition matched', ['conditionIndex' => $conditionIndex]);
                    $nextNode = $this->getNextNodeId("condition_{$conditionIndex}");
                    if ($nextNode) {
                        $nextNode->process($message, $data);

                        return;
                    }
                }
            }

            Log::info('WhatsApp Flow: no conditions matched, routing to else');
            $nextNode = $this->getNextNodeId('else');
            if ($nextNode) {
                $nextNode->process($message, $data);
            }
        } else {
            $nextNode = $this->getNextNodeId('onFlowCompleted');
            if ($nextNode) {
                Log::info('WhatsApp Flow: routing to onFlowCompleted node');
                $nextNode->process($message, $data);
            } else {
                Log::info('WhatsApp Flow: no onFlowCompleted node connected');
            }
        }
    }

    public function process($message, $data)
    {
        Log::info('WhatsApp Flow: processing', ['isStartNode' => $this->isStartNode, 'nodeId' => $this->id]);

        if ($this->isStartNode) {
            // Check if we're resuming (user completed the flow) by checking if extra data exists
            $extraData = $data->extra ?? null;

            if (! empty($extraData)) {
                // User has completed the flow - resume and listen for reply
                Log::info('WhatsApp Flow: resuming after flow completion', ['extraData' => $extraData]);
                $this->listenForReply($message, $data);
            } else {
                // No extra data — but check if we're already waiting for this flow.
                // sendMessage() fires ContactReplies internally (with extra=null) before
                // our explicit dispatch (with extra=flow_token). That first event would
                // incorrectly re-send the form. Guard against it by checking current_node.
                $contactId = is_object($data) ? $data->contact_id : $data['contact_id'];
                $contact = \Modules\Flowmaker\Models\Contact::find($contactId);
                $currentNode = $contact ? $contact->getContactStateValue($this->flow_id, 'current_node') : '';

                if ($currentNode === $this->id) {
                    Log::info('WhatsApp Flow: already waiting for completion — ignoring trigger without response data', [
                        'nodeId' => $this->id,
                    ]);

                    return ['success' => true];
                }

                // First time — send the flow
                Log::info('WhatsApp Flow: sending flow for first time');

                return $this->sendFlow($message, $data);
            }

            return ['success' => true];
        }

        return $this->sendFlow($message, $data);
    }

    /**
     * Send the WhatsApp Flow to the contact via Meta's interactive flow message API
     */
    private function sendFlow($message, $data)
    {
        $contactId = is_object($data) ? $data->contact_id : $data['contact_id'];
        $contact = \Modules\Flowmaker\Models\Contact::find($contactId);
        $settings = $this->getDataAsArray()['settings'] ?? [];

        $flowId = $settings['whatsappFlowId'] ?? null;

        if (! $flowId) {
            Log::error('WhatsApp Flow: no WhatsApp Flow configured in node settings');

            return ['success' => false];
        }

        $whatsappFlow = WhatsappFlowModel::find($flowId);
        if (! $whatsappFlow) {
            Log::error('WhatsApp Flow: flow not found', ['flowId' => $flowId]);

            return ['success' => false];
        }

        $contact->setContactState($this->flow_id, 'whatsapp_flow_name', $whatsappFlow->name);

        $result = app(WhatsappFlowSendService::class)->sendToContact(
            $whatsappFlow,
            $contact,
            $this->flow_id,
            $this->id,
            $settings['header'] ?? 'Complete the form',
            $settings['footer'] ?? 'Your responses help us serve you better'
        );

        if (! ($result['success'] ?? false)) {
            Log::error('WhatsApp Flow: failed to send flow', ['message' => $result['message'] ?? 'Unknown error']);

            return ['success' => false];
        }

        return ['success' => true];
    }

    /**
     * @param  list<array<string, mixed>>  $scoreRules
     * @param  array<string, mixed>  $responseData
     */
    protected function computeResponseScore(array $scoreRules, array $responseData): float
    {
        $score = 0.0;

        foreach ($scoreRules as $rule) {
            if (! is_array($rule)) {
                continue;
            }

            $points = (float) ($rule['points'] ?? 0);
            if ($points == 0.0) {
                continue;
            }

            if ($this->evaluateSingleCondition($rule, $responseData)) {
                $score += $points;
            }
        }

        return $score;
    }

    /**
     * Evaluate a condition against response data.
     *
     * Condition format:
     * {
     *   fieldName: "contact_preference",
     *   operator: "==",
     *   value: "yes",
     *   allOf?: [{ fieldName, operator, value }, ...]
     * }
     */
    protected function evaluateCondition(array $condition, array $responseData): bool
    {
        $allOf = $condition['allOf'] ?? null;
        if (is_array($allOf) && $allOf !== []) {
            $clauses = $allOf;
            if (! empty($condition['fieldName'])) {
                array_unshift($clauses, [
                    'fieldName' => $condition['fieldName'],
                    'operator' => $condition['operator'] ?? '==',
                    'value' => $condition['value'] ?? '',
                ]);
            }

            foreach ($clauses as $clause) {
                if (! is_array($clause) || ! $this->evaluateSingleCondition($clause, $responseData)) {
                    return false;
                }
            }

            return true;
        }

        return $this->evaluateSingleCondition($condition, $responseData);
    }

    /**
     * @param  array<string, mixed>  $condition
     * @param  array<string, mixed>  $responseData
     */
    protected function evaluateSingleCondition(array $condition, array $responseData): bool
    {
        $fieldName = $condition['fieldName'] ?? '';
        $operator = $condition['operator'] ?? '==';
        $expectedValue = $condition['value'] ?? '';

        if (empty($fieldName)) {
            Log::warning('WhatsApp Flow: condition missing field name');

            return false;
        }

        $actualValue = $responseData[$fieldName] ?? '';

        if (is_array($actualValue)) {
            $actualValue = implode(',', $actualValue);
        }

        $actualValue = (string) $actualValue;
        $expectedValue = (string) $expectedValue;

        Log::debug('WhatsApp Flow: evaluating condition', [
            'fieldName' => $fieldName,
            'operator' => $operator,
            'expectedValue' => $expectedValue,
            'actualValue' => $actualValue,
        ]);

        switch ($operator) {
            case '==':
                return strtolower($actualValue) === strtolower($expectedValue);
            case '!=':
                return strtolower($actualValue) !== strtolower($expectedValue);
            case 'contains':
                return stripos($actualValue, $expectedValue) !== false;
            case 'starts':
                return strpos(strtolower($actualValue), strtolower($expectedValue)) === 0;
            case 'gt':
            case 'lt':
            case 'gte':
            case 'lte':
                if (! is_numeric($actualValue) || ! is_numeric($expectedValue)) {
                    return false;
                }
                $left = (float) $actualValue;
                $right = (float) $expectedValue;

                return match ($operator) {
                    'gt' => $left > $right,
                    'lt' => $left < $right,
                    'gte' => $left >= $right,
                    'lte' => $left <= $right,
                };
            case 'in':
                $haystack = array_map(
                    fn ($item) => strtolower(trim((string) $item)),
                    preg_split('/\s*,\s*/', $expectedValue) ?: []
                );

                return in_array(strtolower($actualValue), $haystack, true);
            default:
                Log::warning('WhatsApp Flow: unknown operator', ['operator' => $operator]);

                return false;
        }
    }

    /**
     * @param  array<string, mixed>  $responseData
     * @param  array<string, mixed>  $settings
     */
    protected function applyCrmMappings($contact, array $responseData, array $settings): void
    {
        $mappings = $settings['fieldMappings'] ?? [];
        if (! is_array($mappings) || $mappings === []) {
            return;
        }

        foreach ($mappings as $mapping) {
            $formFieldKey = $mapping['formFieldKey'] ?? null;
            $contactFieldId = $mapping['contactFieldId'] ?? null;
            if (! $formFieldKey || ! $contactFieldId || $contactFieldId === 'none') {
                continue;
            }

            if (! array_key_exists($formFieldKey, $responseData)) {
                continue;
            }

            $field = \Modules\Contacts\Models\Field::query()
                ->where('id', $contactFieldId)
                ->where('company_id', $contact->company_id)
                ->first();

            if (! $field) {
                continue;
            }

            $value = $responseData[$formFieldKey];
            if (is_array($value)) {
                $value = json_encode($value);
            }

            $existing = $contact->fields()->where('custom_contacts_fields.id', $field->id)->exists();
            if ($existing) {
                $contact->fields()->updateExistingPivot($field->id, ['value' => (string) $value]);
            } else {
                $contact->fields()->attach($field->id, ['value' => (string) $value]);
            }
        }
    }

    /**
     * @param  array<string, mixed>  $settings
     */
    protected function applyOnCompleteActions($contact, array $settings): void
    {
        $onComplete = $settings['onComplete'] ?? [];
        if (! is_array($onComplete)) {
            return;
        }

        $groupId = $onComplete['groupId'] ?? null;
        if (! empty($groupId) && $groupId !== 'none') {
            $group = \Modules\Contacts\Models\Group::query()
                ->where('id', $groupId)
                ->where('company_id', $contact->company_id)
                ->first();

            if ($group && ! $contact->groups()->where('group_id', $groupId)->exists()) {
                $contact->groups()->attach($groupId);
                if (class_exists(\Modules\Journies\Support\GroupRuleBridge::class)) {
                    \Modules\Journies\Support\GroupRuleBridge::contactAddedToGroups($contact, [$groupId]);
                }
            }
        }

        $stageId = $onComplete['stageId'] ?? null;
        if (! empty($stageId) && $stageId !== 'none' && class_exists(\Modules\Journies\Models\JourneyStage::class)) {
            $stage = \Modules\Journies\Models\JourneyStage::query()
                ->where('id', $stageId)
                ->whereHas('journey', fn ($query) => $query->where('company_id', $contact->company_id))
                ->first();

            if ($stage) {
                app(\Modules\Journies\Services\JourneyContactService::class)->moveContactToStage(
                    $contact,
                    $stage,
                    'flow',
                    null,
                    true,
                    false,
                );
            }
        }
    }

    /**
     * Get the next node by handle ID
     */
    protected function getNextNodeId($handleId = null)
    {
        foreach ($this->outgoingEdges as $edge) {
            $sourceHandle = $edge->getSourceHandle() ?? '';
            if ($handleId === null || str_contains($sourceHandle, $handleId)) {
                return $edge->getTarget();
            }
        }

        return null;
    }
}
