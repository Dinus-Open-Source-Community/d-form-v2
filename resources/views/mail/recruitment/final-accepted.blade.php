<p style="margin:0 0 16px;font-size:16px;line-height:1.65;color:#374151;"><strong style="color:#111827;">Halo {{ $applicant_name ?? '' }},</strong></p>
<p style="margin:0 0 20px;font-size:16px;line-height:1.65;color:#374151;">Selamat! Kamu diterima sebagai {{ $membership_type ?? '' }} di divisi {{ $final_division ?? '' }}.</p>
<table role="presentation" border="0" width="100%" cellspacing="0" cellpadding="0" style="border-collapse:collapse;mso-table-lspace:0pt;mso-table-rspace:0pt;background-color:#f9fafb;border:1px solid #e5e7eb;border-radius:12px;margin:0 0 20px;"><tr><td style="padding:18px 20px;">
<p style="margin:0 0 10px;font-size:13px;font-weight:700;letter-spacing:0.06em;text-transform:uppercase;color:#6b7280;">Hasil akhir</p>
<table role="presentation" border="0" width="100%" cellspacing="0" cellpadding="0" style="border-collapse:collapse;mso-table-lspace:0pt;mso-table-rspace:0pt;font-size:15px;line-height:1.55;color:#374151;">
<tr><td style="padding:8px 0;border-top:1px solid #e5e7eb;color:#6b7280;width:38%;">Status</td><td style="padding:8px 0;border-top:1px solid #e5e7eb;font-weight:600;color:#111827;">Diterima</td></tr>
@if(!empty($final_division))
<tr><td style="padding:8px 0;border-top:1px solid #e5e7eb;color:#6b7280;">Divisi</td><td style="padding:8px 0;border-top:1px solid #e5e7eb;font-weight:600;color:#111827;">{{ $final_division }}</td></tr>
@endif
@if(!empty($membership_type))
<tr><td style="padding:8px 0;border-top:1px solid #e5e7eb;color:#6b7280;">Tipe</td><td style="padding:8px 0;border-top:1px solid #e5e7eb;color:#374151;">{{ $membership_type }}</td></tr>
@endif
@if(!empty($whatsapp_group_url))
<tr><td style="padding:8px 0;border-top:1px solid #e5e7eb;color:#6b7280;">Grup WA</td><td style="padding:8px 0;border-top:1px solid #e5e7eb;color:#374151;word-break:break-all;overflow-wrap:break-word;"><a href="{{ $whatsapp_group_url }}" style="color:#16a34a;font-weight:600;word-break:break-all;">{{ $whatsapp_group_url }}</a></td></tr>
@endif
</table>
</td></tr></table>
@if(!empty($public_message))
<table role="presentation" border="0" width="100%" cellspacing="0" cellpadding="0" style="border-collapse:collapse;mso-table-lspace:0pt;mso-table-rspace:0pt;background-color:#f9fafb;border:1px solid #e5e7eb;border-radius:12px;margin:0 0 20px;"><tr><td style="padding:18px 20px;">
<p style="margin:0 0 10px;font-size:13px;font-weight:700;letter-spacing:0.06em;text-transform:uppercase;color:#6b7280;">Catatan dari tim</p>
<p style="margin:0;font-size:15px;line-height:1.6;color:#374151;overflow-wrap:break-word;">{!! nl2br(e($public_message)) !!}</p>
</td></tr></table>
@endif
@if(!empty($whatsapp_group_url) && !empty($tracking_url))
<table role="presentation" border="0" cellspacing="0" cellpadding="0" align="center" style="border-collapse:collapse;mso-table-lspace:0pt;mso-table-rspace:0pt;margin:0 auto 12px;"><tr><td style="border-radius:10px;background-color:#25d366;"><a href="{{ $whatsapp_group_url }}" style="display:inline-block;padding:14px 32px;font-size:15px;font-weight:600;color:#ffffff;text-decoration:none;border-radius:10px;">Gabung Grup WA</a></td></tr></table>
<table role="presentation" border="0" cellspacing="0" cellpadding="0" align="center" style="border-collapse:collapse;mso-table-lspace:0pt;mso-table-rspace:0pt;margin:0 auto 8px;"><tr><td style="border-radius:10px;background-color:#178ADF;"><a href="{{ $tracking_url }}" style="display:inline-block;padding:14px 32px;font-size:15px;font-weight:600;color:#ffffff;text-decoration:none;border-radius:10px;">Pantau progress pendaftaran</a></td></tr></table>
<p style="margin:8px 0 0;font-size:13px;line-height:1.6;color:#6b7280;text-align:center;">Jika tombol tidak berfungsi, salin URL ini ke browser:<br><span style="word-break:break-all;color:#374151;">{{ $tracking_url }}</span></p>
@elseif(!empty($whatsapp_group_url))
<table role="presentation" border="0" cellspacing="0" cellpadding="0" align="center" style="border-collapse:collapse;mso-table-lspace:0pt;mso-table-rspace:0pt;margin:0 auto 8px;"><tr><td style="border-radius:10px;background-color:#25d366;"><a href="{{ $whatsapp_group_url }}" style="display:inline-block;padding:14px 32px;font-size:15px;font-weight:600;color:#ffffff;text-decoration:none;border-radius:10px;">Gabung Grup WA</a></td></tr></table>
<p style="margin:8px 0 0;font-size:13px;line-height:1.6;color:#6b7280;text-align:center;">Jika tombol tidak berfungsi, salin URL ini ke browser:<br><span style="word-break:break-all;color:#374151;">{{ $whatsapp_group_url }}</span></p>
@endif
<p style="margin:0;font-size:14px;line-height:1.6;color:#4b5563;">Informasi orientasi dan langkah berikutnya akan kami kirim lewat email berikutnya.</p>
