<script>
document.addEventListener('DOMContentLoaded', function () {
    if (!document.getElementById('journeyPipelinesReport')) {
        return;
    }

    new Vue({
        el: '#journeyPipelinesReport',
        data: {
            loading: true,
            stats: { journeys_count: 0, total_contacts: 0, campaign_stats: {} },
        },
        mounted() {
            axios.get('{{ route('api.journies.analytics') }}')
                .then((response) => {
                    this.stats = response.data.data;
                    this.loading = false;
                })
                .catch(() => {
                    this.loading = false;
                });
        },
    });
});
</script>
