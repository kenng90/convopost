let catalogUsage = null;
let catalogListCache = [];
let catalogTemplatesCache = [];
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
    loadCatalogTemplates().then(() => {
        const importMode = document.getElementById('importCatalogMode');
        if (importMode) {
            updateImportVerticalOptions();
        }
    });
    loadCatalogs();
    setupFileInputHandlers();
    setupReimportHandlers();
    setupImportModalHandlers();
});

function setupImportModalHandlers() {
    const modal = document.getElementById('catalogImportModal');
    if (!modal || !window.jQuery) {
        return;
    }

    window.jQuery(modal).on('show.bs.modal', () => {
        updateImportVerticalOptions();
        resetImportWizard();
    });
}

let importBookablePlan = null;
let reimportBookablePlan = null;

function isBookableCatalogMode(mode) {
    return mode === 'listing' || mode === 'service';
}

function currentImportVertical() {
    return document.getElementById('importCatalogVertical')?.value || '';
}

function currentReimportVertical() {
    return document.getElementById('reimportCatalogVertical')?.value || '';
}

function verticalSupportsBookableImport(mode, vertical) {
    if (!isBookableCatalogMode(mode)) {
        return false;
    }

    if (vertical === 'jobs') {
        return false;
    }

    const modeConfig = catalogTemplatesCache.find(entry => entry.key === mode);
    const verticalConfig = modeConfig?.verticals?.find(entry => entry.key === vertical);
    if (verticalConfig && verticalConfig.supports_booking === false) {
        return false;
    }

    return true;
}

function isBookableCatalogImport(mode, vertical) {
    return verticalSupportsBookableImport(mode, vertical || currentImportVertical());
}

function isBookableCatalogReimport(mode, vertical) {
    return verticalSupportsBookableImport(mode, vertical || currentReimportVertical());
}

function resetImportWizard() {
    importBookablePlan = null;
    showImportDetailsStep();
    const rows = document.getElementById('importBookableRows');
    if (rows) {
        rows.innerHTML = '';
    }
}

function resetReimportWizard() {
    reimportBookablePlan = null;
    showReimportDetailsStep();
    const rows = document.getElementById('reimportBookableRows');
    if (rows) {
        rows.innerHTML = '';
    }
}

function showImportDetailsStep() {
    const details = document.getElementById('importStepDetails');
    const bookable = document.getElementById('importStepBookable');
    const backBtn = document.getElementById('importBookableBackBtn');
    const primaryBtn = document.getElementById('importPrimaryBtn');
    const title = document.getElementById('catalogImportModalTitle');

    if (details) details.style.display = '';
    if (bookable) bookable.style.display = 'none';
    if (backBtn) backBtn.style.display = 'none';
    if (primaryBtn) {
        primaryBtn.textContent = isBookableCatalogImport(document.getElementById('importCatalogMode')?.value)
            ? 'Next'
            : 'Import';
    }
    if (title) title.textContent = 'Import from Excel';
}

function showImportBookableStep() {
    const details = document.getElementById('importStepDetails');
    const bookable = document.getElementById('importStepBookable');
    const backBtn = document.getElementById('importBookableBackBtn');
    const primaryBtn = document.getElementById('importPrimaryBtn');
    const title = document.getElementById('catalogImportModalTitle');

    if (details) details.style.display = 'none';
    if (bookable) bookable.style.display = '';
    if (backBtn) backBtn.style.display = '';
    if (primaryBtn) primaryBtn.textContent = 'Import';
    if (title) title.textContent = 'Make these bookable';
    updateBookingStaffWarning('import');
}

function showReimportDetailsStep() {
    const details = document.getElementById('reimportStepDetails');
    const bookable = document.getElementById('reimportStepBookable');
    const backBtn = document.getElementById('reimportBookableBackBtn');
    const primaryBtn = document.getElementById('reimportPrimaryBtn');

    if (details) details.style.display = '';
    if (bookable) bookable.style.display = 'none';
    if (backBtn) backBtn.style.display = 'none';
    if (primaryBtn) {
        const mode = document.getElementById('reimportCatalogMode')?.value || 'commerce';
        primaryBtn.textContent = isBookableCatalogReimport(mode) ? 'Next' : 'Update catalog';
    }
}

function showReimportBookableStep() {
    const details = document.getElementById('reimportStepDetails');
    const bookable = document.getElementById('reimportStepBookable');
    const backBtn = document.getElementById('reimportBookableBackBtn');
    const primaryBtn = document.getElementById('reimportPrimaryBtn');

    if (details) details.style.display = 'none';
    if (bookable) bookable.style.display = '';
    if (backBtn) backBtn.style.display = '';
    if (primaryBtn) primaryBtn.textContent = 'Update catalog';
    updateBookingStaffWarning('reimport');
}

