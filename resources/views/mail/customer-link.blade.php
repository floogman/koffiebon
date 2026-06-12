<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Koffiebon</title>
</head>
<body style="margin:0;padding:0;background:#f5ece1;font-family:-apple-system,Segoe UI,Roboto,Helvetica,Arial,sans-serif;color:#1e1410;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f5ece1;padding:32px 0;">
        <tr>
            <td align="center">
                <table role="presentation" width="480" cellpadding="0" cellspacing="0" style="background:#ffffff;border-radius:16px;overflow:hidden;">
                    <tr>
                        <td style="background:#1e1410;padding:24px 32px;color:#f5ece1;font-size:22px;font-weight:700;">
                            ☕ Koffiebon
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:32px;">
                            @if ($isRecovery)
                                <h1 style="margin:0 0 12px;font-size:20px;">Je kaarten herstellen</h1>
                                <p style="margin:0 0 24px;line-height:1.5;color:#3a2a20;">
                                    Klik op de knop om je Koffiebon-kaarten op dit toestel terug te laden.
                                    Je saldo staat veilig op de server.
                                </p>
                            @else
                                <h1 style="margin:0 0 12px;font-size:20px;">Bevestig je e-mailadres</h1>
                                <p style="margin:0 0 24px;line-height:1.5;color:#3a2a20;">
                                    Welkom! Bevestig je e-mailadres om je Koffiebon te kunnen gebruiken.
                                </p>
                            @endif

                            <a href="{{ $url }}" style="display:inline-block;background:#c5772a;color:#ffffff;text-decoration:none;padding:14px 28px;border-radius:10px;font-weight:700;">
                                {{ $isRecovery ? 'Kaarten herstellen' : 'E-mailadres bevestigen' }}
                            </a>

                            <p style="margin:24px 0 0;font-size:13px;color:#8a7565;line-height:1.5;">
                                Werkt de knop niet? Kopieer deze link:<br>
                                <span style="word-break:break-all;color:#c5772a;">{{ $url }}</span>
                            </p>
                        </td>
                    </tr>
                </table>
                <p style="margin:16px 0 0;font-size:12px;color:#8a7565;">Deze link is beperkt geldig.</p>
            </td>
        </tr>
    </table>
</body>
</html>
