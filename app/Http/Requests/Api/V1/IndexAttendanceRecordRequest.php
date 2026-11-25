<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class IndexAttendanceRecordRequest extends FormRequest
{
    /**
     * 認証不要の一覧取得リクエストを許可する。
     *
     * @return bool リクエストを許可する
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * 勤怠一覧の検索条件とページ指定を検証する。
     *
     * @return array<string, array<int, string>> 検証ルール
     */
    public function rules(): array
    {
        return [
            'user_id' => ['nullable', 'integer'],
            'date' => ['nullable', 'date_format:Y-m-d'],
            'month' => ['nullable', 'date_format:Y-m'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }

    /**
     * バリデーションエラーの日本語メッセージを定義する。
     *
     * @return array<string, string> エラーメッセージ
     */
    public function messages(): array
    {
        return [
            'user_id.integer' => 'ユーザーIDは整数で指定してください。',
            'date.date_format' => '日付は YYYY-MM-DD 形式で指定してください。',
            'month.date_format' => '年月は YYYY-MM 形式で指定してください。',
            'page.integer' => 'ページ番号は整数で指定してください。',
            'page.min' => 'ページ番号は1以上で指定してください。',
            'per_page.integer' => '表示件数は整数で指定してください。',
            'per_page.min' => '表示件数は1以上で指定してください。',
            'per_page.max' => '表示件数は100以下で指定してください。',
        ];
    }
}