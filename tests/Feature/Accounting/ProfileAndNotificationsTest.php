<?php

namespace Tests\Feature\Accounting;

use App\Models\User;
use App\Services\Notifications\AccountingNotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class ProfileAndNotificationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_accounting_layout_displays_top_right_profile_and_notification_controls(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Open notifications')
            ->assertSee('Open account menu')
            ->assertSee(route('accounting.profile'), false)
            ->assertSee(route('accounting.notifications.index'), false);
    }

    public function test_user_can_update_profile_photo_and_password(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();

        $this->actingAs($user)
            ->put(route('accounting.profile.photo.update'), [
                'profile_photo' => UploadedFile::fake()->image('avatar.jpg', 200, 200),
            ])
            ->assertRedirect(route('accounting.profile'));

        $user->refresh();
        $this->assertNotNull($user->profile_photo_path);
        Storage::disk('public')->assertExists($user->profile_photo_path);

        $this->actingAs($user)
            ->put(route('accounting.profile.password'), [
                'current_password' => 'password',
                'new_password' => 'NewSecurePass123!',
                'new_password_confirmation' => 'NewSecurePass123!',
            ])
            ->assertRedirect(route('accounting.profile').'#change-password');

        $this->assertTrue(Hash::check('NewSecurePass123!', (string) $user->fresh()->password));
    }

    public function test_notification_service_is_company_scoped_and_supports_deduplication(): void
    {
        $companyAdmin = User::factory()->create();
        $otherCompanyAdmin = User::factory()->create();

        $service = app(AccountingNotificationService::class);
        $sent = $service->notifyCompanyAdministrators((int) $companyAdmin->company_id, [
            'title' => 'Party Created',
            'message' => 'A party was created.',
            'url' => route('parties.index'),
        ], 'company-scope-test');

        $this->assertSame(1, $sent);
        $this->assertSame(1, $companyAdmin->notifications()->count());
        $this->assertSame(0, $otherCompanyAdmin->notifications()->count());

        $sentAgain = $service->notifyCompanyAdministrators((int) $companyAdmin->company_id, [
            'title' => 'Party Created',
            'message' => 'A party was created.',
        ], 'company-scope-test');

        $this->assertSame(0, $sentAgain);
        $this->assertSame(1, $companyAdmin->notifications()->count());
    }

    public function test_notification_feed_and_read_actions_only_access_the_authenticated_users_records(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $notificationId = (string) Str::uuid();
        $otherNotificationId = (string) Str::uuid();
        $now = now();

        DB::table('notifications')->insert([
            [
                'id' => $notificationId,
                'type' => 'hisebghor.accounting',
                'notifiable_type' => User::class,
                'notifiable_id' => $user->id,
                'data' => json_encode(['title' => 'Own notification', 'message' => 'Visible']),
                'read_at' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'id' => $otherNotificationId,
                'type' => 'hisebghor.accounting',
                'notifiable_type' => User::class,
                'notifiable_id' => $otherUser->id,
                'data' => json_encode(['title' => 'Other notification', 'message' => 'Hidden']),
                'read_at' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);

        $this->actingAs($user)
            ->getJson(route('accounting.notifications.feed'))
            ->assertOk()
            ->assertJsonPath('unread_count', 1)
            ->assertJsonPath('notifications.0.id', $notificationId)
            ->assertJsonMissing(['id' => $otherNotificationId]);

        $this->actingAs($user)
            ->postJson(route('accounting.notifications.read', ['notification' => $notificationId]))
            ->assertOk()
            ->assertJsonPath('unread_count', 0);

        $this->actingAs($user)
            ->postJson(route('accounting.notifications.read', ['notification' => $otherNotificationId]))
            ->assertNotFound();
    }

    public function test_successful_accounting_mutation_creates_an_admin_activity_notification(): void
    {
        $admin = User::factory()->create();

        $this->actingAs($admin)
            ->post(route('master.business-types.store'), [
                'code' => 'CONSULTING',
                'name' => 'Consulting',
                'description' => 'Consulting services',
                'sort_order' => 10,
                'is_default' => false,
                'is_active' => true,
            ])
            ->assertRedirect(route('master.business-types.index'));

        $notification = $admin->notifications()->latest()->first();
        $this->assertNotNull($notification);
        $this->assertSame('Business Type Created', $notification->data['title']);
        $this->assertSame('created', $notification->data['action']);
    }
}
