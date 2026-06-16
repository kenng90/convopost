let catalogUsage = null;
let catalogListCache = [];
let hasShopify = false;
let hasWooCommerce = false;

function formatCatalogPrice(amount) {
    let normalized = amount;

    if (typeof normalized === 'string') {
        normalized = normalized.trim()
            .replace(/^\s*(ksh|kes|usd|eur|gbp)\s*/i, '')
            .replace(/^\$+/, '')
            .replace(/,/g, '');
    }

    const value = parseFloat(normalized) || 0;

    return value.toLocaleString(undefined, {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    });
}

function csrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
}

document.addEventListener('DOMContentLoaded', function () {
    loadCatalogs();
    setupFileInputHandlers();
    setupReimportHandlers();
});

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

function setupReimportHandlers() {
    const reimportFile = document.getElementById('reimportFile');
    if (reimportFile) {
        reimportFile.addEventListener('change', previewReimportFile);
    }
}

function loadCatalogs() {
    fetch('/api/list-catalogs')
        .then(response => {
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            return response.json();
        })
        .then(data => {
            if (data.success) {
                catalogUsage = data.catalog_item_usage;
                catalogListCache = data.catalogs || [];
                hasShopify = !!data.has_shopify;
                hasWooCommerce = !!data.has_woocommerce;
                displayCatalogItemUsage(data.catalog_item_usage);
                displayCatalogs(data.catalogs);
                renderAiCatalogAttachments(data.catalogs, data.ai_catalog_ids || []);
                toggleStoreButtons();
            } else {
                showError('Failed to load catalogs: ' + (data.message || 'Unknown error'));
            }
        })
        .catch(error => {
            showError('Error loading catalogs: ' + error.message);
        });
}

function toggleStoreButtons() {
    const shopifyBtn = document.getElementById('importShopifyBtn');
    const wooBtn = document.getElementById('importWooBtn');
    if (shopifyBtn) {
        shopifyBtn.style.display = hasShopify ? 'inline-block' : 'none';
    }
    if (wooBtn) {
        wooBtn.style.display = hasWooCommerce ? 'inline-block' : 'none';
    }
}

function displayCatalogItemUsage(usage) {
    const el = document.getElementById('catalog-item-usage');
    if (!el || !usage) {
        return;
    }

    if (usage.unlimited) {
        el.textContent = 'Catalog items: unlimited on your plan';
        el.className = 'text-muted small mb-0';
    } else {
        const remaining = usage.remaining ?? 0;
        el.textContent = `Catalog items: ${usage.used} / ${usage.limit} used (${remaining} remaining)`;
        el.className = remaining <= 10 ? 'text-warning small mb-0 font-weight-bold' : 'text-muted small mb-0';
    }

    el.style.display = 'block';
}

