@include('partials.input',['name'=>'Name','id'=>"name",'placeholder'=>"Plan name",'required'=>true,'value'=>(isset($plan)?$plan->name:null)])
<div class="row">
    <div class="col-md-12">
        @include('partials.input',['name'=>'Plan description','id'=>"description",'placeholder'=>"Plan description...",'required'=>false,'value'=>(isset($plan)?$plan->description:null)])
    </div>
    <div class="col-md-12">
        @include('partials.input',['name'=>'Features list (separate features with comma)','id'=>"features",'placeholder'=>"Plan Features comma separated...",'required'=>false,'value'=>(isset($plan)?$plan->features:null)])
    </div>
</div>

@include('partials.input',['type'=>'number','name'=>'Price','id'=>"price",'placeholder'=>"Plan prce",'required'=>true,'value'=>(isset($plan)?$plan->price:null)])

@if (config('settings.enable_credits'))
    <div style="width: 50%;">
        @include('partials.input',['class'=>'','additionalInfo'=>'Number of credits that will be added to the user\'s account when they subscribe to this plan, on the interval selected below','type'=>'number','name'=>'Credit amount','id'=>"credit_amount",'placeholder'=>"Plan credit amount",'required'=>true,'value'=>(isset($plan)?$plan->credit_amount:null)])
    </div>
@endif

<div class="row">
    <!-- THIS IS SPECIAL -->
    <div class="col-md-6">
        <label class="form-control-label">{{ __("Plan period") }}</label>
        <div class="custom-control custom-radio mb-3">
            <input name="period" class="custom-control-input" id="monthly"  @if (isset($plan))  @if ($plan->period == 1) checked @endif @else checked @endif  value="monthly" type="radio">
            <label class="custom-control-label" for="monthly">{{ __('Monthly') }}</label>
        </div>
        <div class="custom-control custom-radio mb-3">
            <input name="period" class="custom-control-input" id="anually" value="anually" @if (isset($plan) && $plan->period == 2) checked @endif type="radio">
            <label class="custom-control-label" for="anually">{{ __('Anually') }}</label>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-12 mt-4"><h6 class="heading text-muted mb-4">{{ __('Payment processor') }}</h6></div>
    @if(config('settings.subscription_processor',"Stripe")=='Stripe')
    <div class="col-md-6">
        @include('partials.input',['name'=>'Stripe Pricing Plan ID','id'=>"stripe_id",'placeholder'=>"Product price plan id from Stripe starting with price_xxxxxx",'required'=>false,'value'=>(isset($plan)?$plan->stripe_id:null)])
    </div>
@else
    @if(strtolower(config('settings.subscription_processor'))!='local')
        @include($theSelectedProcessor."-subscribe::planid")
    @endif
@endif
</div>

