// Catalog Manager
console.log('Catalog manager script loaded');

// Initialize on DOM ready
document.addEventListener('DOMContentLoaded', function() {
    console.log('DOM Content Loaded - Starting to initialize catalog manager');
    
    // Load catalogs
    loadCatalogs();
    
    // Setup file input handlers
    setupFileInputHandlers();
});

// Setup file input handlers
function setupFileInputHandlers() {
    const fileInput = document.getElementById('catalogFile');
    if (fileInput) {
        fileInput.addEventListener('change', () => {
            updateFileName();
            previewImportFile();
        });
    }

    const dropZone = document.getElementById('dropZone');
    if (dropZone && fileInput) {
        dropZone.addEventListener('click', () => fileInput.click());
        dropZone.addEventListener('dragover', (e) => {
            e.preventDefault();
            dropZone.style.borderColor = '#007bff';
            dropZone.style.backgroundColor = '#f8f9ff';
        });
        dropZone.addEventListener('dragleave', () => {
            dropZone.style.borderColor = '#ccc';
            dropZone.style.backgroundColor = 'transparent';
        });
        dropZone.addEventListener('drop', (e) => {
            e.preventDefault();
            dropZone.style.borderColor = '#ccc';
            dropZone.style.backgroundColor = 'transparent';
            if (e.dataTransfer.files.length > 0) {
                fileInput.files = e.dataTransfer.files;
                updateFileName();
                previewImportFile();
            }
        });
    }
}

// Load all catalogs
function loadCatalogs() {
    fetch('/api/list-catalogs')
        .then(response => {
            console.log('Catalogs response status:', response.status);
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            return response.json();
        })
        .then(data => {
            console.log('Catalogs data:', data);
            if (data.success) {
                displayCatalogItemUsage(data.catalog_item_usage);
                displayCatalogs(data.catalogs);
            } else {
                showError('Failed to load catalogs: ' + (data.message || 'Unknown error'));
            }
        })
        .catch(error => {
            console.error('Error loading catalogs:', error);
            showError('Error loading catalogs: ' + error.message);
        });
}

function displayCatalogItemUsage(usage) {
    const el = document.getElementById('catalog-item-usage');
    if (!el || !usage) {
        return;
    }

    if (usage.unlimited) {
        el.textContent = 'Catalog items: unlimited';
    } else {
        el.textContent = `Catalog items: ${usage.used} / ${usage.limit}` +
            (usage.remaining !== null ? ` (${usage.remaining} remaining)` : '');
    }

    el.style.display = 'block';
}