function displayCatalogs(catalogs) {
    const catalogsList = document.getElementById('catalogs-list');

    if (!catalogs || catalogs.length === 0) {
        catalogsList.innerHTML = `
            <tr>
                <td colspan="6" class="text-center py-4 text-muted">
                    No catalogs yet.
                    <a href="#" onclick="openCreateEmptyModal(); return false;">Create one manually</a>
                    or <a href="#" onclick="document.querySelector('[data-target=\\'#catalogImportModal\\']').click(); return false;">import from Excel</a>.
                </td>
            </tr>
        `;
        return;
    }

    catalogsList.innerHTML = catalogs.map(catalog => {
        const escapedName = escapeHtml(catalog.name);
        const sourceLabel = catalog.store_source || catalog.source || 'manual';
        const flowsBadge = catalog.flows_count > 0
            ? `<span class="badge badge-info ml-1" title="Used in flows">${catalog.flows_count} flow(s)</span>`
            : '';

        return `
            <tr>
                <td>
                    <strong>${escapedName}</strong>${flowsBadge}
                    ${catalog.description ? `<br><small class="text-muted">${escapeHtml(catalog.description)}</small>` : ''}
                </td>
                <td><span class="badge badge-primary">${catalog.item_count || 0}</span></td>
                <td><span class="badge badge-secondary text-uppercase">${escapeHtml(sourceLabel)}</span></td>
                <td><small class="text-muted">${catalog.created_at ? new Date(catalog.created_at).toLocaleDateString() : 'N/A'}</small></td>
                <td class="text-nowrap">
                    <button type="button" class="btn btn-sm btn-outline-secondary mr-1" title="Copy shop link" onclick="copyCatalogLink('${escapeAttr(catalog.public_url)}')">
                        <i class="ni ni-single-copy-04"></i>
                    </button>
                    <button type="button" class="btn btn-sm btn-outline-secondary mr-1" title="QR code" onclick="showCatalogQr('${escapeAttr(catalog.public_url)}', '${escapeAttr(catalog.name)}')">
                        <i class="ni ni-mobile-button"></i>
                    </button>
<a href="${escapeAttr(catalog.public_url)}" target="_blank" class="btn btn-sm btn-info mr-1" title="Open shop"><i class="ni ni-shop"></i></a>
    <a href="javascript:void(0)" class="btn btn-sm btn-outline-primary mr-1" title="Analytics" onclick="showCatalogAnalytics(${catalog.id})"><i class="ni ni-chart-bar-32"></i></a>
    <a href="/catalogs/${catalog.id}/items" class="btn btn-sm btn-success mr-1" title="Manage items"><i class="ni ni-bag-17"></i></a>
    <a href="javascript:void(0)" class="btn btn-sm btn-outline-warning mr-1" title="Re-import Excel" onclick="openReimportModal(${catalog.id}, '${escapeAttr(catalog.name)}')"><i class="ni ni-cloud-upload-96"></i></a>
    <a href="javascript:void(0)" class="btn btn-sm btn-warning mr-1" title="Edit" onclick="openEditCatalog(${catalog.id})"><i class="ni ni-settings-gear-65"></i></a>
    <a href="javascript:void(0)" class="btn btn-sm btn-danger" title="Delete" onclick="deleteCatalog(${catalog.id}, '${escapeAttr(catalog.name)}')"><i class="ni ni-fat-remove"></i></a>
                </td>
            </tr>
        `;
    }).join('');
}

function renderAiCatalogAttachments(catalogs, selectedIds) {
    const container = document.getElementById('aiCatalogAttachments');
    if (!container) {
        return;
    }

    if (!catalogs.length) {
        container.innerHTML = '<p class="text-muted mb-0">Create a catalog first to attach it to Voice AI.</p>';
        return;
    }

    container.innerHTML = catalogs.map(catalog => `
        <div class="custom-control custom-checkbox mb-2">
            <input type="checkbox" class="custom-control-input ai-catalog-checkbox" id="ai-catalog-${catalog.id}" value="${catalog.id}" ${selectedIds.includes(catalog.id) ? 'checked' : ''}>
            <label class="custom-control-label" for="ai-catalog-${catalog.id}">${escapeHtml(catalog.name)} <small class="text-muted">(${catalog.item_count} items)</small></label>
        </div>
    `).join('');
}

function saveAiCatalogAttachments() {
    const ids = Array.from(document.querySelectorAll('.ai-catalog-checkbox:checked')).map(el => parseInt(el.value, 10));

    fetch('/api/list-catalogs/attachments', {
        method: 'PUT',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrfToken(),
        },
        body: JSON.stringify({ ai_catalog_ids: ids }),
    })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                showSuccess('Voice AI catalog attachments saved.');
            } else {
                showError(data.message || 'Failed to save attachments');
            }
        })
        .catch(err => showError(err.message));
}

function openCreateEmptyModal() {
    document.getElementById('emptyCatalogName').value = '';
    document.getElementById('emptyCatalogDescription').value = '';
    $('#createEmptyCatalogModal').modal('show');
}

function submitCreateEmptyCatalog() {
    const name = document.getElementById('emptyCatalogName').value.trim();
    if (!name) {
        showError('Catalog name is required');
        return;
    }

    fetch('/api/list-catalogs/create-empty', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrfToken(),
        },
        body: JSON.stringify({
            name,
            description: document.getElementById('emptyCatalogDescription').value.trim() || null,
        }),
    })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                $('#createEmptyCatalogModal').modal('hide');
                loadCatalogs();
                showSuccess(data.message);
                showPostImportChecklist(data.catalog);
            } else {
                showError(data.message || 'Failed to create catalog');
            }
        })
        .catch(err => showError(err.message));
}

