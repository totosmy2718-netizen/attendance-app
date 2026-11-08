<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
class StaffController extends Controller
{
    /**
     * スタッフ一覧画面
     */
    public function index()
    {
        abort_unless(auth()->user()->admin_status, 403);

        $users = User::where('admin_status', false)
            ->get();

        return view('admin.staff-list', compact('users', ));
    }

    /**
     * スタッフ別勤怠一覧画面
     */
    public function show(Request $request, $id)
    {
        abort_unless(auth()->user()->admin_status, 403);

        $user = User::where('admin_status', false)->findOrFail($id);

        $request->validate([
            'date' => ['sometimes', 'required', 'date_format:Y-m'],
        ]);

        $month = $request->input('date', now()->format('Y-m'));
        $date = Carbon::parse($month . '-01');

        $previousMonth = $date->copy()->subMonth()->format('Y-m');
        $nextMonth = $date->copy()->addMonth()->format('Y-m');

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
                return $record->work_date->toDateString()
                    === $currentDate->toDateString();
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
                // 勤怠がある日の表示データを作る
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


                if (!$hasUnfinishedBreak) {
                    $breakHours = intdiv($totalBreakSeconds, 3600);
                    $breakMinutes = intdiv($totalBreakSeconds % 3600, 60);

                    $totalBreakTime = sprintf(
                        '%02d:%02d:00',
                        $breakHours,
                        $breakMinutes
                    );

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
        return view('admin.staff-attendance-list', compact(
            'user',
            'date',
            'previousMonth',
            'nextMonth',
            'formattedAttendanceRecords'
        ));
    }
}