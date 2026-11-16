<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use App\Models\Attendance;
use Illuminate\Contracts\View\View;
class StaffController extends Controller
{
    /**
     * 一般ユーザーの氏名・メールアドレスをスタッフ一覧に表示する。
     *
     * @return View スタッフ一覧画面
     */
    public function index(): View
    {
        abort_unless(auth()->user()->admin_status, 403);

        $users = User::where('admin_status', false)
            ->get();

        return view('admin.staff-list', compact('users', ));
    }

    /**
     * 指定したスタッフの月次勤怠一覧を表示する。
     *
     * @param Request $request 表示対象月を受け取るリクエスト
     * @param int $id 表示対象のスタッフID
     * @return View スタッフ別月次勤怠一覧画面
     */
    public function show(Request $request, int $id): View
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
            $attendance = $attendances->first(
                function (Attendance $record) use ($currentDate): bool {
                    return $record->work_date->toDateString()
                        === $currentDate->toDateString();
                }
            );

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