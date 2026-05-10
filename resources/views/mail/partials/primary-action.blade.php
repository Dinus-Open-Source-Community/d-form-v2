@php
    /** @var string $url */
    /** @var string $label */
@endphp
<tr>
    <td style="padding:16px 32px 28px;text-align:center;">
        <table role="presentation" cellspacing="0" cellpadding="0" align="center" style="border-collapse:collapse;margin:0 auto;">
            <tr>
                <td style="border-radius:10px;background-color:#4f46e5;">
                    <a href="{{ $url }}" style="display:inline-block;padding:14px 32px;font-size:15px;font-weight:600;color:#ffffff;text-decoration:none;border-radius:10px;">{{ $label }}</a>
                </td>
            </tr>
        </table>
        <p style="margin:18px 0 0;font-size:13px;line-height:1.55;color:#6b7280;">
            {{ __('If the button does not work, paste this URL into your browser:') }}<br>
            <span style="word-break:break-all;color:#374151;font-size:12px;line-height:1.5;">{{ $url }}</span>
        </p>
    </td>
</tr>
