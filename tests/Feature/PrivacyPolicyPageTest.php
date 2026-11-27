<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PrivacyPolicyPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_privacy_policy_is_public_and_explains_account_deletion(): void
    {
        $this->get('/privacy-policy')->assertOk()
            ->assertSee('Privacy policy')
            ->assertSee('id="delete-account"', false)
            ->assertSee('Delete my Fitnix account');

        $this->get('/delete-account')->assertRedirect('/privacy-policy#delete-account');
        $this->get('/')->assertSee(route('privacy'), false);
    }
}