function importFromStore(source) {
    const name = prompt(`Name for your ${source} catalog:`, source === 'shopify' ? 'Shopify Products' : 'WooCommerce Products');
    if (!name) {
        return;
    }

    const endpoint = source === 'shopify' ? '/api/list-catalogs/import-shopify' : '/api/list-catalogs/import-woocommerce';

    fetch(endpoint, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrfToken(),
        },
        body: JSON.stringify({ catalogName: name }),
    })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                loadCatalogs();
                showSuccess(data.message);
                showPostImportChecklist(data.catalog);
            } else {
                showError(data.message || 'Import failed');
            }
        })
        .catch(err => showError(err.message));
}

function updateFileName() {
    const fileInput = document.getElementById('catalogFile');
    const fileName = document.getElementById('fileName');
    const previewStatus = document.getElementById('importPreviewStatus');

    if (fileInput && fileInput.files.length > 0) {
        fileName.textContent = '✓ ' + fileInput.files[0].name;
        checkImportPlanLimit(fileInput.files);
    } else {
        fileName.textContent = '';
        if (previewStatus) {
            previewStatus.style.display = 'none';
            previewStatus.textContent = '';
        }
    }
}

function checkImportPlanLimit(file) {
    if (!catalogUsage || catalogUsage.unlimited) {
        return;
    }

    const previewStatus = document.getElementById('importPreviewStatus');
    if (!previewStatus) {
        return;
    }

    const formData = new FormData();
    formData.append('file', file);

    fetch('/api/list-catalogs/preview-excel', {
        method: 'POST',
        body: formData,
        headers: { 'X-CSRF-TOKEN': csrfToken() },
    })
        .then(r => r.json())
        .then(data => {
            if (!data.success) {
                return;
            }

            const count = data.total_count || 0;
            const remaining = catalogUsage.remaining ?? 0;
            previewStatus.style.display = 'block';

            if (count > remaining) {
                previewStatus.className = 'text-sm mt-2 text-danger';
                previewStatus.textContent = `⚠ This file has ${count} items but you only have ${remaining} slots left on your plan.`;
            }
        });
}

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
        headers: { 'X-CSRF-TOKEN': csrfToken() },
    })
        .then(r => r.json())
        .then(data => {
            if (!data.success) {
                previewStatus.className = 'text-sm mt-2 text-danger';
                previewStatus.textContent = data.message || 'Could not read file';
                return;
            }

            const mappedLabels = Object.values(data.column_mapping || {});
            previewStatus.className = 'text-sm mt-2 text-success';
            previewStatus.textContent = `✓ ${data.total_count || 0} row(s) found. Mapped: ${mappedLabels.join(', ')}`;
            checkImportPlanLimit(fileInput.files[0]);
        })
        .catch(error => {
            previewStatus.className = 'text-sm mt-2 text-danger';
            previewStatus.textContent = 'Error checking file: ' + error.message;
        });
}

function submitImportForm() {
    const fileInput = document.getElementById('catalogFile');
    const catalogName = document.getElementById('catalogName');

    if (!fileInput || !fileInput.files.length) {
        showError('Please select a file');
        return;
    }

    if (!catalogName || !catalogName.value.trim()) {
        showError('Please enter a catalog name');
        return;
    }

    const formData = new FormData();
    formData.append('file', fileInput.files[0]);
    formData.append('catalogName', catalogName.value.trim());

    const btn = document.querySelector('[onclick="submitImportForm()"]');
    const originalText = btn ? btn.textContent : 'Import';
    if (btn) {
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm mr-2"></span>Importing...';
    }

    fetch('/api/list-catalogs/import-excel', {
        method: 'POST',
        body: formData,
        headers: { 'X-CSRF-TOKEN': csrfToken() },
    })
        .then(r => r.json())
        .then(data => {
            if (btn) {
                btn.disabled = false;
                btn.textContent = originalText;
            }

            if (data.success) {
                showSuccess(data.message);
                $('#catalogImportModal').modal('hide');
                document.getElementById('catalogImportForm').reset();
                document.getElementById('fileName').textContent = '';
                loadCatalogs();
                showPostImportChecklist(data.catalog);
            } else {
                showError(data.message || 'Failed to import catalog');
            }
        })
        .catch(error => {
            if (btn) {
                btn.disabled = false;
                btn.textContent = originalText;
            }
            showError('Error importing catalog: ' + error.message);
        });
}

function openReimportModal(catalogId, catalogName) {
    document.getElementById('reimportCatalogId').value = catalogId;
    document.getElementById('reimportCatalogName').textContent = catalogName;
    document.getElementById('reimportFile').value = '';
    document.getElementById('reimportPreviewStatus').textContent = '';
    $('#reimportCatalogModal').modal('show');
}

