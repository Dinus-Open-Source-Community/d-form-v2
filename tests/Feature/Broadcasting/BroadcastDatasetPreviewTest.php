<?php

namespace Tests\Feature\Broadcasting;

use App\Http\Controllers\Dashboard\Broadcasting\BroadcastDatasetController;
use App\Models\EmailDataset;
use App\Models\User;
use App\Services\Broadcasting\BroadcastDatasetResolver;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;

/**
 * EP-04 AUDIT-P2-009 M6: dataset preview must be one streaming pass.
 *
 * The feature flag is toggled only inside this test so the policy authorizes
 * the controller; `config/features.php` keeps broadcast disabled.
 */
class BroadcastDatasetPreviewTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        config(['features.broadcast' => true]);
    }

    public function test_preview_counts_in_one_streaming_pass_and_keeps_five_rows(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('super-admin');
        $this->actingAs($admin);

        $dataset = EmailDataset::query()->create([
            'name' => 'Preview dataset',
            'source_type' => 'custom',
            'created_by' => null,
        ]);

        $rows = [];

        for ($i = 0; $i < 25; $i++) {
            $rows[] = [
                'id' => (string) Str::uuid(),
                'dataset_id' => $dataset->id,
                'name' => 'Preview '.$i,
                'email' => 'preview'.$i.'@example.com',
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        $dataset->recipients()->insert($rows);

        // Materializing accessors must never be touched by the preview path.
        $resolver = new class () extends BroadcastDatasetResolver {
            public function customDataset(string $datasetId): array
            {
                throw new RuntimeException('preview materialized the dataset');
            }
        };

        $request = Request::create('/admin/broadcasts/datasets/preview', 'GET', [
            'type' => 'custom',
            'dataset_id' => $dataset->id,
        ]);

        $response = app(BroadcastDatasetController::class)->preview($request, $resolver);
        $payload = $response->getData(true);

        $this->assertSame(25, $payload['total']);
        $this->assertCount(5, $payload['sample']);

        foreach ($payload['sample'] as $row) {
            $this->assertArrayHasKey('name', $row);
            $this->assertArrayHasKey('email', $row);
            $this->assertStringEndsWith('@example.com', $row['email']);
        }
    }

    public function test_preview_without_dataset_returns_empty_stream(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('super-admin');
        $this->actingAs($admin);

        $request = Request::create('/admin/broadcasts/datasets/preview', 'GET', ['type' => 'custom']);

        $payload = app(BroadcastDatasetController::class)
            ->preview($request, app(BroadcastDatasetResolver::class))
            ->getData(true);

        $this->assertSame(0, $payload['total']);
        $this->assertSame([], $payload['sample']);
    }
}
