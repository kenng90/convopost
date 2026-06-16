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
                    <label>{{ __('Catalog Name') }}</label>
                    <input type="text" id="emptyCatalogName" class="form-control" placeholder="e.g., Summer Collection" required>
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
                        <label>{{ __('Template') }}</label>
                        <p class="text-sm text-muted mb-2">
                            {{ __('Row 1 headers:') }}
                            <strong>Item ID, Title, Description, Price, Category, Image URL, Stock Status, Variants, Tags</strong>.
                            {{ __('Item ID and Title are required.') }}
                        </p>
                        <a href="{{ route('catalogs.import-template') }}" class="btn btn-sm btn-outline-primary" download>
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
