<?php

namespace Modules\Reminders\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Validator;
use Laravel\Sanctum\PersonalAccessToken;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Modules\Reminders\Models\Reminder;
use Modules\Reminders\Models\Remineder;
use Modules\Reminders\Models\Reservation;
use Modules\Reminders\Models\Source;
use Modules\Wpbox\Traits\Contacts;

class APIController extends Controller
{
    use Contacts; 


    private function authenticate(Request $request, \Closure $next, $rules = ['token' => 'required'])
    {
        $validator = Validator::make($request->all(), $rules);
        
        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors()
            ], 400);
        }

        //If user is logged in, use it.
        if(Auth::check()){
            return $next($request);
        }

        $token = PersonalAccessToken::findToken($request->token);
        if (!$token) {
            return response()->json(['status' => 'error', 'message' => 'Invalid token'], 401);
        } else {
            $user = User::findOrFail($token->tokenable_id);
            Auth::login($user);
            return $next($request);
        }
    }

    public function getReminders(Request $request)
    {
        return $this->authenticate($request, function($request) {
            $reminders = Remineder::where('user_id', Auth::id())->get();
            return response()->json(['status' => 'success', 'reminders' => $reminders]);
        });
    }

    public function getContactReservations(Request $request)
    {
        return $this->authenticate($request, function($request) {
            $reservations = Reservation::where('contact_id', $request->contact_id)->get();
            return response()->json(['status' => 'success', 'reservations' => $reservations]);
        });
    }

    public function createReminder(Request $request)
    {
        return $this->authenticate($request, function($request) {
            $validator = Validator::make($request->all(), [
                'title' => 'required|string',
                'description' => 'nullable|string',
                'due_date' => 'required|date',
            ]);

            if ($validator->fails()) {
                return response()->json(['status' => 'error', 'errors' => $validator->errors()], 400);
            }

            $reminder = Remineder::create([
                'user_id' => Auth::id(),
                'title' => $request->title,
                'description' => $request->description,
                'due_date' => $request->due_date,
            ]);

            return response()->json(['status' => 'success', 'reminder' => $reminder], 201);
        });
    }

    public function getReservations(Request $request)
    {
        return $this->authenticate($request, function($request) {
            $reservations = Reservation::where('user_id', Auth::id())->get();
            return response()->json(['status' => 'success', 'reservations' => $reservations]);
        });
    }

    public function createReservation(Request $request)
    {
        return $this->authenticate($request, function($request) {
        
                //Company
                $company=$this->getCompany();

                //Get or create contact
                $contact=$this->getOrMakeContact($request->phone,$company,$request->name);
    
                //Get or create source
                //Find source by name
                $source=Source::where('name',$request->source)->first();
                if(!$source){
                $source=Source::create(['name'=>$request->source,'company_id'=>$company->id]);
                }

                //Create reservation
                $reservation=Reservation::create([
                    'contact_id'=>$contact->id,
                    'company_id'=>$company->id,
                    'source_id'=>$source->id,
                    'start_date'=>$request->start_date,
                    'end_date'=>$request->end_date,
                    'status'=>1,
                    'external_id'=>$request->external_id
                ]);

   

                      

            return response()->json(['status' => 'success', 'reservation' => $reservation], 201);
        },
        [
            'token' => 'required',
            'phone' => 'required',
            'name' => 'required',
            'source' => 'required',
            'start_date' => 'required',
            'end_date' => 'required'
        ]);
    }
}