<div class="row">
    
    <div class="col-12 mt-4"><h6 class="heading text-muted mb-4">{{ __('Plan limits') }}</h6></div>
    @if (config('settings.limit_items_show',true))
        <div class="col-md-6">
            @include('partials.input',['type'=>"number", 'name'=>config('settings.limit_items_name',"Limit items"),'id'=>"limit_items",'placeholder'=>"Number of allowed usage",'required'=>false,'additionalInfo'=>"0 is unlimited numbers of usage per plan period",'value'=>(isset($plan)?$plan->limit_items:null)])
        </div>
    @endif

    @if (config('settings.limit_views_show',false))
        <div class="col-md-6">
            @include('partials.input',['type'=>"number", 'name'=>config('settings.limit_views_name',"Limit views"),'id'=>"limit_views",'placeholder'=>"Number of allowed usage",'required'=>false,'additionalInfo'=>"0 is unlimited numbers of usage per plan period",'value'=>(isset($plan)?$plan->limit_views:null)])
        </div>
   @endif

   @if (config('settings.limit_orders_show',false))
        <div class="col-md-6">
            @include('partials.input',['type'=>"number", 'name'=>config('settings.limit_orders_name',"Limit orders"),'id'=>"limit_orders",'placeholder'=>"Number of allowed usage",'required'=>false,'additionalInfo'=>"0 is unlimited numbers of usage per plan period",'value'=>(isset($plan)?$plan->limit_orders:null)])
        </div>
   @endif

    <div class="col-md-6">
        @include('partials.input',['type'=>"number", 'name'=>__('Catalog item limit'),'id'=>"limit_catalog_items",'placeholder'=>"Number of catalog items allowed",'required'=>false,'additionalInfo'=>"0 is unlimited numbers of items per plan period",'value'=>(isset($plan)?($plan->limit_catalog_items ?? 0):null)])
    </div>

    <div class="col-md-6">
        @include('partials.input',['type'=>"number", 'name'=>__('Agent seat limit'),'id'=>"limit_agents",'placeholder'=>"Number of staff agents allowed",'required'=>false,'additionalInfo'=>"0 is unlimited agents per organization",'value'=>(isset($plan)?($plan->limit_agents ?? 0):null)])
    </div>

    <div class="col-md-6">
        @include('partials.input',['type'=>"number", 'name'=>__('Organization limit'),'id'=>"limit_companies",'placeholder'=>"Number of organizations per owner",'required'=>false,'additionalInfo'=>"0 is unlimited WhatsApp numbers / organizations",'value'=>(isset($plan)?($plan->limit_companies ?? 0):null)])
    </div>

    <div class="col-md-6">
        @include('partials.input',['type'=>"number", 'name'=>__('Store integration limit'),'id'=>"limit_integrations",'placeholder'=>"Number of connected stores per organization",'required'=>false,'additionalInfo'=>"0 is unlimited Shopify/WooCommerce connections",'value'=>(isset($plan)?($plan->limit_integrations ?? 0):null)])
    </div>

    <div class="col-12 mt-2"><h6 class="heading text-muted mb-2">{{ __('Per-seat billing (Stripe add-ons)') }}</h6></div>

    <div class="col-md-6">
        @include('partials.input',['type'=>"number", 'name'=>__('Included agent seats'),'id'=>"included_agent_seats",'placeholder'=>"Seats included in base price",'required'=>false,'additionalInfo'=>"Extra seats bill via stripe_agent_seat_price_id",'value'=>(isset($plan)?($plan->included_agent_seats ?? 0):null)])
    </div>

    <div class="col-md-6">
        @include('partials.input',['type'=>"number", 'name'=>__('Agent seat add-on price'),'id'=>"agent_seat_price",'placeholder'=>"Display price per extra agent",'required'=>false,'additionalInfo'=>"For UI only; Stripe price ID controls billing",'value'=>(isset($plan)?($plan->agent_seat_price ?? 0):null)])
    </div>

    @if (config('settings.subscription_processor',"Stripe")=='Stripe' || strtolower(config('settings.subscription_processor'))=='stripeh')
    <div class="col-md-6">
        @include('partials.input',['name'=>__('Stripe agent seat price ID'),'id'=>"stripe_agent_seat_price_id",'placeholder'=>"price_xxxx per extra agent seat",'required'=>false,'value'=>(isset($plan)?($plan->stripe_agent_seat_price_id ?? null):null)])
    </div>
    @endif

    <div class="col-md-6">
        @include('partials.input',['type'=>"number", 'name'=>__('Included organizations'),'id'=>"included_companies",'placeholder'=>"Organizations included in base price",'required'=>false,'additionalInfo'=>"Extra orgs bill via stripe_company_seat_price_id",'value'=>(isset($plan)?($plan->included_companies ?? 0):null)])
    </div>

    <div class="col-md-6">
        @include('partials.input',['type'=>"number", 'name'=>__('Organization add-on price'),'id'=>"company_seat_price",'placeholder'=>"Display price per extra organization",'required'=>false,'value'=>(isset($plan)?($plan->company_seat_price ?? 0):null)])
    </div>

    @if (config('settings.subscription_processor',"Stripe")=='Stripe' || strtolower(config('settings.subscription_processor'))=='stripeh')
    <div class="col-md-6">
        @include('partials.input',['name'=>__('Stripe organization seat price ID'),'id'=>"stripe_company_seat_price_id",'placeholder'=>"price_xxxx per extra organization",'required'=>false,'value'=>(isset($plan)?($plan->stripe_company_seat_price_id ?? null):null)])
    </div>
    @endif

</div>
   
<input name="ordering" value="enabled" type="hidden" /> 

@include('plans.plugins')

<div class="text-center">
    <button type="submit" class="btn btn-success mt-4">{{ isset($plan)?__('Update plan'):__('SAVE') }}</button>
</div>
