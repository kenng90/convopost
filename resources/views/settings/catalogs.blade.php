@extends('layouts.app', ['title' => __('Catalog Management')])

@section('admin_title')
    {{ __('Catalog Management') }}
@endsection

@section('content')
<div class="container-fluid mt-5">
    <div class="row">
        <div class="col-12">
            <!-- Header -->
            <div class="mb-4">
                <h1 class="h3 mb-1">{{ __('Catalog Management') }}</h1>
                <p class="text-muted">{{ __('Create and manage product catalogs for your WhatsApp flows') }}</p>
                <p id="catalog-item-usage" class="text-muted small mb-0" style="display: none;"></p>
            </div>

            <!-- Import Button -->
            <div class="mb-3">
                <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#catalogImportModal">
                    <i class="ni ni-fat-add mr-2"></i>
                    {{ __('Import New Catalog') }}
                </button>
            </div>

            <!-- Catalogs Table -->
            <div class="card">
                <div class="table-responsive">
                    <table class="table table-sm table-hover mb-0">
                        <thead class="thead-light">
                            <tr>
                                <th>{{ __('Name') }}</th>
                                <th>{{ __('Items') }}</th>
                                <th>{{ __('Version') }}</th>
                                <th>{{ __('Created') }}</th>
                                <th>{{ __('Actions') }}</th>
                            </tr>
                        </thead>
                        <tbody id="catalogs-list">
                            <tr>
                                <td colspan="5" class="text-center py-4">
                                    <div class="spinner-border spinner-border-sm text-primary" role="status">
                                        <span class="sr-only">{{ __('Loading') }}</span>
                                    </div>
                                    <p class="text-muted mt-2 mb-0">{{ __('Loading catalogs') }}...</p>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Import Modal -->
<div class="modal fade" id="catalogImportModal" tabindex="-1" role="dialog" aria-labelledby="catalogImportModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title" id="catalogImportModalLabel">{{ __('Import Catalog') }}</h6>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <form id="catalogImportForm">
                    <!-- Catalog Name -->
                    <div class="form-group">
                        <label>{{ __('Catalog Name') }}</label>
                        <input type="text" id="catalogName" name="catalogName" placeholder="e.g., Summer Products" class="form-control" required>
                    </div>

                    <div class="form-group">
                        <label>{{ __('Import template') }}</label>
                        <p class="text-sm text-muted mb-2">
                            {{ __('Use these column headers in row 1 of your spreadsheet:') }}
                            <strong>Item ID, Title, Description, Price, Category, Image URL, Stock Status, Variants, Tags</strong>.
                            {{ __('Item ID and Title are required. Enter prices in Kenyan Shillings (KSh).') }}
                        </p>
                        <a href="{{ route('catalogs.import-template') }}" class="btn btn-sm btn-outline-primary" download>
                            <i class="ni ni-cloud-download-95 mr-1"></i>
                            {{ __('Download Excel template') }}
                        </a>
                    </div>

                    <!-- File Upload -->
                    <div class="form-group">
                        <label>{{ __('Upload Excel/CSV File') }}</label>
                        <div id="dropZone" class="border p-4 text-center rounded" style="border: 2px dashed #ccc; cursor: pointer;">
                            <i class="ni ni-cloud-upload-96 text-muted"></i>
                            <p class="mt-2 text-sm text-muted">
                                <strong>{{ __('Click to upload') }}</strong> {{ __('or drag and drop') }}
                            </p>
                            <p class="text-xs text-muted">{{ __('Excel (.xlsx, .xls) or CSV files') }}</p>
                            <input type="file" id="catalogFile" name="file" class="d-none" accept=".xlsx,.xls,.csv" required>
                        </div>
                        <p id="fileName" class="text-sm text-muted mt-2"></p>
                        <p id="importPreviewStatus" class="text-sm mt-2" style="display: none;"></p>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">{{ __('Cancel') }}</button>
                <button type="button" class="btn btn-primary" onclick="submitImportForm()">{{ __('Import') }}</button>
            </div>
        </div>
    </div>
</div>

<!-- Preview Modal -->
<div class="modal fade" id="previewCatalogModal" tabindex="-1" role="dialog" aria-labelledby="previewCatalogModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title" id="previewCatalogModalLabel">{{ __('Catalog Preview') }}</h6>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div id="previewTableContent"></div>
            </div>
        </div>
    </div>
</div>

<!-- Edit Catalog Modal -->
<div class="modal fade" id="editCatalogModal" tabindex="-1" role="dialog" aria-labelledby="editCatalogModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title" id="editCatalogModalLabel">{{ __('Edit Catalog') }}</h6>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <form id="editCatalogForm">
                    <input type="hidden" id="editCatalogId">

                    <!-- Catalog Name -->
                    <div class="form-group">
                        <label>{{ __('Catalog Name') }}</label>
                        <input type="text" id="editCatalogName" class="form-control" placeholder="e.g., Summer Products" required>
                    </div>

                    <!-- Catalog Description -->
                    <div class="form-group">
                        <label>{{ __('Description') }} <small class="text-muted">(Optional)</small></label>
                        <textarea id="editCatalogDescription" class="form-control" rows="4" placeholder="Describe your catalog"></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">{{ __('Cancel') }}</button>
                <button type="button" class="btn btn-primary" onclick="saveEditCatalog()">{{ __('Save Changes') }}</button>
            </div>
        </div>
    </div>
</div>

<script src="{{ asset('js/catalog-manager.js') }}?v={{ filemtime(public_path('js/catalog-manager.js')) }}"></script>

@endsection
