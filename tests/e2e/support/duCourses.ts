import { runArtisanCommand } from './artisan';

/** Upsert satu kelas cache DU supaya spec browser tidak pernah memanggil DU. */
export function seedDuCourse(uid: string, title: string): void {
    runArtisanCommand(['e2e:du-course', `--uid=${uid}`, `--title=${title}`]);
}

/** Hapus kelas cache DU berdasarkan prefix uid (bersih-bersih setelah spec). */
export function clearDuCourses(prefix: string): void {
    runArtisanCommand(['e2e:du-course', `--clear=${prefix}`]);
}
