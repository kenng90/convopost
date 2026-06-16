<script src="https://cdn.jsdelivr.net/npm/jkanban@1.3.1/dist/jkanban.min.js"></script>
<script>
    var journeyId = "{{ $journey->id }}";
    var stages = @json($journey->stages);
    var confirmBeforeSend = @json($confirmBeforeSend);
    var chatBaseUrl = "{{ route('chat.index') }}";

    document.addEventListener('DOMContentLoaded', function () {
        var kanban = new jKanban({
            element: "#myKanban",
            gutter: "10px",
            widthBoard: "370px",
            itemHandleOptions: { enabled: true },
            dragBoards: true,
            boardDragHandler: ".kanban-board-header",
            dropBoard: function (el, target) {
                var boardIds = Array.from(document.querySelectorAll('#myKanban .kanban-board'))
                    .map(function (board) { return board.getAttribute('data-id'); });

                axios.post('/journies/' + journeyId + '/stages/reorder', { stage_ids: boardIds })
                    .catch(function (error) { console.error(error); });
            },
            dropEl: function (el, target) {
                var stageId = target.parentElement.getAttribute('data-id');
                var contactId = el.dataset.eid;

                axios.get('/stages/' + stageId + '/move-contact/' + contactId)
                    .then(function (response) {
                        if (response.data.campaign_queued && response.data.campaign_name) {
                            showKanbanToast('{{ __('Campaign queued') }}: ' + response.data.campaign_name);
                        }
                    })
                    .catch(function (error) {
                        showKanbanToast('{{ __('Failed to move contact') }}', true);
                    });
            },
            boards: stages.map(function (stage) {
                var campaignLabel = stage.campaign ? stage.campaign.name : '{{ __('No automation') }}';

                return {
                    id: stage.id.toString(),
                    title: '<div class="kanban-board-header">' +
                        '<div><strong>' + stage.name + '</strong> <span class="badge badge-light">' + stage.contacts_count + '</span></div>' +
                        '<div class="stage-meta">' + campaignLabel + '</div>' +
                        '<a href="/stages/' + stage.id + '/edit" class="btn btn-sm btn-outline-neutral float-right mt-1"><i class="ni ni-ruler-pencil"></i></a>' +
                        '</div>',
                    class: "info",
                    item: stage.contacts.map(function (contact) {
                        var avatarHtml = contact.avatar
                            ? '<img src="' + contact.avatar + '" class="avatar avatar-sm rounded-circle mr-2">'
                            : '<div class="avatar avatar-sm avatar-content bg-gradient-success rounded-circle mr-2">' + (contact.name ? contact.name[0] : '?') + '</div>';

                        return {
                            id: contact.id.toString(),
                            title: '<a class="contact-card-link" href="' + chatBaseUrl + '?contact=' + contact.id + '">' +
                                '<div style="display:flex;align-items:center;">' + avatarHtml + '<span>' + contact.name + '</span></div></a>'
                        };
                    })
                };
            })
        });

        var searchInput = document.getElementById('contact_search');
        var resultsBox = document.getElementById('contact_search_results');
        var selectedIdInput = document.getElementById('selected_contact_id');
        var selectedLabel = document.getElementById('selected_contact_label');
        var submitBtn = document.getElementById('add_contact_submit');
        var searchTimer = null;

        function renderResults(contacts) {
            resultsBox.innerHTML = '';

            if (!contacts.length) {
                resultsBox.innerHTML = '<div class="list-group-item text-muted">{{ __('No contacts found') }}</div>';
                return;
            }

            contacts.forEach(function (contact) {
                var item = document.createElement('button');
                item.type = 'button';
                item.className = 'list-group-item list-group-item-action';
                item.textContent = contact.name + ' (' + contact.phone + ')';
                item.addEventListener('click', function () {
                    selectedIdInput.value = contact.id;
                    selectedLabel.textContent = '{{ __('Selected') }}: ' + contact.name;
                    submitBtn.disabled = false;
                });
                resultsBox.appendChild(item);
            });
        }

        function searchContacts(query) {
            axios.get('/api/journies/' + journeyId + '/contacts/search', {
                params: { q: query, exclude_existing: true }
            }).then(function (response) {
                renderResults(response.data.data || []);
            });
        }

        searchInput.addEventListener('input', function () {
            clearTimeout(searchTimer);
            searchTimer = setTimeout(function () {
                searchContacts(searchInput.value.trim());
            }, 250);
        });

        submitBtn.addEventListener('click', function () {
            if (!selectedIdInput.value) {
                return;
            }

            axios.post('/journey.add-contact/' + journeyId, {
                contact_id: selectedIdInput.value,
                fire_campaign: true
            }).then(function () {
                window.location.reload();
            }).catch(function () {
                showKanbanToast('{{ __('Failed to add contact') }}', true);
            });
        });

        searchContacts('');
    });

    function showKanbanToast(message, isError) {
        if (typeof toastr !== 'undefined') {
            isError ? toastr.error(message) : toastr.success(message);
        }
    }
</script>
