<?php

namespace Modules\PaystackSubscribe\Http\Controllers;

use App\Models\Plans;
use App\Models\User;
use App\Services\DefaultPlanService;
use App\Services\Security\WebhookSignature;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class Main extends Controller
{
    //Manage
    public function updateCancelSubscription()
    {
        $client = new \GuzzleHttp\Client();

        $payload = [
            'headers' => [
                'Authorization' => 'Bearer '.config('paystack-subscribe.secret'),
                'Accept' => 'application/json',
            ],
        ];
        $response = $client->request('GET', 'https://api.paystack.co/subscription/'.Auth::user()->paystack_subscribtion_id.'/manage/link', $payload);
        $responseDecoded = json_decode($response->getBody());

        return redirect($responseDecoded->data->link);
    }

    //Subscribe
    public function subscribe(Request $request)
    {
        //Assign user to plan
        //auth()->user()->plan_id = $request->planID;
        // auth()->user()->paystack_subscribtion_id = $request->subscriptionID;
        //auth()->user()->update();

        return response()->json(
            [
                'status' => true,
                'success_url' => redirect()->intended('/plan')->getTargetUrl(),
            ]
        );
    }

    //Webhook called when there is an event
    public function webhook(Request $request)
    {
        $secret = (string) config('paystack-subscribe.secret');
        $signature = $request->header('x-paystack-signature');

        if (! app(WebhookSignature::class)->paystackIsValid($request->getContent(), $signature, $secret)) {
            Log::warning('Paystack subscription webhook rejected: invalid signature');

            return response()->json(['status' => false], 401);
        }

        $event = $request->event;
        $customerEmail = data_get($request->all(), 'data.customer.email');
        $user = is_string($customerEmail) ? User::where('email', $customerEmail)->first() : null;

        Log::info('Paystack subscription webhook received', [
            'event' => $event,
            'user_id' => $user?->id,
        ]);

        if ($user) {
            if ($event == 'subscription.create' || $event == 'charge.success') {
                $subscription_plan_id = $request->data['plan']['plan_code'] ?? null;
                $plan = $subscription_plan_id
                    ? Plans::where('paystack_id', $subscription_plan_id)->first()
                    : null;

                if ($plan) {
                    $user->plan_id = $plan->id;
                    $user->paystack_subscribtion_id = $request->data['subscription_code'] ?? $user->paystack_subscribtion_id;
                    $user->update();
                }
            }
            if ($event == 'subscription.disable' || $event == 'subscription.not_renew') {
                $user->paystack_subscribtion_id = null;
                app(DefaultPlanService::class)->assignToUser($user);
            }
        }

        return response()->json(['status' => true]);
    }

    /**
     * Display a listing of the resource.
     *
     * @return Response
     */
    public function index()
    {
        return view('paystack-subscribe::index');
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return Response
     */
    public function create()
    {
        return view('paystack-subscribe::create');
    }

    /**
     * Store a newly created resource in storage.
     *
     * @return Response
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Show the specified resource.
     *
     * @param  int  $id
     * @return Response
     */
    public function show($id)
    {
        return view('paystack-subscribe::show');
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return Response
     */
    public function edit($id)
    {
        return view('paystack-subscribe::edit');
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  int  $id
     * @return Response
     */
    public function update(Request $request, $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return Response
     */
    public function destroy($id)
    {
        //
    }
}
