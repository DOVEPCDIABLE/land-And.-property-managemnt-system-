<?php

namespace Tests\Feature;

use App\Jobs\SendFollowUp;
use App\Models\FollowUp;
use App\Models\User;
use App\Notifications\SystemNotification;
use Carbon\CarbonImmutable;
use Database\Seeders\AccessControlSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\SendQueuedNotifications;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Symfony\Component\Mailer\Exception\TransportException;
use Tests\TestCase;

class MessagingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AccessControlSeeder::class);
    }

    public function test_notification_is_queued_for_the_selected_channels(): void
    {
        Queue::fake();
        $user = User::factory()->create();
        $user->notify(new SystemNotification('Hello', 'Test message', ['database', 'mail']));
        Queue::assertPushed(SendQueuedNotifications::class, 2);
    }

    public function test_notification_inbox_and_read_action_are_user_scoped(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $other->notifyNow(new SystemNotification('Private message', 'Hidden content'));
        $notification = $other->notifications()->sole();
        $this->actingAs($user)->get('/notifications')->assertOk()->assertDontSee('Hidden content');
        $this->patch('/notifications/'.$notification->id.'/read')->assertNotFound();
        $this->actingAs($other)->patch('/notifications/'.$notification->id.'/read')->assertRedirect();
        $this->assertNotNull($notification->fresh()->read_at);
    }

    public function test_admin_cannot_notify_another_agency(): void
    {
        Notification::fake();
        $admin = User::factory()->role('agency_admin')->create();
        $other = User::factory()->create();
        $this->actingAs($admin)->post('/notifications', ['user_id' => $other->id, 'subject' => 'Hi', 'message' => 'Test', 'channels' => ['database']])->assertNotFound();
        Notification::assertNothingSent();
    }

    public function test_due_followups_are_queued_once_and_future_or_cancelled_items_are_untouched(): void
    {
        Queue::fake();
        $due = FollowUp::factory()->create();
        FollowUp::factory()->create(['due_at' => now()->addDay()]);
        FollowUp::factory()->create(['status' => 'cancelled']);
        $this->artisan('followups:dispatch')->assertSuccessful();
        $this->artisan('followups:dispatch')->assertSuccessful();
        Queue::assertPushed(SendFollowUp::class, 1);
        $this->assertSame('queued', $due->fresh()->status);
    }

    public function test_followup_delivers_and_repeated_job_does_not_deliver_again(): void
    {
        $item = FollowUp::factory()->create(['status' => 'queued']);
        (new SendFollowUp($item->id))->handle();
        (new SendFollowUp($item->id))->handle();
        $this->assertSame('sent', $item->fresh()->status);
        $this->assertDatabaseCount('notifications', 1);
    }

    public function test_inactive_recipient_cancels_followup(): void
    {
        $item = FollowUp::factory()->create(['status' => 'queued']);
        $item->user->forceFill(['is_active' => false])->save();
        (new SendFollowUp($item->id))->handle();
        $this->assertSame('cancelled', $item->fresh()->status);
        $this->assertDatabaseCount('notifications', 0);
    }

    public function test_agent_can_schedule_only_a_personal_reminder_and_time_is_stored_in_utc(): void
    {
        $agent = User::factory()->role('agent')->create();
        $other = User::factory()->create(['agency_id' => $agent->agency_id]);
        $time = now()->addDay()->timezone('Africa/Lagos')->format('Y-m-d\TH:i');
        $data = ['user_id' => $other->id, 'subject' => 'Reminder', 'message' => 'Test', 'channels' => ['database'], 'due_at' => $time];
        $this->actingAs($agent)->post('/follow-ups', $data)->assertNotFound();
        $data['user_id'] = $agent->id;
        $this->post('/follow-ups', $data)->assertSessionHasNoErrors();
        $this->assertSame(CarbonImmutable::parse($time, 'Africa/Lagos')->utc()->format('Y-m-d H:i:s'), FollowUp::sole()->due_at->format('Y-m-d H:i:s'));
    }

    public function test_zeptomail_transport_sends_authenticated_payload_without_exposing_key(): void
    {
        config(['zeptomail.key' => 'zepto-secret']);
        Http::preventStrayRequests();
        Http::fake(['api.zeptomail.com/*' => Http::response(['data' => [['code' => 'EM_101', 'message' => 'Email request received']]], 201)]);
        Mail::mailer('zeptomail')->raw('A transactional message', fn ($mail) => $mail->to('recipient@example.test')->subject('Test email'));
        Http::assertSent(fn ($request): bool => $request->hasHeader('Authorization', 'Zoho-enczapikey zepto-secret') && $request['to'][0]['email_address']['address'] === 'recipient@example.test' && $request['textbody'] === 'A transactional message');
    }

    public function test_zeptomail_rejections_raise_a_retryable_transport_exception(): void
    {
        config(['zeptomail.key' => 'zepto-secret']);
        Http::fake(['api.zeptomail.com/*' => Http::response(['error' => ['message' => 'Rejected']], 429)]);
        $this->expectException(TransportException::class);
        Mail::mailer('zeptomail')->raw('Test', fn ($mail) => $mail->to('recipient@example.test')->subject('Test'));
    }

    public function test_past_lagos_times_are_rejected_even_when_ahead_of_utc_clock(): void
    {
        $agent = User::factory()->role('agent')->create();
        $time = now()->subMinutes(10)->timezone('Africa/Lagos')->format('Y-m-d\TH:i');
        $this->actingAs($agent)->post('/follow-ups', ['user_id' => $agent->id, 'subject' => 'Too late', 'message' => 'Test', 'channels' => ['database'], 'due_at' => $time])->assertSessionHasErrors('due_at');
        $this->assertDatabaseCount('follow_ups', 0);
    }

    public function test_failed_followup_can_be_retried_after_the_cause_is_fixed(): void
    {
        $item = FollowUp::factory()->create(['status' => 'failed']);
        (new SendFollowUp($item->id))->handle();
        $this->assertSame('sent', $item->fresh()->status);
        $this->assertDatabaseCount('notifications', 1);
    }

    public function test_authentication_emails_use_the_notification_queue_and_are_encrypted(): void
    {
        Queue::fake();
        $user = User::factory()->unverified()->create();
        $user->sendEmailVerificationNotification();
        $user->sendPasswordResetNotification('test-token');
        Queue::assertPushed(SendQueuedNotifications::class, 2);
        Queue::assertPushed(SendQueuedNotifications::class, fn ($job): bool => $job->queue === 'notifications' && $job->shouldBeEncrypted);
    }
}
