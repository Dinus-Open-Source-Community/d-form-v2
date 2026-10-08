<p style="margin:0 0 16px;font-size:16px;line-height:1.65;color:#374151;"><strong style="color:#111827;">Halo {{ $applicant_name ?? '' }},</strong></p>
<p style="margin:0 0 20px;font-size:16px;line-height:1.65;color:#374151;">Terima kasih — pendaftaran OpenRecruitment DOSCOM kamu sudah kami terima. Tim kami akan meninjau berkasmu dan menghubungimu lagi untuk tahap berikutnya.</p>
<table role="presentation" border="0" width="100%" cellspacing="0" cellpadding="0" style="border-collapse:collapse;mso-table-lspace:0pt;mso-table-rspace:0pt;background-color:#f9fafb;border:1px solid #e5e7eb;border-radius:12px;margin:0 0 20px;"><tr><td style="padding:18px 20px;">
<p style="margin:0 0 10px;font-size:13px;font-weight:700;letter-spacing:0.06em;text-transform:uppercase;color:#6b7280;">Data untuk tracking</p>
<table role="presentation" border="0" width="100%" cellspacing="0" cellpadding="0" style="border-collapse:collapse;mso-table-lspace:0pt;mso-table-rspace:0pt;font-size:15px;line-height:1.55;color:#374151;">
<tr><td style="padding:8px 0;border-top:1px solid #e5e7eb;color:#6b7280;width:38%;">Nomor Pendaftaran</td><td style="padding:8px 0;border-top:1px solid #e5e7eb;font-weight:600;color:#111827;">{{ $registration_number ?? '' }}</td></tr>
<tr><td style="padding:8px 0;border-top:1px solid #e5e7eb;color:#6b7280;">Token Tracking</td><td style="padding:8px 0;border-top:1px solid #e5e7eb;"><span style="font-family:ui-monospace,SFMono-Regular,Menlo,Consolas,monospace;font-size:15px;letter-spacing:0.08em;color:#111827;word-break:break-all;overflow-wrap:break-word;">{{ $tracking_token ?? '' }}</span></td></tr>
</table>
</td></tr></table>
<p style="margin:0 0 8px;font-size:14px;line-height:1.6;color:#4b5563;">Simpan nomor dan token ini baik-baik. Keduanya dipakai untuk masuk ke portal tracking.</p>
@php($portalUrl = $tracking_portal_url ?? $tracking_url ?? '')
@if($portalUrl !== '')
<table role="presentation" border="0" cellspacing="0" cellpadding="0" align="center" style="border-collapse:collapse;mso-table-lspace:0pt;mso-table-rspace:0pt;margin:0 auto 8px;"><tr><td style="border-radius:10px;background-color:#178ADF;"><a href="{{ $portalUrl }}" style="display:inline-block;padding:14px 32px;font-size:15px;font-weight:600;color:#ffffff;text-decoration:none;border-radius:10px;">Lihat progress pendaftaran</a></td></tr></table>
@endif
<p style="margin:8px 0 0;font-size:13px;line-height:1.6;color:#6b7280;">Jika tombol tidak berfungsi, salin URL ini ke browser:<br><span style="word-break:break-all;color:#374151;">{{ $portalUrl }}</span></p>
