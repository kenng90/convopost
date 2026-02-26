<?php

namespace Modules\Reminders\Models;

use App\Scopes\CompanyScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Modules\Wpbox\Models\Contact;

class Reservation extends Model
{
    use HasFactory;

    protected $table = 'rem_reservations';
    public $guarded = [];

    // Contact relation
    public function contact()
    {
        return $this->belongsTo(Contact::class)->withTrashed();
    }

    public function source()
    {
        return $this->belongsTo(Source::class);
    }

    public function makeMessages(){
        //Make the actual messages

        //Get all the Reminders for this reservation
        $reminders=Remineder::where('company_id',$this->company_id)->get();

        //Remove the reminders that are not for the same source as the reservation
        $reminders = $reminders->reject(function ($reminder) {
            return $reminder->source_id !== null && $reminder->source_id != $this->source_id;
        });

        //For each reminder, make the messages
        foreach($reminders as $reminder){
            $reminder->makeMessages($this);
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

        //Handle after create
        static::created(function ($model){
            //Make the actual messages
            $model->makeMessages();
        });
    }
}