function updateBookingStaffWarning(prefix) {
    const staffSelect = document.getElementById(`${prefix}BookingStaff`);
    const warning = document.getElementById(`${prefix}BookingStaffWarning`);
    if (!staffSelect || !warning) {
        return;
    }

    const selected = Array.from(staffSelect.selectedOptions || []).map(o => o.value).filter(Boolean);
    let hasCreate = false;

    const sharedAction = document.getElementById(`${prefix}SharedAction`);
    const sharedPanel = document.getElementById(`${prefix}BookableSharedPanel`);
    if (sharedPanel && sharedPanel.style.display !== 'none' && sharedAction) {
        hasCreate = sharedAction.value === 'create';
    } else {
        hasCreate = Array.from(document.querySelectorAll(`#${prefix}BookableRows select.bookable-action-select`))
            .some(select => select.value === 'create');
    }

    warning.style.display = hasCreate && selected.length === 0 ? 'block' : 'none';
}

function buildWorkingHoursFromInputs(prefix) {
    const weekdays = document.getElementById(`${prefix}HoursWeekdays`)?.checked;
    const weekend = document.getElementById(`${prefix}HoursWeekend`)?.checked;
    const start = document.getElementById(`${prefix}HoursStart`)?.value || '09:00';
    const end = document.getElementById(`${prefix}HoursEnd`)?.value || '17:00';
    const days = {
        monday: weekdays,
        tuesday: weekdays,
        wednesday: weekdays,
        thursday: weekdays,
        friday: weekdays,
        saturday: weekend,
        sunday: weekend,
    };

    const hours = {};
    Object.keys(days).forEach(day => {
        hours[day] = {
            enabled: !!days[day],
            start,
            end,
        };
    });

    return hours;
}

function collectBookingDefaults(prefix) {
    const staffSelect = document.getElementById(`${prefix}BookingStaff`);
    const staffIds = staffSelect
        ? Array.from(staffSelect.selectedOptions || []).map(o => parseInt(o.value, 10)).filter(Boolean)
        : [];

    return {
        default_duration_minutes: parseInt(document.getElementById(`${prefix}BookingDuration`)?.value || '30', 10) || 30,
        timezone: document.getElementById(`${prefix}BookingTimezone`)?.value || 'UTC',
        working_hours: buildWorkingHoursFromInputs(prefix),
        staff_ids: staffIds,
    };
}

function collectBookingShared(prefix) {
    const action = document.getElementById(`${prefix}SharedAction`)?.value || 'skip';
    const name = document.getElementById(`${prefix}SharedName`)?.value || '';
    const sourceId = parseInt(document.getElementById(`${prefix}SharedSource`)?.value || '0', 10) || null;
    const duration = parseInt(document.getElementById(`${prefix}BookingDuration`)?.value || '30', 10) || 30;

    return {
        action,
        name,
        source_id: action === 'link' ? sourceId : null,
        duration_minutes: duration,
    };
}

function collectBookingDecisions(prefix) {
    const rows = document.querySelectorAll(`#${prefix}BookableRows tr[data-item-id]`);
    return Array.from(rows).map(row => {
        const itemId = row.getAttribute('data-item-id');
        const action = row.querySelector('.bookable-action-select')?.value || 'skip';
        const name = row.querySelector('.bookable-name-input')?.value || '';
        const sourceId = parseInt(row.querySelector('.bookable-source-select')?.value || '0', 10) || null;
        const duration = parseInt(row.getAttribute('data-duration') || '30', 10) || 30;

        return {
            item_id: itemId,
            action,
            name,
            source_id: action === 'link' ? sourceId : null,
            duration_minutes: duration,
        };
    });
}

function appendBookingPlanPayload(formData, prefix, plan) {
    formData.append('booking_defaults', JSON.stringify(collectBookingDefaults(prefix)));

    if (plan?.strategy === 'shared') {
        formData.append('booking_shared', JSON.stringify(collectBookingShared(prefix)));
        return;
    }

    formData.append('booking_decisions', JSON.stringify(collectBookingDecisions(prefix)));
}

