<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AttendanceMonthRequest extends FormRequest
{
    /**
     * このリクエストを許可する。
     *
     * @return bool 許可する場合はtrue
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * 表示対象月の検証ルールを定義する。
     *
     * @return array<string, array<int, string>> 検証ルール
     */
    public function rules(): array
    {
        return [
            'date' => ['sometimes', 'required', 'date_format:Y-m'],
        ];
    }
}