<div class="card card-profile bg-secondary shadow">
    <div class="card-header">
        <h5 class="h3 mb-0">{{ __("Subscription plan")}}</h5>
    </div>
    <div class="card-body">
        <form method="POST" action="{{ route('update.plan')}}" >
            @csrf
            <input type="hidden" name="user_id" value="{{ $company->user->id }}">
            <input type="hidden" name="company_id" value="{{ $company->id }}">
            @include('partials.fields',['fields'=>[
                ['class'=>'col-12', 'ftype'=>'select','name'=>"Current plan",'id'=>"plan_id",'data'=>$plans,'required'=>true,'value'=>$company->user->mplanid()],
            ]])
            <div class="text-center">
                <button type="submit" class="btn btn-success mt-4">{{ __('Save') }}</button>
            </div>
        </form>
    </div>
</div>

@if (config('settings.enable_credits') || config('managed-ai.enabled', true))
    <div class="card card-profile bg-secondary shadow mt-4">
        <div class="card-header">
            <h5 class="h3 mb-0">{{ __('Grant credits') }}</h5>
        </div>
        <div class="card-body">
            <p class="text-muted mb-3">
                {{ __('Add messaging or AI credits for this organization’s owner without waiting for plan renewal.') }}
            </p>
            <a href="{{ route('credits.create', ['company_id' => $company->id]) }}" class="btn btn-primary">
                {{ __('Open credit grant') }}
            </a>
        </div>
    </div>
@endif
