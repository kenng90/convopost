function formatCatalogPrice(amount) {
    let normalized = amount;

    if (typeof normalized === 'string') {
        normalized = normalized.trim()
            .replace(/^\s*(ksh|kes|usd)\s*/i, '')
            .replace(/^\$+/, '')
            .replace(/,/g, '');
    }

    const value = parseFloat(normalized) || 0;

    return 'KSh ' + value.toLocaleString('en-KE', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    });
}

let currentPage = 1;
let currentSearch = '';
const catalogPresentation = window.catalogPresentation || { supports_inventory: true, status_field: 'stockStatus' };

function supportsInventory() {
    return !!catalogPresentation.supports_inventory;
}

function itemFieldValue(item, key) {
    if (item[key] !== undefined && item[key] !== null && item[key] !== '') {
        return item[key];
    }

    if (item.metadata && item.metadata[key] !== undefined && item.metadata[key] !== null && item.metadata[key] !== '') {
        return item.metadata[key];
    }

    return '';
}

function collectVerticalFieldValues(scope) {
    const values = {};
    const selector = scope === 'edit' ? '#editItemModal .catalog-vertical-field' : '#addItemForm .catalog-vertical-field';

    document.querySelectorAll(selector).forEach((field) => {
        const key = field.dataset.fieldKey;
        if (!key) {
            return;
        }

        values[key] = field.value.trim();
    });

    return values;
}

function fillVerticalFieldValues(item, scope) {
    const selector = scope === 'edit' ? '#editItemModal .catalog-vertical-field' : '#addItemForm .catalog-vertical-field';

    document.querySelectorAll(selector).forEach((field) => {
        const key = field.dataset.fieldKey;
        if (!key) {
            return;
        }

        field.value = itemFieldValue(item, key);
    });
}

function buildItemPayload(base, scope = 'new') {
    const payload = { ...base };
    const imagesTextEl = document.getElementById(scope === 'edit' ? 'editItemImagesText' : 'newItemImagesText');
    if (imagesTextEl && imagesTextEl.value.trim() !== '') {
        payload.imagesText = imagesTextEl.value.trim();
    }

    if (!supportsInventory()) {
        Object.assign(payload, collectVerticalFieldValues(scope));
        payload.stockStatus = undefined;
        payload.variants = [];
    }

    const bookingSourceEl = document.getElementById(scope === 'edit' ? 'editItemBookingSource' : 'newItemBookingSource');
    if (bookingSourceEl) {
        payload.booking_source_id = bookingSourceEl.value
            ? parseInt(bookingSourceEl.value, 10)
            : null;
    }

    return payload;
}

document.addEventListener('DOMContentLoaded', function () {
    const catalogIdEl = document.getElementById('currentCatalogId');
    if (!catalogIdEl) {
        return;
    }

    loadItems(catalogIdEl.value, 1);
});

function searchItems(event) {
    event.preventDefault();
    const catalogId = document.getElementById('currentCatalogId').value;
    currentSearch = document.getElementById('itemsSearch').value.trim();
    loadItems(catalogId, 1);
}

function clearItemsSearch() {
    document.getElementById('itemsSearch').value = '';
    currentSearch = '';
    loadItems(document.getElementById('currentCatalogId').value, 1);
}

function reloadItems() {
    loadItems(document.getElementById('currentCatalogId').value, currentPage);
}

