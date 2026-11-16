<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AttendanceRequest extends FormRequest
{
    /**
     * このリクエストの実行を許可する。
     * ログインの確認はルートのauthミドルウェアで行う。
     *
     * @return bool 実行を許可する場合はtrue
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * 打刻操作の入力ルールを定義する。
     *
     * @return array<string, array<int, string>> 入力項目ごとの検証ルール
     */
    public function rules(): array
    {
        return [
            'action' => [
                'required',
                'in:clock_in,clock_out,break_in,break_out',
            ],
        ];
    }
}