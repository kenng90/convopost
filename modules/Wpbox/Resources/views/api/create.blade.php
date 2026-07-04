@extends('layouts.app', ['title' => $campaign ? __('Edit API campaign') : __('Create API campaign')])

@section('content')
@include('companies.partials.modals')
<div class="header pb-8 pt-5 pt-md-8">
    <div class="container-fluid">
        <div class="header-body">
            <h1 class="mb-3 mt--3">🔌 {{ $campaign ? __('Edit API campaign') : __('Create new API campaign') }}</h1>
            <p class="text-muted mb-0">
                {{ __('Define a WhatsApp template that can be triggered later by campaign ID via the API, integrations, or journeys.') }}
            </p>
        </div>
    </div>
</div>

<form method="POST" action="{{ $formAction }}" id="campign" enctype="multipart/form-data">
    @csrf
    @if (($formMethod ?? 'POST') === 'PUT')
        @method('PUT')
    @endif
    <input type="hidden" name="type" value="api">

    <div class="container-fluid mt--7" id="campign_managment">
        <div class="row">
            <div class="col-xl-4">
                <div class="card shadow">
                    <div class="card-header bg-white border-0">
                        <h3 class="mb-0">{{ __('API campaign') }}</h3>
                    </div>
                    <div class="card-body">
                        @include('partials.input', [
                            'id' => 'name',
                            'name' => 'Campaign name',
                            'placeholder' => 'Name for your API campaign',
                            'required' => true,
                            'value' => old('name', $campaign->name ?? request('name')),
                        ])

                        @include('partials.select', [
                            'id' => 'template_id',
                            'name' => 'Template',
                            'data' => $templates,
                            'required' => true,
                            'value' => old('template_id', $selectedTemplate->id ?? $campaign->template_id ?? ''),
                        ])

                        <button onclick="submitJustCampign()" class="btn btn-success mt-4" type="button">
                            {{ __('Apply template') }}
                        </button>
                    </div>
                </div>

                <div class="card shadow mt-4">
                    <div class="card-body">
                        <p class="mb-2 text-muted small">
                            {{ __('No audience or schedule is selected here. Recipients are supplied when the campaign is triggered.') }}
                        </p>
                        <a href="{{ route('wpbox.api.index') }}" class="btn btn-outline-secondary btn-sm">{{ __('Back to API campaigns') }}</a>
                    </div>
                </div>
            </div>

            @if ($selectedTemplate)
                <div class="col-xl-4">
                    <div class="card shadow">
                        <div class="card-header bg-white border-0">
                            <h3 class="mb-0">{{ __('Variables') }}</h3>
                        </div>
                        <div class="card-body">
                            <p class="text-muted small">
                                {{ __('For API-defined values, enter the payload path (e.g. order.id) in the variable field and choose “Use API defined value”.') }}
                            </p>
                            @include('wpbox::api.variables')
                        </div>
                    </div>
                </div>

                <div class="col-xl-4">
                    <div class="card shadow">
                        <div class="card-header bg-white border-0">
                            <h3 class="mb-0">{{ __('Preview') }}</h3>
                        </div>
                        @include('wpbox::campaigns.new.preview')
                    </div>

                    <div class="card shadow mt-4">
                        <div class="card-header bg-white border-0">
                            <h3 class="mb-0">{{ __('Save API campaign') }}</h3>
                        </div>
                        <div class="card-body">
                            <p>{{ __('This message will be sent when the API is called with this campaign ID, or when an integration/journey triggers it.') }}</p>
                            <button class="btn btn-success mt-2" type="submit">
                                {{ $campaign ? __('Update API campaign') : __('Save API campaign') }}
                            </button>
                        </div>
                    </div>
                </div>
            @endif
        </div>
    </div>
</form>
@endsection

@section('js')
<script>
    function submitJustCampign() {
        event.preventDefault();
        const formData = new FormData(document.getElementById('campign'));
        const params = new URLSearchParams();
        for (const [key, value] of formData.entries()) {
            if (key === '_token' || key === '_method' || key === 'type') {
                continue;
            }
            if (value instanceof File) {
                continue;
            }
            params.append(key, value);
        }
        window.location.href = window.location.pathname + '?' + params.toString();
    }

    window.onload = function () {
        if (typeof Vue === 'undefined') {
            return;
        }

        window.vuec = new Vue({
            el: '#campign_managment',
            data: {
                body_1: '', body_2: '', body_3: '', body_4: '', body_5: '',
                body_6: '', body_7: '', body_8: '', body_9: '',
                header_1: '',
                imagePreview: null,
                videoPreview: null,
            },
            methods: {
                setPreviewValue: function () {
                    this.body_1 = this.$refs['paramvalues[body][1]'] ? this.$refs['paramvalues[body][1]'].value : '';
                    this.body_2 = this.$refs['paramvalues[body][2]'] ? this.$refs['paramvalues[body][2]'].value : '';
                    this.body_3 = this.$refs['paramvalues[body][3]'] ? this.$refs['paramvalues[body][3]'].value : '';
                    this.body_4 = this.$refs['paramvalues[body][4]'] ? this.$refs['paramvalues[body][4]'].value : '';
                    this.body_5 = this.$refs['paramvalues[body][5]'] ? this.$refs['paramvalues[body][5]'].value : '';
                    this.body_6 = this.$refs['paramvalues[body][6]'] ? this.$refs['paramvalues[body][6]'].value : '';
                    this.body_7 = this.$refs['paramvalues[body][7]'] ? this.$refs['paramvalues[body][7]'].value : '';
                    this.body_8 = this.$refs['paramvalues[body][8]'] ? this.$refs['paramvalues[body][8]'].value : '';
                    this.body_9 = this.$refs['paramvalues[body][9]'] ? this.$refs['paramvalues[body][9]'].value : '';
                    this.header_1 = this.$refs['paramvalues[header][1]'] ? this.$refs['paramvalues[header][1]'].value : '';
                },
                handleImageUpload(event) {
                    const selectedFile = event.target.files[0];
                    if (!selectedFile) {
                        this.imagePreview = null;
                        return;
                    }
                    const reader = new FileReader();
                    reader.onload = () => { this.imagePreview = reader.result; };
                    reader.readAsDataURL(selectedFile);
                },
                handleVideoUpload(event) {
                    const selectedFile = event.target.files[0];
                    if (!selectedFile) {
                        this.videoPreview = null;
                        return;
                    }
                    const reader = new FileReader();
                    reader.onload = () => { this.videoPreview = reader.result; };
                    reader.readAsDataURL(selectedFile);
                },
            }
        });
        window.vuec.setPreviewValue();
    };
</script>
@endsection