function populateBookablePlanUI(prefix, plan) {
    const staffSelect = document.getElementById(`${prefix}BookingStaff`);
    const tbody = document.getElementById(`${prefix}BookableRows`);
    const durationInput = document.getElementById(`${prefix}BookingDuration`);
    const timezoneInput = document.getElementById(`${prefix}BookingTimezone`);
    const sharedPanel = document.getElementById(`${prefix}BookableSharedPanel`);
    const rowsPanel = document.getElementById(`${prefix}BookableRowsPanel`);
    const title = document.getElementById(`${prefix}BookableTitle`);
    const subtitle = document.getElementById(`${prefix}BookableSubtitle`);

    if (!plan) {
        return;
    }

    if (durationInput && plan.defaults?.default_duration_minutes) {
        durationInput.value = plan.defaults.default_duration_minutes;
    }
    if (timezoneInput && plan.defaults?.timezone) {
        timezoneInput.value = plan.defaults.timezone;
    }

    if (staffSelect) {
        staffSelect.innerHTML = (plan.staff || []).map(staff => (
            `<option value="${staff.id}">${escapeHtml(staff.name)}</option>`
        )).join('');
        staffSelect.onchange = () => updateBookingStaffWarning(prefix);
    }

    if (plan.strategy === 'shared') {
        if (title) title.textContent = 'Shared bookable service';
        if (subtitle) {
            subtitle.textContent = 'Listings share one appointment type named after the listing type (for example Real estate or Automotive).';
        }
        if (sharedPanel) sharedPanel.style.display = '';
        if (rowsPanel) rowsPanel.style.display = 'none';

        const shared = plan.shared || {};
        const actionSelect = document.getElementById(`${prefix}SharedAction`);
        const nameInput = document.getElementById(`${prefix}SharedName`);
        const sourceSelect = document.getElementById(`${prefix}SharedSource`);
        const itemCount = document.getElementById(`${prefix}SharedItemCount`);

        if (nameInput) nameInput.value = shared.name || '';
        if (actionSelect) actionSelect.value = shared.action || 'create';
        if (sourceSelect) {
            sourceSelect.innerHTML = `<option value="">Select service...</option>` + (plan.sources || []).map(source => (
                `<option value="${source.id}" ${String(source.id) === String(shared.source_id || '') ? 'selected' : ''}>${escapeHtml(source.name)}</option>`
            )).join('');
            sourceSelect.disabled = (shared.action || 'create') !== 'link';
        }
        if (itemCount) {
            itemCount.textContent = `${shared.item_count || 0} listing(s) will use this shared service.`;
        }
        if (actionSelect) {
            actionSelect.onchange = () => {
                if (sourceSelect) {
                    sourceSelect.disabled = actionSelect.value !== 'link';
                }
                updateBookingStaffWarning(prefix);
            };
        }

        updateBookingStaffWarning(prefix);
        return;
    }

    if (title) title.textContent = 'Make these bookable';
    if (subtitle) {
        subtitle.textContent = 'Create appointment services or link existing ones so customers can book slots.';
    }
    if (sharedPanel) sharedPanel.style.display = 'none';
    if (rowsPanel) rowsPanel.style.display = '';

    if (!tbody) {
        return;
    }

    tbody.innerHTML = (plan.rows || []).map(row => {
        const action = row.action || 'create';
        const selectedSource = row.source_id || '';
        return `
            <tr data-item-id="${escapeAttr(row.item_id)}" data-duration="${escapeAttr(String(row.duration_minutes || 30))}">
                <td class="small">${escapeHtml(row.title || row.item_id)}</td>
                <td><input type="text" class="form-control form-control-sm bookable-name-input" value="${escapeAttr(row.name || row.title || '')}"></td>
                <td>
                    <select class="form-control form-control-sm bookable-action-select">
                        <option value="create" ${action === 'create' ? 'selected' : ''}>Create new</option>
                        <option value="link" ${action === 'link' ? 'selected' : ''}>Link existing</option>
                        <option value="skip" ${action === 'skip' ? 'selected' : ''}>Skip</option>
                    </select>
                </td>
                <td>
                    <select class="form-control form-control-sm bookable-source-select" ${action === 'link' ? '' : 'disabled'}>
                        <option value="">Select service...</option>
                        ${(plan.sources || []).map(source => (
                            `<option value="${source.id}" ${String(source.id) === String(selectedSource) ? 'selected' : ''}>${escapeHtml(source.name)}</option>`
                        )).join('')}
                    </select>
                </td>
            </tr>
        `;
    }).join('');

    tbody.querySelectorAll('.bookable-action-select').forEach(select => {
        select.addEventListener('change', () => {
            const row = select.closest('tr');
            const sourceSelect = row?.querySelector('.bookable-source-select');
            if (sourceSelect) {
                sourceSelect.disabled = select.value !== 'link';
            }
            updateBookingStaffWarning(prefix);
        });
    });

    updateBookingStaffWarning(prefix);
}

