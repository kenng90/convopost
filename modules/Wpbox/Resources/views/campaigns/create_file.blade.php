@extends('layouts.app', ['title' => __('Send new campaign')])
@section('head')
@endsection

@section('content')
@include('companies.partials.modals')

<div class="header pb-8 pt-5 pt-md-8">
    <div class="container-fluid">
        <div class="header-body">
            <h1 class="mb-3 mt--3">📁 {{__('File Broadcast Campaign')}}</h1>
        </div>
    </div>
</div>

<form method="POST" action="{{ route('campaigns.store') }}" id="campign" enctype="multipart/form-data">
    @csrf
    <input type="hidden" name="broadcast_type" value="file">

    <div class="container-fluid mt--7">
        <div class="row">

            {{-- ══════════════════════════════════════════════════════
                 COLUMN 1 — Campaign name + template + file upload
            ══════════════════════════════════════════════════════ --}}
            <div class="col-xl-4">

                <div class="card shadow">
                    <div class="card-header bg-white border-0">
                        <h3 class="mb-0">{{__('Campaign')}}</h3>
                    </div>
                    <div class="card-body">

                        {{-- Campaign name --}}
                        <div class="form-group">
                            <label class="form-control-label">{{__('Campaign name')}}</label>
                            <input type="text" name="name" id="name"
                                   class="form-control"
                                   placeholder="{{__('Name for your campaign')}}"
                                   value="{{ $_GET['name'] ?? '' }}">
                        </div>

                        {{-- Template selector --}}
                        <div class="form-group">
                            <label class="form-control-label">
                                {{__('Template')}} <span class="text-danger">*</span>
                            </label>
                            <select name="template_id" id="template_id" class="form-control" required>
                                <option value="">-- {{__('Select template')}} --</option>
                                @foreach($templates as $id => $name)
                                    <option value="{{ $id }}"
                                        {{ (isset($_GET['template_id']) && $_GET['template_id'] == $id) ? 'selected' : '' }}>
                                        {{ $name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        {{-- Schedule --}}
                        <div class="form-group">
                            <label class="form-control-label">{{__('Schedule send time')}}</label>
                            <input class="form-control" type="datetime-local"
                                   id="send_time" name="send_time"
                                   min="{{ \Carbon\Carbon::now()->format('Y-m-d\TH:i') }}"
                                   value="{{ $_GET['send_time'] ?? '' }}">
                            <small class="text-muted">
                                <strong>{{__('Per client, based on the contact timezone')}}</strong>
                            </small>
                        </div>

                        <div class="form-group">
                            <div class="custom-control custom-toggle">
                                <input type="checkbox" name="send_now" id="send_now"
                                       class="custom-control-input"
                                       {{ isset($_GET['send_now']) ? 'checked' : '' }}>
                                <label class="custom-control-label" for="send_now"
                                       data-label-off="{{__('Schedule send')}}"
                                       data-label-on="{{__('Send now')}}">
                                </label>
                            </div>
                        </div>

                    </div>
                </div>

                {{-- File upload card --}}
                <div class="card shadow mt-4">
                    <div class="card-header bg-white border-0">
                        <h3 class="mb-0">📤 {{__('Contact File')}}</h3>
                    </div>
                    <div class="card-body">

                        <div class="form-group">
                            <label class="form-control-label">
                                {{__('Upload CSV or Excel file')}} <span class="text-danger">*</span>
                            </label>
                            <input type="file" name="contact_file" id="contact_file"
                                   accept=".csv,.xlsx,.xls" class="form-control">
                            <small class="text-muted">
                                {{__('First row must be column headers. One column must contain phone numbers.')}}
                            </small>
                        </div>

                        {{-- Parsing spinner --}}
                        <div id="file-parsing" style="display:none;" class="text-center py-2">
                            <div class="spinner-border spinner-border-sm text-primary" role="status"></div>
                            <span class="ml-2 text-muted">{{__('Reading file...')}}</span>
                        </div>

                        {{-- Badges --}}
                        <div id="file-meta" style="display:none;" class="mt-2">
                            <span class="badge badge-success" id="file-row-count"></span>
                            <span class="badge badge-info ml-1" id="file-col-count"></span>
                        </div>

                        {{-- Phone column picker --}}
                        <div id="phone-column-wrapper" style="display:none;" class="mt-3">
                            <div class="form-group mb-0">
                                <label class="form-control-label">
                                    {{__('Phone number column')}} <span class="text-danger">*</span>
                                </label>
                                <select name="phone_column" id="phone_column" class="form-control">
                                    <option value="">-- {{__('Select column')}} --</option>
                                </select>
                            </div>
                        </div>

                    </div>
                </div>

            </div>{{-- /col 1 --}}

            {{-- ══════════════════════════════════════════════════════
                 COLUMN 2 — Variable → file column mapping
            ══════════════════════════════════════════════════════ --}}
            <div class="col-xl-4">
                <div class="card shadow">
                    <div class="card-header bg-white border-0">
                        <h3 class="mb-0">{{__('Variables')}}</h3>
                    </div>
                    <div class="card-body" id="variables-card-body">

                        {{-- State A: nothing selected yet --}}
                        <div id="state-no-template" class="text-center text-muted py-4">
                            <i class="ni ni-notification-70 mb-3" style="font-size:2.5rem; display:block;"></i>
                            <p>{{__('Select a template on the left to begin.')}}</p>
                        </div>

                        {{-- State B: template chosen, waiting for file --}}
                        <div id="state-no-file" style="display:none;" class="text-center text-muted py-4">
                            <i class="ni ni-cloud-upload-96 mb-3" style="font-size:2.5rem; display:block;"></i>
                            <p>{{__('Now upload a contact file on the left to map columns to variables.')}}</p>
                        </div>

                        {{-- State C: both ready — JS injects mapping selects here --}}
                        <div id="variable-mappers-container" style="display:none;"></div>

                        {{-- Media uploads (image/video/document) — always shown when template needs them --}}
                        <div id="media-uploads" style="display:none;"></div>

                    </div>
                </div>
            </div>

            {{-- ══════════════════════════════════════════════════════
                 COLUMN 3 — Preview + Send
            ══════════════════════════════════════════════════════ --}}
            <div class="col-xl-4">
                <div class="card shadow">
                    <div class="card-header bg-white border-0">
                        <h3 class="mb-0">{{__('Preview')}}</h3>
                    </div>
                    <div class="card-body" id="preview-card-body"
                         style="justify-content:flex-end; text-align:right; background:url('{{ asset('default/wpbox/bg.png') }}')">

                        <div id="preview-placeholder" class="text-center text-muted py-4">
                            <p>{{__('Select a template to see a preview.')}}</p>
                        </div>

                        <div id="preview-content" style="display:none;">
                            {{-- JS renders template preview here --}}
                        </div>

                    </div>
                </div>

                <div class="card shadow mt-4">
                    <div class="card-header bg-white border-0">
                        <h3 class="mb-0">{{__('Send campaign')}}</h3>
                    </div>
                    <div class="card-body">
                        <p id="send-summary" class="text-muted small">
                            {{__('Complete the steps above to send.')}}
                        </p>
                        <button type="submit" id="send-btn"
                                class="btn btn-success btn-lg btn-block"
                                style="display:none;">
                            🚀 {{__('Send campaign')}}
                        </button>
                    </div>
                </div>
            </div>

        </div>{{-- /row --}}
    </div>
</form>
@endsection

@php
    // All template data serialised for JS — avoids any PHP logic inside <script>
    $allTemplates = [];
    foreach(\Modules\Wpbox\Models\Template::where('status','APPROVED')->get() as $tpl) {
        $allTemplates[$tpl->id] = [
            'id'         => $tpl->id,
            'name'       => $tpl->name,
            'components' => json_decode($tpl->components, true),
        ];
    }
@endphp

<script>
// ── All template data ────────────────────────────────────────────────────────
var ALL_TEMPLATES = @json($allTemplates);

// ── State ────────────────────────────────────────────────────────────────────
var parsedHeaders  = [];
var parsedRows     = [];
var selectedTplId  = null;
var templateVars   = {};   // { body: [{id,exampleValue},...], header: [...] }
var templateComponents = [];

// ── Helpers ──────────────────────────────────────────────────────────────────
function escapeHtml(s) {
    return String(s)
        .split('&').join('&amp;')
        .split('<').join('&lt;')
        .split('>').join('&gt;')
        .split('"').join('&quot;');
}

function buildColOptions(headers, blankLabel) {
    var html = blankLabel
        ? '<option value="">' + escapeHtml(blankLabel) + '</option>'
        : '';
    for (var i = 0; i < headers.length; i++) {
        html += '<option value="' + escapeHtml(headers[i]) + '">'
              + escapeHtml(headers[i]) + '</option>';
    }
    return html;
}

// ── Extract variables from template components ────────────────────────────────
function extractVars(components) {
    var vars = {};
    if (!components) return vars;
    var paramRe = new RegExp('\\{\\{(\\d+)\\}\\}', 'g');

    components.forEach(function (comp) {

        if (comp.type === 'BODY') {
            var m, matches = [];
            paramRe.lastIndex = 0;
            while ((m = paramRe.exec(comp.text)) !== null) matches.push(m[1]);
            if (matches.length) {
                vars.body = matches.map(function (id, idx) {
                    var ex = '';
                    try { ex = comp.example.body_text[0][idx]; } catch(e) {}
                    return { id: id, exampleValue: ex };
                });
            }
        }

        if (comp.type === 'HEADER' && comp.format === 'TEXT') {
            var m2, matches2 = [];
            paramRe.lastIndex = 0;
            while ((m2 = paramRe.exec(comp.text)) !== null) matches2.push(m2[1]);
            if (matches2.length) {
                vars.header = matches2.map(function (id, idx) {
                    var ex = '';
                    try { ex = comp.example.header_text[idx]; } catch(e) {}
                    return { id: id, exampleValue: ex };
                });
            }
        }

        if (comp.type === 'HEADER') {
            if (comp.format === 'IMAGE')    vars.image    = true;
            if (comp.format === 'VIDEO')    vars.video    = true;
            if (comp.format === 'DOCUMENT') vars.document = true;
        }
    });
    return vars;
}

// ── Render template preview ───────────────────────────────────────────────────
function renderPreview(components) {
    if (!components || !components.length) return;

    var previewPlaceholder = document.getElementById('preview-placeholder');
    var previewContent     = document.getElementById('preview-content');
    if (!previewContent) return;

    var html = '<div class="card" style="min-width:18rem; text-align:left; border-top-left-radius:0;">';

    components.forEach(function (comp) {
        if (comp.type === 'HEADER') {
            if (comp.format === 'IMAGE')    html += '<div class="card-img-top bg-light text-center py-4"><i class="ni ni-image" style="font-size:3rem"></i></div>';
            if (comp.format === 'DOCUMENT') html += '<div class="card-img-top bg-light text-center py-4"><i class="ni ni-single-copy-04" style="font-size:3rem"></i></div>';
            if (comp.format === 'VIDEO')    html += '<div class="card-img-top bg-light text-center py-4"><i class="ni ni-button-play" style="font-size:3rem"></i></div>';
        }
    });

    html += '<div class="card-body">';
    components.forEach(function (comp) {
        if (comp.type === 'HEADER' && comp.format === 'TEXT') {
            html += '<h5 class="card-title mb-2">' + escapeHtml(comp.text) + '</h5>';
        }
        if (comp.type === 'BODY') {
            html += '<pre style="font-family:inherit;white-space:pre-wrap;background:none;border:none;margin:0;">'
                  + escapeHtml(comp.text) + '</pre>';
        }
        if (comp.type === 'FOOTER') {
            html += '<small class="text-muted">' + escapeHtml(comp.text) + '</small>';
        }
    });
    html += '</div></div>';

    components.forEach(function (comp) {
        if (comp.type === 'BUTTONS') {
            comp.buttons.forEach(function (btn) {
                html += '<div class="card mt-1" style="min-width:18rem;text-align:center;">'
                      + '<div class="card-body py-2" style="color:#00a5f4">' + escapeHtml(btn.text) + '</div>'
                      + '</div>';
            });
        }
    });

    previewContent.innerHTML = html;
    previewContent.style.display = '';
    if (previewPlaceholder) previewPlaceholder.style.display = 'none';
}

// ── Build variable mappers once we have both template vars AND file headers ───
function buildVariableMappers() {
    var container  = document.getElementById('variable-mappers-container');
    var noFile     = document.getElementById('state-no-file');
    var noTemplate = document.getElementById('state-no-template');
    var mediaDiv   = document.getElementById('media-uploads');
    var sendBtn    = document.getElementById('send-btn');
    var sendSummary= document.getElementById('send-summary');

    if (!container) return;

    // Need both template and file before we can map
    if (!selectedTplId || !parsedHeaders.length) return;

    var hasTextVars = (templateVars.body && templateVars.body.length)
                   || (templateVars.header && templateVars.header.length);

    var html = '';

    if (!hasTextVars) {
        html = '<div class="alert alert-info">'
             + '{{ __("This template has no text variables. Just upload your file, pick the phone column and send.") }}'
             + '</div>';
    } else {
        var sections = ['body', 'header'];
        sections.forEach(function (section) {
            if (!templateVars[section] || !templateVars[section].length) return;

            var label = section === 'body'
                ? '{{ __("Message Body Variables") }}'
                : '{{ __("Header Variables") }}';

            html += '<h5 class="mb-3 mt-2">' + label + '</h5>';

            templateVars[section].forEach(function (item) {
                html += '<div class="form-group">'
                      +   '<label class="form-control-label">'
                      +     '<span class="badge badge-primary mr-1">{{' + item.id + '}}</span>'
                      +     '{{ __("Variable") }} ' + item.id;

                if (item.exampleValue) {
                    html += ' <small class="text-muted">('
                          + escapeHtml(item.exampleValue) + ')</small>';
                }

                html +=   '</label>'
                      +   '<select name="file_column_map[' + section + '][' + item.id + ']"'
                      +           ' id="map_' + section + '_' + item.id + '"'
                      +           ' class="form-control" required>'
                      +     buildColOptions(parsedHeaders, '-- {{ __("Select column") }} --')
                      +   '</select>'
                      +   '<small class="text-muted">'
                      +     '{{ __("Which column from your file provides this value?") }}'
                      +   '</small>'
                      + '</div>';
            });
        });
    }

    container.innerHTML = html;
    container.style.display = '';
    if (noFile)     noFile.style.display     = 'none';
    if (noTemplate) noTemplate.style.display = 'none';

    // Media uploads
    var mediaHtml = '';
    if (templateVars.image) {
        mediaHtml += '<div class="form-group mt-3">'
                   + '<label class="form-control-label">{{ __("Image") }} <span class="text-danger">*</span></label>'
                   + '<input type="file" name="imageupload" class="form-control" accept=".jpg,.jpeg,.png">'
                   + '</div>';
    }
    if (templateVars.video) {
        mediaHtml += '<div class="form-group mt-3">'
                   + '<label class="form-control-label">{{ __("Video") }} <span class="text-danger">*</span></label>'
                   + '<input type="file" name="imageupload" class="form-control" accept=".mp4">'
                   + '</div>';
    }
    if (templateVars.document) {
        mediaHtml += '<div class="form-group mt-3">'
                   + '<label class="form-control-label">{{ __("PDF Document") }} <span class="text-danger">*</span></label>'
                   + '<input type="file" name="pdf" class="form-control" accept="application/pdf">'
                   + '</div>';
    }
    if (mediaDiv) {
        mediaDiv.innerHTML = mediaHtml;
        mediaDiv.style.display = mediaHtml ? '' : 'none';
    }

    // Send button
    if (sendSummary) {
        sendSummary.textContent = '{{ __("Ready to send to") }} '
                                + parsedRows.length
                                + ' {{ __("contacts from your file.") }}';
    }
    if (sendBtn) sendBtn.style.display = '';
}

// ── CSV parser ────────────────────────────────────────────────────────────────
function parseCSV(text) {
    var newlineRe = new RegExp('\r?\n');
    var lines = text.trim().split(newlineRe);
    if (!lines.length) return { headers: [], rows: [] };
    var headers = splitCSVLine(lines[0]);
    var rows = [];
    for (var i = 1; i < lines.length; i++) {
        if (lines[i].trim()) rows.push(splitCSVLine(lines[i]));
    }
    return { headers: headers, rows: rows };
}

function splitCSVLine(line) {
    var result = [], cur = '', inQ = false;
    for (var i = 0; i < line.length; i++) {
        var c = line[i];
        if (c === '"') { inQ = !inQ; }
        else if (c === ',' && !inQ) { result.push(cur.trim()); cur = ''; }
        else { cur += c; }
    }
    result.push(cur.trim());
    return result;
}

async function parseXlsxFromServer(file) {
    var fd = new FormData();
    fd.append('contact_file', file);
    fd.append('_token', document.querySelector('meta[name="csrf-token"]').getAttribute('content'));
    var resp = await fetch("{{ route('campaigns.parse-file') }}", { method: 'POST', body: fd });
    if (!resp.ok) throw new Error('Server error ' + resp.status);
    return resp.json();
}

async function parseContactFile(file) {
    var ext = file.name.split('.').pop().toLowerCase();
    if (ext === 'csv') return parseCSV(await file.text());
    return parseXlsxFromServer(file);
}

// ── Main init ─────────────────────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', function () {

    var templateSelect  = document.getElementById('template_id');
    var fileInput       = document.getElementById('contact_file');
    var phonePicker     = document.getElementById('phone_column');
    var phoneWrapper    = document.getElementById('phone-column-wrapper');
    var fileMeta        = document.getElementById('file-meta');
    var rowCountEl      = document.getElementById('file-row-count');
    var colCountEl      = document.getElementById('file-col-count');
    var fileParsing     = document.getElementById('file-parsing');
    var stateNoTemplate = document.getElementById('state-no-template');
    var stateNoFile     = document.getElementById('state-no-file');

    // ── Template change ───────────────────────────────────────────────────────
    templateSelect.addEventListener('change', function () {
        var tplId = this.value;
        if (!tplId) {
            selectedTplId = null;
            templateVars = {};
            templateComponents = [];
            stateNoTemplate.style.display = '';
            stateNoFile.style.display = 'none';
            document.getElementById('variable-mappers-container').style.display = 'none';
            document.getElementById('variable-mappers-container').innerHTML = '';
            document.getElementById('preview-placeholder').style.display = '';
            document.getElementById('preview-content').style.display = 'none';
            document.getElementById('preview-content').innerHTML = '';
            document.getElementById('send-btn').style.display = 'none';
            return;
        }

        var tpl = ALL_TEMPLATES[tplId];
        if (!tpl) return;

        selectedTplId      = tplId;
        templateComponents = tpl.components || [];
        templateVars       = extractVars(templateComponents);

        // Show preview immediately
        renderPreview(templateComponents);

        // Show correct state in col 2
        stateNoTemplate.style.display = 'none';
        if (parsedHeaders.length) {
            // File already uploaded — build mappers right away
            buildVariableMappers();
        } else {
            stateNoFile.style.display = '';
            document.getElementById('variable-mappers-container').style.display = 'none';
        }
    });

    // ── File change ───────────────────────────────────────────────────────────
    fileInput.addEventListener('change', async function () {
        var file = this.files[0];
        if (!file) return;

        // Show spinner
        if (fileParsing) fileParsing.style.display = '';
        if (fileMeta)    fileMeta.style.display    = 'none';
        phoneWrapper.style.display = 'none';

        var parsed;
        try {
            parsed = await parseContactFile(file);
        } catch (e) {
            if (fileParsing) fileParsing.style.display = 'none';
            alert('{{ __("Could not parse file:") }} ' + e.message);
            return;
        }

        parsedHeaders = parsed.headers || [];
        parsedRows    = parsed.rows    || [];

        // Hide spinner, show badges
        if (fileParsing) fileParsing.style.display = 'none';
        if (rowCountEl)  rowCountEl.textContent    = parsedRows.length + ' {{ __("rows") }}';
        if (colCountEl)  colCountEl.textContent    = parsedHeaders.length + ' {{ __("columns") }}';
        if (fileMeta)    fileMeta.style.display    = '';

        // Phone column picker
        phonePicker.innerHTML = '<option value="">-- {{ __("Select column") }} --</option>'
                              + buildColOptions(parsedHeaders, null);
        phoneWrapper.style.display = '';

        // If template already selected, build mappers now
        if (selectedTplId) {
            buildVariableMappers();
        } else {
            stateNoTemplate.style.display = '';
        }
    });

    // ── Form submit validation ────────────────────────────────────────────────
    document.getElementById('campign').addEventListener('submit', function (e) {
        if (!fileInput.files || !fileInput.files.length) {
            e.preventDefault();
            alert('{{ __("Please upload a contact file.") }}');
            return false;
        }
        if (!templateSelect.value) {
            e.preventDefault();
            alert('{{ __("Please select a template.") }}');
            return false;
        }
        var phoneCol = document.getElementById('phone_column');
        if (!phoneCol || !phoneCol.value) {
            e.preventDefault();
            alert('{{ __("Please select the phone number column.") }}');
            return false;
        }
    });

});
</script>