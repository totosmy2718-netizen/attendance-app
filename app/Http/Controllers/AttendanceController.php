<?php

namespace App\Http\Controllers;

use App\Http\Requests\AttendanceRequest;
use App\Models\AttendanceBreak;
use Illuminate\Http\Request;
use App\Models\Attendance;
use Illuminate\Support\Carbon;

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

    public function list(Request $request)
    {
        $request->validate([
            'date' => ['sometimes', 'required', 'date_format:Y-m'],
        ]);

        $month = $request->input('date', now()->format('Y-m'));
        $date = Carbon::parse($month . '-01');

        $previousMonth = $date->copy()->subMonth()->format('Y-m');
        $nextMonth = $date->copy()->addMonth()->format('Y-m');

        $user = auth()->user();

        $attendances = $user->attendances()
            ->with('attendance_breaks')
            ->whereYear('work_date', $date->year)
            ->whereMonth('work_date', $date->month)
            ->orderBy('work_date')->get();

        $endOfMonth = $date->copy()->endOfMonth();

        $formattedAttendanceRecords = [];
        $currentDate = $date->copy();

        while ($currentDate->lte($endOfMonth)) {

            $attendance = $attendances->first(function ($record) use ($currentDate) {
                return $record->work_date->toDateString() === $currentDate->toDateString();
            });

            if ($attendance === null) {
                $formattedAttendanceRecords[] = [
                    'id' => null,
                    'date' => $currentDate->locale('ja')->isoFormat('MM/DD(ddd)'),
                    'clock_in' => '',
                    'clock_out' => '',
                    'total_break_time' => '',
                    'total_time' => '',
                ];
            } else {
                // 休憩時間を秒で合計する
                $totalBreakSeconds = 0;
                $hasUnfinishedBreak = false;

                foreach ($attendance->attendance_breaks as $break) {
                    if ($break->break_end_at === null) {
                        $hasUnfinishedBreak = true;
                        continue;
                    }

                    $totalBreakSeconds += $break->break_start_at
                        ->diffInSeconds($break->break_end_at);
                }

                $totalBreakTime = '';
                $totalTime = '';

                // 休憩がすべて終了している場合
                if (!$hasUnfinishedBreak) {
                    $breakHours = intdiv($totalBreakSeconds, 3600);
                    $breakMinutes = intdiv($totalBreakSeconds % 3600, 60);

                    $totalBreakTime = sprintf(
                        '%02d:%02d:00',
                        $breakHours,
                        $breakMinutes
                    );

                    // 退勤済みなら、実働時間も計算する
                    if ($attendance->clock_out_at !== null) {
                        $totalSeconds = $attendance->clock_in_at
                            ->diffInSeconds($attendance->clock_out_at)
                            - $totalBreakSeconds;

                        $workHours = intdiv($totalSeconds, 3600);
                        $workMinutes = intdiv($totalSeconds % 3600, 60);

                        $totalTime = sprintf(
                            '%02d:%02d:00',
                            $workHours,
                            $workMinutes
                        );
                    }
                }

                $formattedAttendanceRecords[] = [
                    'id' => $attendance->id,
                    'date' => $currentDate->locale('ja')->isoFormat('MM/DD(ddd)'),
                    'clock_in' => $attendance->clock_in_at->format('H:i'),
                    'clock_out' => $attendance->clock_out_at?->format('H:i') ?? '',
                    'total_break_time' => $totalBreakTime,
                    'total_time' => $totalTime,
                ];
            }

            $currentDate->addDay();
        }
        return view('user.user-attendance-list', compact(
            'date',
            'previousMonth',
            'nextMonth',
            'formattedAttendanceRecords'
        ));
    }
}