function handleImportPrimaryAction() {
    const mode = document.getElementById('importCatalogMode')?.value || 'commerce';
    const onDetails = document.getElementById('importStepBookable')?.style.display === 'none'
        || !document.getElementById('importStepBookable');

    if (isBookableCatalogImport(mode) && onDetails) {
        goToImportBookableStep();
        return;
    }

    submitImportForm();
}

function goToImportBookableStep() {
    const fileInput = document.getElementById('catalogFile');
    const catalogName = document.getElementById('catalogName');
    const primaryBtn = document.getElementById('importPrimaryBtn');

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
    formData.append('catalog_mode', document.getElementById('importCatalogMode')?.value || 'service');
    formData.append('vertical', document.getElementById('importCatalogVertical')?.value || 'general_service');
    formData.append('include_bookable_plan', '1');

    if (primaryBtn) {
        primaryBtn.disabled = true;
        primaryBtn.innerHTML = '<span class="spinner-border spinner-border-sm mr-2"></span>Loading...';
    }

    fetch('/api/list-catalogs/preview-excel', {
        method: 'POST',
        body: formData,
        headers: { 'X-CSRF-TOKEN': csrfToken() },
    })
        .then(r => r.json())
        .then(data => {
            if (primaryBtn) {
                primaryBtn.disabled = false;
                primaryBtn.textContent = 'Next';
            }

            if (!data.success || !data.bookable_plan) {
                showError(data.message || 'Could not prepare bookable services step');
                return;
            }

            importBookablePlan = data.bookable_plan;
            populateBookablePlanUI('import', importBookablePlan);
            showImportBookableStep();
        })
        .catch(error => {
            if (primaryBtn) {
                primaryBtn.disabled = false;
                primaryBtn.textContent = 'Next';
            }
            showError('Error preparing bookable step: ' + error.message);
        });
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
    formData.append('catalog_mode', document.getElementById('importCatalogMode')?.value || 'commerce');
    formData.append('vertical', document.getElementById('importCatalogVertical')?.value || 'retail');

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
    formData.append('catalog_mode', document.getElementById('importCatalogMode')?.value || 'commerce');
    formData.append('vertical', document.getElementById('importCatalogVertical')?.value || 'retail');

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
    const mode = document.getElementById('importCatalogMode')?.value || 'commerce';

    if (!fileInput || !fileInput.files.length) {
        showError('Please select a file');
        return;
    }

    if (!catalogName || !catalogName.value.trim()) {
        showError('Please enter a catalog name');
        return;
    }

    if (isBookableCatalogImport(mode) && !importBookablePlan) {
        goToImportBookableStep();
        return;
    }

    const formData = new FormData();
    formData.append('file', fileInput.files[0]);
    formData.append('catalogName', catalogName.value.trim());
    formData.append('catalog_mode', mode);
    formData.append('vertical', document.getElementById('importCatalogVertical')?.value || 'retail');

    if (isBookableCatalogImport(mode)) {
        appendBookingPlanPayload(formData, 'import', importBookablePlan);
    }

    const btn = document.getElementById('importPrimaryBtn');
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
                resetImportWizard();
                loadCatalogs();
                showPostImportChecklist(data.catalog, data.booking_stats);
                openGoLiveWizard(data.catalog?.id);
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

function openReimportModal(catalogId, catalogName, catalogMode = 'commerce', catalogVertical = '') {
    document.getElementById('reimportCatalogId').value = catalogId;
    document.getElementById('reimportCatalogName').textContent = catalogName;
    document.getElementById('reimportCatalogMode').value = catalogMode || 'commerce';
    const verticalInput = document.getElementById('reimportCatalogVertical');
    if (verticalInput) {
        verticalInput.value = catalogVertical || '';
    }
    document.getElementById('reimportFile').value = '';
    document.getElementById('reimportPreviewStatus').textContent = '';
    resetReimportWizard();
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

            if (data.bookable_plan) {
                reimportBookablePlan = data.bookable_plan;
            }
            if (data.catalog_mode) {
                document.getElementById('reimportCatalogMode').value = data.catalog_mode;
            }
            if (data.catalog_vertical) {
                const verticalInput = document.getElementById('reimportCatalogVertical');
                if (verticalInput) {
                    verticalInput.value = data.catalog_vertical;
                }
            }
        });
}

