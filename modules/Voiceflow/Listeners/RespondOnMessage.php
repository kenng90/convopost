<?php

namespace Modules\Voiceflow\Listeners;

use App\Models\Company;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Modules\Wpbox\Models\Reply;

class RespondOnMessage
{

    public function handleMessageByContact($event){
        try {
            $contact=$event->message->contact;
            $message=$event->message;
            if($contact->enabled_ai_bot&&!$message->bot_has_replied){
            
                //Based on the contact company, find this company firs active AI Bot
                $company_id= $contact->company_id;


                //Get the company
                $company=Company::findOrFail($company_id);

                //Check if enable_voiceflow and voiceflow_api_key>5
                if($company->getConfig('enable_voiceflow',false)&&strlen($company->getConfig('voiceflow_api_key'))>5){
                   //Send question to bot
                   $messageFromBot=$this->makePredictionRequest($message,$contact,$company);    
       
                   if($messageFromBot){

                        foreach ($messageFromBot as $mkey => $replyfrombot) {
                            if($replyfrombot['type']=="text"){
                                //if next mkey is choice, use that one for the text
                                if($mkey<count($messageFromBot)-1&&$messageFromBot[$mkey+1]['type']=="choice"){
                                    
                                }else{
                                    $contact->sendMessage($replyfrombot['payload']['message'],false);
                                }
                                
                            }
                            if($replyfrombot['type']=="visual"&&$replyfrombot['payload']['visualType']=="image"){
                                $contact->sendMessage($replyfrombot['payload']['image'],false,false,'IMAGE');
                            }
                            if($replyfrombot['type']=="choice"){
                               
                                //Create a reply with buttons
                                $reply=Reply::create([
                                    'trigger' => '---',
                                    'name'=>"test",
                                    'header'=>"",
                                    'footer'=>"",
                                    'text' => "Make a choice",
                                    'company_id' => $company_id,
                                    'created_at' => now(),
                                    'updated_at' => now()
                                ]);

                                //If previous mkey is text, use that one fot the text
                                if($mkey>0&&$messageFromBot[$mkey-1]['type']=="text"){
                                    $reply->text=$messageFromBot[$mkey-1]['payload']['message'];
                                }
                                
                                //HANDLE BUTTONS
                                try {
                                    foreach ($replyfrombot['payload']['buttons'] as $key => $button) {

                                        if($key<3){
                                            $index=$key+1;
                                            $reply->{"button".$index} =$button['name'];
                                            $reply->{"button".$index."_id"}=$button['request']['type'];
                                        }
                                       
                                       
                                          
                                       
                                           
                                    }
                                } catch (\Throwable $th) {
                                  
                                }
                                $reply->update();

                                Log::info($reply);
                                

                                //SEND THE REPLY

                                $contact->sendReply($reply);

                                $reply->delete();

                                
                            }
                            if($replyfrombot['type']=="cardV2"){
                                if(isset($replyfrombot['payload']['imageUrl'])&&strlen($replyfrombot['payload']['imageUrl'])>5){
                                    $contact->sendMessage($replyfrombot['payload']['imageUrl'],false,false,'IMAGE');
                                }

                                //Create a reply with buttons
                                $reply=Reply::create([
                                    'trigger' => '---',
                                    'name'=>"test",
                                    'header'=>$replyfrombot['payload']['title'],
                                    'footer'=>"",
                                    'text' => $replyfrombot['payload']['description']['text'],
                                    'company_id' => $company_id,
                                    'created_at' => now(),
                                    'updated_at' => now()
                                ]);
                                
                              
                                //HANDLE BUTTONS
                                try {
                                    foreach ($replyfrombot['payload']['buttons'] as $key => $button) {
                                        if($button['request']['type']=="get-it-apiclnp"&&$button['request']['payload']['actions']['0']['type']=="open_url"){
                                            $reply->button_name=$button['name'];
                                            $reply->button_url=$button['request']['payload']['actions']['0']['payload']['url'];
                                        }else{
                                            $index=$key+1;
                                            $reply->{"button".$index} =$button['name'];
                                            $reply->{"button".$index."_id"}=$button['request']['type'];
                                        } 
                                    }
                                } catch (\Throwable $th) {
                                    
                                }
                                $reply->update();
                                

                                //SEND THE REPLY
                                $contact->sendReply($reply);

                                $reply->delete();

                                
                            }
                        }
                       $message->ai_bot_has_replied=true;
                       $message->update();
                   
                   }

                }
    
         
    
    
            }
        } catch (\Throwable $th) {
           
        }
       
        


    }

    public function makePredictionRequest($message, $contact, $company)
    {
        $url = "https://general-runtime.voiceflow.com/state/user/" . $contact->phone . "/interact?logs=off";

        $headers = [
            'Content-Type' => 'application/json',
            'accept' => 'application/json',
            'versionID' => 'production',
            'Authorization' => $company->getConfig('voiceflow_api_key', '')
        ];

        $data = [
            "action" => [
                "type" => "text",
                "payload" => $message->value,
            ]
        ];

        //If there is extra in the message
        if ($message->extra) {
            $data['action']['type'] = $message->extra;
        }

        $response = Http::withHeaders($headers)->post($url, $data);

        if ($response->successful()) {
            $responseData = $response->json();
            return $responseData;
        } else {
            $errorCode = $response->status();
            return null;
        }
    }

    public function subscribe($events)
    {
        $events->listen(
            'Modules\Wpbox\Events\ContactReplies',
            [RespondOnMessage::class, 'handleMessageByContact']
        );
    }

}
