<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttendanceBreak extends Model
{
    use HasFactory;
    protected $fillable = [
        'attendance_id',
        'break_start_at',
        'break_end_at',
    ];
    protected $casts = [
        'break_start_at' => 'datetime',
        'break_end_at' => 'datetime',
    ];

    /**
     * この休憩が所属する勤怠へのリレーションを定義する。
     *
     * @return BelongsTo 勤怠へのリレーション
     */
    public function attendance(): BelongsTo
    {
        return $this->belongsTo(Attendance::class);
    }
}
