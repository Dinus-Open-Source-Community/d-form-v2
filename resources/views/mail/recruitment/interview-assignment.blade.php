<p style="margin:0 0 16px;font-size:16px;line-height:1.65;color:#374151;"><strong style="color:#111827;">Halo {{ $interviewer_name ?? '' }},</strong></p>
<p style="margin:0 0 20px;font-size:16px;line-height:1.65;color:#374151;">Kamu ditugaskan sebagai interviewer untuk <strong style="color:#111827;">{{ $applicant_name ?? '' }}</strong> ({{ $registration_number ?? '' }}).</p>
<table role="presentation" border="0" width="100%" cellspacing="0" cellpadding="0" style="border-collapse:collapse;mso-table-lspace:0pt;mso-table-rspace:0pt;background-color:#f9fafb;border:1px solid #e5e7eb;border-radius:12px;margin:0 0 20px;"><tr><td style="padding:18px 20px;">
<p style="margin:0 0 10px;font-size:13px;font-weight:700;letter-spacing:0.06em;text-transform:uppercase;color:#6b7280;">Penugasan interview</p>
<table role="presentation" border="0" width="100%" cellspacing="0" cellpadding="0" style="border-collapse:collapse;mso-table-lspace:0pt;mso-table-rspace:0pt;font-size:15px;line-height:1.55;color:#374151;">
@if(!empty($applicant_name))
<tr><td style="padding:8px 0;border-top:1px solid #e5e7eb;color:#6b7280;width:38%;">Applicant</td><td style="padding:8px 0;border-top:1px solid #e5e7eb;font-weight:600;color:#111827;">{{ $applicant_name }}</td></tr>
@endif
@if(!empty($registration_number))
<tr><td style="padding:8px 0;border-top:1px solid #e5e7eb;color:#6b7280;">Nomor Pendaftaran</td><td style="padding:8px 0;border-top:1px solid #e5e7eb;font-weight:600;color:#111827;">{{ $registration_number }}</td></tr>
@endif
@if(!empty($interview_date))
<tr><td style="padding:8px 0;border-top:1px solid #e5e7eb;color:#6b7280;">Tanggal</td><td style="padding:8px 0;border-top:1px solid #e5e7eb;font-weight:600;color:#111827;">{{ $interview_date }}</td></tr>
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
@if(!empty($application_admin_url))
<table role="presentation" border="0" cellspacing="0" cellpadding="0" align="center" style="border-collapse:collapse;mso-table-lspace:0pt;mso-table-rspace:0pt;margin:0 auto 8px;"><tr><td style="border-radius:10px;background-color:#178ADF;"><a href="{{ $application_admin_url }}" style="display:inline-block;padding:14px 32px;font-size:15px;font-weight:600;color:#ffffff;text-decoration:none;border-radius:10px;">Lihat applicant</a></td></tr></table>
<p style="margin:8px 0 0;font-size:13px;line-height:1.6;color:#6b7280;text-align:center;">Jika tombol tidak berfungsi, salin URL ini ke browser:<br><span style="word-break:break-all;color:#374151;">{{ $application_admin_url }}</span></p>
@endif
