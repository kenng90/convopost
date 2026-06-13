@extends('layouts.app', ['title' =>  __("CSV contacts Import ") ])


@section('content')
    <div class="header  pb-8 pt-5 pt-md-8">
    </div>
    <div class="container-fluid mt--7">
        <div class="row">
            <div class="col">
                <div class="card shadow">
                    <div class="card-header border-0">
                        <div class="row align-items-center">
                            <div class="col-8">
                                <h3 class="mb-0">{{ __("CSV contacts Import ") }}</h3>
                  
   
                                
                            </div>
                            
                               
                        </div>
                       
                    </div>
                    <div class="card-body">
                            @if ($hasActiveImport)
                                <div class="alert alert-warning">
                                    {{ __('An import is already running. You can monitor it above. Starting another import is disabled until it finishes.') }}
                                    <a href="{{ route('contacts.import.history') }}" class="alert-link">{{ __('View import history') }}</a>
                                </div>
                            @endif

                            <form id="contacts-import-form" action="{{ route('contacts.import.store') }}" method="POST" enctype="multipart/form-data" @if($hasActiveImport) class="pe-none opacity-50" @endif>
                                @csrf
                                @include('partials.input',['additionalInfo'=>"Headers phone,name,custom_field_name_1,custom_field_name_2. Avoid duplicate headers such as name and Name.",'class'=>'col-md-4','name'=>"CSV file",'id'=>'csv','type'=>'file','placeholder'=>"",'required'=>true,'accept'=>".csv"])
                                @include('partials.select',['class'=>'col-md-4','name'=>"Group to insert into",'id'=>'group','placeholder'=>"",'required'=>false,'data'=>$groups])
                            <div class="form-group">
                                    <button type="submit" id="contacts-import-submit" class="btn btn-success ml-3 mt-2" @if($hasActiveImport) disabled @endif>
                                        <span class="contacts-import-submit-default">{{ __('Import contact')}}</span>
                                        <span class="contacts-import-submit-loading d-none">
                                            <span class="spinner-border spinner-border-sm mr-2" role="status" aria-hidden="true"></span>
                                            {{ __('Importing contacts...') }}
                                        </span>
                                    </button>
                                    <a href="{{ route('contacts.import.history') }}" class="btn btn-outline-primary mt-2">{{ __('Import history') }}</a>
                                </div>
                                
                            </form>
                        </div>
                    <div class="col-12">
                        @include('partials.flash')
                    </div>

                    @include('contacts::contacts.partials.import-active-banner', [
                        'activeImports' => $activeImports,
                        'recentImports' => $recentImports,
                    ])

                   
                       
                   
                    
     
         


                </div>
            </div>
        </div>

        @include('layouts.footers.auth')
    </div>

    <div id="contacts-import-overlay" class="contacts-import-overlay d-none" aria-live="polite" aria-busy="true">
        <div class="contacts-import-overlay-content">
            <div class="spinner-border text-success mb-3" role="status" style="width: 3rem; height: 3rem;">
                <span class="sr-only">{{ __('Loading') }}...</span>
            </div>
            <h4 class="mb-2">{{ __('Queuing contact import') }}</h4>
            <p class="text-muted mb-0">{{ __('Your file is being uploaded and queued. You will be redirected to the import status page.') }}</p>
        </div>
    </div>
@endsection

@section('js')
    <style>
        .contacts-import-overlay {
            position: fixed;
            inset: 0;
            z-index: 2000;
            display: flex;
            align-items: center;
            justify-content: center;
            background: rgba(255, 255, 255, 0.92);
        }

        .contacts-import-overlay-content {
            max-width: 28rem;
            padding: 2rem;
            text-align: center;
        }
    </style>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            var form = document.getElementById('contacts-import-form');
            var overlay = document.getElementById('contacts-import-overlay');
            var submitButton = document.getElementById('contacts-import-submit');
            var defaultLabel = submitButton.querySelector('.contacts-import-submit-default');
            var loadingLabel = submitButton.querySelector('.contacts-import-submit-loading');

            var isSubmitting = false;

            form.addEventListener('submit', function (event) {
                if (!form.checkValidity()) {
                    return;
                }

                if (isSubmitting) {
                    event.preventDefault();
                    return;
                }

                isSubmitting = true;
                overlay.classList.remove('d-none');
                defaultLabel.classList.add('d-none');
                loadingLabel.classList.remove('d-none');
                submitButton.classList.add('disabled');
                submitButton.setAttribute('aria-disabled', 'true');
            });
        });
    </script>
    @stack('js')
@endsection
