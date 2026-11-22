<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ExportAttendanceRequest extends FormRequest
{
    /**
     * 管理者によるCSV出力を許可する。
     *
     * @return bool 管理者の場合はtrue
     */
    public function authorize(): bool
    {
        return $this->user()?->admin_status ?? false;
    }

    /**
     * 出力対象ユーザーと年月の検証ルールを定義する。
     *
     * @return array<string, array<int, string>> 検証ルール
     */
    public function rules(): array
    {
        return [
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'year_month' => ['required', 'date_format:Y-m'],
        ];
    }
}
