@extends('layouts.app', ['title' => __('Manage Catalog Items')])

@section('admin_title')
    {{ __('Manage Items') }} — {{ $catalog->name }}
@endsection

@section('content')
<style>
    .catalog-items-table th.col-actions,
    .catalog-items-table td.catalog-item-actions {
        width: 120px;
        min-width: 120px;
        white-space: nowrap;
    }

    .catalog-items-table .catalog-item-actions .btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-width: 2.375rem;
        height: 2.375rem;
        padding: 0;
    }

    .catalog-items-table .catalog-item-actions .btn i {
        font-size: 0.95rem;
        line-height: 1;
    }
</style>
<div class="container-fluid mt-5">
    <div class="row">
        <div class="col-12">
            <div class="mb-4">
                <a href="{{ route('catalogs.page') }}" class="btn btn-sm btn-outline-secondary mb-3">
                    <i class="ni ni-bold-left mr-1"></i>{{ __('Back to Catalog Management') }}
                </a>
                <h1 class="h3 mb-1">{{ __('Manage Items') }}</h1>
                <p class="text-muted mb-0">
                    {{ $catalog->name }}
                    <span class="mx-1">·</span>
                    {{ __('Version') }} {{ $catalog->version ?? 1 }}
                    <span class="mx-1">·</span>
                    <span id="itemsCount" class="badge badge-primary">{{ count($catalog->items ?? []) }}</span> {{ __('items') }}
                </p>
            </div>

            <input type="hidden" id="currentCatalogId" value="{{ $catalog->id }}">

            <!-- Add Item Form -->
            <div class="card mb-4">
                <div class="card-header bg-light">
                    <h6 class="mb-0">{{ __('Add New Item') }}</h6>
                </div>
                <div class="card-body">
                    <form id="addItemForm">
                        <div class="row">
                            <div class="col-md-4 col-lg-3">
                                <div class="form-group">
                                    <label>{{ __('Item ID') }} <span class="text-danger">*</span></label>
                                    <input type="text" id="newItemId" class="form-control" placeholder="e.g., PROD_001" required>
                                </div>
                            </div>
                            <div class="col-md-8 col-lg-5">
                                <div class="form-group">
                                    <label>{{ __('Title') }} <span class="text-danger">*</span></label>
                                    <input type="text" id="newItemTitle" class="form-control" placeholder="e.g., Blue Dog Bowl" required>
                                </div>
                            </div>
                            <div class="col-md-6 col-lg-2">
                                <div class="form-group">
                                    <label>{{ __('Price (KSh)') }}</label>
                                    <input type="number" id="newItemPrice" class="form-control" step="0.01" min="0" placeholder="0.00">
                                </div>
                            </div>
                            <div class="col-md-6 col-lg-2">
                                <div class="form-group">
                                    <label>{{ __('Category') }}</label>
                                    <input type="text" id="newItemCategory" class="form-control" placeholder="e.g., Pet Supplies">
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-lg-6">
                                <div class="form-group">
                                    <label>{{ __('Description') }}</label>
                                    <textarea id="newItemDescription" class="form-control" rows="2" placeholder="Item description"></textarea>
                                </div>
                            </div>
                            <div class="col-lg-6">
                                <div class="form-group">
                                    <label>{{ __('Image') }}</label>
                                    <input type="url" id="newItemImageUrl" class="form-control mb-2" placeholder="https://example.com/image.jpg">
                                    <input type="file" id="newItemImageFile" class="form-control-file" accept="image/*">
                                    <small class="text-muted">{{ __('Upload an image or paste a URL') }}</small>
                                </div>
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group mb-md-0">
                                            <label>{{ __('Stock Status') }}</label>
                                            <select id="newItemStockStatus" class="form-control">
                                                <option value="In Stock">In Stock</option>
                                                <option value="Out of Stock">Out of Stock</option>
                                                <option value="Low Stock">Low Stock</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group mb-0">
                                            <label>{{ __('Variants') }}</label>
                                            <input type="text" id="newItemVariants" class="form-control" placeholder="S, M, L">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="row align-items-end">
                            <div class="col-md-8">
                                <div class="form-group mb-0">
                                    <label>{{ __('Tags') }}</label>
                                    <input type="text" id="newItemTags" class="form-control" placeholder="New, Sale, Popular">
                                </div>
                            </div>
                            <div class="col-md-4 text-md-right mt-3 mt-md-0">
                                <button type="button" class="btn btn-primary" onclick="addNewItem()">
                                    <i class="ni ni-fat-add mr-2"></i>{{ __('Add Item') }}
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Items List -->
            <div class="card">
                <div class="card-header bg-light">
                    <div class="row align-items-center">
                        <div class="col-md-4">
                            <h6 class="mb-md-0">{{ __('Catalog Items') }}</h6>
                        </div>
                        <div class="col-md-8">
                            <form id="itemsSearchForm" class="mb-0" onsubmit="searchItems(event)">
                                <div class="input-group input-group-sm">
                                    <input type="search" id="itemsSearch" class="form-control" placeholder="{{ __('Search by ID, title, or description...') }}">
                                    <div class="input-group-append">
                                        <button type="submit" class="btn btn-primary">{{ __('Search') }}</button>
                                        <button type="button" class="btn btn-outline-secondary" onclick="clearItemsSearch()">{{ __('Clear') }}</button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
                <div class="px-3 py-2 border-bottom">
                    <small id="itemsPaginationMeta" class="text-muted"></small>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover mb-0 catalog-items-table">
                        <thead class="thead-light">
                            <tr>
                                <th>{{ __('ID') }}</th>
                                <th>{{ __('Title') }}</th>
                                <th>{{ __('Category') }}</th>
                                <th>{{ __('Price (KSh) & Stock') }}</th>
                                <th>{{ __('Tags & Variants') }}</th>
                                <th class="text-right col-actions">{{ __('Actions') }}</th>
                            </tr>
                        </thead>
                        <tbody id="itemsList">
                            <tr>
                                <td colspan="6" class="text-center py-4 text-muted">
                                    <div class="spinner-border spinner-border-sm text-primary" role="status"></div>
                                    <span class="ml-2">{{ __('Loading items') }}...</span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div class="card-footer bg-white">
                    <nav id="itemsPagination" aria-label="Catalog items pagination"></nav>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Edit Item Modal -->
