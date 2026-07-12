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
                        <option value="listing">{{ __('Show listings (property, vehicles, etc.)') }}</option>
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
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title">{{ __('Import from Excel') }}</h6>
                <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
            </div>
            <div class="modal-body">
                <form id="catalogImportForm">
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
                        <select id="importCatalogVertical" class="form-control" onchange="updateImportTemplateHelp()"></select>
                    </div>
                    <div class="form-group">
                        <label>{{ __('Excel template') }}</label>
                        <p class="text-sm text-muted mb-2" id="importTemplateHelp">
                            {{ __('Select a catalog type and template above. The download includes the correct headers plus 5 sample items.') }}
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
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">{{ __('Cancel') }}</button>
                <button type="button" class="btn btn-primary" onclick="submitImportForm()">{{ __('Import') }}</button>
            </div>
        </div>
    </div>
</div>

<!-- Re-import -->
<div class="modal fade" id="reimportCatalogModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title">{{ __('Update from Excel') }} — <span id="reimportCatalogName"></span></h6>
                <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="reimportCatalogId">
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
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">{{ __('Cancel') }}</button>
                <button type="button" class="btn btn-primary" onclick="submitReimport()">{{ __('Update catalog') }}</button>
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
