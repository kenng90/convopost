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
                <p class="text-muted">{{ __('Create, manage, and organize your product catalogs for WhatsApp Catalog nodes') }}</p>
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

<script>
    // Load catalogs on page load
    document.addEventListener('DOMContentLoaded', loadCatalogs);

    async function loadCatalogs() {
        try {
            const response = await fetch('/api/list-catalogs');
            const result = await response.json();

            if (result.success && Array.isArray(result.catalogs)) {
                const catalogsList = document.getElementById('catalogs-list');
                
                if (result.catalogs.length === 0) {
                    catalogsList.innerHTML = `
                        <tr>
                            <td colspan="5" class="text-center py-4">
                                <p class="text-muted">{{ __('No catalogs found. Import one to get started.') }}</p>
                            </td>
                        </tr>
                    `;
                } else {
                    catalogsList.innerHTML = result.catalogs.map(catalog => `
                        <tr>
                            <td><strong>${catalog.name}</strong></td>
                            <td>${catalog.item_count} {{ __('items') }}</td>
                            <td>v${catalog.version}</td>
                            <td>${new Date(catalog.created_at).toLocaleDateString()}</td>
                            <td>
                                <a href="javascript:void(0)" onclick="previewCatalog(${catalog.id})" class="btn btn-sm btn-info mr-2" title="{{ __('Preview') }}">
                                    <i class="ni ni-zoom-split-in"></i>
                                </a>
                                <a href="javascript:void(0)" onclick="deleteCatalog(${catalog.id}, '${catalog.name}')" class="btn btn-sm btn-danger" title="{{ __('Delete') }}">
                                    <i class="ni ni-fat-remove"></i>
                                </a>
                            </td>
                        </tr>
                    `).join('');
                }
            }
        } catch (error) {
            console.error('Error loading catalogs:', error);
            document.getElementById('catalogs-list').innerHTML = `
                <tr>
                    <td colspan="5" class="text-center py-4">
                        <p class="text-danger">{{ __('Error loading catalogs. Please try again.') }}</p>
                    </td>
                </tr>
            `;
        }
    }

    // File upload handling
    const dropZone = document.getElementById('dropZone');
    const fileInput = document.getElementById('catalogFile');

    dropZone.addEventListener('click', () => fileInput.click());

    dropZone.addEventListener('dragover', (e) => {
        e.preventDefault();
        dropZone.style.borderColor = '#007bff';
        dropZone.style.backgroundColor = '#f0f7ff';
    });

    dropZone.addEventListener('dragleave', () => {
        dropZone.style.borderColor = '#ccc';
        dropZone.style.backgroundColor = 'transparent';
    });

    dropZone.addEventListener('drop', (e) => {
        e.preventDefault();
        dropZone.style.borderColor = '#ccc';
        dropZone.style.backgroundColor = 'transparent';
        const files = e.dataTransfer.files;
        if (files.length) {
            fileInput.files = files;
            updateFileName();
        }
    });

    fileInput.addEventListener('change', updateFileName);

    function updateFileName() {
        if (fileInput.files.length > 0) {
            document.getElementById('fileName').textContent = `{{ __('Selected') }}: ${fileInput.files[0].name}`;
        } else {
            document.getElementById('fileName').textContent = '';
        }
    }

    // Form submission
    function submitImportForm() {
        const catalogName = document.getElementById('catalogName').value;
        const file = document.getElementById('catalogFile').files[0];

        if (!catalogName || !file) {
            alert("{{ __('Please fill in all fields') }}");
            return;
        }

        const formData = new FormData();
        formData.append('file', file);
        formData.append('catalogName', catalogName);
        formData.append('columnMapping', JSON.stringify({
            title: 'title',
            description: 'description',
            price: 'price',
            id: 'id'
        }));

        fetch('/api/list-catalogs/import-excel', {
            method: 'POST',
            body: formData,
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
            }
        })
        .then(response => response.json())
        .then(result => {
            if (result.success) {
                alert("{{ __('Catalog imported successfully!') }}");
                $('#catalogImportModal').modal('hide');
                document.getElementById('catalogImportForm').reset();
                document.getElementById('fileName').textContent = '';
                loadCatalogs();
            } else {
                alert("{{ __('Error') }}: " + (result.message || "{{ __('Failed to import catalog') }}"));
            }
        })
        .catch(error => {
            console.error('Error importing catalog:', error);
            alert("{{ __('Error importing catalog. Please try again.') }}");
        });
    }

    function previewCatalog(catalogId) {
        fetch(`/api/list-catalogs/${catalogId}`)
        .then(response => response.json())
        .then(result => {
            if (result.success && result.catalog) {
                const catalog = result.catalog;
                document.getElementById('previewCatalogModalLabel').textContent = `${catalog.name} - {{ __('Preview') }}`;
                
                let html = '<table class="table table-sm"><thead><tr><th>ID</th><th>Title</th><th>Description</th><th>Price</th></tr></thead><tbody>';
                
                if (catalog.items && catalog.items.length > 0) {
                    catalog.items.forEach(item => {
                        html += `<tr><td>${item.id || '-'}</td><td><strong>${item.title || '-'}</strong></td><td>${item.description || '-'}</td><td>${item.price || '-'}</td></tr>`;
                    });
                } else {
                    html += `<tr><td colspan="4" class="text-center text-muted">{{ __('No items in this catalog') }}</td></tr>`;
                }
                
                html += '</tbody></table>';
                document.getElementById('previewTableContent').innerHTML = html;
                $('#previewCatalogModal').modal('show');
            }
        })
        .catch(error => {
            console.error('Error previewing catalog:', error);
            alert("{{ __('Error loading catalog preview') }}");
        });
    }

    function deleteCatalog(catalogId, catalogName) {
        if (!confirm(`{{ __('Are you sure you want to delete') }} "${catalogName}"? {{ __('This cannot be undone.') }}`)) {
            return;
        }

        fetch(`/api/list-catalogs/${catalogId}`, {
            method: 'DELETE',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
            }
        })
        .then(response => response.json())
        .then(result => {
            if (result.success) {
                alert("{{ __('Catalog deleted successfully') }}");
                loadCatalogs();
            } else {
                alert("{{ __('Error') }}: " + (result.message || "{{ __('Failed to delete catalog') }}"));
            }
        })
        .catch(error => {
            console.error('Error deleting catalog:', error);
            alert("{{ __('Error deleting catalog') }}");
        });
    }
</script>
@endsection
