<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#1e1410">
    <title>@yield('title', 'Koffiebon')</title>
    <style>
        :root {
            --espresso: #1e1410;
            --cream: #f5ece1;
            --caramel: #c5772a;
            --caramel-dark: #a8631f;
            --bean: #3a2a20;
            --muted: #8a7565;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: -apple-system, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            background: var(--cream);
            color: var(--espresso);
            line-height: 1.5;
        }
        a { color: inherit; }
        .wrap { max-width: 880px; margin: 0 auto; padding: 0 20px; }
        header.site {
            background: var(--espresso);
            color: var(--cream);
        }
        header.site .wrap {
            display: flex; align-items: center; justify-content: space-between;
            padding-top: 18px; padding-bottom: 18px;
        }
        .brand { display: flex; align-items: center; gap: 12px; font-weight: 800; font-size: 20px; }
        .brand .cup {
            width: 40px; height: 40px; border-radius: 12px; background: #000;
            display: grid; place-items: center; font-size: 20px;
        }
        nav.site a {
            text-decoration: none; margin-left: 18px; font-weight: 600; opacity: .9;
        }
        nav.site a:hover { opacity: 1; }
        .btn {
            display: inline-flex; align-items: center; gap: 8px; justify-content: center;
            background: var(--caramel); color: #fff; text-decoration: none;
            padding: 14px 26px; border-radius: 12px; font-weight: 700; border: 0; cursor: pointer;
        }
        .btn:hover { background: var(--caramel-dark); }
        .btn.ghost { background: transparent; color: var(--bean); border: 1px solid rgba(0,0,0,.12); }
        .card { background: #fff; border-radius: 18px; box-shadow: 0 1px 3px rgba(0,0,0,.06); }
        .muted { color: var(--muted); }
        footer.site { padding: 40px 0; text-align: center; color: var(--muted); font-size: 14px; }
        h1, h2 { line-height: 1.15; }
    </style>
</head>
<body>
    <header class="site">
        <div class="wrap">
            <a class="brand" href="{{ route('site.home') }}" style="text-decoration:none;">
                <span class="cup">☕</span>
                <span>{{ $merchant?->name ?? 'Koffiebon' }}</span>
            </a>
            <nav class="site">
                <a href="{{ route('site.menu') }}">Menukaart</a>
                <a href="{{ $frontendUrl }}">Koffiebon</a>
            </nav>
        </div>
    </header>

    @yield('content')

    <footer class="site">
        <div class="wrap">
            <p>{{ $merchant?->name ?? 'Koffiebon' }} · prepaid koffie, één kop per scan.</p>
            <p><a href="{{ $frontendUrl }}/balie">Balie-login</a></p>
        </div>
    </footer>
</body>
</html>
