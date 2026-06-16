<script>
    "use strict";

    window.addEventListener('load', function () {
        if (!window.chatList) {
            return;
        }

        chatList.updateProperty('journeys', []);
        chatList.updateProperty('journeyActivities', []);
        chatList.updateProperty('isLoadingJournies', false);

        function getJourneyData(contactId) {
            if (!contactId) {
                chatList.updateProperty('journeys', []);
                chatList.updateProperty('journeyActivities', []);
                chatList.updateProperty('isLoadingJournies', false);
                return;
            }

            chatList.updateProperty('isLoadingJournies', true);

            axios.get('/api/journies/contact/' + contactId).then(function (response) {
                chatList.updateProperty('journeys', response.data.journeys || []);
                chatList.updateProperty('journeyActivities', response.data.activities || []);
                chatList.updateProperty('isLoadingJournies', false);
            }).catch(function () {
                chatList.updateProperty('journeys', []);
                chatList.updateProperty('journeyActivities', []);
                chatList.updateProperty('isLoadingJournies', false);
            });
        }

        function moveContact(stageId, fireCampaign) {
            if (!chatList.activeChat || !chatList.activeChat.id || chatList.dynamicProperties.isLoadingJournies) {
                return;
            }

            chatList.updateProperty('isLoadingJournies', true);

            axios.post('/api/journies/move-contact', {
                stage_id: stageId,
                contact_id: chatList.activeChat.id,
                fire_campaign: fireCampaign
            }).then(function (response) {
                getJourneyData(chatList.activeChat.id);
            }).catch(function () {
                chatList.updateProperty('isLoadingJournies', false);
            });
        }

        chatList.promptMoveContact = function (journey, stage) {
            if (!stage || stage.contact_in) {
                return;
            }

            var needsConfirm = journey.confirm_before_send && stage.campaign_name;
            var message = needsConfirm
                ? '{{ __('Move to') }} "' + stage.name + '" {{ __('and send') }} "' + stage.campaign_name + '"?'
                : '{{ __('Move contact to') }} "' + stage.name + '"?';

            if (!window.confirm(message)) {
                return;
            }

            moveContact(stage.id, needsConfirm || !!stage.campaign_id);
        };

        chatList.removeFromJourney = function (journeyId) {
            if (!chatList.activeChat || !chatList.activeChat.id) {
                return;
            }

            if (!window.confirm('{{ __('Remove this contact from the journey?') }}')) {
                return;
            }

            axios.post('/journies/' + journeyId + '/remove-contact/' + chatList.activeChat.id)
                .then(function () {
                    getJourneyData(chatList.activeChat.id);
                })
                .catch(function () {
                    chatList.updateProperty('isLoadingJournies', false);
                });
        };

        chatList.$watch('activeChat', function (newVal, oldVal) {
            if (newVal !== oldVal) {
                getJourneyData(newVal && newVal.id ? newVal.id : null);
            }
        });
    });
</script>