function handleReimportPrimaryAction() {
    const mode = document.getElementById('reimportCatalogMode')?.value || 'commerce';
    const onDetails = document.getElementById('reimportStepBookable')?.style.display === 'none'
        || !document.getElementById('reimportStepBookable');

    if (isBookableCatalogReimport(mode) && onDetails) {
        goToReimportBookableStep();
        return;
    }

    submitReimport();
}

function goToReimportBookableStep() {
    const catalogId = document.getElementById('reimportCatalogId').value;
    const fileInput = document.getElementById('reimportFile');
    const primaryBtn = document.getElementById('reimportPrimaryBtn');

    if (!fileInput.files.length) {
        showError('Select a file to re-import');
        return;
    }

    const formData = new FormData();
    formData.append('file', fileInput.files[0]);
    formData.append('preview_only', '1');

    if (primaryBtn) {
        primaryBtn.disabled = true;
        primaryBtn.innerHTML = '<span class="spinner-border spinner-border-sm mr-2"></span>Loading...';
    }

    fetch(`/api/list-catalogs/${catalogId}/reimport-excel`, {
        method: 'POST',
        body: formData,
        headers: { 'X-CSRF-TOKEN': csrfToken() },
    })
        .then(r => r.json())
        .then(data => {
            if (primaryBtn) {
                primaryBtn.disabled = false;
                primaryBtn.textContent = 'Next';
            }

            if (!data.success || !data.bookable_plan) {
                showError(data.message || 'Could not prepare bookable services step');
                return;
            }

            reimportBookablePlan = data.bookable_plan;
            if (data.catalog_mode) {
                document.getElementById('reimportCatalogMode').value = data.catalog_mode;
            }
            if (data.catalog_vertical) {
                const verticalInput = document.getElementById('reimportCatalogVertical');
                if (verticalInput) {
                    verticalInput.value = data.catalog_vertical;
                }
            }
            populateBookablePlanUI('reimport', reimportBookablePlan);
            showReimportBookableStep();
        })
        .catch(error => {
            if (primaryBtn) {
                primaryBtn.disabled = false;
                primaryBtn.textContent = 'Next';
            }
            showError('Error preparing bookable step: ' + error.message);
        });
}

function submitReimport() {
    const catalogId = document.getElementById('reimportCatalogId').value;
    const fileInput = document.getElementById('reimportFile');
    const removeMissing = document.getElementById('reimportRemoveMissing').checked;
    const mode = document.getElementById('reimportCatalogMode')?.value || 'commerce';

    if (!fileInput.files.length) {
        showError('Select a file to re-import');
        return;
    }

    if (isBookableCatalogReimport(mode) && !reimportBookablePlan) {
        goToReimportBookableStep();
        return;
    }

    const formData = new FormData();
    formData.append('file', fileInput.files[0]);
    formData.append('remove_missing', removeMissing ? '1' : '0');

    if (isBookableCatalogImport(mode)) {
        appendBookingPlanPayload(formData, 'reimport', reimportBookablePlan);
    }

    const btn = document.getElementById('reimportPrimaryBtn');
    const originalText = btn ? btn.textContent : 'Update catalog';
    if (btn) {
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm mr-2"></span>Updating...';
    }

    fetch(`/api/list-catalogs/${catalogId}/reimport-excel`, {
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
                $('#reimportCatalogModal').modal('hide');
                resetReimportWizard();
                loadCatalogs();
                showSuccess(data.message);
            } else {
                showError(data.message || 'Re-import failed');
            }
        })
        .catch(err => {
            if (btn) {
                btn.disabled = false;
                btn.textContent = originalText;
            }
            showError(err.message);
        });
}