function previewReimportFile() {
    const catalogId = document.getElementById('reimportCatalogId').value;
    const fileInput = document.getElementById('reimportFile');
    const status = document.getElementById('reimportPreviewStatus');

    if (!fileInput.files.length) {
        return;
    }

    const formData = new FormData();
    formData.append('file', fileInput.files[0]);
    formData.append('preview_only', '1');

    status.textContent = 'Analyzing changes...';

    fetch(`/api/list-catalogs/${catalogId}/reimport-excel`, {
        method: 'POST',
        body: formData,
        headers: { 'X-CSRF-TOKEN': csrfToken() },
    })
        .then(r => r.json())
        .then(data => {
            if (!data.success) {
                status.textContent = data.message || 'Preview failed';
                return;
            }

            const p = data.preview;
            status.textContent = `Preview: ${p.to_add} to add, ${p.to_update} to update, ${p.to_remove} removable → ${p.resulting_count} total items`;
        });
}

function submitReimport() {
    const catalogId = document.getElementById('reimportCatalogId').value;
    const fileInput = document.getElementById('reimportFile');
    const removeMissing = document.getElementById('reimportRemoveMissing').checked;

    if (!fileInput.files.length) {
        showError('Select a file to re-import');
        return;
    }

    const formData = new FormData();
    formData.append('file', fileInput.files[0]);
    formData.append('remove_missing', removeMissing ? '1' : '0');

    fetch(`/api/list-catalogs/${catalogId}/reimport-excel`, {
        method: 'POST',
        body: formData,
        headers: { 'X-CSRF-TOKEN': csrfToken() },
    })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                $('#reimportCatalogModal').modal('hide');
                loadCatalogs();
                showSuccess(data.message);
            } else {
                showError(data.message || 'Re-import failed');
            }
        })
        .catch(err => showError(err.message));
}

function showPostImportChecklist(catalog) {
    if (!catalog) {
        return;
    }

    const body = document.getElementById('postImportChecklistBody');
    body.innerHTML = `
        <p class="mb-3"><strong>${escapeHtml(catalog.name)}</strong> is ready with ${catalog.item_count || 0} product(s).</p>
        <ol class="mb-3 pl-3">
            <li class="mb-2"><a href="${escapeAttr(catalog.public_url)}" target="_blank">Preview your shop</a></li>
            <li class="mb-2"><button type="button" class="btn btn-sm btn-outline-secondary" onclick="copyCatalogLink('${escapeAttr(catalog.public_url)}')">Copy shop link</button></li>
            <li class="mb-2"><a href="/catalogs/${catalog.id}/items">Add or edit products</a></li>
            <li class="mb-2">Add a <strong>Send Catalog Link</strong> node in Flowmaker and select this catalog</li>
            <li>Attach to Voice AI below if you use phone orders</li>
        </ol>
        <div id="postImportQr" class="text-center"></div>
    `;

    const qrUrl = `https://api.qrserver.com/v1/create-qr-code/?size=180x180&data=${encodeURIComponent(catalog.public_url)}`;
    document.getElementById('postImportQr').innerHTML = `<img src="${qrUrl}" alt="QR code" class="img-fluid" style="max-width:180px"><p class="small text-muted mt-2">Scan to open shop</p>`;

    $('#postImportChecklistModal').modal('show');
}

function copyCatalogLink(url) {
    navigator.clipboard.writeText(url).then(() => showSuccess('Shop link copied to clipboard')).catch(() => {
        prompt('Copy this link:', url);
    });
}

function showCatalogQr(url, name) {
    document.getElementById('qrCatalogName').textContent = name;
    document.getElementById('qrCodeImage').src = `https://api.qrserver.com/v1/create-qr-code/?size=220x220&data=${encodeURIComponent(url)}`;
    document.getElementById('qrCatalogUrl').textContent = url;
    $('#catalogQrModal').modal('show');
}

