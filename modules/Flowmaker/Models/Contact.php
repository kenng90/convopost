<?php

namespace Modules\Flowmaker\Models;

use Illuminate\Support\Facades\Log;
use Modules\Flowmaker\Models\Node;
use Modules\Wpbox\Models\Contact as ModelsContact;

class Contact extends ModelsContact
{
    public function changeVariables($content, $flowId = null){
        //Change the variables in the content
        $content=str_replace('{{contact_name}}',$this->name,$content);
        $content=str_replace('{{contact_phone}}',$this->phone,$content);
        $content=str_replace('{{contact_email}}',$this->email,$content);

        //Get the last message from the contact, but do a query to get the last message from the contact
        $contactLastMessage = Contact::findOrFail($this->id)->last_message;
        $content=str_replace('{{contact_last_message}}',$contactLastMessage,$content);

        //Replace the country
        if($this->country) {
            $content=str_replace('{{contact_country}}',$this->country->name,$content);
        }

        //Get the custom fields
        $fields=$this->fields;
        foreach ($fields as $key => $field) {
            $content=str_replace('{{'.$field->name.'}}',$field->pivot->value,$content);
        }
        Log::info('Processed Custom Fields', ['content' => $content]);
        
        // Replace flow-specific variables from contact states
        // This handles variables from HTTP nodes, LLM nodes, Question nodes, etc.
        try{
        if ($flowId) {
            $contactStates = $this->getContactState($flowId);
            Log::info('Contact States', ['contactStates' => $contactStates]);
            foreach ($contactStates as $state) {
                $variableName = $state->state;
                $variableValue = $state->value;
                
                  // If the value is JSON, try to decode it for individual field access
                 /*if (is_string($variableValue) && $this->isJson($variableValue)) {
                     $decodedValue = json_decode($variableValue, true);
                     if (is_array($decodedValue)) {
                         // Replace individual fields from JSON
                         foreach ($decodedValue as $key => $value) {
                             if (is_scalar($value)) {
                                 $content = str_replace('{{'.$variableName.'_'.$key.'}}', $value, $content);
                             }
                         }
                         // Also replace the full JSON as string
                         $content = str_replace('{{'.$variableName.'}}', json_encode($decodedValue), $content);
                     }
                                                   } else {
                     // Replace scalar values directly
                     $content = str_replace('{{'.$variableName.'}}', $variableValue, $content);
                 }*/
             }
         }
         Log::info('Processed Contact States', ['content' => $content]);
        }catch(\Exception $e){
            Log::error('Error processing contact states', ['error' => $e->getMessage()]);
        }
        
        // Enhanced variable replacement using regex for more flexible matching
        // This will catch any remaining variables that might be dynamically created
        $content = preg_replace_callback('/\{\{([^}]+)\}\}/', function($matches) use ($flowId) {
            $variableName = trim($matches[1]);
            
            // Try to get from contact states if flowId is provided
            if ($flowId) {
                $value = $this->getContactStateValue($flowId, $variableName);
                if ($value !== null) {
                    return $value;
                }
            }
            
            // If not found, return the original placeholder
            return $matches[0];
        }, $content);
        
        return $content;
    }
    
    /**
     * Check if a string is valid JSON
     */
    private function isJson($string) {
        json_decode($string);
        return json_last_error() === JSON_ERROR_NONE;
    }
    
    //Add contact state
    public function contactState()
    {
        return $this->hasMany(ContactState::class);
    }

    public function getContactState($flowId){
        $states = ContactState::where('contact_id', $this->id)->where('flow_id', $flowId)->get();
        $result = [];
        foreach($states as $state) {
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

    public function clearAllContactState($flowId){
        ContactState::where('contact_id', $this->id)
            ->where('flow_id', $flowId)
            ->delete();
    }

    public function clearContactState($flowId, $state){
        ContactState::where('contact_id', $this->id)->where('flow_id', $flowId)->where('state', $state)->delete();
    }

    public function setContactState($flowId, $state, $value){
        ContactState::updateOrCreate(
            [
                'contact_id' => $this->id,
                'flow_id' => $flowId,
                'state' => $state
            ],
            [
                'value' => $value
            ]
        );
    }

     //Update or set multiple contact states
     public function updateContactStates($flowId, $states){
        foreach($states as $state){
            $this->setContactState($flowId, $state['state'], $state['value']);
        }
    }

    public function getContactStateValue($flowId, $state){
        $contactState = ContactState::where('contact_id', $this->id)->where('flow_id', $flowId)->where('state', $state)->first();
        return $contactState ? $contactState->value : "";
    }
    
    /**
     * Get the AI conversation summary for this contact and flow
     */
    public function getAISummary($flowId){
        return $this->getContactStateValue($flowId, 'ai_summary');
    }
    
    /**
     * Add to the AI conversation summary for this contact and flow
     */
    public function addToAISummary($flowId, $message){
        $currentSummary = $this->getAISummary($flowId);
        $newSummary = $currentSummary . "\n" . $message;
        $this->setContactState($flowId, 'ai_summary', $newSummary);
        return $newSummary;
    }
}