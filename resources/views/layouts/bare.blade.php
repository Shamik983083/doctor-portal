<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'MEDAXIS')</title>
    <style>
        {{--
            Bare shell: the preview stylesheet, no sidebar or topbar. Used to
            render the Review and approve form inside a modal iframe over the grid
            (Devin msg 2292: make it a pop so providers don't change screens).
        --}}
        @include('layouts.partials.preview-css')
        body { background: transparent; }
        .bare-wrap { padding: 22px; max-width: 1100px; margin: 0 auto; }
    </style>
</head>
<body>
    <div class="bare-wrap">
        @yield('view')
    </div>
    @yield('scripts')
</body>
</html>
