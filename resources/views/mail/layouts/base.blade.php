<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="color-scheme" content="light">
    <meta name="supported-color-schemes" content="light">
    <title>@yield('mail_title')</title>
</head>
<body style="margin:0;padding:0;-webkit-text-size-adjust:100%;-ms-text-size-adjust:100%;background-color:#f3f4f6;font-family:'Segoe UI',system-ui,-apple-system,BlinkMacSystemFont,Roboto,'Helvetica Neue',Arial,sans-serif;font-size:16px;line-height:1.65;color:#374151;">
    <table role="presentation" cellspacing="0" cellpadding="0" width="100%" style="border-collapse:collapse;background-color:#f3f4f6;padding:32px 16px;">
        <tbody>
            <tr>
                <td align="center">
                    <table role="presentation" cellspacing="0" cellpadding="0" width="100%" style="border-collapse:collapse;max-width:600px;background-color:#ffffff;border-radius:16px;border:1px solid #e5e7eb;">
                        <tbody>
                            @yield('mail_card')
                        </tbody>
                    </table>
                </td>
            </tr>
        </tbody>
    </table>
</body>
</html>
