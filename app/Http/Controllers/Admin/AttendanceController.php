<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use App\Models\AttendanceBreak;
use App\Http\Requests\Admin\UpdateAttendanceRequest;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use App\Http\Requests\AttendanceDateRequest;

class AttendanceController extends Controller
{
    /**
     * 指定日の全ユーザーの勤怠を取得し、日次勤怠一覧を表示する。
     *
     * @param AttendanceDateRequest $request 表示対象日を受け取るリクエスト
     * @return View 管理者用の日次勤怠一覧画面
     */
    public function index(AttendanceDateRequest $request): View
    {
        // 管理者かどうか確認
        abort_unless(auth()->user()->admin_status, 403);

        // 表示日と前日翌日を用意
        $selectedDate = $request->input('date', now()->toDateString());
        $date = Carbon::parse($selectedDate);

        $previousDay = $date->copy()->subDay()->format('Y-m-d');
        $nextDay = $date->copy()->addDay()->format('Y-m-d');

        $attendanceRecords = Attendance::with('attendance_breaks')
            ->whereDate('work_date', $selectedDate)
            ->get();

        $users = User::get();

        foreach ($attendanceRecords as $attendance) {
            // Bladeが読む名前に合わせる
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

            // 休憩がすべて終了している場合だけ表示用の時間を作る
            if (!$hasUnfinishedBreak) {
                $breakHours = intdiv($totalBreakSeconds, 3600);
                $breakMinutes = intdiv($totalBreakSeconds % 3600, 60);

                $attendance->total_break_time = sprintf(
                    '%02d:%02d:00',
                    $breakHours,
                    $breakMinutes
                );

                // 退勤済みなら実働時間も計算
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
     * 指定した勤怠の詳細と修正入力欄を表示する。
     *
     * @param int $id 表示対象の勤怠ID
     * @return View 管理者用の勤怠詳細画面
     */
    public function show(int $id): View
    {
        abort_unless(auth()->user()->admin_status, 403);

        $attendance = Attendance::with(['user', 'attendance_breaks'])->findOrFail($id);

        $user = $attendance->user;

        $breaks = $attendance->attendance_breaks
            ->map(function (AttendanceBreak $break): array {
                return [
                    'break_in' => $break->break_start_at->format('H:i'),
                    'break_out' => $break->break_end_at?->format('H:i') ?? '',
                ];
            })
            ->all();

        $attendanceRecord = [
            'id' => $attendance->id,
            'year' => $attendance->work_date->format('Y年'),
            'date' => $attendance->work_date->format('n月j日'),
            'clock_in' => $attendance->clock_in_at->format('H:i'),
            'clock_out' => $attendance->clock_out_at?->format('H:i') ?? '',
            'breaks' => $breaks,
            'comment' => $attendance->memo ?? '',
        ];

        return view('admin.admin-detail', compact(
            'user',
            'attendanceRecord'
        ));
    }

    /**
     * 管理者として勤怠の出退勤時刻・備考を更新し、休憩を置き換える。
     *
     * @param UpdateAttendanceRequest $request 検証済みの勤怠修正リクエスト
     * @param int $id 修正対象の勤怠ID
     * @return RedirectResponse 管理者用勤怠詳細画面へのリダイレクト
     */
    public function update(UpdateAttendanceRequest $request, int $id): RedirectResponse
    {
        $validated = $request->validated();

        $attendance = Attendance::with(['user', 'attendance_breaks'])->findOrFail($id);

        $workDate = $attendance->work_date->toDateString();
        $newClockIn = $validated['new_clock_in'];
        $clockIn = $workDate . ' ' . $newClockIn;

        $newClockOut = $validated['new_clock_out'];
        $clockOut = $workDate . ' ' . $newClockOut;

        DB::transaction(function () use ($attendance, $clockIn, $clockOut, $validated, $workDate): void {
            $attendance->update([
                'clock_in_at' => $clockIn,
                'clock_out_at' => $clockOut,
                'memo' => $validated['comment'],
            ]);

            $attendance->attendance_breaks()->delete();

            // 休憩開始があれば取り出して保存
            foreach ($validated['new_break_in'] ?? [] as $index => $breakIn) {
                $breakOut = $validated['new_break_out'][$index] ?? null;

                if ($breakIn === null && $breakOut === null) {
                    continue;
                }

                AttendanceBreak::create([
                    'attendance_id' => $attendance->id,
                    'break_start_at' => $workDate . ' ' . $breakIn,
                    'break_end_at' => $workDate . ' ' . $breakOut,
                ]);
            }
        });
        return redirect('/admin/attendance/' . $attendance->id);

    }
}