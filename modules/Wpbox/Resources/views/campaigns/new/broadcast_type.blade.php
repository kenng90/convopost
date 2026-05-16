<!-- resources/views/wpbox/campaigns/new/broadcast_type.blade.php -->

<div class="row mb-5">
    <div class="col-12 text-center">
        <h3 class="mb-4">{{ __('Pick How You Want to Send your WhatsApp Broadcast') }}</h3>
    </div>
</div>

<div class="row" id="broadcast-type-selector">
    <!-- File Broadcast -->
    <div class="col-lg-4 col-md-6 mb-4">
        <div class="card h-100 shadow-sm border-0 cursor-pointer" 
             id="type-file" 
             onclick="selectBroadcastType('file')">
            <div class="card-body text-center py-5">
                <div class="mb-4">
                    <i class="ni ni-single-copy-04 text-warning" style="font-size: 3.8rem;"></i>
                </div>
                <h5>File Broadcast</h5>
                <p class="text-muted small mb-0">Upload CSV / Excel with contacts + variables</p>
            </div>
        </div>
    </div>

    <!-- Group Broadcast -->
    <div class="col-lg-4 col-md-6 mb-4">
        <div class="card h-100 shadow-sm border-0 cursor-pointer" 
             id="type-group" 
             onclick="selectBroadcastType('group')">
            <div class="card-body text-center py-5">
                <div class="mb-4">
                    <i class="ni ni-circle-08 text-info" style="font-size: 3.8rem;"></i>
                </div>
                <h5>Group Broadcast</h5>
                <p class="text-muted small mb-0">Send to an existing contact group</p>
            </div>
        </div>
    </div>

    <!-- Quick Broadcast -->
    <div class="col-lg-4 col-md-6 mb-4">
        <div class="card h-100 shadow-sm border-0 cursor-pointer" 
             id="type-quick" 
             onclick="selectBroadcastType('quick')">
            <div class="card-body text-center py-5">
                <div class="mb-4">
                    <i class="ni ni-mobile-button text-success" style="font-size: 3.8rem;"></i>
                </div>
                <h5>Quick Broadcast</h5>
                <p class="text-muted small mb-0">Enter multiple phone numbers manually</p>
            </div>
        </div>
    </div>
</div>

<script>
    // Define the function immediately when this partial loads
    window.selectBroadcastType = function(type) {
        let url = "{{ route('campaigns.create') }}/" + type;
        
        @if(request()->has('type'))
            url += "?type={{ request('type') }}";
        @endif
        
        window.location.href = url;
    };
</script>