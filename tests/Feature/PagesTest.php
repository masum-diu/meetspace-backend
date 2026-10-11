<?php

namespace Tests\Feature;

use Tests\TestCase;

class PagesTest extends TestCase
{
    public function test_pages_render_with_the_shared_layout(): void
    {
        config(['services.clerk.publishable_key' => 'pk_test_'.base64_encode('demo-app-1.clerk.accounts.dev$'), 'services.clerk.secret_key' => 'sk_test_hidden']);

        $this->get('/')->assertOk()->assertSee('id="blist"', false)->assertSee('href="/book"', false);
        $this->get('/book')->assertOk()->assertSee('id="bookForm"', false);
        $this->get('/profile')->assertOk()->assertSee('id="myList"', false)
            ->assertSee('demo-app-1.clerk.accounts.dev', false)
            ->assertDontSee('sk_test_hidden', false);
    }
}
