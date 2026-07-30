<!-- Create empty catalog -->
<div class="modal fade" id="createEmptyCatalogModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title">{{ __('New Catalog') }}</h6>
                <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label>{{ __('What are you showcasing?') }}</label>
                    <select id="emptyCatalogMode" class="form-control" onchange="updateCatalogVerticalOptions()">
                        <option value="commerce">{{ __('Sell products') }}</option>
                        <option value="listing">{{ __('Show listings (property, vehicles, jobs, etc.)') }}</option>
                        <option value="service">{{ __('Offer services') }}</option>
                    </select>
                    <small class="form-text text-muted" id="emptyCatalogModeHelp">{{ __('WhatsApp shop with cart and checkout.') }}</small>
                </div>
                <div class="form-group" id="emptyCatalogVerticalGroup" style="display: none;">
                    <label>{{ __('Template') }}</label>
                    <select id="emptyCatalogVertical" class="form-control"></select>
                </div>
                <div class="form-group">
                    <label>{{ __('Catalog Name') }}</label>
                    <input type="text" id="emptyCatalogName" class="form-control" placeholder="e.g., Westlands Apartments" required>
                </div>
                <div class="form-group mb-0">
                    <label>{{ __('Description') }} <small class="text-muted">(optional)</small></label>
                    <textarea id="emptyCatalogDescription" class="form-control" rows="3"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">{{ __('Cancel') }}</button>
                <button type="button" class="btn btn-primary" onclick="submitCreateEmptyCatalog()">{{ __('Create') }}</button>
            </div>
        </div>
    </div>
</div>

