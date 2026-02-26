<?php

namespace Modules\Flowmaker\Models\Nodes;

use Illuminate\Support\Facades\Log;
use Modules\Flowmaker\Models\Contact;
use Modules\Contacts\Models\Group;

class AssignGroup extends Node
{
    
    public function process($message, $data)
    {
        Log::info('Processing message in AssignGroup node', ['message' => $message, 'data' => $data]);
        
        try {
            // Get group settings from node data
            $settings = $this->getDataAsArray()['settings'] ?? [];
            
            Log::info('AssignGroup Settings', ['settings' => $settings]);

            // Find the contact
            $contactId = is_object($data) ? $data->contact_id : $data['contact_id'];
            $contact = Contact::find($contactId);
            
            if (!$contact) {
                Log::error('Contact not found', ['contactId' => $contactId]);
                return ['success' => false];
            }

            Log::info('Contact found', ['contact' => $contact->id]);

            // Extract group ID from settings
            $groupId = $settings['groupId'] ?? null;
            $action = $settings['action'] ?? 'add'; // 'add' or 'remove'

            if (empty($groupId) || $groupId === 'none') {
                Log::info('No group selected');
                return ['success' => true];
            }

            // Verify the group exists and belongs to the same company
            $group = Group::where('id', $groupId)
                ->where('company_id', $contact->company_id)
                ->first();
            
            if (!$group) {
                Log::error('Group not found or does not belong to the same company', [
                    'groupId' => $groupId, 
                    'companyId' => $contact->company_id
                ]);
                return ['success' => false];
            }

            if ($action === 'add') {
                // Add contact to group (if not already added)
                if (!$contact->groups()->where('group_id', $groupId)->exists()) {
                    $contact->groups()->attach($groupId);
                    Log::info('Contact added to group', [
                        'contactId' => $contact->id,
                        'groupId' => $groupId,
                        'groupName' => $group->name
                    ]);
                } else {
                    Log::info('Contact already in group', [
                        'contactId' => $contact->id,
                        'groupId' => $groupId
                    ]);
                }
            } else if ($action === 'remove') {
                // Remove contact from group
                $contact->groups()->detach($groupId);
                Log::info('Contact removed from group', [
                    'contactId' => $contact->id,
                    'groupId' => $groupId,
                    'groupName' => $group->name
                ]);
            }
            
            Log::info('Contact group assignment updated successfully', [
                'contactId' => $contact->id,
                'groupId' => $groupId,
                'action' => $action
            ]);

        } catch (\Exception $e) {
            Log::error('Error processing AssignGroup node', ['error' => $e->getMessage()]);
            return ['success' => false];
        }

        // Continue flow to next node if one exists
        $nextNode = $this->getNextNodeId();
        if ($nextNode) {
            $nextNode->process($message, $data);
        }

        return ['success' => true];
    }

    protected function getNextNodeId($data = null)
    {
        // Get the first outgoing edge's target
        if (!empty($this->outgoingEdges)) {
            return $this->outgoingEdges[0]->getTarget();
        }
        return null;
    }
} 