<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Validasi query halaman publik `/events`; key error harus tetap `q` dan `page`
 * karena form pencarian frontend membaca map `errors` dari Inertia.
 */
class EventListValidationTest extends TestCase
{
    use RefreshDatabase;

    public function test_query_longer_than_100_characters_is_rejected_on_q_key(): void
    {
        $this->get('/events?q='.str_repeat('a', 101))
            ->assertRedirect()
            ->assertSessionHasErrors('q');
    }

    public function test_page_below_one_is_rejected_on_page_key(): void
    {
        $this->get('/events?page=0')
            ->assertRedirect()
            ->assertSessionHasErrors('page');
    }
}