<!-- Import Modal -->
<div class="modal fade" id="catalogImportModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title" id="catalogImportModalTitle">{{ __('Import from Excel') }}</h6>
                <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
            </div>
            <div class="modal-body">
                <form id="catalogImportForm">
                    <div id="importStepDetails">
                        <div class="form-group">
                            <label>{{ __('Catalog Name') }}</label>
                            <input type="text" id="catalogName" class="form-control" placeholder="e.g., Summer Products" required>
                        </div>
                        <div class="form-group">
                            <label>{{ __('Catalog type') }}</label>
                            <select id="importCatalogMode" class="form-control" onchange="updateImportVerticalOptions()">
                                <option value="commerce">{{ __('Sell products') }}</option>
                                <option value="listing">{{ __('Show listings') }}</option>
                                <option value="service">{{ __('Offer services') }}</option>
                            </select>
                        </div>
                        <div class="form-group" id="importCatalogVerticalGroup" style="display:none;">
                            <label>{{ __('Template') }}</label>
                            <select id="importCatalogVertical" class="form-control" onchange="updateImportTemplateHelp(); updateImportPrimaryButtonLabel();"></select>
                        </div>
                        <div class="form-group">
                            <label>{{ __('Excel template') }}</label>
                            <p class="text-sm text-muted mb-2" id="importTemplateHelp">
                                {{ __('Select a catalog type and template above. The download includes the correct headers plus 10 sample items.') }}
                            </p>
                            <a href="{{ route('catalogs.import-template', ['catalog_mode' => 'commerce', 'vertical' => 'retail']) }}" id="importTemplateDownload" class="btn btn-sm btn-outline-primary" download="catalog-import-commerce-retail.xlsx">
                                <i class="ni ni-cloud-download-95 mr-1"></i>{{ __('Download template') }}
                            </a>
                        </div>
                        <div class="form-group mb-0">
                            <label>{{ __('Upload file') }}</label>
                            <div id="dropZone" class="border p-4 text-center rounded" style="border: 2px dashed #ccc; cursor: pointer;">
                                <i class="ni ni-cloud-upload-96 text-muted"></i>
                                <p class="mt-2 text-sm text-muted mb-0"><strong>{{ __('Click or drag') }}</strong> Excel / CSV</p>
                                <input type="file" id="catalogFile" class="d-none" accept=".xlsx,.xls,.csv" required>
                            </div>
                            <p id="fileName" class="text-sm text-muted mt-2"></p>
                            <p id="importPreviewStatus" class="text-sm mt-2" style="display: none;"></p>
                        </div>
                    </div>

                    <div id="importStepBookable" style="display: none;">
                        <h6 class="mb-1" id="importBookableTitle">{{ __('Make these bookable') }}</h6>
                        <p class="text-muted small mb-3" id="importBookableSubtitle">{{ __('Create appointment services or link existing ones so customers can book slots.') }}</p>

                        <div class="border rounded p-3 mb-3 bg-light">
                            <div class="form-row">
                                <div class="form-group col-md-4">
                                    <label class="small mb-1">{{ __('Default duration (minutes)') }}</label>
                                    <input type="number" min="5" id="importBookingDuration" class="form-control form-control-sm" value="30">
                                </div>
                                <div class="form-group col-md-4">
                                    <label class="small mb-1">{{ __('Timezone') }}</label>
                                    <input type="text" id="importBookingTimezone" class="form-control form-control-sm" value="{{ config('app.timezone', 'UTC') }}">
                                </div>
                                <div class="form-group col-md-4">
                                    <label class="small mb-1">{{ __('Staff (optional)') }}</label>
                                    <select id="importBookingStaff" class="form-control form-control-sm" multiple size="3"></select>
                                </div>
                            </div>
                            <div class="form-row align-items-end">
                                <div class="form-group col-md-3 mb-0">
                                    <div class="custom-control custom-checkbox">
                                        <input type="checkbox" class="custom-control-input" id="importHoursWeekdays" checked>
                                        <label class="custom-control-label small" for="importHoursWeekdays">{{ __('Mon–Fri') }}</label>
                                    </div>
                                </div>
                                <div class="form-group col-md-3 mb-0">
                                    <div class="custom-control custom-checkbox">
                                        <input type="checkbox" class="custom-control-input" id="importHoursWeekend">
                                        <label class="custom-control-label small" for="importHoursWeekend">{{ __('Sat–Sun') }}</label>
                                    </div>
                                </div>
                                <div class="form-group col-md-3 mb-0">
                                    <label class="small mb-1">{{ __('Start') }}</label>
                                    <input type="time" id="importHoursStart" class="form-control form-control-sm" value="09:00">
                                </div>
                                <div class="form-group col-md-3 mb-0">
                                    <label class="small mb-1">{{ __('End') }}</label>
                                    <input type="time" id="importHoursEnd" class="form-control form-control-sm" value="17:00">
                                </div>
                            </div>
                            <p id="importBookingStaffWarning" class="text-warning small mt-2 mb-0" style="display:none;">
                                {{ __('Without staff assigned, new services will not return booking slots until you add team members in Bookings.') }}
                            </p>
                        </div>

                        <div id="importBookableSharedPanel" style="display:none;">
                            <div class="border rounded p-3 mb-0">
                                <p class="small text-muted mb-3" id="importSharedHelp">
                                    {{ __('Listings share one appointment type (for viewings / test drives). Individual cars or homes do not each get their own service.') }}
                                </p>
                                <div class="form-row">
                                    <div class="form-group col-md-4">
                                        <label class="small mb-1">{{ __('Action') }}</label>
                                        <select id="importSharedAction" class="form-control form-control-sm">
                                            <option value="create">{{ __('Create new') }}</option>
                                            <option value="link">{{ __('Link existing') }}</option>
                                            <option value="skip">{{ __('Skip (not bookable)') }}</option>
                                        </select>
                                    </div>
                                    <div class="form-group col-md-4">
                                        <label class="small mb-1">{{ __('Service name') }}</label>
                                        <input type="text" id="importSharedName" class="form-control form-control-sm">
                                    </div>
                                    <div class="form-group col-md-4">
                                        <label class="small mb-1">{{ __('Existing service') }}</label>
                                        <select id="importSharedSource" class="form-control form-control-sm" disabled>
                                            <option value="">{{ __('Select service...') }}</option>
                                        </select>
                                    </div>
                                </div>
                                <p class="small text-muted mb-0" id="importSharedItemCount"></p>
                            </div>
                        </div>

                        <div id="importBookableRowsPanel">
                            <div class="table-responsive" style="max-height: 320px; overflow-y: auto;">
                                <table class="table table-sm table-bordered mb-0">
                                    <thead class="thead-light">
                                        <tr>
                                            <th>{{ __('Item') }}</th>
                                            <th>{{ __('Service name') }}</th>
                                            <th>{{ __('Action') }}</th>
                                            <th>{{ __('Existing service') }}</th>
                                        </tr>
                                    </thead>
                                    <tbody id="importBookableRows"></tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">{{ __('Cancel') }}</button>
                <button type="button" class="btn btn-outline-secondary" id="importBookableBackBtn" style="display:none;" onclick="showImportDetailsStep()">{{ __('Back') }}</button>
                <button type="button" class="btn btn-primary" id="importPrimaryBtn" onclick="handleImportPrimaryAction()">{{ __('Import') }}</button>
            </div>
        </div>
    </div>
