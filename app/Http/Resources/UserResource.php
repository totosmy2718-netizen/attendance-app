<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    /**
     * ユーザー情報をAPIのレスポンス用に整形する。
     *
     * @param Request $request APIリクエスト
     * @return array<string, mixed> ユーザー情報
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
        ];
    }
}