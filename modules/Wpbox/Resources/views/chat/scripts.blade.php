<script>
    "use strict";
    var chatList=null;
    var lastmessagetime="none";
    var chatMessages={};
    var chatMessageCacheOrder=[];
    var chatMessageCacheLimit=10;
    var pusherConn = null;
    var channel = null;
    var channelUpdate=null;
    var pusherActiveChat=null;
    var companyID="<?php echo auth()->user()->getCurrentCompany()->id; ?>";
    var serverTimezone = "<?php echo config('app.timezone'); ?>";
    var pusherAvailable=false;
    var searchQuery="";
    var searchDebounceTimer=null;

    var momentDayFormatter=function(date){
        return moment.tz(date, serverTimezone).format('dddd, D MMM, YYYY');
    };

    var formatMessageHtml=function(message){
        if(!message){ return ''; }
        const linkRegex = /https?:\/\/[^\s/$.?#].[^\s]*/g;
        var replacedText = String(message).replace(linkRegex, '<a href="$&" class="text-bold">$&</a>');
        return replacedText.replace(/\n/g, '<br>');
    };

    var prepareMessageForDisplay=function(message){
        message._formatted = formatMessageHtml(message.value || '');
        message._originalFormatted = message.original_message && message.original_message.length > 0
            ? formatMessageHtml('{{ __('Original:')}}' + ' ' + message.original_message)
            : '';
        try {
            message._buttons = JSON.parse(message.buttons || '[]');
        } catch (e) {
            message._buttons = [];
        }
        message._day = momentDayFormatter(message.created_at);
        return message;
    };

    var prepareMessagesForDisplay=function(messages){
        return messages.map(prepareMessageForDisplay);
    };

    var touchChatMessageCache=function(contactId){
        chatMessageCacheOrder = chatMessageCacheOrder.filter(function(id){ return id !== contactId; });
        chatMessageCacheOrder.unshift(contactId);
        while(chatMessageCacheOrder.length > chatMessageCacheLimit){
            var evictId = chatMessageCacheOrder.pop();
            delete chatMessages[evictId];
        }
    };
    var initPusher=function(){
        if (typeof Pusher !== 'undefined') {
            // The variable is defined
            // You can safely use it here
            Pusher.logToConsole = false;

            pusherConn = new Pusher(PUSHER_APP_KEY, {
                cluster: PUSHER_APP_CLUSTER
            });
            pusherAvailable=true;

            channelUpdate = pusherConn.subscribe('chatupdate.'+companyID);
            channelUpdate.bind('general', chatListUpdate);

            try{
                const wcChan = pusherConn.subscribe('whatsappcall.'+companyID);
                wcChan.bind('incoming', function(call){
                    try{
                        if(window.wpIncomingCall){ window.wpIncomingCall(call); }
                    }catch(err){ console.error('UIC handler error', err); }
                });
                wcChan.bind('ended', function(call){
                    try{
                        if(window.wpCallEnded){ window.wpCallEnded(call); }
                    }catch(err){ console.error('UIC ended handler error', err); }
                });
                wcChan.bind('claimed', function(call){
                    try{
                        if(window.wpCallClaimed){ window.wpCallClaimed(call); }
                    }catch(err){ console.error('UIC claimed handler error', err); }
                });
            }catch(err){ console.warn('Failed to subscribe to whatsappcall channel', err); }

            

        } else {
            // Pusher
            js.notify("Error: Pusher is not defined. Chat will not load new messages. Please check documentation","danger");
        }
    }


    var connectToChannel=function(chatID){
        if(pusherActiveChat!=chatID && pusherAvailable){
            if(channel!=null){
                //Change chat, release old one
                channel.unsubscribe();
                channel.unbind('general', receivedMessageInPusher);
            }
            //Set active chat
            pusherActiveChat=chatID;

            //Bind to new chat (scoped by organisation)
            channel = pusherConn.subscribe('chat.'+companyID+'.'+chatID);
            channel.bind('general', receivedMessageInPusher);

            

        }else{
            //Same chat, no changes
        }
    }

    var receivedMessageInPusher=function(data){
        if (!chatList.activeChat || data.contact.id !== chatList.activeChat.id) {
            return;
        }

        const index = chatList.contacts.findIndex(item => item.id === data.contact.id);
        if (index === -1) {
            return;
        }

        if (!chatMessages[data.contact.id]) {
            chatMessages[data.contact.id] = [];
        }

        chatMessages[data.contact.id].push(prepareMessageForDisplay(data.message));
      
        //Update the last message
        chatList.contacts[index].last_message = data.message.value;

        //Scroll to bottom
        setTimeout(() => {
            if($('#chatMessages')[0]&&$('#chatMessages')[0].scrollHeight){
                $('#chatMessages').scrollTop($('#chatMessages')[0].scrollHeight); 
            }
            
        }, 1000);
        
        
        
    }

    var getChatListFilter=function(){
        if(!chatList){
            return 'open';
        }

        switch(chatList.tab){
            case 'mine':
                return 'mine';
            case 'new':
                return 'new';
            case 'resolved':
                return 'resolved';
            default:
                return 'open';
        }
    };

    var mergeContactsIntoList=function(incoming){
        incoming.forEach(function(incomingContact){
            var idx=chatList.all.findIndex(function(contact){ return contact.id===incomingContact.id; });
            if(idx!==-1){
                Object.assign(chatList.all[idx], incomingContact);
            }else{
                chatList.all.unshift(incomingContact);
            }
        });

        chatList.all.sort(function(a, b){
            return new Date(b.last_reply_at)-new Date(a.last_reply_at);
        });
    };

    var buildContactFromPusher=function(data){
        return {
            id: data.contact_id || data.contact,
            name: data.name || '',
            last_message: data.last_message || '',
            last_reply_at: data.last_reply_at,
            is_last_message_by_contact: data.is_last_message_by_contact ? 1 : 0,
            resolved_chat: data.resolved_chat ? 1 : 0,
        };
    };

    var applyChatListResponse=function(response, options){
        options=options||{};
        var incremental=options.incremental===true;
        var playSoundOnUpdate=options.playSoundOnUpdate!==false;

        if(!response.data.status){
            return;
        }

        var initialChatLoad=chatList.all.length===0;
        var incomingContacts=response.data.data || [];

        if(incremental && incomingContacts.length>0){
            mergeContactsIntoList(incomingContacts);
        }else if(!incremental){
            chatList.all=incomingContacts;
        }

        chatList.numberOfPages=response.data.numberOfPages;
        chatList.myMessagesCount=response.data.myChatsCount;
        chatList.totalMessagesCount=response.data.totalChats;
        chatList.newMessagesCount=response.data.newMessagesCount;

        chatList.filterContacts();

        if(incomingContacts.length>0){
            var newestTimestamp=incremental
                ? incomingContacts[0].last_reply_at
                : (chatList.all[0] ? chatList.all[0].last_reply_at : null);

            if(newestTimestamp){
                lastmessagetime=newestTimestamp;
            }

            if(!initialChatLoad && playSoundOnUpdate){
                playSound();
            }
        }

        openPendingInitialContact();
    };

    var chatListUpdate=function(data){
        var contactId=data.contact_id || data.contact;
        if(!contactId || !chatList){ return; }

        var allIndex=chatList.all.findIndex(function(item){ return item.id===contactId; });

        if(allIndex!==-1){
            var contact=chatList.all[allIndex];
            if(data.last_message!==undefined){ contact.last_message=data.last_message; }
            if(data.last_reply_at!==undefined){ contact.last_reply_at=data.last_reply_at; }
            if(data.is_last_message_by_contact!==undefined){
                contact.is_last_message_by_contact=data.is_last_message_by_contact ? 1 : 0;
            }
            if(data.resolved_chat!==undefined){
                contact.resolved_chat=data.resolved_chat ? 1 : 0;
            }
            if(data.name!==undefined && data.name){
                contact.name=data.name;
            }

            chatList.all.splice(allIndex, 1);
            chatList.all.unshift(contact);
            chatList.filterContacts();

            if(contactId!==chatList.activeChat.id){
                if(!chatList.stopPlaySound){ playSound(); }
            }

            return;
        }

        if(data.last_reply_at){
            if(data.is_last_message_by_contact && chatList.tab==='resolved'){
                chatList.tab='all';
            }

            mergeContactsIntoList([buildContactFromPusher(data)]);
            chatList.filterContacts();

            if(contactId!==chatList.activeChat.id){
                if(!chatList.stopPlaySound){ playSound(); }
            }
        }

        if(contactId!==chatList.activeChat.id){
            getChatsJS(1, chatList.searchQuery, { incremental: false, playSoundOnUpdate: false });
        }
    }

    


    

    var getChatJS=function(contact_id, before_id){
        if(chatMessages[contact_id]){
            chatList.messages=chatMessages[contact_id];
        }

        var url = '/api/wpbox/chat/'+contact_id;
        if(before_id){
            url += '?before_id=' + before_id;
        }

        axios.get(url).then(function (response) {
            var messages=response.data.data;
            messages=prepareMessagesForDisplay(messages.reverse());

            if(before_id && chatMessages[contact_id]){
                chatMessages[contact_id] = messages.concat(chatMessages[contact_id]);
            } else {
                chatMessages[contact_id]=messages;
            }

            touchChatMessageCache(contact_id);
            chatList.messages=chatMessages[contact_id];
            chatList.hasMoreMessages = !!response.data.has_more;

            const index = chatList.contacts.findIndex(item => item.id == contact_id);
            const allIndex = chatList.all.findIndex(item => item.id == contact_id);
            if (index !== -1) {
                chatList.contacts[index].is_last_message_by_contact=0;
            }
            if (allIndex !== -1) {
                chatList.all[allIndex].is_last_message_by_contact=0;
                chatList.filterContacts();
            }
        }).catch(function (error) {
        });

        connectToChannel(contact_id);
    }

    var getChatsJS=function(page=1,search_query="",options={}){
        var incremental=options.incremental===true;
        var cursor=incremental ? lastmessagetime : 'none';
        var params={ filter: getChatListFilter() };
        if (chatList && chatList.channelFilter && chatList.channelFilter !== 'all') {
            params.channel = chatList.channelFilter;
        }

        axios.get('/api/wpbox/chats/'+cursor+'/'+page+'/'+search_query, { params: params }).then(function (response) {
            applyChatListResponse(response, options);
        }).catch(function (error) {
        });
    }

    function openPendingInitialContact() {
        if (!window.pendingInitialContactId) {
            return;
        }

        const contactId = parseInt(window.pendingInitialContactId, 10);
        window.pendingInitialContactId = null;

        if (!contactId || !chatList) {
            return;
        }

        chatList.setCurrentChat(contactId);
    }

    function playSound() {
        if(!chatList.stopPlaySound){
            var audio = new Audio('/vendor/meta/pling.mp3');
            audio.play();
        }
        chatList.stopPlaySound=false;
    }

    function escapeSingleQuotesInJSON(jsonString) {
        // Use a regular expression to find and replace single quotes inside string values
        const escapedJSONString = jsonString.replace(/"([^"]*?)":\s*"([^"]*?)"/g, function(match, key, value) {
            const escapedValue = value.replace(/'/g, "\\'");
            return `"${key}": "${escapedValue}"`;
        });

        return escapedJSONString;
    }

    

    window.pendingInitialContactId = @json($initialContactId ?? null);

    window.onload = function () {
        //VUE Chat list — initialize first so sideapp scripts can use chatList on load
        Vue.config.devtools=true;

        chatList = new Vue({
        el: '#chatList',
        data: {
            templates: @json($templates),
            replies: @json($replies),
            users: @json($users),
            languages: @json($languages),
            currentUserID: "{{auth()->user()->id}}",
            contacts: [],
            all:[],
            stopPlaySound:false,
            numberOfPages:1,
            page:1,
            activeChat:{},
            activeChatGroups:{},
            activeChatCustomFields:{},
            latestFormSubmission: null,
            messages:[],
            activeMessage:"",
            copilotSuggestions:[],
            activeNote:"",
            selectedImage: null,
            selectedFile: null,
            filterText: '',
            filterTemplates: '',
            mobileChat:window.innerWidth<768,
            conversationsShown:true,
            tab:"all",
            chatTab:"reply",
            fetcherModules: @json($fetcherModules),
            selectedFetcher:null,
            filterFetcher:"",
            isRefreshingLinks:false,
            currentSideApp: null,
            currentSideAppName: null,
            searchQuery:"",
            newMessagesCount: 0,
            myMessagesCount: 0,
            totalMessagesCount: 0,
            hasMoreMessages: false,
            loadingOlderMessages: false,
            dynamicProperties: {}, // Placeholder object
            enabledChannels: @json($enabledChannels ?? [['value' => 'all', 'label' => 'All channels']]),
            channelFilter: @json($channelFilter ?? 'all'),
        },
        mounted() {
            var self = this;
            this.$nextTick(function(){
                var el = self.$refs.scrollableDiv;
                if(!el){ return; }
                el.addEventListener('scroll', function(){
                    if(el.scrollTop < 80 && self.hasMoreMessages && !self.loadingOlderMessages && self.activeChat && self.activeChat.id){
                        self.loadOlderMessages();
                    }
                });
            });
        },
        errorCaptured(err, component, info) {
            console.error('An error occurred:', err);
            console.error('Component in which error occurred:', component);
            console.error('Additional information:', info);
            // Keep the inbox interactive even if a child panel throws
            // (e.g. missing country on Messenger/Instagram contacts).
            return false;
        },
       computed: {
            filteredReplies() {
                const filterText = this.filterText.toLowerCase();
                return this.replies.filter(item => item.name.toLowerCase().includes(filterText));
            },
            filteredTemplates() {
                const filterTemplates = this.filterTemplates.toLowerCase();
                return this.templates.filter(item => item.name.toLowerCase().includes(filterTemplates));
            },
            filteredFetcherData(){
                const filterFetcher = this.filterFetcher.toLowerCase();
                return this.fetcherModules[this.selectedFetcher].data.filter(item => item.title.toLowerCase().includes(filterFetcher));
            }
        },
        watch: {
            page(newVal, oldVal) {
                if (newVal !== oldVal) {
                    this.stopPlaySound=true;
                    this.searchQuery="";
                    getChatsJS(newVal, "", { incremental: false, playSoundOnUpdate: false });
                }
            },
            searchQuery(newVal, oldVal) {
                if (newVal !== oldVal) {
                    this.stopPlaySound=true;
                    var self = this;
                    clearTimeout(searchDebounceTimer);
                    searchDebounceTimer = setTimeout(function(){
                        self.page = 1;
                        getChatsJS(1, newVal, { incremental: false, playSoundOnUpdate: false });
                    }, 400);
                }
            }
        },
        methods: {
            async requestWaCallPermission(){
            },
            marked(text) {
                if (!text) return '';
            
                return text
                        // Headers
                        .replace(/^### (.*$)/gm, '<h3>$1</h3>')
                        .replace(/^## (.*$)/gm, '<h2>$1</h2>')
                        .replace(/^# (.*$)/gm, '<h1>$1</h1>')
                        // Bold
                        .replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>')
                        // Italic
                        .replace(/\*(.*?)\*/g, '<em>$1</em>')
                        // Links
                        .replace(/\[([^\]]+)\]\(([^)]+)\)/g, '<a href="$2">$1</a>')
                        // Lists
                        .replace(/^\s*\-\s(.*)/gm, '<li>$1</li>')
                        // Line breaks
                        .replace(/\n/g, '<br>');
            },
            addProperty(property, value) {
                this.$set(this.dynamicProperties, property, value);
            },
            updateProperty(property, value) {
                this.$set(this.dynamicProperties, property, value);
            },
            getProperty(property) {
                return this.dynamicProperties[property];
            },
            switchChatTab(tab){
                this.chatTab=tab;
            },
            setChannelFilter(value) {
                this.channelFilter = value;
                this.page = 1;
                // Keep the open chat visible even if it belongs to another channel.
                getChatsJS(1, this.searchQuery, { incremental: false, playSoundOnUpdate: false });
            },
            channelLabel(channel) {
                const labels = {
                    whatsapp: '{{ __('WhatsApp') }}',
                    instagram: '{{ __('Instagram') }}',
                    messenger: '{{ __('Messenger') }}',
                };
                return labels[channel] || channel;
            },
            channelBadgeClass(channel) {
                const classes = {
                    instagram: 'badge-danger',
                    messenger: 'badge-primary',
                    whatsapp: 'badge-success',
                };
                return classes[channel] || 'badge-secondary';
            },
            mineMessages:function(){
                this.tab="mine";
                this.page=1;
                getChatsJS(1, this.searchQuery, { incremental: false, playSoundOnUpdate: false });
            },
            allMessages:function(){
                this.tab="all";
                this.page=1;
                getChatsJS(1, this.searchQuery, { incremental: false, playSoundOnUpdate: false });
            },
            newMessages:function(){
                this.tab="new";
                this.page=1;
                getChatsJS(1, this.searchQuery, { incremental: false, playSoundOnUpdate: false });
            },
            resolvedMessages:function(){
                this.tab="resolved";
                this.page=1;
                getChatsJS(1, this.searchQuery, { incremental: false, playSoundOnUpdate: false });
            },
            filterContacts() {
                this.contacts=this.all.slice();

                if(this.activeChat && this.activeChat.id){
                    const index = this.contacts.findIndex(item => item.id == this.activeChat.id);
                    if (index !== -1) {
                        this.$set(this.contacts[index], 'isActive', true);
                    }
                }
            },
            formatIt: function(message){
                
                const linkRegex = /https?:\/\/[^\s/$.?#].[^\s]*/g;

                // Replace links with placeholders for rendering
                var replacedText = message.replace(linkRegex, '<a href="$&" class="text-bold">$&</a>');

                //Replace \n with <br>
                replacedText = replacedText.replace(/\n/g, '<br>');

                return replacedText;
            
            },
            getAssignedUser: function(contact){
                if(contact.user_id){
                    const user = Object.keys(this.users).find(user => user == contact.user_id);
                    return this.users[user] ? this.users[user] : '-';
                }
                return 'Not assigned';
            },
            translationLanguage(contact){
                return contact.language &&contact.language!="none"  ? contact.language : "{{ __('No translation')}}";
            },
            setLanguage: function(lang, contact){
                axios.post('/api/wpbox/setlanguage/'+contact.id, {language: lang}).then(function (response) {
                    if(response.data.status){
                        contact.language=lang;
                    }else{
                        js.notify(response.data.errMsg,"danger");
                    }
                }).catch(function (error) {
                    console.log(error);
                });
            },
            assignUser: function(user_id, contact_id){
                axios.post('/api/wpbox/assign/'+contact_id, {user_id: user_id}).then(function (response) {
                    if(response.data.status){
                        chatList.activeChat.user_id=user_id;
                        const indexUpdate = chatList.all.findIndex(item => item.id == contact_id);
                        console.log(indexUpdate);
                        if (indexUpdate !== -1) {
                            chatList.all[indexUpdate].user_id = user_id;
                        }
                        getChatsJS(chatList.page, chatList.searchQuery, { incremental: false, playSoundOnUpdate: false });
                    }else{  
                        js.notify(response.data.errMsg,"danger");
                    }
                }).catch(function (error) {
                    console.log(error);
                });
            },
            getReplyNotification(contact){
                if(!contact || !contact.last_client_reply_at){
                    if(contact && contact.channel && contact.channel !== 'whatsapp'){
                        return {
                            "class":"badge-warning",
                            "text":"{{ __('Reply within the 24-hour messaging window')}}"
                        };
                    }

                    return {
                        "class":"badge-danger",
                        "text":"{{ __('You can reply only with template')}}!"
                    };
                }

                var timeSinceLastClientReply= moment.tz(contact.last_client_reply_at,serverTimezone).add(24, 'hours');
                const minutesDifference = timeSinceLastClientReply.diff(moment.now(), 'minutes');
                var statusOfReply={
                    "class":"badge-danger",
                    "text": contact.channel && contact.channel !== 'whatsapp'
                        ? "{{ __('Messaging window expired')}}"
                        : "{{ __('You can reply only with template')}}!"
                };
                if(minutesDifference>0){
                    if(minutesDifference>60){
                        statusOfReply.class="badge-success";
                        statusOfReply.text=moment.duration(minutesDifference, 'minutes').humanize();
                    }else{
                        statusOfReply.class="badge-warning";
                        statusOfReply.text=moment.duration(minutesDifference, 'minutes').humanize();
                    }
                    statusOfReply.text+=" {{ __('left to reply')}}";
                }
                return statusOfReply;
            },
            setCurrentChat: function (contact_id) {


                if(this.mobileChat){
                    this.conversationsShown=false;
                }

                contact_id = parseInt(contact_id, 10);
                if(!contact_id){
                    return;
                }
                
                getChatJS(contact_id);

                console.log("Remove previous active chat");
                const indexRemove = this.all.findIndex(item => item.id == this.activeChat.id);
                console.log(indexRemove);
                if (indexRemove !== -1 && this.all[indexRemove]) {
                    // Make sure the object exists before modifying it
                    if (this.all[indexRemove].name) {
                        this.all[indexRemove].name = this.all[indexRemove].name + " ";
                    }
                    this.$set(this.all[indexRemove], 'isActive', false);
                }
                
                console.log("Set new active chat");
                const index = this.all.findIndex(item => item.id == contact_id);
                console.log(index);
                if (index !== -1 && this.all[index]) {
                    console.log("Set new active chat for index "+index);
                    console.log(this.all[index]);
                    if (this.all[index].name) {
                        this.all[index].name = this.all[index].name+" ";
                    } else {
                        this.all[index].name = this.channelLabel(this.all[index].channel || 'messenger');
                    }
                    this.$set(this.all[index], 'isActive', true);
                    this.activeChat = this.all[index];
                    this.filterContacts();
                    console.log("Active chat set to "+index);
                    console.log(this.all[index].name);

                    // Fetch contact's groups
                    console.log("Fetch contact's groups");
                    axios.get('/api/wpbox/contact-groups-and-custom-fields/' + contact_id)
                        .then(response => {
                            this.activeChatGroups = response.data.groups;
                            this.activeChatCustomFields = response.data.customFields;
                            this.latestFormSubmission = response.data.latestFormSubmission || null;
                        })
                        .catch(error => {
                            console.error('Error fetching contact groups:', error);
                            this.activeChatGroups = {};
                            this.activeChatCustomFields = {};
                            this.latestFormSubmission = null;
                        });
                }

                setTimeout(() => {
                    this.scrollToBottomOfChat();
                }, 1000);
               
               



                
            },
            getChats:function (){
                getChatsJS(this.page, this.searchQuery, { incremental: false, playSoundOnUpdate: false });
            },
            momentIt: function (date) {
                return moment.tz(date,serverTimezone).fromNow();
            },
            momentHM: function (date) {
                return moment.tz(date,serverTimezone).format('HH:mm');;
            },
            momentDay:function (date) {
                return moment.tz(date,serverTimezone).format('dddd, D MMM, YYYY');
            },
            momentDaySimple:function (date) {
                return moment.tz(date,serverTimezone).format('D MMM, YYYY');
            },
            scrollToBottomOfChat() {
                const scrollableDiv = this.$refs.scrollableDiv;
                if( scrollableDiv && scrollableDiv.scrollHeight){
                    scrollableDiv.scrollTop = scrollableDiv.scrollHeight;
                   
                }
            },
            loadOlderMessages() {
                if(!this.activeChat || !this.activeChat.id || !this.hasMoreMessages || this.loadingOlderMessages){
                    return;
                }
                var oldest = this.messages.length > 0 ? this.messages[0] : null;
                if(!oldest || !oldest.id){
                    return;
                }
                this.loadingOlderMessages = true;
                var self = this;
                getChatJS(this.activeChat.id, oldest.id);
                setTimeout(function(){ self.loadingOlderMessages = false; }, 800);
            },
            parseJSON:function(jsonString){
                if(jsonString==null||jsonString==""){
                    return [];
                }
                return JSON.parse(jsonString);
            },
            setMessage(message){
                this.$bvModal.hide('modal-replies');    
                message=message.replace("\{\{name\}\}",this.activeChat.name);   
                message=message.replace("\{\{phone\}\}",this.activeChat.phone);   
                this.activeMessage=message;
            },
            setVueMessage(message){
                this.activeMessage=this.activeMessage+message;
            },
            sendLinkMessage(link){
                console.log(link);
                this.activeMessage=link;

                //Close the modal
                this.$bvModal.hide('modal-link-fetcher');

                //On the next tick
                this.$nextTick(() => {
                    this.sendChatMessage();

                    //Clear the filter
                    this.filterFetcher="";
                });
            },
            toggleSideApp(appName,appTitle) {
                if (this.currentSideApp === appName) {
                    this.closeSideApp();
                } else {
                    this.currentSideApp = appName;
                    this.currentSideAppName = appTitle;

                    var chatAndTools = document.querySelector('#chatAndTools');
                    var sideApps = document.querySelector('#sideApps');
                    var sideBarButtons = document.querySelector('#sideBarButtons');

                    if (chatAndTools) {
                        chatAndTools.classList.add('transition');
                    }
                    if (sideApps) {
                        sideApps.classList.add('transition');
                    }
                    if (sideBarButtons) {
                        sideBarButtons.classList.add('rounded-0');
                    }

                    setTimeout(function () {
                        if (chatAndTools) {
                            chatAndTools.classList.remove('transition');
                        }
                        if (sideApps) {
                            sideApps.classList.remove('transition');
                        }
                    }, 50);
                }
            },
            closeSideApp() {
                this.currentSideApp = null;

                var dropdownBtn = document.querySelector('#dropdown-right__BV_button_');
                if (dropdownBtn) {
                    dropdownBtn.classList.remove('d-none');
                }

                var sideBarButtons = document.querySelector('#sideBarButtons');
                if (sideBarButtons) {
                    sideBarButtons.classList.remove('rounded-0');
                }
            },
            capitalize(value) {
                if (!value) return '';
                value = value.toString();
                return value.charAt(0).toUpperCase() + value.slice(1);
            },
            refreshLinkData(alias){
                console.log(alias+" --> Load new data");
                this.isRefreshingLinks=true;
                //Reload the data, by making a AJAX call to /alias/getData/1
                axios.get('/'+alias+'/getData/1').then(function (response) {
                    //Set the data to the fetcherModules[alias].data
                    chatList.fetcherModules[alias].data=response.data;
                    chatList.isRefreshingLinks=false;
                });
            },
            sendChatMessage(){
                var message=this.activeMessage;
                this.activeMessage="";
                axios.post('/api/wpbox/send/'+chatList.activeChat.id, {message: message}).then(function (response) {
                    
                    if(response.data.status){
                        lastmessagetime=response.data.messagetime;

                    }else{
                        js.notify(response.data.errMsg,"danger");
                    }}).catch(function (error) {
                
                    });
                    
            },
            loadCopilotSuggestions(){
                if(!this.activeChat || !this.activeChat.id){ return; }
                var draft = encodeURIComponent(this.activeMessage || '');
                axios.get('/api/wpbox/copilot/'+this.activeChat.id+'/suggest?draft='+draft).then((response) => {
                    this.copilotSuggestions = response.data.suggestions || [];
                    if(response.data.tone_variants && response.data.tone_variants.friendly){
                        this.copilotSuggestions.push({
                            text: response.data.tone_variants.friendly,
                            source: '{{ __("Friendly tone") }}'
                        });
                    }
                }).catch(function(){});
            },
            sendNote(){
                var note=this.activeNote;
                this.activeNote = "";
                axios.post('/api/wpbox/sendnote/'+chatList.activeChat.id, {note: note}).then(function (response) {
                    if(response.data.status){
                        
                        this.$nextTick(() => {
                            this.$refs.noteTextarea.value = "";
                        });
                    }else{
                        js.notify(response.data.errMsg,"danger");
                    }
                }).catch(function (error) {
                    console.log(error);
                });
            },
            showConversations(){
                const indexRemove = this.contacts.findIndex(item => item.id === this.activeChat.id);
                if (indexRemove !== -1) {
                    this.contacts[indexRemove].name = this.contacts[indexRemove].name+" ";
                    this.contacts[indexRemove].isActive = false;
                }   
                this.activeChat={};
                this.conversationsShown=true;
            },
            openImageSelector() {
                // Trigger the file input click event
                this.$refs.imageInput.click();
            },
            openFileSelector() {
                // Trigger the file input click event
                this.$refs.fileInput.click();
            },
            handleImageChange(event) {
                // Get the selected image file
                this.selectedImage = event.target.files[0];

                if (!this.selectedImage) {
                    alert('Please select an image first.');
                    return;
                }else{
                     // Create a FormData object to send the image to the API
                    const formData = new FormData();
                    formData.append('image', this.selectedImage);
                    axios.post('/api/wpbox/sendimage/'+chatList.activeChat.id, formData);
                }
            },
            handleFileChange(event) {
                // Get the selected file
                this.selectedFile = event.target.files[0];

                if (!this.selectedFile) {
                    alert('Please select a file first.');
                    return;
                }else{
                     // Create a FormData object to send the image to the API
                    const formData = new FormData();
                    formData.append('file', this.selectedFile);
                    axios.post('/api/wpbox/sendfile/'+chatList.activeChat.id, formData);
                }
            },
            openLinkFetcher(alias){
                this.selectedFetcher=alias;

                //On next tick
                this.$nextTick(() => {
                    //Open modal
                    this.$bvModal.show('modal-link-fetcher');
                });

            },
            updateAIBotStatus() {
                axios.post('/api/wpbox/updateAIBot', {
                    id: this.activeChat.id,
                    enabled_ai_bot: this.activeChat.enabled_ai_bot ? 1 : 0
                }).then(response => {
                    if (response.data.status === 'success') {
                        js.notify(response.data.message, 'success');
                    } else {
                        js.notify(response.data.message, 'danger');
                    }
                }).catch(error => {
                    console.error('Error updating AI Bot status:', error);
                    js.notify('Error updating AI Bot status', 'danger');
                });
            },

            updateChatStatus(contact_id) {
                axios.post('/api/wpbox/updateChatStatus/' + contact_id)
                    .then(response => {
                        if (response.data.status) {
                            this.activeChat.resolved_chat = 1;
                            js.notify('Chat marked as closed', 'success');
                            getChatsJS(this.page, this.searchQuery, { incremental: false, playSoundOnUpdate: false });
                        } else {
                            js.notify(response.data.message, 'danger');
                        }
                    })
                    .catch(error => {
                        console.error('Error updating chat status:', error);
                        js.notify('Error updating chat status', 'danger');
                    });
            },
            reopenChat(contact_id) {
                axios.post('/api/wpbox/reopenChat/' + contact_id)
                    .then(response => {
                        if (response.data.status) {
                            this.activeChat.resolved_chat = 0;
                            js.notify('Chat reopened successfully', 'success');
                            getChatsJS(this.page, this.searchQuery, { incremental: false, playSoundOnUpdate: false });
                        } else {
                            js.notify(response.data.message, 'danger');
                        }
                    })
                    .catch(error => {
                        console.error('Error reopening chat:', error);
                        js.notify('Error reopening chat', 'danger');
                    });
            },
            isCallBrief(message) {
                return message && (message.is_call_brief === true || message.is_call_brief === 1 || message.is_call_brief === '1');
            },
            callBriefPayload(message) {
                if (!message) return {};
                if (message.call_brief_payload && typeof message.call_brief_payload === 'object') {
                    return message.call_brief_payload;
                }
                if (typeof message.call_brief_payload === 'string') {
                    try { return JSON.parse(message.call_brief_payload); } catch (e) { return {}; }
                }
                return {};
            },
            callBriefTitle(message) {
                var p = this.callBriefPayload(message);
                var parts = [@json(__('AI call'))];
                if (p.duration_seconds) {
                    var m = Math.floor(p.duration_seconds / 60);
                    var s = p.duration_seconds % 60;
                    parts.push(m > 0 ? (m + 'm ' + String(s).padStart(2, '0') + 's') : (s + 's'));
                }
                if (p.handoff_requested) {
                    parts.push(@json(__('handoff requested')));
                }
                return parts.join(' · ');
            },
            callBriefBullets(message) {
                return this.callBriefPayload(message).summary_bullets || [];
            },
            callBriefFields(message) {
                return this.callBriefPayload(message).fields || [];
            },
            callBriefFieldIcon(status) {
                switch (status) {
                    case 'confirmed': return '✓';
                    case 'corrected': return '↻';
                    case 'missing': return '✗';
                    default: return '~';
                }
            },
        },
    });

        initPusher();
        getChatsJS();
        axios.defaults.baseURL = window.location.origin;

        //Emoji picker
        setTimeout(() => {
            new EmojiPicker({
                trigger: [
                    {
                    selector: '#emoji-btn',
                    insertInto: '#message'

                }
            ],
           
            closeButton: true,
            specialButtons: 'green' // #008000, rgba(0, 128, 0);
        });
        }, 1000);
};


</script>

<script src="https://unpkg.com/@dotlottie/player-component@2.7.12/dist/dotlottie-player.mjs" type="module"></script>