</div>

<!-- Re-import -->
<div class="modal fade" id="reimportCatalogModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title" id="reimportModalTitle">{{ __('Update from Excel') }} — <span id="reimportCatalogName"></span></h6>
                <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="reimportCatalogId">
                <input type="hidden" id="reimportCatalogMode" value="commerce">
                <input type="hidden" id="reimportCatalogVertical" value="">

                <div id="reimportStepDetails">
                    <p class="text-muted small">{{ __('Items are matched by Item ID. Existing rows are updated; new rows are added.') }}</p>
                    <div class="form-group">
                        <input type="file" id="reimportFile" class="form-control-file" accept=".xlsx,.xls,.csv">
                    </div>
                    <div class="custom-control custom-checkbox">
                        <input type="checkbox" class="custom-control-input" id="reimportRemoveMissing">
                        <label class="custom-control-label" for="reimportRemoveMissing">{{ __('Remove items not in the spreadsheet') }}</label>
                    </div>
                    <p id="reimportPreviewStatus" class="text-sm text-muted mt-2 mb-0"></p>
                </div>

                <div id="reimportStepBookable" style="display: none;">
                    <h6 class="mb-1" id="reimportBookableTitle">{{ __('Make these bookable') }}</h6>
                    <p class="text-muted small mb-3" id="reimportBookableSubtitle">{{ __('Create appointment services or link existing ones so customers can book slots.') }}</p>

                    <div class="border rounded p-3 mb-3 bg-light">
                        <div class="form-row">
                            <div class="form-group col-md-4">
                                <label class="small mb-1">{{ __('Default duration (minutes)') }}</label>
                                <input type="number" min="5" id="reimportBookingDuration" class="form-control form-control-sm" value="30">
                            </div>
                            <div class="form-group col-md-4">
                                <label class="small mb-1">{{ __('Timezone') }}</label>
                                <input type="text" id="reimportBookingTimezone" class="form-control form-control-sm" value="{{ config('app.timezone', 'UTC') }}">
                            </div>
                            <div class="form-group col-md-4">
                                <label class="small mb-1">{{ __('Staff (optional)') }}</label>
                                <select id="reimportBookingStaff" class="form-control form-control-sm" multiple size="3"></select>
                            </div>
                        </div>
                        <div class="form-row align-items-end">
                            <div class="form-group col-md-3 mb-0">
                                <div class="custom-control custom-checkbox">
                                    <input type="checkbox" class="custom-control-input" id="reimportHoursWeekdays" checked>
                                    <label class="custom-control-label small" for="reimportHoursWeekdays">{{ __('Mon–Fri') }}</label>
                                </div>
                            </div>
                            <div class="form-group col-md-3 mb-0">
                                <div class="custom-control custom-checkbox">
                                    <input type="checkbox" class="custom-control-input" id="reimportHoursWeekend">
                                    <label class="custom-control-label small" for="reimportHoursWeekend">{{ __('Sat–Sun') }}</label>
                                </div>
                            </div>
                            <div class="form-group col-md-3 mb-0">
                                <label class="small mb-1">{{ __('Start') }}</label>
                                <input type="time" id="reimportHoursStart" class="form-control form-control-sm" value="09:00">
                            </div>
                            <div class="form-group col-md-3 mb-0">
                                <label class="small mb-1">{{ __('End') }}</label>
                                <input type="time" id="reimportHoursEnd" class="form-control form-control-sm" value="17:00">
                            </div>
                        </div>
                        <p id="reimportBookingStaffWarning" class="text-warning small mt-2 mb-0" style="display:none;">
                            {{ __('Without staff assigned, new services will not return booking slots until you add team members in Bookings.') }}
                        </p>
                    </div>

                    <div id="reimportBookableSharedPanel" style="display:none;">
                        <div class="border rounded p-3 mb-0">
                            <p class="small text-muted mb-3">
                                {{ __('Listings share one appointment type (for viewings / test drives). Individual cars or homes do not each get their own service.') }}
                            </p>
                            <div class="form-row">
                                <div class="form-group col-md-4">
                                    <label class="small mb-1">{{ __('Action') }}</label>
                                    <select id="reimportSharedAction" class="form-control form-control-sm">
                                        <option value="create">{{ __('Create new') }}</option>
                                        <option value="link">{{ __('Link existing') }}</option>
                                        <option value="skip">{{ __('Skip (not bookable)') }}</option>
                                    </select>
                                </div>
                                <div class="form-group col-md-4">
                                    <label class="small mb-1">{{ __('Service name') }}</label>
                                    <input type="text" id="reimportSharedName" class="form-control form-control-sm">
                                </div>
                                <div class="form-group col-md-4">
                                    <label class="small mb-1">{{ __('Existing service') }}</label>
                                    <select id="reimportSharedSource" class="form-control form-control-sm" disabled>
                                        <option value="">{{ __('Select service...') }}</option>
                                    </select>
                                </div>
                            </div>
                            <p class="small text-muted mb-0" id="reimportSharedItemCount"></p>
                        </div>
                    </div>

                    <div id="reimportBookableRowsPanel">
                        <div class="table-responsive" style="max-height: 320px; overflow-y: auto;">
                            <table class="table table-sm table-bordered mb-0">
                                <thead class="thead-light">
                                    <tr>
                                        <th>{{ __('Item') }}</th>
                                        <th>{{ __('Service name') }}</th>
                                        <th>{{ __('Action') }}</th>
                                        <th>{{ __('Existing service') }}</th>
                                    </tr>
                                </thead>
                                <tbody id="reimportBookableRows"></tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">{{ __('Cancel') }}</button>
                <button type="button" class="btn btn-outline-secondary" id="reimportBookableBackBtn" style="display:none;" onclick="showReimportDetailsStep()">{{ __('Back') }}</button>
                <button type="button" class="btn btn-primary" id="reimportPrimaryBtn" onclick="handleReimportPrimaryAction()">{{ __('Update catalog') }}</button>
            </div>
        </div>
    </div>
