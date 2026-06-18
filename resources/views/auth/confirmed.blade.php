<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="theme-color" content="#1e1410">
    <title>Koffiebon — ingelogd</title>
    <style>
        :root { color-scheme: light; }
        body {
            margin: 0; min-height: 100vh; display: flex; align-items: center; justify-content: center;
            background: #f5ece1; color: #1e1410;
            font-family: -apple-system, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            padding: 24px;
        }
        .card {
            background: #fff; border-radius: 16px; max-width: 380px; width: 100%;
            padding: 40px 32px; text-align: center; box-shadow: 0 12px 40px rgba(30, 20, 16, .12);
        }
        .emoji { font-size: 56px; line-height: 1; }
        h1 { font-size: 22px; margin: 20px 0 8px; }
        p { margin: 0; line-height: 1.6; color: #6b5648; font-size: 15px; }
        .hint { margin-top: 20px; font-size: 13px; color: #8a7565; }
    </style>
</head>
<body>
    <div class="card">
        @if ($status === 'already')
            <div class="emoji">✅</div>
            <h1>Al ingelogd</h1>
            <p>Deze login is al gebruikt. Ga terug naar de Koffiebon-app — je bent ingelogd.</p>
        @else
            <div class="emoji">☕️</div>
            <h1>Gelukt — je bent ingelogd</h1>
            <p>Ga terug naar de Koffiebon-app waar je dit startte. Die logt zichzelf nu in;
               je hoeft hier niets meer te doen.</p>
        @endif
        <p class="hint">Je kunt dit tabblad sluiten.</p>
    </div>
</body>
</html>
