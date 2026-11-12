# Sapto Changes — 3 November 2026 (DFORM-E62)

## Ringkasan

Ticket `dform-e62` meminta queue worker dan scheduler berjalan di luar container app. Investigasi menunjukkan premisnya sebagian tidak akurat dan keputusan user adalah **runtime produksi bukan scope agent**: tidak ada perubahan `docker-compose*.yml`, `docker/entrypoint*.sh`, `Dockerfile*`, Jenkins, atau artefak deploy. Ticket di-defer dengan alasan tertulis; yang dikerjakan hanya koreksi fakta dokumentasi.

| Issue       | Commit                                                          |
| ----------- | --------------------------------------------------------------- |
| `dform-e62` | `docs(context): record actual queue worker and scheduler state` |

## Fakta terverifikasi

| Klaim lama                           | Keadaan aktual                                                                                                                            | Bukti                                                                                    |
| ------------------------------------ | ----------------------------------------------------------------------------------------------------------------------------------------- | ---------------------------------------------------------------------------------------- |
| Worker tidak berjalan di produksi    | Worker **sudah** berjalan di dalam container `app` sebagai proses background entrypoint, `QUEUE_WORKER` default `1`, loop restart 2 detik | `docker/entrypoint.sh:105-110`, `docker/entrypoint.prod.sh:201-206`                      |
| Scheduler berjalan di host/eksternal | **Tidak ada** `schedule:run`/`schedule:work` di entrypoint, compose, Jenkins, maupun systemd host                                         | `grep -rn "schedule:run\|schedule:work" docker docker-compose*.yml Jenkinsfile` → kosong |
| Dua task terjadwal                   | **Tiga** task terjadwal, termasuk `otp:prune` yang belum tercatat                                                                         | `php artisan schedule:list`                                                              |

```text
*/15 * * * *  php artisan recruitment:send-interview-reminders
*    * * * *  php artisan broadcast:dispatch-scheduled
0    * * * *  php artisan otp:prune
```

Jadi yang hilang hanya scheduler, bukan worker. Dampak operasional: pengingat interview dan prune token OTP tidak berjalan otomatis di produksi selama scheduler belum dijalankan.

## Perubahan

- `CONTEXT.md` §3 (diagram topologi, konsekuensi operasional, daftar task) dan §14 butir 5 dikoreksi sesuai fakta di atas.
- **Catatan:** `CONTEXT.md` ada di `.gitignore` repo ini (baris 65) dan tidak tracked, sehingga koreksi tersebut tersimpan lokal dan tidak ikut commit. Perubahan yang di-commit hanya changelog ini + baris README.
- Tidak ada perubahan kode, compose, entrypoint, atau deploy.

## Keputusan

**Deferred.** Alasan: user menyatakan runtime produksi tidak boleh disentuh oleh agent ("jangan ubah apa-apa di prod"). Rencana teknis lengkap tetap dicatat sebagai lampiran di komentar Beads `dform-e62` agar bisa dieksekusi manusia saat scope dibuka:

- Opsi A (rekomendasi): service compose terpisah `worker` + `scheduler` memakai image yang sama, `QUEUE_WORKER=0` pada app, entrypoint worker tanpa migrasi.
- Opsi B: supervisord di image prod (ditolak: tetap satu container, Octane restart mematikan worker).
- Opsi C: unit systemd/cron di host menjalankan `docker exec` (fallback darurat: coupling ke nama container, di luar compose).

## Verifikasi

```text
$ grep -n "QUEUE_WORKER|queue:work" docker/entrypoint.sh docker/entrypoint.prod.sh
docker/entrypoint.sh:106:if [ "${QUEUE_WORKER:-1}" = "1" ]; then
docker/entrypoint.sh:110:            php artisan queue:work redis --sleep=1 --tries=3 --timeout=90 || true
docker/entrypoint.prod.sh:202:if [ "${QUEUE_WORKER:-1}" = "1" ]; then
docker/entrypoint.prod.sh:206:            php artisan queue:work redis --sleep=1 --tries=3 --timeout=90 || true

$ php artisan schedule:list   → 3 task (lihat tabel di atas)
```

Tidak ada test yang dijalankan karena tidak ada perubahan perilaku; tidak ada file runtime yang disentuh.
