<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

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

    public function attendance()
    {
        return $this->belongsTo(Attendance::class);
    }

    public function correction_request_breaks()
    {
        return $this->hasMany(CorrectionRequestBreak::class);
    }

}
