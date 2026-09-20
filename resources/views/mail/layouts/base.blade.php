<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="color-scheme" content="light">
    <meta name="supported-color-schemes" content="light">
    <meta name="x-apple-disable-message-reformatting">
    <title>@yield('mail_title')</title>
</head>
<body style="margin:0;padding:0;width:100%;-webkit-text-size-adjust:100%;-ms-text-size-adjust:100%;background-color:#f3f4f6;font-family:'Segoe UI',system-ui,-apple-system,BlinkMacSystemFont,Roboto,'Helvetica Neue',Arial,sans-serif;font-size:16px;line-height:1.65;color:#374151;">
    <div style="display:none;max-height:0;overflow:hidden;mso-hide:all;" aria-hidden="true">@yield('mail_preheader')</div>
    <table role="presentation" border="0" cellspacing="0" cellpadding="0" width="100%" bgcolor="#f3f4f6" style="border-collapse:collapse;mso-table-lspace:0pt;mso-table-rspace:0pt;background-color:#f3f4f6;">
        <tbody>
            <tr>
                <td align="center" style="padding:32px 16px;">
                    <!--[if mso]>
                    <table role="presentation" border="0" cellspacing="0" cellpadding="0" width="600" style="border-collapse:collapse;mso-table-lspace:0pt;mso-table-rspace:0pt;">
                    <tr><td>
                    <![endif]-->
                    <table role="presentation" border="0" cellspacing="0" cellpadding="0" width="100%" bgcolor="#ffffff" style="border-collapse:collapse;mso-table-lspace:0pt;mso-table-rspace:0pt;max-width:600px;background-color:#ffffff;border-radius:16px;border:1px solid #e5e7eb;">
                        <tbody>
                            @yield('mail_card')
                        </tbody>
                    </table>
                    <!--[if mso]>
                    </td></tr>
                    </table>
                    <![endif]-->
                </td>
            </tr>
        </tbody>
    </table>
</body>
</html>
