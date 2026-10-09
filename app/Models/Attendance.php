<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Attendance extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'work_date',
        'clock_in_at',
        'clock_out_at',
    ];
    protected $casts = [
        'work_date' => 'date',
        'clock_in_at' => 'datetime',
        'clock_out_at' => 'datetime',
    ];

    public function attendance_breaks()
    {
        return $this->hasMany(AttendanceBreak::class);
    }

    public function correction_requests()
    {
        return $this->hasMany(CorrectionRequest::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

}
