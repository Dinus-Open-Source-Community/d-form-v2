@extends('mail.layouts.base')

@section('mail_title')
    @if($receiptKind === 'answer_collection')
        {{ __('Jawabanmu sudah kami terima: :form', ['form' => $form->title]) }}
    @elseif($receiptKind === 'feedback')
        {{ __('Terima kasih atas penilaianmu: :form', ['form' => $form->title]) }}
    @else
        {{ __('Kartu absen kelas: :class', ['class' => $form->title]) }}
    @endif
@endsection

@section('mail_card')
    @include('mail.partials.card-header', [
        'eyebrow' => $receiptKind === 'd_kelas' ? __('Kartu absen') : ($receiptKind === 'feedback' ? __('Feedback') : __('Pengumpulan jawaban')),
        'accent' => '#178ADF',
        'headline' => $form->title,
    ])
    <tr>
        <td style="padding:24px 32px 0;">
            <p style="margin:0 0 18px;font-size:16px;line-height:1.65;color:#374151;">
                <strong style="color:#111827;">{{ __('Halo') }} {{ $recipientName }},</strong>
            </p>
            @if($receiptKind === 'answer_collection')
                <p style="margin:0 0 18px;font-size:16px;line-height:1.65;color:#374151;">{{ __('Jawabanmu untuk :form sudah tersimpan.', ['form' => $form->title]) }}</p>
                <p style="margin:0;font-size:16px;line-height:1.65;color:#374151;">{{ __('Ringkasan isian ada di bawah. Kamu tidak perlu membawa kode QR. Email ini adalah bukti bahwa isianmu sudah masuk.') }}</p>
            @elseif($receiptKind === 'feedback')
                <p style="margin:0 0 18px;font-size:16px;line-height:1.65;color:#374151;">{{ __('Terima kasih sudah mengisi :form.', ['form' => $form->title]) }}</p>
                <p style="margin:0;font-size:16px;line-height:1.65;color:#374151;">{{ __('Masukanmu kami pakai untuk memperbaiki acara berikutnya. Tidak ada yang perlu kamu lakukan lagi.') }}</p>
            @else
                <p style="margin:0 0 18px;font-size:16px;line-height:1.65;color:#374151;">{{ __('Kamu terdaftar di :class.', ['class' => $form->title]) }}</p>
                <p style="margin:0;font-size:16px;line-height:1.65;color:#374151;">{{ __('Kode QR di bawah adalah kartu absenmu untuk seluruh pertemuan. Scan dilakukan panitia saat kelas dimulai, dan hanya berlaku satu kali di setiap tanggal: :dates.', ['dates' => implode(', ', $sessionDates)]) }}</p>
            @endif
        </td>
    </tr>
    @if(count($answersSummary) > 0)
        <tr>
            <td style="padding:24px 32px;">
                <p style="margin:0 0 14px;font-size:13px;font-weight:700;letter-spacing:0.06em;text-transform:uppercase;color:#6b7280;">{{ __('Ringkasan isian') }}</p>
                <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="border-collapse:collapse;font-size:15px;line-height:1.55;">
                    @foreach($answersSummary as $label => $display)
                        <tr><td style="padding:10px 0;border-top:1px solid #f3f4f6;color:#6b7280;width:39%;">{{ $label }}</td><td style="padding:10px 0;border-top:1px solid #f3f4f6;color:#374151;">{{ $display }}</td></tr>
                    @endforeach
                </table>
            </td>
        </tr>
    @endif
    @if($receiptKind === 'd_kelas' && $showAttendanceQr)
        <tr><td style="padding:0 32px 28px;text-align:center;"><img src="cid:qr-code.png" alt="{{ __('Kode QR absensi') }}" width="240" height="240" style="display:block;margin:0 auto;border:1px solid #e5e7eb;border-radius:12px;background-color:#ffffff;"></td></tr>
    @endif
@endsection
