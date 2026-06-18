<?php

namespace Tests\Feature\Accounting;

use App\Models\BusinessType;
use App\Models\FormDraft;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FormDraftsTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_saved_draft_is_private_and_does_not_create_a_business_record(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create(['company_id' => $owner->company_id]);
        $draftKey = 'business-types.create';

        $this->actingAs($owner)
            ->putJson(route('accounting.form-drafts.store', ['draftKey' => $draftKey]), [
                'title' => 'Business Type',
                'payload' => [
                    'fields' => [
                        'code' => 'DRAFT-CONSULTING',
                        'name' => 'Draft Consulting',
                        'is_active' => true,
                    ],
                    'omitted_files' => false,
                    'omitted_sensitive' => false,
                ],
            ])
            ->assertOk()
            ->assertJsonPath('message', 'Draft saved.');

        $this->assertDatabaseHas('form_drafts', [
            'company_id' => $owner->company_id,
            'user_id' => $owner->id,
            'draft_key' => $draftKey,
        ]);
        $this->assertDatabaseMissing('business_types', ['code' => 'DRAFT-CONSULTING']);

        $this->actingAs($otherUser)
            ->getJson(route('accounting.form-drafts.show', ['draftKey' => $draftKey]))
            ->assertOk()
            ->assertJsonPath('exists', false);
    }

    public function test_successful_form_submission_removes_only_the_matching_draft(): void
    {
        $user = User::factory()->create();
        $draftKey = 'business-types.create';

        FormDraft::query()->create([
            'company_id' => $user->company_id,
            'user_id' => $user->id,
            'draft_key' => $draftKey,
            'title' => 'Business Type',
            'payload' => ['fields' => ['name' => 'Consulting']],
        ]);

        $this->actingAs($user)
            ->post(route('master.business-types.store'), [
                '_draft_key' => $draftKey,
                'code' => 'CONSULTING-DRAFT-TEST',
                'name' => 'Consulting Draft Test',
                'description' => 'Created from a completed draft.',
                'sort_order' => 50,
                'is_default' => false,
                'is_active' => true,
            ])
            ->assertRedirect(route('master.business-types.index'));

        $this->assertDatabaseHas('business_types', [
            'company_id' => $user->company_id,
            'code' => 'CONSULTING-DRAFT-TEST',
        ]);
        $this->assertDatabaseMissing('form_drafts', [
            'user_id' => $user->id,
            'draft_key' => $draftKey,
        ]);
    }

    public function test_validation_failure_does_not_delete_the_draft(): void
    {
        $user = User::factory()->create();
        $draftKey = 'business-types.create';

        FormDraft::query()->create([
            'company_id' => $user->company_id,
            'user_id' => $user->id,
            'draft_key' => $draftKey,
            'title' => 'Business Type',
            'payload' => ['fields' => ['name' => 'Incomplete']],
        ]);

        $this->actingAs($user)
            ->from(route('master.business-types.index'))
            ->post(route('master.business-types.store'), [
                '_draft_key' => $draftKey,
                'code' => '',
                'name' => '',
            ])
            ->assertRedirect(route('master.business-types.index'))
            ->assertSessionHasErrors();

        $this->assertDatabaseHas('form_drafts', [
            'user_id' => $user->id,
            'draft_key' => $draftKey,
        ]);
        $this->assertSame(0, BusinessType::query()->where('company_id', $user->company_id)->where('code', '')->count());
    }

    public function test_profile_page_has_a_visible_photo_chooser(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('accounting.profile'))
            ->assertOk()
            ->assertSee('Choose Photo')
            ->assertSee('data-profile-photo-uploader', false)
            ->assertSee('No new photo selected');
    }
}
