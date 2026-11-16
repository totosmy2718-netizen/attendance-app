<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CorrectionRequestBreak extends Model
{
    use HasFactory;

    protected $fillable = [
        'correction_request_id',
        'requested_break_start_at',
        'requested_break_end_at',
    ];
    protected $casts = [
        'requested_break_start_at' => 'datetime',
        'requested_break_end_at' => 'datetime',
    ];

    /**
     * この申請休憩が所属する修正申請へのリレーションを定義する。
     *
     * @return BelongsTo 修正申請へのリレーション
     */
    public function correction_request(): BelongsTo
    {
        return $this->belongsTo(CorrectionRequest::class);
    }

}
