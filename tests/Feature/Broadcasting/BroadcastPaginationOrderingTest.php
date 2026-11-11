<?php

namespace Tests\Feature\Broadcasting;

use App\Enums\EmailBroadcastStatus;
use App\Http\Controllers\Dashboard\Broadcasting\BroadcastController;
use App\Http\Controllers\Dashboard\Broadcasting\BroadcastDatasetController;
use App\Http\Controllers\Dashboard\Broadcasting\BroadcastRecipientController;
use App\Models\EmailBroadcast;
use App\Models\EmailBroadcastRecipient;
use App\Models\EmailDataset;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Pagination deterministik saat `created_at` banyak baris identik: tie-breaker
 * `id` wajib ada, kalau tidak baris yang sama bisa muncul di dua halaman dan
 * baris lain tidak pernah tampil.
 */
class BroadcastPaginationOrderingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        config(['features.broadcast' => true]);

        Route::middleware(['web', 'auth'])->get(
            '/__test/broadcast-pagination/recipients/{broadcast}',
            [BroadcastRecipientController::class, 'index'],
        );
        Route::middleware(['web', 'auth'])->get(
            '/__test/broadcast-pagination/broadcasts',
            [BroadcastController::class, 'index'],
        );
        Route::middleware(['web', 'auth'])->get(
            '/__test/broadcast-pagination/datasets',
            [BroadcastDatasetController::class, 'index'],
        );
    }

    private function authorizedUser(): User
    {
        $user = User::factory()->create();
        $user->givePermissionTo('email-broadcast.view');

        return $user;
    }

    /**
     * @return list<string>
     */
    private function pageIds(string $url, int $page): array
    {
        $response = $this->actingAs($this->authorizedUser())->get($url.'?page='.$page);
        $response->assertOk();

        $page_data = $response->viewData('page');

        return array_column($page_data['props']['recipients']['data'] ?? $page_data['props']['broadcasts']['data'] ?? $page_data['props']['datasets']['data'], 'id');
    }

    public function test_recipient_pages_do_not_repeat_rows_when_created_at_ties(): void
    {
        $broadcast = EmailBroadcast::query()->create([
            'name' => 'Tie recipients',
            'status' => EmailBroadcastStatus::Draft,
            'created_by' => $this->authorizedUser()->id,
        ]);

        $createdAt = Carbon::parse('2026-11-10 08:00:00');

        for ($index = 1; $index <= 30; $index++) {
            EmailBroadcastRecipient::query()->create([
                'broadcast_id' => $broadcast->id,
                'name' => 'Recipient '.$index,
                'email' => 'recipient'.$index.'@example.test',
                'status' => 'pending',
                'created_at' => $createdAt->copy(),
                'updated_at' => $createdAt->copy(),
            ]);
        }

        $url = '/__test/broadcast-pagination/recipients/'.$broadcast->id;
        $first = $this->pageIds($url, 1);
        $second = $this->pageIds($url, 2);

        $this->assertCount(25, $first);
        $this->assertCount(5, $second);
        $this->assertSame([], array_values(array_intersect($first, $second)));
        $this->assertCount(30, array_unique([...$first, ...$second]));
    }

    public function test_broadcast_and_dataset_pages_do_not_repeat_rows_when_created_at_ties(): void
    {
        $user = $this->authorizedUser();
        $createdAt = Carbon::parse('2026-11-10 08:00:00');

        for ($index = 1; $index <= 16; $index++) {
            EmailBroadcast::query()->create([
                'name' => 'Broadcast '.$index,
                'status' => EmailBroadcastStatus::Draft,
                'created_by' => $user->id,
                'created_at' => $createdAt->copy(),
                'updated_at' => $createdAt->copy(),
            ]);
            EmailDataset::query()->create([
                'name' => 'Dataset '.$index,
                'source_type' => 'custom',
                'created_by' => $user->id,
                'created_at' => $createdAt->copy(),
                'updated_at' => $createdAt->copy(),
            ]);
        }

        foreach (['/__test/broadcast-pagination/broadcasts', '/__test/broadcast-pagination/datasets'] as $url) {
            $first = $this->pageIds($url, 1);
            $second = $this->pageIds($url, 2);

            $this->assertCount(15, $first);
            $this->assertCount(1, $second);
            $this->assertSame([], array_values(array_intersect($first, $second)));
        }
    }
}
