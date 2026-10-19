<?php

namespace App\Http\Controllers;

use App\Http\Requests\AttendanceRequest;
use App\Models\AttendanceBreak;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Attendance;

class AttendanceController extends Controller
{
    public function index()
    {
        $user = auth()->user();

        $today = now()->toDateString();

        $attendance = $user->attendances()->whereDate('work_date', $today)->first();

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
        $user->attendance_status = $status;

        $formattedDate = now()
            ->locale('ja')
            ->isoFormat('YYYY年M月D日(ddd)');

        $formattedTime = now()->format('H:i');

        return view('user.attendance-register', compact(
            'user',
            'formattedDate',
            'formattedTime'
        ));

    }

    public function store(AttendanceRequest $request)
    {
        $user = auth()->user();
        $today = now()->toDateString();

        $attendance = $user->attendances()
            ->whereDate('work_date', $today)
            ->first();

        //出勤打刻
        if ($request->input('action') === 'clock_in') {
            if ($attendance !== null) {
                return redirect('/attendance');
            }
            $attendance = Attendance::create([
                'user_id' => $user->id,
                'work_date' => $today,
                'clock_in_at' => now(),
            ]);
            //休憩打刻
        } elseif ($request->input('action') === 'break_in') {
            if ($attendance === null) {
                return redirect('/attendance');
            }

            if ($attendance->clock_out_at !== null) {
                return redirect('/attendance');
            }

            if (
                $attendance->attendance_breaks()
                    ->whereNull('break_end_at')
                    ->exists()
            ) {
                return redirect('/attendance');
            }
            AttendanceBreak::create([
                'attendance_id' => $attendance->id,
                'break_start_at' => now(),
            ]);
            //休憩終了打刻
        } elseif ($request->input('action') === 'break_out') {
            if ($attendance === null) {
                return redirect('/attendance');
            }
            if ($attendance->clock_out_at !== null) {
                return redirect('/attendance');
            }

            $attendance_break = $attendance->attendance_breaks()
                ->whereNull('break_end_at')
                ->first();


            if ($attendance_break === null) {
                return redirect('/attendance');
            }

            $attendance_break->update([
                'break_end_at' => now(),
            ]);


            //退勤打刻
        } elseif ($request->input('action') === 'clock_out') {
            if ($attendance === null) {
                return redirect('/attendance');
            }
            if ($attendance->clock_out_at !== null) {
                return redirect('/attendance');
            }
            if (
                $attendance->attendance_breaks()
                    ->whereNull('break_end_at')
                    ->exists()
            ) {
                return redirect('/attendance');
            }

            $attendance->update([
                'clock_out_at' => now(),
            ]);
        }
        return redirect('/attendance');
    }
}