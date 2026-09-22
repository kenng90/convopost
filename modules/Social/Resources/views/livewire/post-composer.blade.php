<div>
    <div class="card shadow mb-4">
        <div class="card-header border-0">
            <h3 class="mb-0">{{ __('Compose post') }}</h3>
        </div>
        <div class="card-body">
            <div class="form-group">
                <label class="form-control-label" for="social-post-content">{{ __('Caption') }}</label>
                <textarea
                    id="social-post-content"
                    class="form-control @error('content') is-invalid @enderror"
                    rows="5"
                    wire:model="content"
                    placeholder="{{ __('Write your post…') }}"
                ></textarea>
                @error('content') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
            </div>

            <div class="form-group">
                <label class="form-control-label">{{ __('Publish to') }}</label>
                @error('selectedAccountIds') <div class="text-danger small mb-2">{{ $message }}</div> @enderror
                <div class="row">
                    @forelse ($accounts as $account)
                        <div class="col-md-4 mb-2" wire:key="account-{{ $account->id }}">
                            <button
                                type="button"
                                class="btn btn-block text-left {{ in_array($account->id, $selectedAccountIds, true) ? 'btn-primary' : 'btn-outline-secondary' }}"
                                wire:click="toggleAccount({{ $account->id }})"
                            >
                                <strong>{{ $account->name ?: $account->external_id }}</strong>
                                <div class="small">{{ ucfirst($account->provider) }}</div>
                            </button>
                        </div>
                    @empty
                        <div class="col-12">
                            <p class="text-muted mb-0">
                                {{ __('No connected accounts yet.') }}
                                <a href="{{ route('social.accounts.index') }}">{{ __('Connect one') }}</a>
                            </p>
                        </div>
                    @endforelse
                </div>
            </div>

            <div class="form-group">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <label class="form-control-label mb-0">{{ __('Per-network captions') }}</label>
                    <button type="button" class="btn btn-sm btn-outline-secondary" wire:click="toggleNetworkOverrides">
                        {{ $showNetworkOverrides ? __('Hide overrides') : __('Customize per network') }}
                    </button>
                </div>
                @if ($showNetworkOverrides)
                    <p class="text-muted small">{{ __('Leave blank to use the main caption for that network.') }}</p>
                    @foreach (['facebook' => 'Facebook', 'instagram' => 'Instagram', 'linkedin' => 'LinkedIn'] as $provider => $label)
                        <div class="mb-3" wire:key="version-{{ $provider }}">
                            <label class="form-control-label" for="version-{{ $provider }}">{{ __($label) }}</label>
                            <textarea
                                id="version-{{ $provider }}"
                                class="form-control"
                                rows="3"
                                wire:model="networkVersions.{{ $provider }}"
                                placeholder="{{ __('Override for :network', ['network' => $label]) }}"
                            ></textarea>
                        </div>
                    @endforeach
                @endif
            </div>

            <div class="form-group">
                <label class="form-control-label" for="social-post-schedule">{{ __('Schedule for') }}</label>
                <input
                    id="social-post-schedule"
                    type="datetime-local"
                    class="form-control @error('scheduledAt') is-invalid @enderror"
                    wire:model="scheduledAt"
                >
                @error('scheduledAt') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                <small class="form-text text-muted">{{ __('Required when scheduling. Leave empty to save as draft.') }}</small>
            </div>

            <div class="form-group">
                <label class="form-control-label" for="social-offer-type">{{ __('Commerce offer') }}</label>
                <select id="social-offer-type" class="form-control @error('offerType') is-invalid @enderror" wire:model.live="offerType">
                    <option value="none">{{ __('No offer') }}</option>
                    <option value="url">{{ __('Custom URL') }}</option>
                    <option value="catalog">{{ __('Catalog') }}</option>
                    <option value="product">{{ __('Product / SKU id') }}</option>
                </select>
                @error('offerType') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
            </div>

            @if ($offerType === 'url')
                <div class="form-group">
                    <label class="form-control-label" for="social-offer-url">{{ __('Offer URL') }}</label>
                    <input id="social-offer-url" type="url" class="form-control @error('offerUrl') is-invalid @enderror" wire:model="offerUrl" placeholder="https://">
                    @error('offerUrl') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                </div>
            @endif

            @if ($offerType === 'catalog')
                <div class="form-group">
                    <label class="form-control-label" for="social-offer-catalog">{{ __('Catalog') }}</label>
                    <select id="social-offer-catalog" class="form-control @error('offerTargetId') is-invalid @enderror" wire:model="offerTargetId">
                        <option value="">{{ __('Select a catalog') }}</option>
                        @foreach ($catalogs as $catalog)
                            <option value="{{ $catalog->id }}">{{ $catalog->name }}</option>
                        @endforeach
                    </select>
                    @error('offerTargetId') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                </div>
            @endif

            @if ($offerType === 'product')
                <div class="form-group">
                    <label class="form-control-label" for="social-offer-product">{{ __('Product / item id') }}</label>
                    <input id="social-offer-product" type="number" min="1" class="form-control @error('offerTargetId') is-invalid @enderror" wire:model="offerTargetId">
                    @error('offerTargetId') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                    <small class="form-text text-muted">{{ __('Use a catalog item id from your store. Tracking link will be generated.') }}</small>
                </div>
                <div class="form-group">
                    <label class="form-control-label" for="social-offer-product-url">{{ __('Optional landing URL') }}</label>
                    <input id="social-offer-product-url" type="url" class="form-control @error('offerUrl') is-invalid @enderror" wire:model="offerUrl" placeholder="https://">
                    @error('offerUrl') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                </div>
            @endif

            <div class="form-group mb-0">
                <label class="form-control-label">{{ __('Attach media') }}</label>
                <div class="row">
                    @forelse ($mediaAssets as $asset)
                        <div class="col-6 col-md-3 mb-3" wire:key="media-{{ $asset->id }}">
                            <button
                                type="button"
                                class="btn btn-block p-0 border {{ in_array($asset->id, $selectedMediaIds, true) ? 'border-primary' : '' }}"
                                wire:click="toggleMedia({{ $asset->id }})"
                                style="height: 110px; overflow: hidden;"
                            >
                                @if ($asset->isImage())
                                    <img src="{{ $asset->url() }}" alt="{{ $asset->original_name }}" class="img-fluid" style="object-fit: cover; width: 100%; height: 110px;">
                                @else
                                    <div class="d-flex align-items-center justify-content-center h-100 bg-light">
                                        <span class="small">{{ __('Video') }}</span>
                                    </div>
                                @endif
                            </button>
                        </div>
                    @empty
                        <div class="col-12">
                            <p class="text-muted mb-0">
                                {{ __('No media in your library.') }}
                                <a href="{{ route('social.media.index') }}">{{ __('Upload media') }}</a>
                            </p>
                        </div>
                    @endforelse
                </div>
            </div>
        </div>
        <div class="card-footer d-flex justify-content-between align-items-center">
            <a href="{{ route('social.posts.index') }}" class="btn btn-link">{{ __('Cancel') }}</a>
            <div class="d-flex gap-2">
                <button type="button" class="btn btn-outline-primary" wire:click="saveDraft" wire:loading.attr="disabled">
                    <span wire:loading.remove wire:target="saveDraft">{{ __('Save draft') }}</span>
                    <span wire:loading wire:target="saveDraft">{{ __('Saving…') }}</span>
                </button>
                @if ($requiresApproval)
                    <button type="button" class="btn btn-primary" wire:click="submitForApproval" wire:loading.attr="disabled">
                        <span wire:loading.remove wire:target="submitForApproval">{{ __('Submit for approval') }}</span>
                        <span wire:loading wire:target="submitForApproval">{{ __('Submitting…') }}</span>
                    </button>
                @else
                    <button type="button" class="btn btn-primary" wire:click="schedule" wire:loading.attr="disabled">
                        <span wire:loading.remove wire:target="schedule">{{ __('Schedule') }}</span>
                        <span wire:loading wire:target="schedule">{{ __('Scheduling…') }}</span>
                    </button>
                @endif
            </div>
        </div>
    </div>
</div>
