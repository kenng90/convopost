@extends('layouts.app', ['title' => __('Send new campaign')])
@section('head')
@endsection

@section('content')
@include('companies.partials.modals')

<div class="header pb-8 pt-5 pt-md-8">
    <div class="container-fluid">
        <div class="header-body">
            <h1 class="mb-3 mt--3">⚡ {{__('Quick Broadcast')}}</h1>
            <div class="row align-items-center pt-2"></div>
        </div>
    </div>
</div>

<form method="POST" action="{{ route('campaigns.store') }}" id="campign" enctype="multipart/form-data">
    @csrf
    <input type="hidden" name="broadcast_type" value="quick">

<div class="container-fluid mt--7" id="campign_managment">
    <div class="row">

        {{-- ══════════════════════════════════════════════════════════════
             COLUMN 1 — Campaign settings + phone number input
        ══════════════════════════════════════════════════════════════ --}}
        <div class="col-xl-4">
       
        {{-- Phone numbers card --}}
            <div class="card shadow mt-0">
                <div class="card-header bg-white border-0">
                    <div class="row align-items-center">
                        <div class="col-8">
                            <h3 class="mb-0">📱 {{__('Phone Numbers')}}</h3>
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    <div class="form-group mb-1">
                        <label class="form-control-label">
                            {{__('Enter phone numbers')}} <span class="text-danger">*</span>
                        </label>
                        <textarea name="quick_phones"
                                  id="quick_phones"
                                  class="form-control"
                                  rows="8"
                                  placeholder="{{__('One number per line, or comma-separated.\nInclude country code, e.g:\n254712345678\n254723456789')}}">{{ old('quick_phones', request()->query('quick_phones', '')) }}</textarea>
                        <small class="text-muted">
                            {{__('Include country code. One per line or comma-separated.')}}
                        </small>
                    </div>

                    {{-- Live count badge --}}
                    <div class="mt-2">
                        <span class="badge badge-success" id="phone-count" style="display:none;"></span>
                    </div>
                </div>
            </div>
            {{-- Campaign card — same partial as group --}}
            <div class="card shadow">
                <div class="card-header bg-white border-0">
                    <div class="row align-items-center">
                        <div class="col-8">
                            <h3 class="mb-0">{{__('Campaign')}}</h3>
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    @include('wpbox::campaigns.new.campaign_quick')
                </div>
            </div>

           

        </div>{{-- /col-xl-4 --}}

        @if (isset($_GET['template_id']))

        {{-- ══════════════════════════════════════════════════════════════
             COLUMN 2 — Variables (identical to create_group)
        ══════════════════════════════════════════════════════════════ --}}
        <div class="col-xl-4">
        
            <div class="card shadow">
                <div class="card-header bg-white border-0">
                    <div class="row align-items-center">
                        <div class="col-8">
                            <h3 class="mb-0">{{__('Variables')}}</h3>
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    @include('wpbox::campaigns.new.variables')
                </div>
            </div>
        </div>

        {{-- ══════════════════════════════════════════════════════════════
             COLUMN 3 — Preview + Send (identical to create_group)
        ══════════════════════════════════════════════════════════════ --}}
        <div class="col-xl-4">
            <div class="card shadow">
                <div class="card-header bg-white border-0">
                    <div class="row align-items-center">
                        <div class="col-8">
                            <h3 class="mb-0">{{__('Preview')}}</h3>
                        </div>
                    </div>
                </div>
                @include('wpbox::campaigns.new.preview')
            </div>

            <div class="card shadow mt-4">
                <div class="card-header bg-white border-0">
                    <div class="row align-items-center">
                        <div class="col-8">
                            <h3 class="mb-0">{{__('Send campaign')}}</h3>
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    <p id="send-summary" class="text-muted">
                        {{__('Enter phone numbers on the left to see how many contacts will be messaged.')}}
                    </p>
                    <button type="submit"
                            id="send-btn"
                            class="btn btn-success mt-4"
                            style="display:none;">
                        🚀 {{__('Send campaign')}}
                    </button>
                </div>
            </div>
        </div>

        @endif {{-- template_id --}}

    </div>
</div>
</form>
@endsection

<script>
var vuec = null;
var component = @json($selectedTemplateComponents);

// ── Apply: reload page with form state in query string (same as create_group) ─
function submitJustCampign(e) {
    if (e) {
        e.preventDefault();
    }

    var form = document.getElementById('campign');
    if (!form) {
        return;
    }

    var phonesEl = document.getElementById('quick_phones');
    if (phonesEl && phonesEl.value.trim()) {
        sessionStorage.setItem('quick_broadcast_phones', phonesEl.value);
    }

    var formData = new FormData(form);
    var params = new URLSearchParams();

    formData.forEach(function (value, key) {
        if (value instanceof File && value.size === 0) {
            return;
        }
        params.append(key, value);
    });

    window.location.href = window.location.pathname + '?' + params.toString();
}

