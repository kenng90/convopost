<script src="https://cdn.jsdelivr.net/npm/jkanban@1.3.1/dist/jkanban.min.js"></script>
<script>
    var journey_id = "{{ $journey->id }}";

    //Stages
    var stages = @json($journey->stages);

    console.log(stages);
    document.addEventListener('DOMContentLoaded', function() {
        var KanbanTest = new jKanban({
            element: "#myKanban",
            gutter: "10px", 
            widthBoard: "370px",
            itemHandleOptions:{
                enabled: false,
            },
            dragBoards: false, // Disable board/stage reordering
            click: function(el) {
                console.log("Trigger on all items click!");
            },
            context: function(el, e) {
                console.log("Trigger on all items right-click!");
            },
            dropEl: function(el, target, source, sibling){
                console.log("element dropped")
                console.log(target.parentElement.getAttribute('data-id'));

                //Get the element id
                var elementId = el.dataset.eid;
                console.log(elementId);
                
                //Make a post request to the move contact to stage endpoint
                var url = "/stages/" + target.parentElement.getAttribute('data-id') + "/move-contact/" + elementId;
                console.log(url);
                axios.get(url).then(response => {
                    console.log(response);
                }).catch(error => {
                    console.log(error);
                });
            },
            boards: stages.map(stage => ({
                id: stage.id.toString(),
                title: `${stage.name} <a href="/stages/${stage.id}/edit" class="btn btn-sm btn-outline-neutral float-right" style="opacity: 0.8"><i class="ni ni-ruler-pencil"></i></a>`,
                class: "info",
                item: [
                    ...stage.contacts.map(contact => ({
                        id: contact.id.toString(),
                        title: `<div style="display:flex; align-items:center; justify-content:flex-start; height:100%">
                            ${contact.avatar && contact.avatar != '' ? 
                                `<img src="${contact.avatar}" class="avatar avatar-sm rounded-circle mr-2">` : 
                                `<div class="avatar avatar-sm avatar-content bg-gradient-success rounded-circle mr-2">${contact.name[0]}</div>`
                            } 
                            ${contact.name}
                        </div>`,
                        drag: function(el, source) {
                            console.log("START DRAG: " + el.dataset.eid);
                        },
                        dragend: function(el) {
                            console.log("END DRAG: " + el.dataset.eid);
                        },
                        drop: function(el) {
                            console.log("DROPPED: " + el.dataset.eid);
                        }
                    }))
                ]
            }))
        });
    });
</script>
