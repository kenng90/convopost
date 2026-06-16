<?php

namespace Modules\Flowmaker\Models;

use Modules\Wpbox\Models\Contact as ModelsContact;

class Contact extends ModelsContact
{
    protected ?array $flowStateCache = null;

    protected ?int $flowStateCacheFlowId = null;

    public function primeFlowStateCache(int $flowId): void
    {
        if ($this->flowStateCacheFlowId === $flowId && $this->flowStateCache !== null) {
            return;
        }

        $this->flowStateCache = ContactState::query()
            ->where('contact_id', $this->id)
            ->where('flow_id', $flowId)
            ->pluck('value', 'state')
            ->all();

        $this->flowStateCacheFlowId = $flowId;
    }

    public function changeVariables($content, $flowId = null)
    {
        $content = str_replace('{{contact_name}}', $this->name, $content);
        $content = str_replace('{{contact_phone}}', $this->phone, $content);
        $content = str_replace('{{contact_email}}', $this->email, $content);
        $content = str_replace('{{contact_last_message}}', $this->last_message ?? '', $content);

        if ($this->relationLoaded('country') ? $this->country : $this->country()->first()) {
            $content = str_replace('{{contact_country}}', $this->country->name, $content);
        }

        if ($this->relationLoaded('fields')) {
            foreach ($this->fields as $field) {
                $content = str_replace('{{'.$field->name.'}}', $field->pivot->value, $content);
            }
        } else {
            foreach ($this->fields as $field) {
                $content = str_replace('{{'.$field->name.'}}', $field->pivot->value, $content);
            }
        }

        if ($flowId) {
            $this->primeFlowStateCache((int) $flowId);

            $content = preg_replace_callback('/\{\{([^}]+)\}\}/', function ($matches) {
                $variableName = trim($matches[1]);

                if (array_key_exists($variableName, $this->flowStateCache ?? [])) {
                    return (string) $this->flowStateCache[$variableName];
                }

                return $matches[0];
            }, $content);
        }

        return $content;
    }

    public function contactState()
    {
        return $this->hasMany(ContactState::class);
    }

    public function getContactState($flowId)
    {
        $states = ContactState::where('contact_id', $this->id)->where('flow_id', $flowId)->get();
        $result = [];
        foreach ($states as $state) {
            if (is_string($state->value)) {
                $decoded = json_decode($state->value, true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $state->value = $decoded;
                }
            }
            $result[] = $state;
        }

        return $result;
    }

    public function clearAllContactState($flowId)
    {
        ContactState::where('contact_id', $this->id)
            ->where('flow_id', $flowId)
            ->delete();

        $this->invalidateFlowStateCache((int) $flowId);
    }

    public function clearContactState($flowId, $state)
    {
        ContactState::where('contact_id', $this->id)->where('flow_id', $flowId)->where('state', $state)->delete();

        if ($this->flowStateCacheFlowId === (int) $flowId && is_array($this->flowStateCache)) {
            unset($this->flowStateCache[$state]);
        }
    }

    public function setContactState($flowId, $state, $value)
    {
        ContactState::updateOrCreate(
            [
                'contact_id' => $this->id,
                'flow_id' => $flowId,
                'state' => $state,
            ],
            [
                'value' => $value,
            ]
        );

        if ($this->flowStateCacheFlowId === (int) $flowId) {
            if ($this->flowStateCache === null) {
                $this->flowStateCache = [];
            }
            $this->flowStateCache[$state] = $value;
        }
    }

    public function updateContactStates($flowId, $states)
    {
        foreach ($states as $state) {
            $this->setContactState($flowId, $state['state'], $state['value']);
        }
    }

    public function getContactStateValue($flowId, $state)
    {
        if ($this->flowStateCacheFlowId === (int) $flowId && is_array($this->flowStateCache)) {
            return $this->flowStateCache[$state] ?? '';
        }

        $contactState = ContactState::where('contact_id', $this->id)->where('flow_id', $flowId)->where('state', $state)->first();

        return $contactState ? $contactState->value : '';
    }

    public function getAISummary($flowId)
    {
        return $this->getContactStateValue($flowId, 'ai_summary');
    }

    public function addToAISummary($flowId, $message)
    {
        $currentSummary = $this->getAISummary($flowId);
        $newSummary = $currentSummary."\n".$message;
        $this->setContactState($flowId, 'ai_summary', $newSummary);

        return $newSummary;
    }

    protected function invalidateFlowStateCache(int $flowId): void
    {
        if ($this->flowStateCacheFlowId === $flowId) {
            $this->flowStateCache = null;
            $this->flowStateCacheFlowId = null;
        }
    }
}
