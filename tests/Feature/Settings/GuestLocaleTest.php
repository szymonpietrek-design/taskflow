<?php

namespace Tests\Feature\Settings;

use Tests\TestCase;

class GuestLocaleTest extends TestCase
{
    public function test_guest_can_choose_polish_interface_language(): void
    {
        $this->from('/login')
            ->post('/ui-locale', ['locale' => 'pl'])
            ->assertRedirect('/login')
            ->assertCookie('locale', 'pl');
    }

    public function test_unsupported_guest_language_is_rejected(): void
    {
        $this->from('/login')
            ->post('/ui-locale', ['locale' => 'zz'])
            ->assertSessionHasErrors('locale');
    }
}
