<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Attendance;

class AttendanceController extends Controller
{
    public function index()
    {
        $user = auth()->user();

        $today = now()->toDateString();

        $attendance = $user->attendances()->where('work_date', $today)->first();

        if ($attendance === null) {
            $status = '勤務外';
        } elseif ($attendance->clock_out_at !== null) {
            $status = '退勤済';
        } elseif (
            $attendance->attendance_breaks()
                ->whereNull('break_end_at')
                ->exists()
        ) {
            $status = '休憩中';
        } else {
            $status = '出勤中';
        }



        return view('user.attendance-register', compact('user', 'attendance'));
    }
}
