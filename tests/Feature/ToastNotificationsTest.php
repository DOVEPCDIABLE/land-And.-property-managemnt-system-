<?php

namespace Tests\Feature;

use Tests\TestCase;

class ToastNotificationsTest extends TestCase
{
    public function test_success_flash_is_rendered_as_an_escaped_dismissible_toast(): void
    {
        $this->withSession(['status' => 'Saved <script>alert(1)</script>'])->get('/login')
            ->assertOk()
            ->assertSee('toast toast-success', false)
            ->assertSee('Dismiss notification')
            ->assertSee('Saved &lt;script&gt;alert(1)&lt;/script&gt;', false)
            ->assertDontSee('Saved <script>alert(1)</script>', false)
            ->assertSee('/js/toasts.js', false);
    }

    public function test_validation_errors_are_shown_in_an_alert_toast(): void
    {
        $this->followingRedirects()->from('/login')->post('/login', ['email' => 'invalid', 'password' => ''])
            ->assertOk()
            ->assertSee('toast toast-error', false)
            ->assertSee('role="alert"', false)
            ->assertSee('The email field must be a valid email address.');
    }

    public function test_empty_flash_does_not_show_a_blank_toast(): void
    {
        $this->get('/login')->assertOk()
            ->assertSee('data-toast-region', false)
            ->assertDontSee('class="toast toast-', false);
    }
}
