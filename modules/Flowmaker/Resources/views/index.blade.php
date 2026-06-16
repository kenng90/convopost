<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Flowmaker</title>
    <meta name="description" content="Flowmaker" />
    <meta name="author" content="Flowmaker" />
    <meta property="og:image" content="/og-image.png" />
    <link rel="stylesheet" href="{{ '/flowmaker/css' }}">

  </head>

  <body>
    <div id="flow"></div>

    <script>
      window.data = JSON.parse(@json($data));

      fetch('/flowmaker/editor-metadata/' + window.data.flow.id, {
        credentials: 'same-origin',
        headers: { 'Accept': 'application/json' },
      })
        .then(function (response) { return response.json(); })
        .then(function (metadata) {
          Object.assign(window.data, metadata);
        })
        .catch(function () {
          window.data.templates = window.data.templates || [];
          window.data.agents = window.data.agents || [];
          window.data.groups = window.data.groups || [];
          window.data.journeys = window.data.journeys || [];
          window.data.planPlugins = window.data.planPlugins || {};
        })
        .finally(function () {
          var script = document.createElement('script');
          script.src = '{{ '/flowmaker/script' }}';
          document.body.appendChild(script);
        });
    </script>

</body>
</html>
