Kehadiran kelas dikonfirmasi — {{ $event->title }}

Halo {{ $recipientName }},

Kehadiran Anda untuk kelas ini telah tercatat.

Tanggal kelas: {{ $attendance->formSession?->session_date?->format('d-m-Y') }}
Pertemuan tersisa: {{ $remaining }} dari {{ $total }}
Form: {{ $form->title }}

Tidak ada tindakan lain yang perlu dilakukan.
