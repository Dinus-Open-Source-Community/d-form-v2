<?php

namespace Tests\Unit;

use App\Services\Event\PublicEventSearch;
use PHPUnit\Framework\TestCase;

/**
 * Normalisasi kata kunci pencarian: sanitasi operator boolean, token pendek,
 * stopword Indonesia, dan batas jumlah token.
 */
class EventSearchTermsTest extends TestCase
{
    public function test_empty_input_returns_null(): void
    {
        $this->assertNull(PublicEventSearch::terms(null));
        $this->assertNull(PublicEventSearch::terms(''));
        $this->assertNull(PublicEventSearch::terms('   '));
    }

    public function test_input_shorter_than_minimum_token_returns_null(): void
    {
        $this->assertNull(PublicEventSearch::terms('ab'));
        $this->assertNull(PublicEventSearch::terms('a'));
    }

    public function test_short_tokens_are_dropped(): void
    {
        $this->assertSame('+seminar* +jakarta*', PublicEventSearch::terms('seminar di jakarta'));
        $this->assertSame('+seminar* +jakarta*', PublicEventSearch::terms('seminar xx jakarta'));
    }

    public function test_indonesian_stopwords_are_dropped(): void
    {
        $this->assertSame('+workshop*', PublicEventSearch::terms('workshop di ke dari dan yang untuk dengan pada atau ini itu'));
        $this->assertNull(PublicEventSearch::terms('di ke dari'));
    }

    public function test_boolean_operators_are_stripped(): void
    {
        $this->assertSame('+seminar*', PublicEventSearch::terms('+-~*()<>"seminar'));
        $this->assertNull(PublicEventSearch::terms('+-~*()<>"'));
    }

    public function test_tokens_are_capped_at_five(): void
    {
        $this->assertSame(
            '+alpha* +bravo* +charlie* +delta* +echo*',
            PublicEventSearch::terms('alpha bravo charlie delta echo foxtrot golf'),
        );
    }

    public function test_unicode_letters_are_kept(): void
    {
        $this->assertSame('+ñandú* +seminar*', PublicEventSearch::terms('ñandú seminar'));
    }

    public function test_numbers_are_kept(): void
    {
        $this->assertSame('+5387* +2026*', PublicEventSearch::terms('5387 2026'));
    }

    public function test_tokens_are_lowercased_so_the_cache_key_is_stable(): void
    {
        $this->assertSame('+seminar* +jakarta*', PublicEventSearch::terms('seminar Di Jakarta'));
        $this->assertSame(PublicEventSearch::terms('Seminar'), PublicEventSearch::terms('SEMINAR'));
    }
}
