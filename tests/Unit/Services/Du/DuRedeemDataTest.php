<?php

namespace Tests\Unit\Services\Du;

use App\Services\Du\DuRedeemData;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\DuRedeemFixture;
use Tests\TestCase;

final class DuRedeemDataTest extends TestCase
{
    public function test_maps_the_documented_response_and_keeps_the_raw_payload(): void
    {
        $data = DuRedeemData::fromArray([
            'uid' => '7e484e3e', 'code' => 'DU-X7K2-9B4Z', 'course_uid' => 'course-a',
            'course_title' => 'Kelas A', 'target_email' => 'buyer@example.test',
            'max_uses' => 1, 'used_count' => 0, 'is_active' => true,
            'expires_at' => '2027-01-31T23:59:59Z', 'created_at' => '2026-11-08T10:00:00Z',
        ]);

        $this->assertNotNull($data);
        $this->assertSame('course-a', $data->courseUid);
        $this->assertSame('DU-X7K2-9B4Z', $data->code);
        $this->assertSame('Kelas A', $data->courseTitle);
        $this->assertSame(1, $data->maxUses);
        $this->assertTrue($data->isActive);
        $this->assertSame('7e484e3e', $data->payload['uid']);
    }

    public function test_rejects_malformed_required_fields(): void
    {
        $this->assertNull(DuRedeemData::fromArray(['code' => 'DU-1']));
        $this->assertNull(DuRedeemData::fromArray(['uid' => '', 'code' => 'DU-1', 'course_uid' => 'a']));
    }

    #[DataProvider('wrongOptionalFieldTypes')]
    public function test_rejects_optional_fields_with_wrong_types(string $field, int|string $value): void
    {
        $this->assertNull(DuRedeemData::fromArray(array_merge(DuRedeemFixture::success(), [$field => $value])));
    }

    /** @return array<string, array{0: string, 1: int|string}> */
    public static function wrongOptionalFieldTypes(): array
    {
        return [
            'course_title is not a string' => ['course_title', 123],
            'target_email is not a string' => ['target_email', 123],
            'max_uses is not an integer' => ['max_uses', '1'],
            'used_count is not an integer' => ['used_count', '1'],
            'is_active is not a boolean' => ['is_active', 1],
            'expires_at is not a string' => ['expires_at', 123],
        ];
    }
}