</div>

<!-- Post-import checklist -->
<div class="modal fade" id="postImportChecklistModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title">{{ __('Catalog ready!') }}</h6>
                <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
            </div>
            <div class="modal-body" id="postImportChecklistBody"></div>
            <div class="modal-footer">
                <button type="button" class="btn btn-primary" data-dismiss="modal">{{ __('Got it') }}</button>
            </div>
        </div>
    </div>
</div>

<!-- Go-live wizard -->
<div class="modal fade" id="catalogGoLiveWizardModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title">{{ __('Go live checklist') }}</h6>
                <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <small class="text-muted" id="goLiveProgressLabel">{{ __('Loading...') }}</small>
                        <small class="font-weight-bold" id="goLivePercentLabel">0%</small>
                    </div>
                    <div class="progress" style="height: 8px;">
                        <div id="goLiveProgressBar" class="progress-bar bg-success" role="progressbar" style="width: 0%;" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100"></div>
                    </div>
                </div>
                <ul class="list-group list-group-flush" id="goLiveChecklist"></ul>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">{{ __('Close') }}</button>
            </div>
        </div>
    </div>
</div>

<!-- QR modal -->
<div class="modal fade" id="catalogQrModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content text-center">
            <div class="modal-header">
                <h6 class="modal-title" id="qrCatalogName">QR Code</h6>
                <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
            </div>
            <div class="modal-body">
                <img id="qrCodeImage" src="" alt="QR" class="img-fluid mb-2">
                <p id="qrCatalogUrl" class="small text-muted text-break mb-0"></p>
            </div>
        </div>
    </div>
</div>

<!-- Analytics -->
<div class="modal fade" id="catalogAnalyticsModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title">{{ __('Catalog Analytics') }}</h6>
                <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
            </div>
            <div class="modal-body" id="analyticsBody"></div>
        </div>
    </div>
</div>

<!-- Preview -->
<div class="modal fade" id="previewCatalogModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title">{{ __('Catalog Preview') }}</h6>
                <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
            </div>
            <div class="modal-body"><div id="previewTableContent"></div></div>
        </div>
    </div>
</div>

<!-- Edit -->
<div class="modal fade" id="editCatalogModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title">{{ __('Edit Catalog') }}</h6>
                <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="editCatalogId">
                <div class="form-group">
                    <label>{{ __('Name') }}</label>
                    <input type="text" id="editCatalogName" class="form-control" required>
                </div>
                <div class="form-group mb-0">
                    <label>{{ __('Description') }}</label>
                    <textarea id="editCatalogDescription" class="form-control" rows="3"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">{{ __('Cancel') }}</button>
                <button type="button" class="btn btn-primary" onclick="saveEditCatalog()">{{ __('Save') }}</button>
            </div>
        </div>
    </div>
</div>
