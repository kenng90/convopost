@extends('layouts.app', ['title' => __('Journey')])
@section('js')
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/jkanban@1.3.1/dist/jkanban.min.css">
    <style>
        #myKanban {
            overflow-x: auto;
            padding: 20px 0;
        }

        .success, .info, .warning, .error {
            background: #4b5563; /* Tailwind gray-600 */
            border-radius: 6px
        }

        .custom-button {
            background-color: #4CAF50;
            border: none;
            color: white;
            padding: 7px 15px;
            margin: 10px;
            text-align: center;
            text-decoration: none;
            display: inline-block;
            font-size: 16px;
        }

        .kanban-title-board {
            color: white;
        }

        

        
    </style>
@endsection

@section('content')
<div class="header pb-8 pt-2 pt-md-7">
    <div class="container-fluid">
        <div class="header-body">
            
            <div class="row">
               
                <div class="col">
                    <h1 class="mb-3 mt--3">📋 {{$journey->name}}</h1>
                </div>
                <div class="col-auto">
                    <a href="{{ route('journies.index') }}" class="btn btn-sm btn-neutral">
                         {{ __('Back to Journies') }}
                    </a>
                    <a href="{{ route('stages.create', $journey) }}" class="btn btn-sm btn-primary">
                        <i class="ni ni-fat-add"></i> {{ __('Add new stage') }}
                    </a>
                    <a href="#" class="btn btn-sm btn-success" data-toggle="modal" data-target="#addContactModal">
                        <i class="ni ni-single-02"></i> {{ __('Add Contact') }}
                    </a>
                </div>
            </div>
            
            <div class="row align-items-center pt-2">
           
                <div class="col-12">
                    @include('partials.flash')
                </div>

                <!-- Modal, select contact -->
                <div class="modal fade" id="addContactModal" tabindex="-1" role="dialog" aria-labelledby="addContactModalLabel" aria-hidden="true">
                    <div class="modal-dialog modal-dialog-centered" role="document">
                        <div class="modal-content">
                            <form action="{{ route('journey.add-contact', $journey) }}" method="POST">
                                @csrf
                                <div class="modal-header">
                                    <h5 class="modal-title" id="addContactModalLabel">{{ __('Add Contact to Journey') }}</h5>
                                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                        <span aria-hidden="true">&times;</span>
                                    </button>
                                </div>
                                <div class="modal-body">
                                    <div class="form-group">
                                        <label for="contact_id">{{ __('Select Contact') }}</label>
                                        <select name="contact_id" id="contact_id" class="form-control" required>
                                            <option value="">{{ __('Choose a contact') }}</option>
                                            @foreach($contacts as $contact)
                                                <option value="{{ $contact->id }}">{{ $contact->name }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-dismiss="modal">{{ __('Close') }}</button>
                                    <button type="submit" class="btn btn-primary">{{ __('Add Contact') }}</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <div class="col-12">
                    <div id="myKanban"></div>
                </div>
            </div>

            
        </div>
    </div>
</div>



@include('journies::scripts')
@endsection
