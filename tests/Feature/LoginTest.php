<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use RefreshDatabase;
    public function test_メールアドレスが未入力の場合バリデーションが機能するか(): void
    {
        User::factory()->create([
            'email' => 'taro@example.com',
            'password' => Hash::make('password123'),
            'admin_status' => false,
        ]);

        $response = $this->post('/login', [
            'email' => '',
            'password' => 'password123',

        ]);

        $response->assertSessionHasErrors([
            'email' => 'メールアドレスを入力してください',
        ]);
    }
    public function test_パスワードが未入力の場合バリデーションが機能するか(): void
    {
        User::factory()->create([
            'email' => 'taro@example.com',
            'password' => Hash::make('password123'),
            'admin_status' => false,
        ]);

        $response = $this->post('/login', [
            'email' => 'taro@example.com',
            'password' => '',

        ]);

        $response->assertSessionHasErrors([
            'password' => 'パスワードを入力してください',
        ]);
    }

    public function test_登録内容と一致しない場合バリデーションが機能するか(): void
    {
        User::factory()->create([
            'email' => 'taro@example.com',
            'password' => Hash::make('password123'),
            'admin_status' => false,
        ]);

        $response = $this->post('/login', [
            'email' => 'jiro@example.com',
            'password' => 'password123',

        ]);

        $response->assertSessionHasErrors([
            'email' => 'ログイン情報が登録されていません',
        ]);
    }
}
