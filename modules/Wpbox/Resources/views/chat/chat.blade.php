<div class="theChatHolder card border rounded-0">
    <div class="card-header wpbox-chat-header" id="theChatHeader">
        <div class="d-flex justify-content-between align-items-center flex-wrap w-100">
            <div v-cloak class="wpbox-chat-header__main d-flex align-items-center flex-grow-1" style="gap:1rem; min-width: 0;">
                <button @click="showConversations" v-cloak v-if="mobileChat" class="btn btn-icon" type="button" aria-label="{{ __('Back to conversations') }}">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="currentColor" class="w-6 h-6" style="width: 20px; height:20px; color: var(--primary, #128c7e);">
                        <path fill-rule="evenodd" d="M9.53 2.47a.75.75 0 010 1.06L4.81 8.25H15a6.75 6.75 0 010 13.5h-3a.75.75 0 010-1.5h3a5.25 5.25 0 100-10.5H4.81l4.72 4.72a.75.75 0 11-1.06 1.06l-6-6a.75.75 0 010-1.06l6-6a.75.75 0 011.06 0z" clip-rule="evenodd" />
                    </svg>
                </button>

                <a :href="'/contacts/contacts/'+activeChat.id+'/edit'" class="profile-picture-container position-relative">
                    <div v-cloak v-if="activeChat&&activeChat.name&&activeChat.name[0]&&(activeChat.avatar==''||activeChat.avatar==null)"
                        class="avatar avatar-content bg-gradient-success wpbox-avatar" style="min-width:48px; height:48px; display:flex; align-items:center; justify-content:center;">@{{ activeChat.name[0] }}
                    </div>
                    <img v-cloak v-if="activeChat&&(activeChat.avatar!=''&&activeChat.avatar!=null)" alt="" :src="activeChat.avatar"
                        :data-src="activeChat.avatar" class="avatar" />

                    <template v-if="activeChat && activeChat.country && activeChat.country.iso2">
                        <span id="userCountry" :class="'fi-'+activeChat.country.iso2.toLowerCase()" class="fi fis flag-icon"></span>
                        <b-tooltip target="userCountry">@{{ activeChat.country.name }}</b-tooltip>
                    </template>
                </a>
                <div class="d-flex flex-column" style="min-width: 0;">
                    <a class="d-flex align-items-center text-decoration-none" :href="'/contacts/contacts/'+activeChat.id+'/edit'">
                        <h3 class="mb-0 d-block text-truncate">@{{ activeChat.name }}</h3>
                    </a>
                    <span class="text-xs wpbox-chat-header__phone" v-if="activeChat.phone">@{{ activeChat.phone }}</span>
                    <span class="text-xs wpbox-chat-header__phone" v-else-if="activeChat.channel && activeChat.channel !== 'whatsapp'">
                        @{{ channelLabel(activeChat.channel) }}
                        <template v-if="activeChat.comment_reply"> · {{ __('Comment') }}</template>
                    </span>
                    <a v-if="activeChat.comment_reply && activeChat.comment_reply.permalink"
                       :href="activeChat.comment_reply.permalink"
                       target="_blank"
                       class="text-xs text-primary">{{ __('View post') }}</a>
                </div>
            </div>

            @include('wpbox::chat.actions')
        </div>
        <div v-cloak v-if="activeChat && activeChat.name" class="w-100">
            <span class="wpbox-template-notice badge badge-pill d-block" :class="(getReplyNotification(activeChat)).class">
                <template v-if="(getReplyNotification(activeChat)).class === 'badge-danger'">⚠️ </template>
                @{{ (getReplyNotification(activeChat)).text }}
            </span>
        </div>
    </div>
    <div class="card-body overflow-auto overflow-x-hidden scrollable-div " ref="scrollableDiv" id="chatMessages" >
        @include('wpbox::chat.message')
    </div>
    @include('wpbox::chat.tools')
</div>
