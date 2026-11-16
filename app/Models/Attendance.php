<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Attendance extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'work_date',
        'clock_in_at',
        'clock_out_at',
        'memo',
    ];
    protected $casts = [
        'work_date' => 'date',
        'clock_in_at' => 'datetime',
        'clock_out_at' => 'datetime',
    ];

    /**
     * この勤怠に紐づく休憩のリレーションを定義する。
     *
     * @return HasMany 休憩への1対多のリレーション
     */
    public function attendance_breaks(): HasMany
    {
        return $this->hasMany(AttendanceBreak::class);
    }

    /**
     * この勤怠に紐づく修正申請のリレーションを定義する。
     *
     * @return HasMany 修正申請への1対多のリレーション
     */
    public function correction_requests(): HasMany
    {
        return $this->hasMany(CorrectionRequest::class);
    }

    /**
     * この勤怠の持ち主へのリレーションを定義する。
     *
     * @return BelongsTo ユーザーへのリレーション
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }


}
