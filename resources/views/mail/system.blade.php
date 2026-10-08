<!doctype html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
</head>
<body style="margin: 0; padding: 24px 12px; background: #f1f5f9; font-family: Inter, Arial, sans-serif; color: #1e293b;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width: 600px; background: #ffffff; border-radius: 8px; overflow: hidden;">
                    <tr>
                        <td style="background: {{ settings()->color('primary') }}; padding: 18px 28px; border-bottom: 4px solid {{ settings()->color('accent') }};">
                            @if ($logo = settings()->get('appearance.logo'))
                                <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($logo) }}" alt="{{ settings()->siteName() }}" style="max-height: 40px;">
                            @else
                                <span style="color: #ffffff; font-size: 18px; font-weight: 700;">{{ settings()->siteName() }}</span>
                            @endif
                        </td>
                    </tr>
                    <tr>
                        <td style="padding: 28px; font-size: 15px; line-height: 1.6;">
                            <style>
                                a { color: {{ settings()->color('accent') }}; }
                                p { margin: 0 0 14px; }
                            </style>
                            {!! $body !!}
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
