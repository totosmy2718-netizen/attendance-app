<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CorrectionRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'attendance_id',
        'requested_clock_in_at',
        'requested_clock_out_at',
        'memo',
        'status',
    ];
    protected $casts = [
        'requested_clock_in_at' => 'datetime',
        'requested_clock_out_at' => 'datetime',
    ];

    /**
     * この修正申請が所属する勤怠へのリレーションを定義する。
     *
     * @return BelongsTo 勤怠へのリレーション
     */
    public function attendance(): BelongsTo
    {
        return $this->belongsTo(Attendance::class);
    }


    /**
     * この修正申請に紐づく申請休憩のリレーションを定義する。
     *
     * @return HasMany 申請休憩への1対多のリレーション
     */
    public function correction_request_breaks(): HasMany
    {
        return $this->hasMany(CorrectionRequestBreak::class);
    }

}
