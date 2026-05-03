<?php

namespace Tests\Feature\Notifications;

use Tests\TestCase;

class MailPreviewTest extends TestCase
{
    public function test_mail_preview_returns_200_with_html(): void
    {
        $response = $this->get(route('mail.preview'));

        $response->assertStatus(200);
        $response->assertSee('Inventory Summary');
    }

    public function test_mail_preview_sets_x_frame_options_header(): void
    {
        $response = $this->get(route('mail.preview'));

        $response->assertHeader('X-Frame-Options', 'SAMEORIGIN');
    }
}
