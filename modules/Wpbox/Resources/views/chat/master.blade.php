@extends('layouts.app', ['title' => __('Chat'), 'hideActions'=>true ])

@section('head')
    <link type="text/css" href="{{ asset('custom') }}/css/chat-inbox.css?id={{ config('version.version') }}" rel="stylesheet">
@endsection

@section('content')

<div class="col-12">
    @include('partials.flash')
</div>
@includeIf('whatsappcall::chat.incoming-call')
<div class="header"></div>
<div class="wpbox-inbox-v2" id="chatList">

        <div v-if="conversationsShown">
            @include('wpbox::chat.conversations')
        </div>
        <div v-if="contacts.length === 0" >
            <div class="d-flex" >
                @include('wpbox::chat.relayout.empty_conversations')
            </div>
        </div>

        <div class="wpbox-inbox-chat-column h-100">

        <div class="h-100 d-flex" v-cloak id="chatAndTools" v-if="activeChat&&activeChat.name  && contacts.length != 0">
            <div class="h-100 flex-grow-1" :class="currentSideApp === null ? '' : ''" id="chatAndToolsContent" >
                @include('wpbox::chat.chat')

            </div>
            <div  class="hide-onmobiles" id="sideApps"  >
                @include('wpbox::chat.sideapps')
            </div>
        </div>

        </div>
</div>
@include('wpbox::chat.scripts')
@includeIf('whatsappcall::chat.call_brief_scripts')
@foreach($sidebarModules as $module)
    @include($module['script'])
@endforeach

@include('wpbox::chat.onesignal')
<script src="{{ asset('vendor/emoji/emojiPicker.js') }}"></script>
@endsection