// Display catalogs in table
function displayCatalogs(catalogs) {
    const catalogsList = document.getElementById('catalogs-list');
    
    if (!catalogs || !Array.isArray(catalogs) || catalogs.length === 0) {
        catalogsList.innerHTML = `
            <tr>
                <td colspan="5" class="text-center py-4 text-muted">
                    No catalogs yet. <a href="#" onclick="document.querySelector('[data-target=\\\'#catalogImportModal\\\']').click(); return false;">Import one</a>
                </td>
            </tr>
        `;
        console.log('No catalogs to display');
        return;
    }

    try {
        catalogsList.innerHTML = catalogs.map(catalog => {
            const itemCount = catalog.item_count || 0;
            const version = catalog.version || 1;
            const createdAt = catalog.created_at ? new Date(catalog.created_at).toLocaleDateString() : 'N/A';
            
            // Escape catalog name for use in JavaScript
            const escapedName = catalog.name.replace(/'/g, "\\'");
            
            return `
                <tr>
                    <td>
                        <strong>${catalog.name}</strong>
                    </td>
                    <td>
                        <span class="badge badge-primary">${itemCount}</span>
                    </td>
                    <td>
                        v${version}
                    </td>
                    <td>
                        <small class="text-muted">${createdAt}</small>
                    </td>
                    <td>
                        <a href="javascript:void(0)" onclick="previewCatalog(${catalog.id})" class="btn btn-sm btn-info mr-2" title="Preview">
                            <i class="ni ni-zoom-split-in"></i>
                        </a>
                        <a href="/catalogs/${catalog.id}/items" class="btn btn-sm btn-success mr-2" title="Manage Items">
                            <i class="ni ni-bag-17"></i>
                        </a>
                        <a href="javascript:void(0)" onclick="openEditCatalog(${catalog.id}, '${escapedName}')" class="btn btn-sm btn-warning mr-2" title="Edit">
                            <i class="ni ni-settings-gear-65"></i>
                        </a>
                        <a href="javascript:void(0)" onclick="deleteCatalog(${catalog.id}, '${escapedName}')" class="btn btn-sm btn-danger" title="Delete">
                            <i class="ni ni-fat-remove"></i>
                        </a>
                    </td>
                </tr>
            `;
        }).join('');
        console.log('Displayed ' + catalogs.length + ' catalogs');
    } catch (error) {
        console.error('Error displaying catalogs:', error);
        showError('Error displaying catalogs: ' + error.message);
    }
}

// Update file name display
function updateFileName() {
    const fileInput = document.getElementById('catalogFile');
    const fileName = document.getElementById('fileName');
    const previewStatus = document.getElementById('importPreviewStatus');

    if (fileInput && fileInput.files.length > 0) {
        fileName.textContent = '✓ ' + fileInput.files[0].name;
    } else {
        fileName.textContent = '';
        if (previewStatus) {
            previewStatus.style.display = 'none';
            previewStatus.textContent = '';
        }
    }
}

// Validate file headers against the standard template via preview API
function previewImportFile() {
    const fileInput = document.getElementById('catalogFile');
    const previewStatus = document.getElementById('importPreviewStatus');

    if (!fileInput || !fileInput.files.length || !previewStatus) {
        return;
    }

    const formData = new FormData();
    formData.append('file', fileInput.files[0]);

    previewStatus.style.display = 'block';
    previewStatus.className = 'text-sm mt-2 text-muted';
    previewStatus.textContent = 'Checking columns...';

    fetch('/api/list-catalogs/preview-excel', {
        method: 'POST',
        body: formData,
        headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
        }
    })
    .then(response => response.json())
    .then(data => {
        if (!data.success) {
            previewStatus.className = 'text-sm mt-2 text-danger';
            previewStatus.textContent = data.message || 'Could not read file';
            return;
        }

        const mapped = data.column_mapping || {};
        const mappedLabels = Object.values(mapped);
        const itemCount = data.total_count || 0;

        previewStatus.className = 'text-sm mt-2 text-success';
        previewStatus.textContent = `✓ ${itemCount} row(s) found. Mapped columns: ${mappedLabels.join(', ')}`;
    })
    .catch(error => {
        previewStatus.className = 'text-sm mt-2 text-danger';
        previewStatus.textContent = 'Error checking file: ' + error.message;
    });
}

// Submit import form
function submitImportForm() {
    const fileInput = document.getElementById('catalogFile');
    const catalogName = document.getElementById('catalogName');

    if (!fileInput || !fileInput.files.length) {
        showError('Please select a file');
        return;
    }

    if (!catalogName || !catalogName.value) {
        showError('Please enter a catalog name');
        return;
    }

    const file = fileInput.files[0];
    const formData = new FormData();
    formData.append('file', file);
    formData.append('catalogName', catalogName.value);

    // Column mapping is resolved server-side from spreadsheet headers

    // Show loading state
    const btn = document.querySelector('[onclick="submitImportForm()"]');
    const originalText = btn ? btn.textContent : 'Import';
    if (btn) {
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm mr-2"></span>Importing...';
    }

    fetch('/api/list-catalogs/import-excel', {
        method: 'POST',
        body: formData,
        headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
        }
    })
    .then(response => response.json())
    .then(data => {
        if (btn) {
            btn.disabled = false;
            btn.textContent = originalText;
        }

        if (data.success) {
            showSuccess(data.message);
            if (data.order_template && !data.order_template.ready) {
                showError('WhatsApp order template: ' + data.order_template.message);
            }
            document.getElementById('catalogImportForm').reset();
            document.getElementById('fileName').textContent = '';
            const previewStatus = document.getElementById('importPreviewStatus');
            if (previewStatus) {
                previewStatus.style.display = 'none';
                previewStatus.textContent = '';
            }
            // Close modal
            const modal = document.getElementById('catalogImportModal');
            if (modal && window.$ && window.$.fn.modal) {
                window.$(modal).modal('hide');
            }
            loadCatalogs();
        } else {
            showError(data.message || 'Failed to import catalog');
        }
    })
    .catch(error => {
        if (btn) {
            btn.disabled = false;
            btn.textContent = originalText;
        }
        console.error('Error:', error);
        showError('Error importing catalog: ' + error.message);
    });
}

