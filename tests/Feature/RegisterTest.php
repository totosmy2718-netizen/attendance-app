<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegisterTest extends TestCase
{
    use RefreshDatabase;

    public function test_ユーザー情報が正しく保存されるか(): void
    {
        $response = $this->post('/register', [
            'name' => '山田太郎',
            'email' => 'taro@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertRedirect('/attendance');

        $this->assertDatabaseHas('users', [
            'name' => '山田太郎',
            'email' => 'taro@example.com'
        ]);

        $this->assertAuthenticated();

    }

    public function test_名前が未入力の場合バリデーションが機能するか(): void
    {
        $response = $this->post('/register', [
            'name' => '',
            'email' => 'taro@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertSessionHasErrors([
            'name' => 'お名前を入力してください',
        ]);
    }

    public function test_メールアドレスが未入力の場合バリデーションが機能するか(): void
    {
        $response = $this->post('/register', [
            'name' => '山田太郎',
            'email' => '',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertSessionHasErrors([
            'email' => 'メールアドレスを入力してください',
        ]);
    }

    public function test_パスワードが8文字未満の場合バリデーションが機能するか(): void
    {
        $response = $this->post('/register', [
            'name' => '山田太郎',
            'email' => 'taro@example.com',
            'password' => '1234567',
            'password_confirmation' => '1234567',
        ]);

        $response->assertSessionHasErrors([
            'password' => 'パスワードは8文字以上で入力してください',
        ]);
    }

    public function test_パスワードが不一致の場合バリデーションが機能するか(): void
    {
        $response = $this->post('/register', [
            'name' => '山田太郎',
            'email' => 'taro@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password456',
        ]);

        $response->assertSessionHasErrors([
            'password' => 'パスワードと一致しません',
        ]);
    }

    public function test_パスワードが未入力の場合バリデーションが機能するか(): void
    {
        $response = $this->post('/register', [
            'name' => '山田太郎',
            'email' => 'taro@example.com',
            'password' => '',
            'password_confirmation' => 'password123',
        ]);

        $response->assertSessionHasErrors([
            'password' => 'パスワードを入力してください',
        ]);
    }
}