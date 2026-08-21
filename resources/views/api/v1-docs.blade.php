<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name') }} Public API</title>
    <link rel="stylesheet" href="https://unpkg.com/swagger-ui-dist@5.17.14/swagger-ui.css">
    <style>
        body { margin: 0; background: #f7fafc; }
        .api-docs-banner {
            padding: 1.25rem 1.5rem;
            background: #0f172a;
            color: #e2e8f0;
            font-family: ui-sans-serif, system-ui, sans-serif;
        }
        .api-docs-banner h1 { margin: 0 0 .35rem; font-size: 1.25rem; }
        .api-docs-banner p { margin: 0; font-size: .9rem; color: #94a3b8; }
        .api-docs-banner code { color: #86efac; }
    </style>
</head>
<body>
    <div class="api-docs-banner">
        <h1>{{ config('app.name') }} Public API v1</h1>
        <p>Authenticate with <code>Authorization: Bearer</code>. Optional <code>X-Company-Id</code> and <code>Idempotency-Key</code> on writes. OpenAPI: <a href="{{ url('/api/v1/openapi.json') }}" style="color:#86efac;">JSON</a> · <a href="{{ url('/api/v1/openapi.yaml') }}" style="color:#86efac;">YAML</a></p>
    </div>
    <div id="swagger-ui"></div>
    <script src="https://unpkg.com/swagger-ui-dist@5.17.14/swagger-ui-bundle.js"></script>
    <script>
        window.ui = SwaggerUIBundle({
            url: @json($specUrl),
            dom_id: '#swagger-ui',
            deepLinking: true,
            presets: [SwaggerUIBundle.presets.apis],
            layout: 'BaseLayout'
        });
    </script>
</body>
</html>
