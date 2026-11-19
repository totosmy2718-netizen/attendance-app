<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;
use Illuminate\Support\Facades\URL;

class EmailVerificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_会員登録後に認証メールが送信されるか(): void
    {
        Notification::fake();

        $this->post('/register', [
            'name' => '山田太郎',
            'email' => 'taro@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertRedirect('/attendance');

        $user = User::where('email', 'taro@example.com')->firstOrFail();

        $this->assertNull($user->email_verified_at);

        Notification::assertSentTo($user, VerifyEmail::class);
    }

    public function test_メール認証誘導画面にメール認証サイトへのリンクがあるか(): void
    {
        $user = User::factory()->unverified()->create([
            'admin_status' => false,
        ]);

        $response = $this->actingAs($user)
            ->get(route('verification.notice'));

        $response->assertOk();
        $response->assertSee('認証はこちらから');
        $response->assertSee('href="http://localhost:8025"', false);
    }

    public function test_メール認証が完了すると勤怠登録画面へ遷移するか(): void
    {
        $user = User::factory()->unverified()->create([
            'admin_status' => false,
        ]);

        $verificationUrl = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            [
                'id' => $user->id,
                'hash' => sha1($user->getEmailForVerification()),
            ]
        );

        $response = $this->actingAs($user)
            ->get($verificationUrl);

        $this->assertNotNull($user->fresh()->email_verified_at);

        $response->assertRedirect('/attendance?verified=1');

        $this->followingRedirects()
            ->get('/attendance')
            ->assertOk()
            ->assertViewIs('user.attendance-register');
    }
}