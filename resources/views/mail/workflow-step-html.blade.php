<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $mailSubject ?? 'Phoenix' }}</title>
</head>
<body style="margin:0;padding:0;background:#f8fafc;font-family:system-ui,-apple-system,Segoe UI,Roboto,sans-serif;color:#0f172a;">
<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#f8fafc;padding:24px 12px;">
    <tr>
        <td align="center">
            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:560px;background:#ffffff;border:1px solid #e2e8f0;border-radius:12px;overflow:hidden;">
                <tr>
                    <td style="padding:20px 24px;border-bottom:1px solid #e2e8f0;background:#0f172a;color:#f8fafc;">
                        <div style="font-size:13px;letter-spacing:0.04em;text-transform:uppercase;opacity:0.8;">Phoenix</div>
                        <div style="margin-top:4px;font-size:16px;font-weight:600;">
                            Rutina #{{ $routineId }} — {{ $typeName }}
                        </div>
                        <div style="margin-top:4px;font-size:13px;opacity:0.85;">Activo: {{ $assetTag }}</div>
                    </td>
                </tr>
                <tr>
                    <td style="padding:24px;font-size:15px;line-height:1.55;">
                        {!! $body !!}
                    </td>
                </tr>
                <tr>
                    <td style="padding:0 24px 24px;">
                        <a href="{{ $url }}" style="display:inline-block;background:#0284c7;color:#ffffff;text-decoration:none;padding:10px 16px;border-radius:8px;font-size:14px;font-weight:600;">
                            Ver detalle en Phoenix
                        </a>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>
</body>
</html>