function loadItems(catalogId, page = 1) {
    currentPage = page;

    const params = new URLSearchParams({
        page: String(page),
        per_page: '25',
    });

    if (currentSearch) {
        params.set('q', currentSearch);
    }

    fetch(`/api/list-catalogs/${catalogId}/manage/items?${params.toString()}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                displayItems(data.items, data.pagination, data.total_in_catalog, data.filtered_total, data.presentation || catalogPresentation);
            } else {
                showError('Failed to load items');
            }
        })
        .catch(error => {
            console.error('Error loading items:', error);
            showError('Error loading items: ' + error.message);
        });
}

function displayItems(items, pagination, totalInCatalog, filteredTotal, presentation = catalogPresentation) {
    const itemsList = document.getElementById('itemsList');
    const itemsCount = document.getElementById('itemsCount');
    const meta = document.getElementById('itemsPaginationMeta');
    const statusField = presentation.status_field || 'stockStatus';
    const isCommerce = !!presentation.supports_inventory;

    if (itemsCount) {
        itemsCount.textContent = totalInCatalog ?? filteredTotal ?? 0;
    }

    if (meta) {
        if ((filteredTotal ?? 0) === 0) {
            meta.textContent = currentSearch
                ? `No items match "${currentSearch}" (${totalInCatalog ?? 0} in catalog)`
                : `${totalInCatalog ?? 0} items in catalog`;
        } else if (pagination && pagination.from && pagination.to) {
            let text = `Showing ${pagination.from}–${pagination.to} of ${filteredTotal}`;
            if (filteredTotal < totalInCatalog) {
                text += ` (${totalInCatalog} total in catalog)`;
            }
            meta.textContent = text;
        } else {
            meta.textContent = `${filteredTotal} item(s)`;
        }
    }

    if (!items || items.length === 0) {
        itemsList.innerHTML = '<tr><td colspan="6" class="text-center py-4 text-muted">No items on this page</td></tr>';
        renderPagination(pagination);
        return;
    }

    itemsList.innerHTML = items.map((item) => {
        const stockStatusBadge = {
            'In Stock': 'badge-success',
            'Out of Stock': 'badge-danger',
            'Low Stock': 'badge-warning',
        };
        const badgeClass = stockStatusBadge[item.stockStatus] || 'badge-secondary';
        const statusValue = itemFieldValue(item, statusField) || item.stockStatus || 'Available';
        const highlights = (presentation.card_highlights || [])
            .filter((key) => key !== statusField)
            .map((key) => itemFieldValue(item, key))
            .filter((value) => value !== '')
            .join(' · ');
        const tagsHtml = Array.isArray(item.tags) && item.tags.length > 0
            ? item.tags.map(tag => `<span class="badge badge-info mr-1">${tag}</span>`).join('')
            : '';
        const escapedId = String(item.id).replace(/'/g, "\\'");

        return `
            <tr>
                <td><strong>${item.id}</strong></td>
                <td>
                    ${item.title}
                    ${item.imageUrl ? '<br><small class="text-muted">Image attached</small>' : ''}
                    ${Array.isArray(item.images) && item.images.length > 1 ? `<br><small class="text-muted">${item.images.length} photos</small>` : ''}
                </td>
                <td>
                    ${item.category || '-'}
                    ${item.description ? '<br><small class="text-muted">' + item.description.substring(0, 40) + (item.description.length > 40 ? '...' : '') + '</small>' : ''}
                </td>
                <td>
                    ${formatCatalogPrice(item.price)}
                    <br><span class="badge ${isCommerce ? badgeClass : 'badge-info'}">${statusValue}</span>
                </td>
                <td>
                    ${tagsHtml}
                    ${highlights ? '<br><small class="text-muted">' + highlights + '</small>' : ''}
                    ${isCommerce && Array.isArray(item.variants) && item.variants.length > 0 ? '<br><small class="text-muted">' + item.variants.length + ' variants</small>' : ''}
                </td>
                <td class="text-right catalog-item-actions">
                    <a type="button" onclick="editItemModal('${escapedId}')" class="btn btn-sm btn-warning mr-2" title="Edit">
                        <i class="ni ni-settings-gear-65"></i>
                    </a>
                    <a type="button" onclick="deleteItemFromCatalog('${escapedId}')" class="btn btn-sm btn-danger" title="Delete">
                        <i class="ni ni-fat-remove"></i>
                    </a>
                </td>
            </tr>
        `;
    }).join('');

    renderPagination(pagination);
}

function renderPagination(pagination) {
    const container = document.getElementById('itemsPagination');
    if (!container) {
        return;
    }

    if (!pagination || pagination.last_page <= 1) {
        container.innerHTML = '';
        return;
    }

    const catalogId = document.getElementById('currentCatalogId').value;
    let html = '<ul class="pagination pagination-sm mb-0 justify-content-center">';

    const prevDisabled = pagination.current_page <= 1 ? ' disabled' : '';
    html += `<li class="page-item${prevDisabled}">
        <button type="button" class="page-link" onclick="loadItems('${catalogId}', ${pagination.current_page - 1})" ${prevDisabled ? 'disabled' : ''}>Previous</button>
    </li>`;

    for (let page = 1; page <= pagination.last_page; page++) {
        if (page === 1 || page === pagination.last_page || Math.abs(page - pagination.current_page) <= 2) {
            const active = page === pagination.current_page ? ' active' : '';
            html += `<li class="page-item${active}">
                <button type="button" class="page-link" onclick="loadItems('${catalogId}', ${page})">${page}</button>
            </li>`;
        } else if (page === pagination.current_page - 3 || page === pagination.current_page + 3) {
            html += '<li class="page-item disabled"><span class="page-link">…</span></li>';
        }
    }

    const nextDisabled = pagination.current_page >= pagination.last_page ? ' disabled' : '';
    html += `<li class="page-item${nextDisabled}">
        <button type="button" class="page-link" onclick="loadItems('${catalogId}', ${pagination.current_page + 1})" ${nextDisabled ? 'disabled' : ''}>Next</button>
    </li>`;

    html += '</ul>';
    container.innerHTML = html;
}

function uploadItemImageFile(catalogId, itemId, fileInput) {
    if (!fileInput || !fileInput.files || !fileInput.files.length) {
        return Promise.resolve();
    }

    const formData = new FormData();
    formData.append('image', fileInput.files[0]);

    return fetch(`/api/list-catalogs/${catalogId}/manage/items/${encodeURIComponent(itemId)}/image`, {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
        },
        body: formData,
    }).then(response => response.json());
}

function addNewItem() {
    const catalogId = document.getElementById('currentCatalogId').value;
    const itemId = document.getElementById('newItemId').value;
    const itemTitle = document.getElementById('newItemTitle').value;

    if (!itemId || !itemTitle) {
        showError('Item ID and Title are required');
        return;
    }

    const btn = document.querySelector('[onclick="addNewItem()"]');
    const originalHtml = btn ? btn.innerHTML : 'Add Item';
    if (btn) {
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm mr-2"></span>Adding...';
    }

    const variantsArray = document.getElementById('newItemVariants')
        ? document.getElementById('newItemVariants').value.split(',').map(v => v.trim()).filter(v => v)
        : [];
    const tagsArray = document.getElementById('newItemTags').value
        .split(',').map(t => t.trim()).filter(t => t);

    const payload = buildItemPayload({
        id: itemId,
        title: itemTitle,
        description: document.getElementById('newItemDescription').value,
        price: document.getElementById('newItemPrice').value || 0,
        category: document.getElementById('newItemCategory').value || '',
        imageUrl: document.getElementById('newItemImageUrl').value || '',
        stockStatus: document.getElementById('newItemStockStatus')?.value || 'In Stock',
        variants: variantsArray,
        tags: tagsArray,
    }, 'new');

    fetch(`/api/list-catalogs/${catalogId}/manage/items`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
        },
        body: JSON.stringify(payload),
    })
        .then(response => response.json())
        .then(data => {
            if (btn) {
                btn.disabled = false;
                btn.innerHTML = originalHtml;
            }

            if (data.success) {
                const imageFile = document.getElementById('newItemImageFile');
                uploadItemImageFile(catalogId, itemId, imageFile).finally(() => {
                    showSuccess('Item added successfully');
                    document.getElementById('addItemForm').reset();
                    loadItems(catalogId, 1);
                });
            } else {
                showError(data.message || 'Failed to add item');
            }
        })
        .catch(error => {
            if (btn) {
                btn.disabled = false;
                btn.innerHTML = originalHtml;
            }
            showError('Error adding item: ' + error.message);
        });
}

function editItemModal(itemId) {
    const catalogId = document.getElementById('currentCatalogId').value;

    fetch(`/api/list-catalogs/${catalogId}/manage/items/${encodeURIComponent(itemId)}`)
        .then(response => response.json())
        .then(data => {
            if (!data.success || !data.item) {
                showError('Failed to load item');
                return;
            }

            const fullItem = data.item;
            document.getElementById('editItemId').value = fullItem.id;
            document.getElementById('editItemTitle').value = fullItem.title;
            document.getElementById('editItemDescription').value = fullItem.description || '';
            document.getElementById('editItemPrice').value = fullItem.price || 0;
            document.getElementById('editItemCategory').value = fullItem.category || '';
            document.getElementById('editItemImageUrl').value = fullItem.imageUrl || '';
            const editImagesText = document.getElementById('editItemImagesText');
            if (editImagesText) {
                const galleryImages = Array.isArray(fullItem.images)
                    ? fullItem.images
                    : (fullItem.metadata && Array.isArray(fullItem.metadata.images) ? fullItem.metadata.images : []);
                const cover = fullItem.imageUrl || '';
                editImagesText.value = galleryImages.filter((url) => url && url !== cover).join('\n');
            }
            if (document.getElementById('editItemStockStatus')) {
                document.getElementById('editItemStockStatus').value = fullItem.stockStatus || 'In Stock';
            }
            if (document.getElementById('editItemVariants')) {
                document.getElementById('editItemVariants').value = Array.isArray(fullItem.variants) ? fullItem.variants.join(', ') : '';
            }
            document.getElementById('editItemTags').value = Array.isArray(fullItem.tags) ? fullItem.tags.join(', ') : '';
            fillVerticalFieldValues(fullItem, 'edit');

            const editBookingSource = document.getElementById('editItemBookingSource');
            if (editBookingSource) {
                const sourceId = fullItem.metadata?.booking_source_id ?? fullItem.booking_source_id ?? '';
                editBookingSource.value = sourceId ? String(sourceId) : '';
            }

            if (window.$ && window.$.fn.modal) {
                window.jQuery('#editItemModal').modal('show');
            }
        })
        .catch(error => {
            showError('Error loading item: ' + error.message);
        });
}

function saveEditedItem() {
    const catalogId = document.getElementById('currentCatalogId').value;
    const itemId = document.getElementById('editItemId').value;
    const itemTitle = document.getElementById('editItemTitle').value;

    if (!itemTitle) {
        showError('Title is required');
        return;
    }

    const btn = document.querySelector('[onclick="saveEditedItem()"]');
    const originalText = btn ? btn.textContent : 'Save Changes';
    if (btn) {
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm mr-2"></span>Saving...';
    }

    const variantsArray = document.getElementById('editItemVariants')
        ? document.getElementById('editItemVariants').value.split(',').map(v => v.trim()).filter(v => v)
        : [];
    const tagsArray = document.getElementById('editItemTags').value
        .split(',').map(t => t.trim()).filter(t => t);

    const payload = buildItemPayload({
        title: itemTitle,
        description: document.getElementById('editItemDescription').value,
        price: document.getElementById('editItemPrice').value || 0,
        category: document.getElementById('editItemCategory').value || '',
        imageUrl: document.getElementById('editItemImageUrl').value || '',
        stockStatus: document.getElementById('editItemStockStatus')?.value || 'In Stock',
        variants: variantsArray,
        tags: tagsArray,
    }, 'edit');

    fetch(`/api/list-catalogs/${catalogId}/manage/items/${encodeURIComponent(itemId)}`, {
        method: 'PUT',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
        },
        body: JSON.stringify(payload),
    })
        .then(response => response.json())
        .then(data => {
            if (btn) {
                btn.disabled = false;
                btn.textContent = originalText;
            }

            if (data.success) {
                const imageFile = document.getElementById('editItemImageFile');
                uploadItemImageFile(catalogId, itemId, imageFile).finally(() => {
                    showSuccess('Item updated successfully');
                    reloadItems();
                    if (window.$ && window.$.fn.modal) {
                        window.jQuery('#editItemModal').modal('hide');
                    }
                });
            } else {
                showError(data.message || 'Failed to update item');
            }
        })
        .catch(error => {
            if (btn) {
                btn.disabled = false;
                btn.textContent = originalText;
            }
            showError('Error updating item: ' + error.message);
        });
}

function deleteItemFromCatalog(itemId) {
    if (!confirm('Are you sure you want to delete this item?')) {
        return;
    }

    const catalogId = document.getElementById('currentCatalogId').value;

    fetch(`/api/list-catalogs/${catalogId}/manage/items/${encodeURIComponent(itemId)}`, {
        method: 'DELETE',
        headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
        },
    })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showSuccess('Item deleted successfully');
                reloadItems();
            } else {
                showError(data.message || 'Failed to delete item');
            }
        })
        .catch(error => {
            showError('Error deleting item: ' + error.message);
        });
}

function showError(message) {
    showAlert(message, 'danger');
}

function syncListingFeed(replaceMissing) {
    const catalogId = document.getElementById('currentCatalogId').value;
    const url = document.getElementById('listingFeedUrl')?.value?.trim();
    const dataPath = document.getElementById('listingFeedDataPath')?.value?.trim() || 'data';

    if (!url) {
        showError('Feed URL is required');
        return;
    }

    fetch(`/api/list-catalogs/${catalogId}/import-api`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
        },
        body: JSON.stringify({
            replace_missing: !!replaceMissing,
            api_config: {
                url,
                data_path: dataPath,
                method: 'GET',
            },
        }),
    })
        .then((response) => response.json())
        .then((data) => {
            if (!data.success) {
                showError(data.message || 'Feed sync failed');
                return;
            }

            showSuccess(data.message || 'Listing feed synced');
            loadItems(catalogId, 1);
        })
        .catch((error) => showError('Feed sync error: ' + error.message));
}

function syncAvailabilityFromReminders() {
    const catalogId = document.getElementById('currentCatalogId').value;

    fetch(`/api/list-catalogs/${catalogId}/sync-availability`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
        },
    })
        .then((response) => response.json())
        .then((data) => {
            if (!data.success) {
                showError(data.message || 'Availability sync failed');
                return;
            }

            showSuccess(data.message || 'Availability synced');
            loadItems(catalogId, 1);
        })
        .catch((error) => showError('Availability sync error: ' + error.message));
}

function showSuccess(message) {
    showAlert(message, 'success');
}

function showAlert(message, type) {
    const alert = document.createElement('div');
    alert.className = `alert alert-${type} alert-dismissible fade show`;
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
    }, type === 'success' ? 3000 : 5000);
}
