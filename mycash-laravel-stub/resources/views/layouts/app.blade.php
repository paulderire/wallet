<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>MY CASH</title>
</head>
<body>
    <header>
        <nav>
            <a href="{{ route('dashboard') }}">Dashboard</a>
            <form method="POST" action="{{ route('logout') }}" style="display:inline">@csrf<button>Logout</button></form>
        </nav>
    </header>
    <main>
        @yield('content')
    </main>
</body>
</html>
