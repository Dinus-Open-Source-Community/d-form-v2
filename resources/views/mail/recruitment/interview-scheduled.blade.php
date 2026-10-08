<p style="margin:0 0 16px;font-size:16px;line-height:1.65;color:#374151;"><strong style="color:#111827;">Halo {{ $applicant_name ?? '' }},</strong></p>
<p style="margin:0 0 20px;font-size:16px;line-height:1.65;color:#374151;">Interview kamu dijadwalkan pada <strong style="color:#111827;">{{ $interview_date ?? '' }}</strong> pukul <strong style="color:#111827;">{{ $interview_time ?? '' }}</strong>.</p>
<table role="presentation" border="0" width="100%" cellspacing="0" cellpadding="0" style="border-collapse:collapse;mso-table-lspace:0pt;mso-table-rspace:0pt;background-color:#f9fafb;border:1px solid #e5e7eb;border-radius:12px;margin:0 0 20px;"><tr><td style="padding:18px 20px;">
<p style="margin:0 0 10px;font-size:13px;font-weight:700;letter-spacing:0.06em;text-transform:uppercase;color:#6b7280;">Jadwal interview</p>
<table role="presentation" border="0" width="100%" cellspacing="0" cellpadding="0" style="border-collapse:collapse;mso-table-lspace:0pt;mso-table-rspace:0pt;font-size:15px;line-height:1.55;color:#374151;">
@if(!empty($interview_date))
<tr><td style="padding:8px 0;border-top:1px solid #e5e7eb;color:#6b7280;width:38%;">Tanggal</td><td style="padding:8px 0;border-top:1px solid #e5e7eb;font-weight:600;color:#111827;">{{ $interview_date }}</td></tr>
@endif
@if(!empty($interview_time))
<tr><td style="padding:8px 0;border-top:1px solid #e5e7eb;color:#6b7280;">Jam</td><td style="padding:8px 0;border-top:1px solid #e5e7eb;font-weight:600;color:#111827;">{{ $interview_time }}</td></tr>
@endif
@if(!empty($interview_location))
<tr><td style="padding:8px 0;border-top:1px solid #e5e7eb;color:#6b7280;">Lokasi</td><td style="padding:8px 0;border-top:1px solid #e5e7eb;color:#374151;">{{ $interview_location }}</td></tr>
@endif
@if(!empty($interview_room))
<tr><td style="padding:8px 0;border-top:1px solid #e5e7eb;color:#6b7280;">Ruang</td><td style="padding:8px 0;border-top:1px solid #e5e7eb;color:#374151;">{{ $interview_room }}</td></tr>
@endif
</table>
</td></tr></table>
<p style="margin:0 0 8px;font-size:14px;line-height:1.6;color:#4b5563;">QR code absensi ada di bagian bawah email ini atau di halaman tracking. Tunjukkan ke panitia saat tiba.</p>
@if(!empty($tracking_url))
<table role="presentation" border="0" cellspacing="0" cellpadding="0" align="center" style="border-collapse:collapse;mso-table-lspace:0pt;mso-table-rspace:0pt;margin:16px auto 8px;"><tr><td style="border-radius:10px;background-color:#178ADF;"><a href="{{ $tracking_url }}" style="display:inline-block;padding:14px 32px;font-size:15px;font-weight:600;color:#ffffff;text-decoration:none;border-radius:10px;">Pantau progress pendaftaran</a></td></tr></table>
<p style="margin:8px 0 0;font-size:13px;line-height:1.6;color:#6b7280;text-align:center;">Jika tombol tidak berfungsi, salin URL ini ke browser:<br><span style="word-break:break-all;color:#374151;">{{ $tracking_url }}</span></p>
@endif
