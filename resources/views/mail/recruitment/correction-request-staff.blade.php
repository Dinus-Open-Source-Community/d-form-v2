<p style="margin:0 0 16px;font-size:16px;line-height:1.65;color:#374151;">Applicant <strong style="color:#111827;">{{ $applicant_name ?? '' }}</strong> ({{ $registration_number ?? '' }}) mengajukan permintaan koreksi.</p>
<p style="margin:0 0 16px;font-size:16px;line-height:1.65;color:#374151;overflow-wrap:break-word;"><strong style="color:#111827;">Pesan:</strong> {{ $correction_request_message ?? '' }}</p>
@if(!empty($application_admin_url))
<p style="margin:0;font-size:14px;line-height:1.6;color:#4b5563;"><a href="{{ $application_admin_url }}" style="color:#178ADF;word-break:break-all;">Buka di dashboard</a></p>
@endif
