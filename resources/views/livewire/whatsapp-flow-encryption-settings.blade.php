<div>
    {{-- Notification toast --}}
    <div
        x-data="{ show: false, message: '', type: 'success' }"
        x-on:showNotification.window="show = true; message = $event.detail[0].message; type = $event.detail[0].type; setTimeout(() => show = false, 5000)"
        x-show="show"
        x-transition
        class="fixed top-4 right-4 z-50 px-5 py-3 rounded shadow-lg text-white text-sm"
        :class="type === 'success' ? 'bg-green-600' : 'bg-red-600'"
        style="display: none"
    >
        <span x-text="message"></span>
    </div>

    <br />
    <h4 class="display-4 mb-0">🔐 WhatsApp Flow Encryption</h4>
    <hr />

    <p class="text-muted small mb-3">
        WhatsApp Flows use RSA-OAEP encryption for webhook communication.
        One keypair covers <strong>all flows</strong> for this account — you only need to set this up once.
    </p>

    {{-- Pre-flight checks --}}
    <div class="mb-3">
        <label class="form-control-label">Pre-flight Checks</label>
        <div class="mt-1 d-flex flex-column" style="gap: 0.4rem;">

            {{-- Phone Number ID --}}
            <div class="d-flex align-items-center" style="gap: 0.5rem;">
                @if ($hasPhoneId)
                    <span class="badge badge-success">&#10003;</span>
                    <small class="text-muted">Phone Number ID set <span class="font-monospace">({{ $phoneIdHint }})</span></small>
                @else
                    <span class="badge badge-danger">&#10007;</span>
                    <small class="text-danger"><strong>Phone Number ID not set</strong> — enter it in the <em>Facebook Developer</em> fields above and save before managing keys.</small>
                @endif
            </div>

            {{-- Private key --}}
            <div class="d-flex align-items-center" style="gap: 0.5rem;">
                @if ($hasKey)
                    <span class="badge badge-success">&#10003;</span>
                    <small class="text-muted">
                        Private key stored &middot; Format: <strong>{{ $keyFormat }}</strong>
                        <span class="font-monospace ml-1" style="word-break: break-all;">{{ $keyPreview }}</span>
                    </small>
                @else
                    <span class="badge badge-warning">&#9888;</span>
                    <small class="text-warning">No private key stored — click <strong>Setup Keys</strong> to generate one.</small>
                @endif
            </div>

        </div>
    </div>

    {{-- Webhook endpoint --}}
    @if ($endpointUrl)
        <div class="form-group mb-3">
            <label class="form-control-label">Flow Endpoint URI</label>
            <div class="input-group">
                <input type="text" class="form-control form-control-sm" readonly value="{{ $endpointUrl }}" />
                <div class="input-group-append">
                    <button
                        type="button"
                        class="btn btn-outline-secondary btn-sm"
                        onclick="navigator.clipboard.writeText('{{ $endpointUrl }}').then(() => { this.textContent = 'Copied!'; setTimeout(() => this.textContent = 'Copy', 2000) })"
                    >Copy</button>
                </div>
            </div>
            <small class="text-muted">Paste this URL into the <strong>Endpoint</strong> tab of your flow in Meta's WhatsApp Flow Builder.</small>
        </div>
    @endif

    {{-- Action buttons --}}
    <div class="d-flex flex-wrap mb-2" style="gap: 0.5rem;">
        <button
            type="button"
            wire:click="generateAndUploadKeys"
            wire:loading.attr="disabled"
            wire:confirm="This generates a NEW RSA keypair and uploads the public key to Meta. All flows will use the new key — do this only if you want to rotate keys. Continue?"
            class="btn btn-secondary btn-sm"
        >
            <span wire:loading.remove wire:target="generateAndUploadKeys">🔑 Setup Keys</span>
            <span wire:loading wire:target="generateAndUploadKeys">Generating...</span>
        </button>

        @if ($hasKey)
            <button
                type="button"
                wire:click="reuploadPublicKey"
                wire:loading.attr="disabled"
                wire:confirm="This re-uploads the public key derived from your stored private key to Meta. Use this to fix a health check 'OAEP decoding error'. Continue?"
                class="btn btn-primary btn-sm"
            >
                <span wire:loading.remove wire:target="reuploadPublicKey">&#8593; Re-upload Public Key</span>
                <span wire:loading wire:target="reuploadPublicKey">Uploading...</span>
            </button>
        @endif
    </div>

    <small class="text-muted d-block">
        <strong>Setup Keys</strong> — generates a brand-new keypair and uploads the public key to Meta.<br />
        <strong>Re-upload Public Key</strong> — re-sends the existing public key without regenerating. Use this when the health check fails with an <em>"OAEP decoding error"</em>.
    </small>
</div>