function showPostImportChecklist(catalog, bookingStats = null) {
    if (!catalog) {
        return;
    }

    const body = document.getElementById('postImportChecklistBody');
    const bookingLine = bookingStats
        ? `<p class="text-muted small">${(bookingStats.created || 0) + (bookingStats.linked || 0)} bookable · ${bookingStats.skipped || 0} showcase only</p>`
        : '';

    body.innerHTML = `
        <p class="mb-3"><strong>${escapeHtml(catalog.name)}</strong> is ready with ${catalog.item_count || 0} product(s).</p>
        ${bookingLine}
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

function loadCatalogTemplates() {
    return fetch('/api/list-catalogs/templates')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                catalogTemplatesCache = data.modes || [];
            }
        })
        .catch(() => {});
}

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
                displayCommerceSettings(data.commerce_settings);
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

function displayCommerceSettings(settings) {
    const input = document.getElementById('whatsappOrderNumber');
    const status = document.getElementById('whatsappOrderNumberStatus');
    if (!input || !status) {
        return;
    }

    input.value = settings?.whatsapp_order_number || '';

    if (settings?.whatsapp_order_number_configured) {
        status.textContent = 'WhatsApp checkout is ready for customers.';
        status.className = 'small mt-2 text-success';
    } else {
        status.textContent = 'Set a number to enable WhatsApp checkout from your shop.';
        status.className = 'small mt-2 text-warning';
    }
}

function saveCommerceSettings() {
    const input = document.getElementById('whatsappOrderNumber');
    if (!input) {
        return;
    }

    fetch('/api/list-catalogs/commerce-settings', {
        method: 'PUT',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrfToken(),
        },
        body: JSON.stringify({
            whatsapp_order_number: input.value.trim(),
        }),
    })
        .then(r => r.json())
        .then(data => {
            if (!data.success) {
                showError(data.message || 'Could not save checkout settings');
                return;
            }

            displayCommerceSettings(data.commerce_settings);
            showSuccess(data.message || 'Catalog checkout settings saved.');
        })
        .catch(() => showError('Could not save checkout settings'));
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
        const typeLabel = catalog.presentation?.mode_label || catalog.catalog_mode || 'commerce';
        const verticalLabel = catalog.presentation?.vertical_label || '';
        const typeBadge = verticalLabel && verticalLabel !== typeLabel
            ? `${typeLabel} · ${verticalLabel}`
            : typeLabel;
        const flowsBadge = catalog.flows_count > 0
            ? `<span class="badge badge-info ml-1" title="Used in flows">${catalog.flows_count} flow(s)</span>`
            : '';

        return `
            <tr>
                <td>
                    <strong>${escapedName}</strong>${flowsBadge}
                    ${catalog.description ? `<br><small class="text-muted">${escapeHtml(catalog.description)}</small>` : ''}
                </td>
                <td><span class="badge badge-light text-dark">${escapeHtml(typeBadge)}</span></td>
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
    <a href="javascript:void(0)" class="btn btn-sm btn-outline-warning mr-1" title="Re-import Excel" onclick="openReimportModal(${catalog.id}, '${escapeAttr(catalog.name)}', '${escapeAttr(catalog.catalog_mode || 'commerce')}', '${escapeAttr(catalog.vertical || '')}')"><i class="ni ni-cloud-upload-96"></i></a>
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

function updateImportPrimaryButtonLabel() {
    const modeSelect = document.getElementById('importCatalogMode');
    const verticalSelect = document.getElementById('importCatalogVertical');
    const primaryBtn = document.getElementById('importPrimaryBtn');
    const bookableStep = document.getElementById('importStepBookable');

    if (!primaryBtn || !modeSelect) {
        return;
    }

    if (bookableStep && bookableStep.style.display !== 'none') {
        return;
    }

    primaryBtn.textContent = isBookableCatalogImport(modeSelect.value, verticalSelect?.value) ? 'Next' : 'Import';
}

function updateImportVerticalOptions() {
    const modeSelect = document.getElementById('importCatalogMode');
    const verticalGroup = document.getElementById('importCatalogVerticalGroup');
    const verticalSelect = document.getElementById('importCatalogVertical');

    if (!modeSelect || !verticalGroup || !verticalSelect) {
        return;
    }

    const selectedMode = catalogTemplatesCache.find(mode => mode.key === modeSelect.value) || { verticals: [] };
    const verticals = selectedMode.verticals || [];
    const previousVertical = verticalSelect.value;

    if (verticals.length <= 1) {
        verticalGroup.style.display = 'none';
        verticalSelect.innerHTML = verticals[0]
            ? `<option value="${verticals[0].key}">${escapeHtml(verticals[0].label)}</option>`
            : '';
    } else {
        verticalGroup.style.display = '';
        verticalSelect.innerHTML = verticals.map(vertical => (
            `<option value="${vertical.key}">${escapeHtml(vertical.label)}</option>`
        )).join('');

        const preferredVertical = verticals.some(vertical => vertical.key === previousVertical)
            ? previousVertical
            : (selectedMode.default_vertical || verticals[0]?.key || '');

        if (preferredVertical) {
            verticalSelect.value = preferredVertical;
        }
    }

    updateImportTemplateHelp();
    updateImportPrimaryButtonLabel();
}

function importTemplateFilename(mode, vertical) {
    const modeSlug = String(mode || 'commerce').replace(/_/g, '-');
    const verticalSlug = String(vertical || 'retail').replace(/_/g, '-');

    return `catalog-import-${modeSlug}-${verticalSlug}.xlsx`;
}

function updateImportTemplateHelp() {
    const mode = document.getElementById('importCatalogMode')?.value || 'commerce';
    const vertical = document.getElementById('importCatalogVertical')?.value
        || (mode === 'commerce' ? 'retail' : (mode === 'listing' ? 'real_estate' : 'general_service'));
    const help = document.getElementById('importTemplateHelp');
    const download = document.getElementById('importTemplateDownload');
    const filename = importTemplateFilename(mode, vertical);

    const selectedMode = catalogTemplatesCache.find(item => item.key === mode);
    const selectedVertical = selectedMode?.verticals?.find(item => item.key === vertical);
    const headers = selectedVertical?.excel_headers
        || (mode === 'commerce'
            ? ['Item ID', 'Title', 'Description', 'Price', 'Category', 'Image URL', 'Stock Status', 'Variants', 'Tags']
            : ['Item ID', 'Title', 'Description', 'Price', 'Category', 'Image URL', 'Image URLs', 'Tags']);

    if (help) {
        help.innerHTML = `Row 1 headers: <strong>${headers.join(', ')}</strong>. Rows 2–11 are sample items for this template. Item ID and Title are required.`;
    }

    if (download) {
        const query = `?catalog_mode=${encodeURIComponent(mode)}&vertical=${encodeURIComponent(vertical)}`;
        download.href = `/api/list-catalogs/import-template${query}`;
        download.setAttribute('download', filename);
    }
}

function openCreateEmptyModal() {
    document.getElementById('emptyCatalogName').value = '';
    document.getElementById('emptyCatalogDescription').value = '';
    document.getElementById('emptyCatalogMode').value = 'commerce';
    updateCatalogVerticalOptions();
    $('#createEmptyCatalogModal').modal('show');
}

function updateCatalogVerticalOptions() {
    const modeSelect = document.getElementById('emptyCatalogMode');
    const verticalGroup = document.getElementById('emptyCatalogVerticalGroup');
    const verticalSelect = document.getElementById('emptyCatalogVertical');
    const help = document.getElementById('emptyCatalogModeHelp');

    if (!modeSelect || !verticalGroup || !verticalSelect) {
        return;
    }

    const selectedMode = catalogTemplatesCache.find(mode => mode.key === modeSelect.value)
        || { verticals: [], description: '' };

    if (help) {
        help.textContent = selectedMode.description || '';
    }

    const verticals = selectedMode.verticals || [];
    if (verticals.length <= 1) {
        verticalGroup.style.display = 'none';
        verticalSelect.innerHTML = '';
        if (verticals[0]) {
            verticalSelect.innerHTML = `<option value="${verticals[0].key}">${escapeHtml(verticals[0].label)}</option>`;
        }
        return;
    }

    verticalGroup.style.display = '';
    verticalSelect.innerHTML = verticals.map(vertical => (
        `<option value="${vertical.key}">${escapeHtml(vertical.label)}</option>`
    )).join('');

    const defaultVertical = selectedMode.default_vertical || verticals[0]?.key || '';
    if (defaultVertical) {
        verticalSelect.value = defaultVertical;
    }
}

function submitCreateEmptyCatalog() {
    const name = document.getElementById('emptyCatalogName').value.trim();
    if (!name) {
        showError('Catalog name is required');
        return;
    }

    const mode = document.getElementById('emptyCatalogMode')?.value || 'commerce';
    const verticalSelect = document.getElementById('emptyCatalogVertical');
    const vertical = verticalSelect?.value || null;

    fetch('/api/list-catalogs/create-empty', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrfToken(),
        },
        body: JSON.stringify({
            name,
            description: document.getElementById('emptyCatalogDescription').value.trim() || null,
            catalog_mode: mode,
            vertical,
        }),
    })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                $('#createEmptyCatalogModal').modal('hide');
                loadCatalogs();
                showSuccess(data.message);
                showPostImportChecklist(data.catalog);
                openGoLiveWizard(data.catalog?.id);
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
                openGoLiveWizard(data.catalog?.id);
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
        checkImportPlanLimit(fileInput.files[0]);
    } else {
        fileName.textContent = '';
        if (previewStatus) {
            previewStatus.style.display = 'none';
            previewStatus.textContent = '';
        }
    }
}

