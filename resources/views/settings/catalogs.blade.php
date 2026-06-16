@extends('layouts.app', ['title' => __('Catalog Management')])

@section('admin_title')
    {{ __('Catalog Management') }}
@endsection

@section('content')
<div class="container-fluid mt-5">
    <div class="row">
        <div class="col-12">
            <div class="mb-4">
                <h1 class="h3 mb-1">{{ __('Product Catalogs') }}</h1>
                <p class="text-muted mb-1">{{ __('Build a WhatsApp shop, share branded links, and connect catalogs to flows and Voice AI.') }}</p>
                <p id="catalog-item-usage" class="text-muted small mb-0" style="display: none;"></p>
            </div>

            <div class="mb-3 d-flex flex-wrap gap-2">
                <button type="button" class="btn btn-primary mr-2 mb-2" onclick="openCreateEmptyModal()">
                    <i class="ni ni-fat-add mr-2"></i>{{ __('New Catalog') }}
                </button>
                <button type="button" class="btn btn-outline-primary mr-2 mb-2" data-toggle="modal" data-target="#catalogImportModal">
                    <i class="ni ni-cloud-upload-96 mr-2"></i>{{ __('Import Excel') }}
                </button>
                <button type="button" id="importShopifyBtn" class="btn btn-outline-success mr-2 mb-2" style="display:none" onclick="importFromStore('shopify')">
                    {{ __('Import from Shopify') }}
                </button>
                <button type="button" id="importWooBtn" class="btn btn-outline-success mb-2" style="display:none" onclick="importFromStore('woocommerce')">
                    {{ __('Import from WooCommerce') }}
                </button>
            </div>

            <div class="card mb-4">
                <div class="card-header bg-light">
                    <h6 class="mb-0">{{ __('Your Catalogs') }}</h6>
                </div>
                <div class="table-responsive">
                    <table class="table table-sm table-hover mb-0">
                        <thead class="thead-light">
                            <tr>
                                <th>{{ __('Name') }}</th>
                                <th>{{ __('Items') }}</th>
                                <th>{{ __('Source') }}</th>
                                <th>{{ __('Created') }}</th>
                                <th>{{ __('Actions') }}</th>
                            </tr>
                        </thead>
                        <tbody id="catalogs-list">
                            <tr>
                                <td colspan="5" class="text-center py-4">
                                    <div class="spinner-border spinner-border-sm text-primary" role="status"></div>
                                    <p class="text-muted mt-2 mb-0">{{ __('Loading catalogs') }}...</p>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="card">
                <div class="card-header bg-light d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="mb-0">{{ __('Voice AI & Automation') }}</h6>
                        <small class="text-muted">{{ __('Attach catalogs for Voice AI product matching and post-call invoicing') }}</small>
                    </div>
                    <button type="button" class="btn btn-sm btn-primary" onclick="saveAiCatalogAttachments()">{{ __('Save') }}</button>
                </div>
                <div class="card-body" id="aiCatalogAttachments">
                    <p class="text-muted mb-0">{{ __('Loading...') }}</p>
                </div>
            </div>
        </div>
    </div>
</div>

@include('settings.partials.catalog-modals')

<script src="{{ asset('js/catalog-manager.js') }}?v={{ filemtime(public_path('js/catalog-manager.js')) }}"></script>
@endsection
