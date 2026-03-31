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

<!-- Manage Items Modal -->
<div class="modal fade" id="itemsModal" tabindex="-1" role="dialog" aria-labelledby="itemsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title" id="itemsModalLabel">{{ __('Manage Items') }}</h6>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="currentCatalogId">

                <!-- Add Item Form -->
                <div class="card mb-4">
                    <div class="card-header bg-light">
                        <h6 class="mb-0">{{ __('Add New Item') }}</h6>
                    </div>
                    <div class="card-body">
                        <form id="addItemForm">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label>{{ __('Item ID') }} <span class="text-danger">*</span></label>
                                        <input type="text" id="newItemId" class="form-control" placeholder="e.g., PROD_001" required>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label>{{ __('Title') }} <span class="text-danger">*</span></label>
                                        <input type="text" id="newItemTitle" class="form-control" placeholder="e.g., Blue Dog Bowl" required>
                                    </div>
                                </div>
                            </div>
                            <div class="form-group">
                                <label>{{ __('Description') }} <small class="text-muted">(Optional)</small></label>
                                <textarea id="newItemDescription" class="form-control" rows="2" placeholder="Item description"></textarea>
                            </div>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label>{{ __('Price') }} <small class="text-muted">(Optional)</small></label>
                                        <input type="number" id="newItemPrice" class="form-control" step="0.01" min="0" placeholder="0.00">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label>{{ __('Category') }} <small class="text-muted">(Optional)</small></label>
                                        <input type="text" id="newItemCategory" class="form-control" placeholder="e.g., Pet Supplies">
                                    </div>
                                </div>
                            </div>
                            <div class="form-group">
                                <label>{{ __('Image URL') }} <small class="text-muted">(Optional)</small></label>
                                <input type="url" id="newItemImageUrl" class="form-control" placeholder="https://example.com/image.jpg">
                            </div>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label>{{ __('Stock Status') }} <small class="text-muted">(Optional)</small></label>
                                        <select id="newItemStockStatus" class="form-control">
                                            <option value="In Stock">In Stock</option>
                                            <option value="Out of Stock">Out of Stock</option>
                                            <option value="Low Stock">Low Stock</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label>{{ __('Variants') }} <small class="text-muted">(comma-separated, e.g., S,M,L,XL)</small></label>
                                        <input type="text" id="newItemVariants" class="form-control" placeholder="e.g., Red,Blue,Green">
                                    </div>
                                </div>
                            </div>
                            <div class="form-group">
                                <label>{{ __('Tags') }} <small class="text-muted">(comma-separated, e.g., New,Sale,Popular)</small></label>
                                <input type="text" id="newItemTags" class="form-control" placeholder="e.g., New,Popular,Eco-friendly">
                            </div>
                            <button type="button" class="btn btn-sm btn-primary" onclick="addNewItem()">
                                <i class="ni ni-fat-add mr-2"></i>{{ __('Add Item') }}
                            </button>
                        </form>
                    </div>
                </div>

                <!-- Items List -->
                <div class="card">
                    <div class="card-header bg-light">
                        <h6 class="mb-0">{{ __('Items') }} <span id="itemsCount" class="badge badge-primary ml-2">0</span></h6>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-sm table-hover mb-0">
                            <thead class="thead-light">
                                <tr>
                                    <th>{{ __('ID') }}</th>
                                    <th>{{ __('Title') }}</th>
                                    <th>{{ __('Category') }}</th>
                                    <th>{{ __('Price & Stock') }}</th>
                                    <th>{{ __('Tags & Variants') }}</th>
                                    <th>{{ __('Actions') }}</th>
                                </tr>
                            </thead>
                            <tbody id="itemsList">
                                <tr>
                                    <td colspan="5" class="text-center py-3 text-muted">{{ __('No items yet') }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Edit Item Modal -->
<div class="modal fade" id="editItemModal" tabindex="-1" role="dialog" aria-labelledby="editItemModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title" id="editItemModalLabel">{{ __('Edit Item') }}</h6>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="editItemId">
                <div class="form-group">
                    <label>{{ __('Title') }} <span class="text-danger">*</span></label>
                    <input type="text" id="editItemTitle" class="form-control" required>
                </div>
                <div class="form-group">
                    <label>{{ __('Description') }} <small class="text-muted">(Optional)</small></label>
                    <textarea id="editItemDescription" class="form-control" rows="2"></textarea>
                </div>
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>{{ __('Price') }} <small class="text-muted">(Optional)</small></label>
                            <input type="number" id="editItemPrice" class="form-control" step="0.01" min="0">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>{{ __('Category') }} <small class="text-muted">(Optional)</small></label>
                            <input type="text" id="editItemCategory" class="form-control">
                        </div>
                    </div>
                </div>
                <div class="form-group">
                    <label>{{ __('Image URL') }} <small class="text-muted">(Optional)</small></label>
                    <input type="url" id="editItemImageUrl" class="form-control">
                </div>
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>{{ __('Stock Status') }} <small class="text-muted">(Optional)</small></label>
                            <select id="editItemStockStatus" class="form-control">
                                <option value="In Stock">In Stock</option>
                                <option value="Out of Stock">Out of Stock</option>
                                <option value="Low Stock">Low Stock</option>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>{{ __('Variants') }} <small class="text-muted">(comma-separated)</small></label>
                            <input type="text" id="editItemVariants" class="form-control">
                        </div>
                    </div>
                </div>
                <div class="form-group">
                    <label>{{ __('Tags') }} <small class="text-muted">(comma-separated)</small></label>
                    <input type="text" id="editItemTags" class="form-control">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">{{ __('Cancel') }}</button>
                <button type="button" class="btn btn-primary" onclick="saveEditedItem()">{{ __('Save Changes') }}</button>
            </div>
        </div>
    </div>
</div>

<script src="{{ asset('js/catalog-manager.js') }}"></script>

@endsection
