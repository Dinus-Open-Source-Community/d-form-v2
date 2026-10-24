<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Services\Event\EventService;
use App\Services\Reporting\EventReportingQuery;
use Illuminate\Http\Request;
use Inertia\Response as InertiaResponse;

/**
 * Beranda penyelenggara — hanya diakses lewat rute dengan middleware organizer.
 *
 * Semua agregat mahal (statistik event, jumlah pendaftar, breakdown kategori) dibaca
 * dari cache; request tidak pernah menghitung sendiri agar cold start tidak 9 s.
 * Isi cache lewat `php artisan dashboard:warm` (dijadwalkan tiap 5 menit di routes/console.php).
 * Bila cache kosong, dashboard menampilkan angka nol dan mencatat `dashboard cache kosong`.
 */
class HomeController extends Controller
{
    public function __construct(
        private readonly EventService $eventService,
        private readonly EventReportingQuery $reportingQuery,
    ) {
    }

    public function __invoke(Request $request): InertiaResponse
    {
        $eventStats = $this->reportingQuery->dashboardEventStats();

        $stats = [
            ...$eventStats,
            'totalRegistrants' => $this->reportingQuery->dashboardRegistrantTotal(),
        ];

        // Kolom `description` tidak dipakai kartu dashboard; hanya 5 baris, tapi tetap dipangkas.
        $recentEvents = Event::query()
            ->whereNull('deleted_at')
            ->orderByDesc('created_at')
            ->limit(5)
            ->get([
                'id', 'slug', 'title', 'location', 'banner', 'price', 'quota',
                'registered_count', 'category', 'session', 'status',
                'start_date', 'end_date', 'registration_start', 'registration_end',
                'created_at', 'updated_at',
            ])
            ->map(fn (Event $e): array => $this->eventService->eventToInertiaArray($e))
            ->values()
            ->all();

        $adminCharts = [
            'registrationTrend' => $this->reportingQuery->registrationTrendOverMonths(6),
            'categoryBreakdown' => $this->reportingQuery->dashboardCategoryTokens(),
        ];

        return inertia('Dashboard/Index', [
            'recentEvents' => $recentEvents,
            'stats' => $stats,
            'adminCharts' => $adminCharts,
        ]);
    }
}