function showCatalogAnalytics(catalogId) {
    fetch(`/api/list-catalogs/${catalogId}/analytics`)
        .then(r => r.json())
        .then(data => {
            if (!data.success) {
                showError('Could not load analytics');
                return;
            }

            const a = data.analytics;
            document.getElementById('analyticsBody').innerHTML = `
                <p class="text-muted">Last ${a.period_days} days</p>
                <div class="row text-center">
                    <div class="col-3"><div class="h4 mb-0">${a.views}</div><small class="text-muted">Views</small></div>
                    <div class="col-3"><div class="h4 mb-0">${a.cart_adds}</div><small class="text-muted">Cart adds</small></div>
                    <div class="col-3"><div class="h4 mb-0">${a.whatsapp_checkouts}</div><small class="text-muted">WhatsApp</small></div>
                    <div class="col-3"><div class="h4 mb-0">${a.invoice_checkouts}</div><small class="text-muted">Invoices</small></div>
                </div>
                ${data.flows?.length ? `<hr><p class="mb-1"><strong>Used in flows:</strong></p><ul class="mb-0">${data.flows.map(f => `<li>${escapeHtml(f.name)}</li>`).join('')}</ul>` : '<hr><p class="text-muted mb-0">Not connected to any flows yet.</p>'}
            `;
            $('#catalogAnalyticsModal').modal('show');
        });
}

function previewCatalog(catalogId) {
    fetch(`/api/list-catalogs/${catalogId}`)
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                displayCatalogPreview(data.catalog);
            } else {
                showError('Failed to load catalog');
            }
        });
}

function displayCatalogPreview(catalog) {
    const previewContent = document.getElementById('previewTableContent');

    if (!catalog.items || catalog.items.length === 0) {
        previewContent.innerHTML = '<p class="text-muted">No items in this catalog</p>';
        $('#previewCatalogModal').modal('show');
        return;
    }

    let html = '<table class="table table-sm"><thead><tr>';
    Object.keys(catalog.items[0]).forEach(key => { html += `<th>${escapeHtml(key)}</th>`; });
    html += '</tr></thead><tbody>';

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
            html += `<td>${escapeHtml(String(display))}</td>`;
        });
        html += '</tr>';
    });

    html += '</tbody></table>';
    previewContent.innerHTML = html;
    $('#previewCatalogModal').modal('show');
}

function openEditCatalog(catalogId) {
    fetch(`/api/list-catalogs/${catalogId}`)
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                document.getElementById('editCatalogId').value = catalogId;
                document.getElementById('editCatalogName').value = data.catalog.name;
                document.getElementById('editCatalogDescription').value = data.catalog.description || '';
                $('#editCatalogModal').modal('show');
            } else {
                showError('Failed to load catalog');
            }
        });
}

function saveEditCatalog() {
    const catalogId = document.getElementById('editCatalogId').value;
    const catalogName = document.getElementById('editCatalogName').value.trim();

    if (!catalogName) {
        showError('Catalog name is required');
        return;
    }

    fetch(`/api/list-catalogs/${catalogId}`, {
        method: 'PUT',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrfToken(),
        },
        body: JSON.stringify({
            name: catalogName,
            description: document.getElementById('editCatalogDescription').value.trim(),
        }),
    })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                showSuccess('Catalog updated successfully');
                $('#editCatalogModal').modal('hide');
                loadCatalogs();
            } else {
                showError(data.message || 'Failed to update catalog');
            }
        });
}

function deleteCatalog(catalogId, catalogName) {
    if (!confirm(`Delete "${catalogName}"? This cannot be undone.`)) {
        return;
    }

    fetch(`/api/list-catalogs/${catalogId}`, {
        method: 'DELETE',
        headers: { 'X-CSRF-TOKEN': csrfToken() },
    })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                showSuccess('Catalog deleted successfully');
                loadCatalogs();
            } else {
                showError(data.message || 'Failed to delete catalog');
            }
        });
}

function showError(message) {
    showAlert(message, 'danger');
}

function showSuccess(message) {
    showAlert(message, 'success');
}

function showAlert(message, type) {
    const alert = document.createElement('div');
    alert.className = `alert alert-${type} alert-dismissible fade show`;
    alert.innerHTML = `${escapeHtml(message)}<button type="button" class="close" data-dismiss="alert"><span>&times;</span></button>`;
    const container = document.querySelector('.container-fluid');
    if (container) {
        container.insertBefore(alert, container.firstChild);
    }
    setTimeout(() => alert.remove(), type === 'success' ? 4000 : 6000);
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function escapeAttr(text) {
    return String(text).replace(/'/g, "\\'").replace(/"/g, '&quot;');
}
