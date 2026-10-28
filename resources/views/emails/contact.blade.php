<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>{{ $contactSubject }}</title>
</head>
<body style="margin:0;padding:24px;background:#f4f2ee;font-family:Arial,Helvetica,sans-serif;color:#1a1a2e;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:600px;margin:0 auto;background:#ffffff;border:1px solid #e5e0d5;">
        <tr>
            <td style="padding:20px 24px;background:#07092e;color:#e8c97a;font-size:18px;font-family:Georgia,serif;">
                Nouveau message depuis le site
            </td>
        </tr>
        <tr>
            <td style="padding:24px;">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="font-size:14px;line-height:1.5;">
                    <tr>
                        <td style="padding:6px 0;width:110px;color:#777;vertical-align:top;">De</td>
                        <td style="padding:6px 0;"><a href="mailto:{{ $senderEmail }}" style="color:#07092e;">{{ $senderEmail }}</a></td>
                    </tr>
                    <tr>
                        <td style="padding:6px 0;color:#777;vertical-align:top;">Objet</td>
                        <td style="padding:6px 0;font-weight:bold;">{{ $contactSubject }}</td>
                    </tr>
                    <tr>
                        <td style="padding:6px 0;color:#777;vertical-align:top;">Reçu le</td>
                        <td style="padding:6px 0;">{{ now()->locale('fr')->isoFormat('dddd D MMMM YYYY [à] HH:mm') }}</td>
                    </tr>
                    <tr>
                        <td style="padding:6px 0;color:#777;vertical-align:top;">Langue</td>
                        <td style="padding:6px 0;">{{ $language === 'en' ? 'Anglais' : 'Français' }}</td>
                    </tr>
                </table>

                <div style="margin-top:20px;padding:16px;background:#f8f6f1;border-left:3px solid #c9a84c;font-size:15px;line-height:1.6;white-space:pre-wrap;">{{ $contactMessage }}</div>

                <p style="margin:24px 0 0;font-size:13px;color:#777;">
                    Répondez simplement à cet e-mail : la réponse partira vers {{ $senderEmail }}.
                </p>
            </td>
        </tr>
    </table>
</body>
</html>
