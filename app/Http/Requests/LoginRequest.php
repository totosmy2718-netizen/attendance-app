<?php

namespace App\Http\Requests;

use Laravel\Fortify\Http\Requests\LoginRequest as FortifyLoginRequest;

class LoginRequest extends FortifyLoginRequest
{
    /**
     * ログインリクエストの実行を許可する。
     * メールアドレス・パスワードの照合はFortifyで行う。
     *
     * @return bool 実行を許可する場合はtrue
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * ログイン時の入力ルールを定義する。
     *
     * @return array<string, array<int, string>> 入力項目ごとの検証ルール
     */
    public function rules(): array
    {

        return [
            'email' => ['required'],
            'password' => ['required'],
        ];
    }

    /**
     * 未入力時に表示する日本語メッセージを定義する。
     *
     * @return array<string, string> 検証ルールごとのエラーメッセージ
     */
    public function messages(): array
    {
        return [
            'email.required' => 'メールアドレスを入力してください',
            'password.required' => 'パスワードを入力してください',
        ];
    }
}
