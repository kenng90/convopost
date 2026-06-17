@php
    $healthUser = auth()->user();
    $healthCompany = $healthUser?->currentCompany();
    $orgAuth = app(\App\Services\OrgAuthorization::class);
    $showHealthAlerts = $healthCompany && $orgAuth->canViewDashboardMetrics($healthUser);
    $healthAlertRoutes = [];
    if ($showHealthAlerts) {
        foreach (['whatsapp.setup', 'templates.index', 'campaigns.index'] as $routeName) {
            if ($orgAuth->canAccessRoute($healthUser, $routeName)) {
                $healthAlertRoutes[$routeName] = route($routeName);
            }
        }
    }
@endphp
@if($showHealthAlerts)
<div class="row mt-3" id="health-alerts-container"></div>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const container = document.getElementById('health-alerts-container');
    if (!container) return;
    fetch(@json(route('health-alerts.index')), { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } })
        .then(r => r.json())
        .then(data => {
            if (!data.alerts || !data.alerts.length) return;
            const routes = @json($healthAlertRoutes);
            let html = '<div class="col-12"><div class="alert alert-warning" role="alert"><strong>{{ __("System health") }}</strong><ul class="mb-0 mt-2">';
            data.alerts.forEach(a => {
                html += '<li><strong>' + a.title + '</strong>: ' + a.message;
                if (a.action_route && routes[a.action_route]) {
                    html += ' <a href="' + routes[a.action_route] + '">{{ __("Fix") }}</a>';
                }
                html += '</li>';
            });
            html += '</ul></div></div>';
            container.innerHTML = html;
        })
        .catch(() => {});
});
</script>
@endif
