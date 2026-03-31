<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Catalog Not Found</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container d-flex align-items-center justify-content-center" style="min-height: 100vh;">
        <div class="text-center">
            <h1 class="display-1">404</h1>
            <p class="fs-3"><span class="text-danger">Oops!</span> Catalog not found.</p>
            <p class="lead">{{ $message ?? 'The catalog you are looking for does not exist.' }}</p>
            <a href="/" class="btn btn-primary">Go Back Home</a>
        </div>
    </div>
</body>
</html>
