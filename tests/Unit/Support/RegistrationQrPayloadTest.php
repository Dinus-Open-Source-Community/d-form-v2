<?php

namespace Tests\Unit\Support;

use App\Support\RegistrationQrPayload;
use Tests\TestCase;

class RegistrationQrPayloadTest extends TestCase
{
    public function test_try_decode_returns_submission_id_for_valid_payload(): void
    {
        $id = 'f47ac10b-58cc-4372-a567-0e02b2c3d479';
        $json = RegistrationQrPayload::encode($id);

        $this->assertSame($id, RegistrationQrPayload::tryDecodeSubmissionId($json));
    }

    public function test_try_decode_returns_null_for_wrong_version(): void
    {
        $json = '{"v":2,"submission_id":"f47ac10b-58cc-4372-a567-0e02b2c3d479"}';

        $this->assertNull(RegistrationQrPayload::tryDecodeSubmissionId($json));
    }

    public function test_try_decode_returns_null_for_invalid_uuid(): void
    {
        $json = '{"v":1,"submission_id":"not-a-uuid"}';

        $this->assertNull(RegistrationQrPayload::tryDecodeSubmissionId($json));
    }

    public function test_try_decode_returns_null_for_plain_text(): void
    {
        $this->assertNull(RegistrationQrPayload::tryDecodeSubmissionId('CHK-123'));
    }
}
