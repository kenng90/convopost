<div class="card shadow max-height-vh-70 overflow-auto overflow-x-hidden">
    <div class="card-header shadow-lg">
        <b>{{ __('Connect with WhatsApp') }}</b>
    </div>

    <div class="card-body overflow-auto overflow-x-hidden scrollable-div" ref="scrollableDiv" >
        @if (config('embeddedlogin.config_id',"")!=""&&!$setupDone)  
            @include('embeddedlogin::whatsappembeded')
        @endif

        <!-- setup done  -->
        @if($setupDone)
            <div class="card-header shadow-lg">
                <b>{{ __('You have coonnected via WhatsApp Embeded login') }}</b>
            </div>
            <br /><br />
            @include('embeddedlogin::whatsappembeded')

        @endif
    </div>
   
    
</div>