<div class="modal fade" id="editItemModal" tabindex="-1" role="dialog" aria-labelledby="editItemModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title" id="editItemModalLabel">{{ __('Edit Item') }}</h6>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="editItemId">
                <div class="row">
                    <div class="col-md-8">
                        <div class="form-group">
                            <label>{{ __('Title') }} <span class="text-danger">*</span></label>
                            <input type="text" id="editItemTitle" class="form-control" required>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label>{{ __('Price (KSh)') }}</label>
                            <input type="number" id="editItemPrice" class="form-control" step="0.01" min="0">
                        </div>
                    </div>
                </div>
                <div class="form-group">
                    <label>{{ __('Description') }}</label>
                    <textarea id="editItemDescription" class="form-control" rows="2"></textarea>
                </div>
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>{{ __('Category') }}</label>
                            <input type="text" id="editItemCategory" class="form-control">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>{{ __('Image') }}</label>
                            <input type="url" id="editItemImageUrl" class="form-control mb-2">
                            <input type="file" id="editItemImageFile" class="form-control-file" accept="image/*">
                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-4">
                        <div class="form-group">
                            <label>{{ __('Stock Status') }}</label>
                            <select id="editItemStockStatus" class="form-control">
                                <option value="In Stock">In Stock</option>
                                <option value="Out of Stock">Out of Stock</option>
                                <option value="Low Stock">Low Stock</option>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label>{{ __('Variants') }}</label>
                            <input type="text" id="editItemVariants" class="form-control">
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label>{{ __('Tags') }}</label>
                            <input type="text" id="editItemTags" class="form-control">
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">{{ __('Cancel') }}</button>
                <button type="button" class="btn btn-primary" onclick="saveEditedItem()">{{ __('Save Changes') }}</button>
            </div>
        </div>
    </div>
</div>

<script src="{{ asset('js/catalog-items.js') }}?v={{ filemtime(public_path('js/catalog-items.js')) }}"></script>
@endsection
