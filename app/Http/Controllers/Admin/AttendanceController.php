<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class AttendanceController extends Controller
{
    public function index(Request $request)
    {
        /**
         *管理者かどうか確認
         */
        abort_unless(auth()->user()->admin_status, 403);

        $request->validate([
            'date' => ['sometimes', 'required', 'date_format:Y-m-d'],
        ]);

        /**
         * 表示日と前日翌日を用意
         */
        $selectedDate = $request->input('date', now()->toDateString());
        $date = Carbon::parse($selectedDate);

        $previousDay = $date->copy()->subDay()->format('Y-m-d');
        $nextDay = $date->copy()->addDay()->format('Y-m-d');

        $attendanceRecords = Attendance::with('attendance_breaks')
            ->whereDate('work_date', $selectedDate)
            ->get();

        $users = User::get();

        foreach ($attendanceRecords as $attendance) {
            /**
             *Bladeが読む名前に合わせる
             */
            $attendance->clock_in = $attendance->clock_in_at;
            $attendance->clock_out = $attendance->clock_out_at;

            $totalBreakSeconds = 0;
            $hasUnfinishedBreak = false;

            $attendance->total_break_time = '';
            $attendance->total_time = '';

            foreach ($attendance->attendance_breaks as $break) {
                if ($break->break_end_at === null) {
                    $hasUnfinishedBreak = true;
                    continue;
                }

                $totalBreakSeconds += $break->break_start_at
                    ->diffInSeconds($break->break_end_at);
            }
            /**
             * 休憩がすべて終了している場合だけ表示用の時間を作る
             */
            if (!$hasUnfinishedBreak) {
                $breakHours = intdiv($totalBreakSeconds, 3600);
                $breakMinutes = intdiv($totalBreakSeconds % 3600, 60);

                $attendance->total_break_time = sprintf(
                    '%02d:%02d:00',
                    $breakHours,
                    $breakMinutes
                );
                /**
                 *退勤済みなら実働時間も計算
                 */
                if ($attendance->clock_out_at !== null) {
                    $totalSeconds = $attendance->clock_in_at
                        ->diffInSeconds($attendance->clock_out_at)
                        - $totalBreakSeconds;

                    $workHours = intdiv($totalSeconds, 3600);
                    $workMinutes = intdiv($totalSeconds % 3600, 60);

                    $attendance->total_time = sprintf(
                        '%02d:%02d:00',
                        $workHours,
                        $workMinutes
                    );
                }
            }
        }

        return view('admin.admin-attendance-list', compact(
            'date',
            'previousDay',
            'nextDay',
            'users',
            'attendanceRecords'
        ));
    }

    /**
     *管理者用勤怠詳細画面
     */
    public function show($id)
    {
        abort_unless(auth()->user()->admin_status, 403);

        $attendance = Attendance::with(['user', 'attendance_breaks'])->findOrFail($id);

        $user = $attendance->user;

        $breaks = [];
        foreach ($attendance->attendance_breaks as $break) {
            $breaks[] = [
                'break_in' => $break->break_start_at->format('H:i'),
                'break_out' => $break->break_end_at?->format('H:i') ?? '',
            ];
        }

        $attendanceRecord = [
            'id' => $attendance->id,
            'year' => $attendance->work_date->format('Y年'),
            'date' => $attendance->work_date->format('n月j日'),
            'clock_in' => $attendance->clock_in_at->format('H:i'),
            'clock_out' => $attendance->clock_out_at?->format('H:i') ?? '',
            'breaks' => $breaks,
            'comment' => '',
        ];

        return view('admin.admin-detail', compact(
            'user',
            'attendanceRecord'
        ));
    }
}