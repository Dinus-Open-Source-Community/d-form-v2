# Member Dashboard Overview - Backend Data Requirements

Tanggal: 31 Mei 2026

## Ringkasan

Halaman member overview sebelumnya berada di `/user/dashboard/overview` dan sekarang diarahkan ke `/dashboard`. Berdasarkan kode saat ini, halaman `resources/js/pages/Dashboard/User/Index.vue` belum sepenuhnya sesuai data backend:

- KPI `Events Joined`, `Upcoming Events`, dan `Pending Registrations` masih memakai angka statis / `dummyEvents`.
- Daftar `My Upcoming Events` masih membaca `dummyEvents` dari `resources/js/lib/dummyData.ts`.
- `EventCalendar` masih membaca data dummy internal, bukan props dari backend.
- Data backend yang sudah nyata saat ini ada di route daftar event member, yaitu `/events/joined`, yang mengambil event berdasarkan `form_answers` user login.

Kesimpulan: overview member belum realtime dan belum menjadi representasi server truth. Saat ini yang paling dekat dengan data backend adalah halaman "Acara diikuti" (`/events/joined`).

## Perubahan URL yang Disepakati

Route member yang perlu dipakai di UI:

| Kebutuhan | URL baru | Catatan |
| --- | --- | --- |
| Overview member | `/dashboard` | Untuk user non-organizer menampilkan `Dashboard/User/Index` |
| Acara diikuti | `/events/joined` | Pengganti `/user/dashboard` |
| Jelajah acara | `/events/joined/events/browse` | Mengikuti prefix portal member baru |
| Detail event member | `/events/joined/events/{event_segment}` | Mengikuti prefix portal member baru |
| Pilih/isi pendaftaran | `/events/joined/events/{event_segment}/register` | Redirect ke form fill resmi bila hanya satu form |
| Detail registrasi | `/events/joined/events/{event_segment}/registration` | Status review, QR, dan ringkasan jawaban |
| Undangan tim | `/events/joined/team-invitations/{token}` | Konfirmasi undangan member |

Route lama `/user/dashboard*` sebaiknya tetap redirect 301 untuk menjaga link lama dari email, bookmark, dan riwayat browser.

## Data Backend yang Dibutuhkan untuk `/dashboard`

Buat query/controller khusus, misalnya `Dashboard\User\MemberDashboardController` atau service `MemberDashboardQuery`, lalu pass props ke `Dashboard/User/Index`.

Props minimal:

```ts
type MemberDashboardProps = {
  stats: {
    eventsJoined: number
    upcomingEvents: number
    pendingRegistrations: number
    acceptedRegistrations: number
  }
  upcomingEvents: IEvent[]
  pendingInvitations: Array<{
    event: IEvent
    invitationUrl: string
  }>
  calendarEvents: Array<{
    id: string | number
    title: string
    start_date: string
    end_date: string | null
    category: string | string[] | null
    href: string
  }>
}
```

Sumber data yang disarankan:

- `form_answers.user_id = auth()->id()`.
- Join ke `forms` lalu `events`.
- Abaikan invitation member yang sudah terminal dengan scope `excludeTerminatedInvitationMembers()`.
- Hitung pending dari `form_answers.review_status` dan/atau `member_confirmation_status = pending`.
- Gunakan `EventService::eventToInertiaArray()` agar shape event konsisten dengan halaman lain.

## Kebutuhan Frontend

Di `resources/js/pages/Dashboard/User/Index.vue`:

- Hapus import `dummyEvents`.
- Terima props `stats`, `upcomingEvents`, `pendingInvitations`, dan `calendarEvents`.
- KPI membaca `stats`, bukan angka hardcoded.
- List `My Upcoming Events` membaca `upcomingEvents`.
- `EventCalendar` perlu menerima props `events` atau dibuat varian kalender member.
- Link detail event memakai `/events/joined/events/{slug}`.

Di komponen kalender:

- `resources/js/components/modules/dashboard/EventCalendar.vue` saat ini masih bergantung pada `dummyEvents`.
- Perlu props `events` agar bisa dipakai admin dan member dengan data server.
- Untuk member, `href` calendar event harus mengarah ke route portal member, bukan `/admin/dashboard/events/{id}`.

## Realtime vs Server Truth

Untuk tahap pertama, cukup jadikan `/dashboard` server truth: setiap reload/Inertia visit mengambil data terbaru dari database.

Jika butuh "realtime" tanpa reload, pilih salah satu:

- Polling ringan dengan `router.reload({ only: ['stats', 'upcomingEvents', 'calendarEvents'] })` tiap 30-60 detik.
- Endpoint JSON `GET /dashboard/member/summary` untuk polling komponen tertentu.
- WebSocket/broadcast hanya jika ada kebutuhan live update antar user saat registrasi/acceptance berubah.

Prioritas implementasi:

1. Backend props untuk overview member.
2. Refactor `Dashboard/User/Index.vue` agar tidak memakai dummy/static data.
3. Refactor `EventCalendar.vue` agar bisa menerima event dari props.
4. Tambahkan feature test untuk memastikan `/dashboard` member merender data sesuai `form_answers`.
5. Tambahkan test redirect URL lama `/user/dashboard*` ke URL baru.
