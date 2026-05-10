{{-- eyebrow / accent headline + optional event form line ($formTitle string) --}}
@php
    /** @var string $eyebrow */
    /** @var string $accent */
    /** @var string $headline */
@endphp
<tr>
    <td style="padding:28px 32px 22px;border-bottom:1px solid #f3f4f6;">
        <p style="margin:0;font-size:12px;font-weight:700;letter-spacing:0.09em;text-transform:uppercase;color:{{ $accent }};">{{ $eyebrow }}</p>
        <h1 style="margin:14px 0 0;font-size:24px;font-weight:700;color:#111827;line-height:1.3;">{{ $headline }}</h1>
        @isset($formTitle)
            <p style="margin:14px 0 0;font-size:15px;color:#6b7280;line-height:1.55;">
                {{ __('Form') }}: <strong style="color:#374151;font-weight:600;">{{ $formTitle }}</strong>
            </p>
        @endisset
    </td>
</tr>
