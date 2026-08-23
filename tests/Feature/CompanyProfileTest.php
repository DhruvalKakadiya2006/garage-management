<?php

namespace Tests\Feature;

use App\Models\CompanyProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CompanyProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_migration_seeds_exactly_one_company_profile_row(): void
    {
        $this->assertDatabaseCount('company_profile', 1);
        $this->assertSame(1, CompanyProfile::count());
    }

    public function test_company_profile_page_requires_authentication(): void
    {
        $this->get(route('company-profile.index'))->assertRedirect('/login');
    }

    public function test_company_profile_page_is_displayed(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('company-profile.index'));

        $response->assertOk();
        $response->assertSee('Company Profile');
        $response->assertSee(CompanyProfile::profile()->company_name);
    }

    public function test_company_profile_can_be_updated(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->put(route('company-profile.update'), [
            'company_name' => 'Kakadiya EV Hub',
            'address' => '12, Ring Road',
            'city' => 'Surat',
            'state' => 'Gujarat',
            'pincode' => '395001',
            'phone' => '9876543210',
            'email' => 'info@example.com',
            'gstin' => '24ABCDE1234F1Z5',
            'bank_name' => 'State Bank of India',
            'bank_account_no' => '1234567890',
            'bank_ifsc' => 'SBIN0001234',
            'terms_and_conditions' => 'No returns after 7 days.',
        ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('company-profile.index'));

        $profile = CompanyProfile::profile();

        $this->assertSame('Kakadiya EV Hub', $profile->company_name);
        $this->assertSame('12, Ring Road', $profile->address);
        $this->assertSame('395001', $profile->pincode);
        $this->assertSame('24ABCDE1234F1Z5', $profile->gstin);
        $this->assertSame('SBIN0001234', $profile->bank_ifsc);
        $this->assertSame('No returns after 7 days.', $profile->terms_and_conditions);
        $this->assertSame(1, CompanyProfile::count());
    }

    public function test_company_name_is_required(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->from(route('company-profile.index'))
            ->put(route('company-profile.update'), [
                'company_name' => '',
            ]);

        $response->assertSessionHasErrors('company_name');
        $response->assertRedirect(route('company-profile.index'));
    }

    public function test_optional_fields_store_empty_strings_for_non_nullable_columns(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->put(route('company-profile.update'), [
            'company_name' => 'Kakadiya EV Hub',
            'address' => '',
            'city' => '',
            'state' => '',
            'pincode' => '',
            'phone' => '',
            'email' => '',
            'gstin' => '',
        ]);

        $response->assertSessionHasNoErrors();

        $profile = CompanyProfile::profile();
        $this->assertSame('', $profile->address);
        $this->assertSame('', $profile->gstin);
        $this->assertSame(1, CompanyProfile::count());
    }

    public function test_logo_can_be_uploaded_and_removed(): void
    {
        Storage::fake('local');
        $user = User::factory()->create();

        $response = $this->actingAs($user)->put(route('company-profile.update'), [
            'company_name' => 'Kakadiya EV Hub',
            'logo' => UploadedFile::fake()->image('logo.png'),
        ]);

        $response->assertSessionHasNoErrors();

        $profile = CompanyProfile::profile();
        $this->assertNotNull($profile->logo_path);
        Storage::disk('local')->assertExists($profile->logo_path);

        $response = $this->actingAs($user)->put(route('company-profile.update'), [
            'company_name' => 'Kakadiya EV Hub',
            'remove_logo' => '1',
        ]);

        $response->assertSessionHasNoErrors();

        $this->assertNull(CompanyProfile::profile()->logo_path);
    }
}
