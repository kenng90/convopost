<div class="row">
    <div class="col-md-6 mt-3">
        <h6 class="heading-small text-muted mb-4">{{ __('Plugins') }}</h6>
        <label for="pluginsSelector">{{ __('Select available plugins or leave empty for all') }}</label><br />
        <select style="height: 200px" name="pluginsSelector[]" multiple="multiple" class="form-control noselecttwo" id="pluginsSelector">
            @php
                $previousPlugins = isset($plan) ? json_decode($plan->getConfig('plugins', '[]'), false) : [];
            @endphp
            @foreach ($allplugins as $plugin)
                <option @if (is_array($previousPlugins) && in_array($plugin->alias, $previousPlugins, true)) selected @endif id="plugin{{ $plugin->alias }}" value="{{ $plugin->alias }}">{{ strlen($plugin->name) > 0 ? $plugin->name : ucfirst($plugin->alias) }}</option>
            @endforeach
        </select>
    </div>

    <div class="col-md-6 mt-3">
        <h6 class="heading-small text-muted mb-4">{{ __('Capabilities') }}</h6>
        <label for="capabilitiesSelector">{{ __('Feature flags for this plan (leave empty for all)') }}</label><br />
        <select style="height: 200px" name="capabilitiesSelector[]" multiple="multiple" class="form-control noselecttwo" id="capabilitiesSelector">
            @php
                $previousCapabilities = isset($plan) ? json_decode($plan->getConfig('capabilities', '[]'), false) : [];
            @endphp
            @foreach (config('plan-entitlements.capability_labels', []) as $key => $label)
                <option @if (is_array($previousCapabilities) && in_array($key, $previousCapabilities, true)) selected @endif value="{{ $key }}">{{ __($label) }}</option>
            @endforeach
        </select>
    </div>
</div>