// Preview catalog
function previewCatalog(catalogId) {
    fetch(`/api/list-catalogs/${catalogId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                displayCatalogPreview(data.catalog);
            } else {
                showError('Failed to load catalog');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showError('Error loading catalog');
        });
}

// Display catalog preview
function displayCatalogPreview(catalog) {
    const previewContent = document.getElementById('previewTableContent');
    
    if (!catalog.items || catalog.items.length === 0) {
        previewContent.innerHTML = '<p class="text-muted">No items in this catalog</p>';
        if (window.$ && window.$.fn.modal) {
            window.jQuery('#previewCatalogModal').modal('show');
        }
        return;
    }

    let html = '<table class="table table-sm"><thead><tr>';
    
    // Get column headers from first item
    const firstItem = catalog.items[0];
    Object.keys(firstItem).forEach(key => {
        html += `<th>${key}</th>`;
    });
    html += '</tr></thead><tbody>';

    // Add rows
    catalog.items.forEach(item => {
        html += '<tr>';
        Object.entries(item).forEach(([key, value]) => {
            let display = value;

            if (key === 'price') {
                display = formatCatalogPrice(value);
            } else if (Array.isArray(value)) {
                display = value.join(', ');
            } else if (value === null || value === undefined || value === '') {
                display = '-';
            }

            html += `<td>${display}</td>`;
        });
        html += '</tr>';
    });

    html += '</tbody></table>';
    previewContent.innerHTML = html;
    if (window.$ && window.$.fn.modal) {
        window.jQuery('#previewCatalogModal').modal('show');
    }
}

// Open edit catalog modal
function openEditCatalog(catalogId, catalogName) {
    fetch(`/api/list-catalogs/${catalogId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                document.getElementById('editCatalogId').value = catalogId;
                document.getElementById('editCatalogName').value = data.catalog.name;
                document.getElementById('editCatalogDescription').value = data.catalog.description || '';
                if (window.$ && window.$.fn.modal) {
                    window.jQuery('#editCatalogModal').modal('show');
                }
            } else {
                showError('Failed to load catalog');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showError('Error loading catalog');
        });
}

// Save edited catalog
function saveEditCatalog() {
    const catalogId = document.getElementById('editCatalogId').value;
    const catalogName = document.getElementById('editCatalogName').value;
    const catalogDescription = document.getElementById('editCatalogDescription').value;

    if (!catalogName) {
        showError('Catalog name is required');
        return;
    }

    const btn = document.querySelector('[onclick="saveEditCatalog()"]');
    const originalText = btn ? btn.textContent : 'Save Changes';
    if (btn) {
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm mr-2"></span>Saving...';
    }

    fetch(`/api/list-catalogs/${catalogId}`, {
        method: 'PUT',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
        },
        body: JSON.stringify({
            name: catalogName,
            description: catalogDescription
        })
    })
    .then(response => response.json())
    .then(data => {
        if (btn) {
            btn.disabled = false;
            btn.textContent = originalText;
        }

        if (data.success) {
            showSuccess('Catalog updated successfully');
            if (window.$ && window.$.fn.modal) {
                window.jQuery('#editCatalogModal').modal('hide');
            }
            loadCatalogs();
        } else {
            showError(data.message || 'Failed to update catalog');
        }
    })
    .catch(error => {
        if (btn) {
            btn.disabled = false;
            btn.textContent = originalText;
        }
        console.error('Error:', error);
        showError('Error updating catalog: ' + error.message);
    });
}

// Delete catalog
function deleteCatalog(catalogId, catalogName) {
    if (!confirm(`Are you sure you want to delete "${catalogName}"? This action cannot be undone.`)) {
        return;
    }

    fetch(`/api/list-catalogs/${catalogId}`, {
        method: 'DELETE',
        headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showSuccess('Catalog deleted successfully');
            loadCatalogs();
        } else {
            showError(data.message || 'Failed to delete catalog');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showError('Error deleting catalog: ' + error.message);
    });
}

// Show error message
function showError(message) {
    // Create alert element
    const alert = document.createElement('div');
    alert.className = 'alert alert-danger alert-dismissible fade show';
    alert.innerHTML = `
        ${message}
        <button type="button" class="close" data-dismiss="alert" aria-label="Close">
            <span aria-hidden="true">&times;</span>
        </button>
    `;
    
    // Insert at top of container
    const container = document.querySelector('.container-fluid');
    if (container) {
        container.insertBefore(alert, container.firstChild);
    }

    // Auto-dismiss after 5 seconds
    setTimeout(() => {
        if (alert.parentNode) {
            alert.remove();
        }
    }, 5000);
}

function showSuccess(message) {
    const alert = document.createElement('div');
    alert.className = 'alert alert-success alert-dismissible fade show';
    alert.innerHTML = `
        ${message}
        <button type="button" class="close" data-dismiss="alert" aria-label="Close">
            <span aria-hidden="true">&times;</span>
        </button>
    `;

    const container = document.querySelector('.container-fluid');
    if (container) {
        container.insertBefore(alert, container.firstChild);
    }

    setTimeout(() => {
        if (alert.parentNode) {
            alert.remove();
        }
    }, 3000);
}
