<?php

namespace Tests\Feature;

use App\Models\TableSessionRequest;
use App\Support\DeviceToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

/**
 * Device-cookie approvals [F-18]: a guest's identity survives IP changes
 * (mobile CGNAT), and one approval does not admit the whole venue NAT.
 */
class DeviceCookieTest extends TestCase
{
    use InteractsWithTenants, RefreshDatabase;

    public function test_qr_entry_sets_a_device_cookie_and_records_it(): void
    {
        $editor = $this->makeEditor();
        [$table, $session] = $this->openTableWithSession($editor, ['table_number' => 2]);

        $response = $this->get('/qr-entry/'.rawurlencode($editor->username).'/2')->assertOk();
        $response->assertCookie(DeviceToken::COOKIE);

        $request = TableSessionRequest::sole();
        $this->assertNotNull($request->device_token);
    }

    public function test_approved_device_keeps_access_when_its_ip_changes(): void
    {
        $editor = $this->makeEditor();
        [$table, $session] = $this->openTableWithSession($editor);
        $device = Str::random(40);

        TableSessionRequest::create([
            'table_session_id' => $session->id,
            'table_id' => $table->id,
            'ip_address' => '10.0.0.1',
            'device_token' => $device,
            'status' => 'approved',
            'requested_at' => now(),
            'approved_at' => now(),
        ]);

        // Same cookie, different IP (CGNAT rotation): still in.
        $this->withServerVariables(['REMOTE_ADDR' => '10.9.9.9'])
            ->withUnencryptedCookie(DeviceToken::COOKIE, $device)
            ->get('/order/'.$table->unique_token)
            ->assertOk();
    }

    public function test_same_ip_with_a_different_device_cookie_is_not_approved(): void
    {
        $editor = $this->makeEditor();
        [$table, $session] = $this->openTableWithSession($editor);

        TableSessionRequest::create([
            'table_session_id' => $session->id,
            'table_id' => $table->id,
            'ip_address' => '127.0.0.1',
            'device_token' => Str::random(40),
            'status' => 'approved',
            'requested_at' => now(),
            'approved_at' => now(),
        ]);

        // Another phone behind the same NAT IP presents its own cookie:
        // it must NOT inherit the first device's approval.
        $this->withUnencryptedCookie(DeviceToken::COOKIE, Str::random(40))
            ->get('/order/'.$table->unique_token)
            ->assertForbidden();
    }

    public function test_legacy_ip_only_approvals_still_work(): void
    {
        $editor = $this->makeEditor();
        [$table, $session] = $this->openTableWithSession($editor);

        // A row recorded before the cookie existed (device_token null).
        $this->approveDevice($session); // ip 127.0.0.1, no device token

        // Cookie-less request from the approved IP: allowed (fallback).
        $this->get('/order/'.$table->unique_token)->assertOk();

        // And a cookie-bearing request from the approved IP also passes
        // through the legacy IP fallback.
        $this->withUnencryptedCookie(DeviceToken::COOKIE, Str::random(40))
            ->get('/order/'.$table->unique_token)
            ->assertOk();
    }
}
