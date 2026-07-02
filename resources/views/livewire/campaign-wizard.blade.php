<div>
    <div class="mb-4">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
            <h3 class="mb-0">{{ __('Campaign wizard') }}</h3>
            <span class="badge badge-primary">{{ __('Step') }} {{ $step }} / {{ $totalSteps }}</span>
        </div>
        <div class="progress mt-3" style="height: 6px;">
            <div class="progress-bar" role="progressbar" style="width: {{ ($step / $totalSteps) * 100 }}%"></div>
        </div>
    </div>

    <div class="row">
        <div class="{{ $step === 3 ? 'col-xl-8' : 'col-12' }}">
            @if ($step === 1)
                @include('livewire.campaign-wizard.step-setup')
            @elseif ($step === 2)
                @include('livewire.campaign-wizard.step-audience')
            @elseif ($step === 3)
                @include('livewire.campaign-wizard.step-content')
            @elseif ($step === 4)
                @include('livewire.campaign-wizard.step-schedule')
            @elseif ($step === 5)
                @include('livewire.campaign-wizard.step-review')
            @endif
        </div>

        @if ($step === 3)
            <div class="col-xl-4">
                @include('livewire.campaign-wizard.preview')
            </div>
        @endif
    </div>

    <div class="d-flex justify-content-between mt-4">
        <div>
            @if ($step > 1)
                <button type="button" class="btn btn-secondary" wire:click="previousStep">{{ __('Back') }}</button>
            @endif
        </div>
        <div class="d-flex" style="gap: 0.5rem;">
            <button type="button" class="btn btn-outline-primary" wire:click="saveDraft">{{ __('Save draft') }}</button>
            @if ($step < $totalSteps)
                <button type="button" class="btn btn-primary" wire:click="nextStep">{{ __('Next') }}</button>
            @else
                <button type="button" class="btn btn-success" wire:loading.attr="disabled" wire:click="launchCampaign">{{ __('Launch campaign') }}</button>
            @endif
        </div>
    </div>
</div>
