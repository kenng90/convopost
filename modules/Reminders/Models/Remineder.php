<?php

namespace Modules\Reminders\Models;

use App\Models\Company;
use App\Scopes\CompanyScope;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Modules\Wpbox\Models\Campaign;
use Modules\Wpbox\Models\Message;

class Remineder extends Model
{
    use HasFactory;

    protected $table = 'reminders';
    public $guarded = [];

    //Relations
    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function source()
    {
        return $this->belongsTo(Source::class);
    }

    public function makeMessages(Reservation $reservation){
        //Make the actual messages

        //Get the campaign
        $campaign=Campaign::findOrFail($this->campaign_id);

        //Calculate the time of send
        //Each reservation has a start date and time and an end date and time
        //Each reminder has a time of send in time_type ( minutes or hours )
        //Each reminder can be before or after the reservation
        //Based on this we calculate the time of send
        
        //Get the carbon instance of the reservation start date and time
        $reservationStartDate=Carbon::parse($reservation->start_date);

        //Get the carbon instance of the reservation end date and time
        $reservationEndDate=Carbon::parse($reservation->end_date);

        //Convert the time to minutes
        $timeOfSendInMinutes=$this->time;

        if($this->time_type=="hours"){
            $timeOfSendInMinutes=$timeOfSendInMinutes*60;
        }else if($this->time_type=="days"){
            $timeOfSendInMinutes=$timeOfSendInMinutes*24*60;
        }else if($this->time_type=="weeks"){
            $timeOfSendInMinutes=$timeOfSendInMinutes*24*60*7;
        }else if($this->time_type=="months"){
            $timeOfSendInMinutes=$timeOfSendInMinutes*24*60*30;
        }



        if($this->type==1){
            //Before
            $timeOfSend=$reservationStartDate->subMinutes($timeOfSendInMinutes);
        }else if($this->type==2){
            //After
            $timeOfSend = $reservationEndDate->addMinutes($timeOfSendInMinutes);
        }
       
        //Manually make a request object (Illuminate\Support\Facades\Request) to the campaign controller to make the messages
        $request=new Request();

        // Add request data manually
        $request->replace([
            'send_time' => $timeOfSend->toDateTimeString()
        ]);
      

        //The contacts are the contacts of the reservation
        $contact=$reservation->contact;

        //Set extras
        $contact->extra_value=[
            'start_date' => $reservationStartDate->toDateString(),
            'start_time' => $reservationStartDate->toTimeString(),
            'start_date_time' => $reservationStartDate->toDateTimeString(),
            'end_date' => $reservationEndDate->toDateString(),
            'end_time' => $reservationEndDate->toTimeString(),
            'end_date_time' => $reservationEndDate->toDateTimeString(),
            'external_id' => $reservation->external_id
        ];

        //Alter the campaign content with the contact extra values
        //In campaign variables, i can have {"body":{"1":"Daniel"}}
        //In campaign variables_match, i can have {"body":{"1":"-4"}}
        //If in variables_match, the value is less than -3, it means that the value is the contact extra value,andd should be changed to -3 and the variables_match should be changed to the contact extra value ex {"body":{"1":"start_date"}}
        //start_date = -4
        //start_time = -5
        //start_date_time = -6
        //end_date = -7
        //end_time = -8
        //end_date_time = -9
        //external_id = -10

        
        $campaign_variables = json_decode($campaign->variables,true);
        $campaign_variables_match = json_decode($campaign->variables_match,true);

        // Mapping of variables_match to contact extra values
        $map_variables_match = [
            -4 => 'start_date',
            -5 => 'start_time',
            -6 => 'start_date_time',
            -7 => 'end_date',
            -8 => 'end_time',
            -9 => 'end_date_time',
            -10 => 'external_id'
        ];

        // Loop through variables_match and update values
        foreach ($campaign_variables_match as $key => $matches) {
            foreach ($matches as $match_key => $match_value) {
                // Convert match_value to an integer
                $match_value_int = (int)$match_value;

                // Check if match_value is less than -3 and is in the map
                if ($match_value_int < -3 && isset($map_variables_match[$match_value_int])) {
                    // Update variables_match to the mapped contact extra value
                    $campaign_variables[$key][$match_key] = $map_variables_match[$match_value_int];

                    // Set the campaign variable to -3
                    $campaign_variables_match[$key][$match_key] = "-3";
                }
            }
        }

        // Update the campaign variables and variables_match
        $campaign->variables = json_encode($campaign_variables);
        $campaign->variables_match = json_encode($campaign_variables_match);
      
       

        //Make the messages
        $message=$campaign->makeMessages($request,$contact);

        try {
            $message->extra=$reservation->id;

            //Save the message
            $message->save();

        } catch (\Throwable $th) {
            //throw $th;
        }
    }


    protected static function booted(){
        static::addGlobalScope(new CompanyScope);

        static::creating(function ($model){
           $company_id=session('company_id',null);
            if($company_id){
                $model->company_id=$company_id;
            }
        });
    }
    
}
