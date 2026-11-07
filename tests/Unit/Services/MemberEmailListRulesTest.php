<?php

namespace Tests\Unit\Services;

use App\Services\Registration\MemberEmailListRules;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class MemberEmailListRulesTest extends TestCase
{
    public function test_normalize_lowercases_and_trims_while_preserving_indexes(): void
    {
        $emails = MemberEmailListRules::normalize([
            0 => '  First@Example.COM ',
            'slot' => "\tSecond@Example.COM\n",
        ]);

        $this->assertSame([
            0 => 'first@example.com',
            'slot' => 'second@example.com',
        ], $emails);
    }

    public function test_resolve_returns_normalized_list_for_a_valid_payload(): void
    {
        $emails = MemberEmailListRules::resolve(
            ['Member@Example.com'],
            'leader@example.com',
            1,
        );

        $this->assertSame(['member@example.com'], $emails);
    }

    public function test_resolve_rejects_a_non_list_email_array(): void
    {
        try {
            MemberEmailListRules::resolve([5 => 'member@example.com'], 'leader@example.com', 1);
            $this->fail('Expected a ValidationException for a sparse email list.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('team_member_emails', $exception->errors());
        }
    }

    public function test_resolve_rejects_the_leader_email_case_insensitively(): void
    {
        try {
            MemberEmailListRules::resolve(['LEADER@Example.com'], 'leader@example.com', 1);
            $this->fail('Expected a ValidationException when the leader lists themself.');
        } catch (ValidationException $exception) {
            $this->assertSame(
                ['You cannot list yourself as a team member.'],
                $exception->errors()['team_member_emails.0'] ?? [],
            );
        }
    }

    public function test_resolve_rejects_duplicate_emails_after_normalization(): void
    {
        try {
            MemberEmailListRules::resolve(
                ['member@example.com', 'MEMBER@example.com'],
                'leader@example.com',
                2,
            );
            $this->fail('Expected a ValidationException for duplicate emails.');
        } catch (ValidationException $exception) {
            $this->assertSame(
                ['Each team member email must be unique.'],
                $exception->errors()['team_member_emails'] ?? [],
            );
        }
    }
}