function openGoLiveWizard(catalogId) {
    const list = document.getElementById('goLiveChecklist');
    const progressBar = document.getElementById('goLiveProgressBar');
    const progressLabel = document.getElementById('goLiveProgressLabel');
    const percentLabel = document.getElementById('goLivePercentLabel');

    if (!list || !progressBar) {
        return;
    }

    list.innerHTML = '<li class="list-group-item text-muted">Loading checklist...</li>';
    progressBar.style.width = '0%';
    progressBar.setAttribute('aria-valuenow', '0');
    if (progressLabel) {
        progressLabel.textContent = 'Loading...';
    }
    if (percentLabel) {
        percentLabel.textContent = '0%';
    }

    $('#catalogGoLiveWizardModal').modal('show');

    const params = new URLSearchParams();
    if (catalogId) {
        params.set('catalog_id', catalogId);
    }

    const url = '/api/list-catalogs/go-live' + (params.toString() ? '?' + params.toString() : '');

    fetch(url, {
        headers: {
            'Accept': 'application/json',
            'X-CSRF-TOKEN': csrfToken(),
        },
    })
        .then(r => r.json())
        .then(data => {
            if (!data.success) {
                list.innerHTML = `<li class="list-group-item text-danger">${escapeHtml(data.message || 'Failed to load checklist')}</li>`;
                return;
            }

            const percent = data.percent || 0;
            progressBar.style.width = percent + '%';
            progressBar.setAttribute('aria-valuenow', String(percent));
            if (percentLabel) {
                percentLabel.textContent = percent + '%';
            }
            if (progressLabel) {
                const catalogName = data.catalog?.name ? ` — ${data.catalog.name}` : '';
                progressLabel.textContent = `${data.completed || 0} of ${data.total || 0} required steps complete${catalogName}`;
            }

            const steps = data.steps || [];
            if (!steps.length) {
                list.innerHTML = '<li class="list-group-item text-muted">No steps available.</li>';
                return;
            }

            list.innerHTML = steps.map(step => {
                const done = !!step.completed;
                const optional = !!step.optional;
                const icon = done
                    ? '<i class="ni ni-check-bold text-success mr-2"></i>'
                    : '<i class="ni ni-fat-add text-muted mr-2"></i>';
                const badge = optional
                    ? '<span class="badge badge-light text-muted ml-2">Optional</span>'
                    : '';
                const action = (!done && step.action_url)
                    ? `<a href="${escapeAttr(step.action_url)}" class="btn btn-sm btn-outline-primary ml-auto" target="_blank" rel="noopener">${escapeHtml(step.action_label || 'Open')}</a>`
                    : (!done && step.action_label)
                        ? `<span class="text-muted small ml-auto">${escapeHtml(step.action_label)}</span>`
                        : '';

                return `
                    <li class="list-group-item d-flex align-items-start">
                        <div class="mr-2 mt-1">${icon}</div>
                        <div class="flex-grow-1">
                            <div class="font-weight-bold">${escapeHtml(step.title || '')}${badge}</div>
                            <div class="small text-muted">${escapeHtml(step.description || '')}</div>
                        </div>
                        ${action}
                    </li>
                `;
            }).join('');
        })
        .catch(err => {
            list.innerHTML = `<li class="list-group-item text-danger">${escapeHtml(err.message || 'Failed to load checklist')}</li>`;
        });
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
            const presentation = data.presentation || {};
            const isListing = !presentation.supports_cart;

            const metrics = isListing
                ? `
                    <div class="col-4"><div class="h4 mb-0">${a.views}</div><small class="text-muted">Views</small></div>
                    <div class="col-4"><div class="h4 mb-0">${a.listing_inquiries || 0}</div><small class="text-muted">Inquiries</small></div>
                    <div class="col-4"><div class="h4 mb-0">${presentation.vertical_label || presentation.mode_label || ''}</div><small class="text-muted">Type</small></div>
                `
                : `
                    <div class="col-3"><div class="h4 mb-0">${a.views}</div><small class="text-muted">Views</small></div>
                    <div class="col-3"><div class="h4 mb-0">${a.cart_adds}</div><small class="text-muted">Cart adds</small></div>
                    <div class="col-3"><div class="h4 mb-0">${a.whatsapp_checkouts}</div><small class="text-muted">WhatsApp</small></div>
                    <div class="col-3"><div class="h4 mb-0">${a.invoice_checkouts}</div><small class="text-muted">Invoices</small></div>
                `;

            document.getElementById('analyticsBody').innerHTML = `
                <p class="text-muted">Last ${a.period_days} days</p>
                <div class="row text-center">${metrics}</div>
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