// ── Parse phone numbers from textarea ────────────────────────────────────────
function parsePhones(raw) {
    // Split on newlines or commas — use RegExp constructor to avoid Blade issues
    var splitRe = new RegExp('[\\n,]+');
    return raw.split(splitRe)
        .map(function (p) { return p.trim().replace(/\s/g, ''); })
        .filter(function (p) { return p.length >= 7; });
}

// ── Live phone count ──────────────────────────────────────────────────────────
function updatePhoneCount() {
    var raw     = document.getElementById('quick_phones').value;
    var phones  = parsePhones(raw);
    var badge   = document.getElementById('phone-count');
    var summary = document.getElementById('send-summary');
    var btn     = document.getElementById('send-btn');

    if (!badge) return;

    if (phones.length > 0) {
        badge.textContent  = phones.length + ' {{ __("number(s) entered") }}';
        badge.style.display = '';
        if (summary) summary.textContent = '{{ __("Ready to send to") }} ' + phones.length + ' {{ __("contacts.") }}';
        if (btn) btn.style.display = '';
    } else {
        badge.style.display = 'none';
        if (summary) summary.textContent = '{{ __("Enter phone numbers on the left to see how many contacts will be messaged.") }}';
        if (btn) btn.style.display = 'none';
    }
}

document.addEventListener('DOMContentLoaded', function () {

    // Live count as user types
    var phonesTextarea = document.getElementById('quick_phones');
    if (phonesTextarea) {
        if (!phonesTextarea.value.trim()) {
            var storedPhones = sessionStorage.getItem('quick_broadcast_phones');
            if (storedPhones) {
                phonesTextarea.value = storedPhones;
            }
        }

        phonesTextarea.addEventListener('input', updatePhoneCount);
        updatePhoneCount();
    }

    // Form submit validation
    document.getElementById('campign').addEventListener('submit', function (e) {
        var raw = document.getElementById('quick_phones').value;
        var phones = parsePhones(raw);
        if (!phones.length) {
            e.preventDefault();
            alert('{{ __("Please enter at least one phone number.") }}');
            return false;
        }

        sessionStorage.removeItem('quick_broadcast_phones');
        if (!document.getElementById('template_id') ||
            !document.getElementById('template_id').value) {
            e.preventDefault();
            alert('{{ __("Please select a template.") }}');
            return false;
        }
    });

    // ── Vue (identical to create_group) ──────────────────────────────────────
    vuec = new Vue({
        el: '#campign_managment',
        data: {
            body_1:'', body_2:'', body_3:'', body_4:'', body_5:'',
            body_6:'', body_7:'', body_8:'', body_9:'',
            header_1:'',
            imagePreview: null,
            videoPreview: null,
        },
        methods: {
            setPreviewValue: function () {
                this.body_1   = this.$refs['paramvalues[body][1]']   ? this.$refs['paramvalues[body][1]'].value   : '';
                this.body_2   = this.$refs['paramvalues[body][2]']   ? this.$refs['paramvalues[body][2]'].value   : '';
                this.body_3   = this.$refs['paramvalues[body][3]']   ? this.$refs['paramvalues[body][3]'].value   : '';
                this.body_4   = this.$refs['paramvalues[body][4]']   ? this.$refs['paramvalues[body][4]'].value   : '';
                this.body_5   = this.$refs['paramvalues[body][5]']   ? this.$refs['paramvalues[body][5]'].value   : '';
                this.body_6   = this.$refs['paramvalues[body][6]']   ? this.$refs['paramvalues[body][6]'].value   : '';
                this.body_7   = this.$refs['paramvalues[body][7]']   ? this.$refs['paramvalues[body][7]'].value   : '';
                this.body_8   = this.$refs['paramvalues[body][8]']   ? this.$refs['paramvalues[body][8]'].value   : '';
                this.body_9   = this.$refs['paramvalues[body][9]']   ? this.$refs['paramvalues[body][9]'].value   : '';
                this.header_1 = this.$refs['paramvalues[header][1]'] ? this.$refs['paramvalues[header][1]'].value : '';
            },
            handleImageUpload: function (event) {
                var self = this, f = event.target.files[0];
                if (f) {
                    var reader = new FileReader();
                    reader.onload = function () { self.imagePreview = reader.result; };
                    reader.readAsDataURL(f);
                } else { this.imagePreview = 'default-image.jpg'; }
            },
            handleVideoUpload: function (event) {
                var self = this, f = event.target.files[0];
                if (f) {
                    var reader = new FileReader();
                    reader.onload = function () { self.videoPreview = reader.result; };
                    reader.readAsDataURL(f);
                } else { this.videoPreview = 'default-image.jpg'; }
            },
        },
    });
    vuec.setPreviewValue();
});
</script>
