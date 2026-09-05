<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $view->unit->title }} – Roo</title>
    @vite('resources/css/public-teaching-unit.scss')
</head>
<body>
    @yield('content')
</body>
</html>
