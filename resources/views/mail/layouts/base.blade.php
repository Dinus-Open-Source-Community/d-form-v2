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
    <style>
        @media only screen and (max-width: 620px) {
            table[width="600"] {
                width: 100% !important;
            }

            td[style*="padding:30px 20px;"] {
                padding: 20px 10px !important;
            }

            td[style*="padding:0 40px 30px 40px;"] {
                padding: 0 10px 20px 10px !important;
            }

            .responsive-img {
                width: 80px !important;
                height: auto !important;
            }

            .responsive-box {
                padding: 12px !important;
                font-size: 16px !important;
            }
        }
    </style>
</head>
<body style="margin:0;padding:0;width:100%;-webkit-text-size-adjust:100%;-ms-text-size-adjust:100%;background-color:#f4f4f4;">
    <div style="display:none;max-height:0;overflow:hidden;mso-hide:all;" aria-hidden="true">@yield('mail_preheader')</div>
    <table role="presentation" border="0" cellspacing="0" cellpadding="0" width="100%" style="border-collapse:collapse;mso-table-lspace:0pt;mso-table-rspace:0pt;background-color:#178ADF;background-image:url('https://i.imgur.com/r76QizC.png');background-size:cover;background-repeat:no-repeat;">
        <tbody>
            <tr>
                <td height="40" style="font-size:0;line-height:0;mso-line-height-rule:exactly;">&nbsp;</td>
            </tr>
            <tr>
                <td align="center">
                    <!--[if mso]>
                    <table role="presentation" border="0" cellspacing="0" cellpadding="0" width="600" style="border-collapse:collapse;mso-table-lspace:0pt;mso-table-rspace:0pt;">
                    <tr><td>
                    <![endif]-->
                    <table role="presentation" border="0" cellspacing="0" cellpadding="0" width="600" style="border-collapse:collapse;mso-table-lspace:0pt;mso-table-rspace:0pt;max-width:600px;background-color:#ffffff;border-radius:6px;overflow:hidden;font-family:Arial,sans-serif;">
                        <tbody>
                            <tr>
                                <td style="padding:30px 20px;" align="center">
                                    <img class="responsive-img" src="https://i.imgur.com/foOg2oz.png" alt="DOSCOM" style="display:block;margin-bottom:10px;" width="120">
                                    <table role="presentation" style="margin:20px auto;" width="80%" border="0" cellpadding="0" cellspacing="0">
                                        <tr>
                                            <td style="border-top:1px solid #ddd;font-size:0;line-height:0;">&nbsp;</td>
                                        </tr>
                                    </table>
                                </td>
                            </tr>
                            @yield('mail_card')
                            <tr>
                                <td style="background-color:#f4f4f4;padding:15px 20px;text-align:center;font-size:12px;color:#666;">
                                    Apabila kamu mendapati pertanyaan silahkan menghubungi
                                    <a href="mailto:doscom.go@gmail.com" style="color:#0066cc;text-decoration:none;">doscom.go@gmail.com</a>
                                    atau hubungi CP kami di nomor <b>0895396188006 ( Faiz )</b>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                    <!--[if mso]>
                    </td></tr>
                    </table>
                    <![endif]-->
                </td>
            </tr>
            <tr>
                <td height="40" style="font-size:0;line-height:0;mso-line-height-rule:exactly;">&nbsp;</td>
            </tr>
        </tbody>
    </table>
</body>
</html>
