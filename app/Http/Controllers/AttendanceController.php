<?php

namespace App\Http\Controllers;

use App\Http\Requests\AttendanceRequest;
use App\Models\AttendanceBreak;
use Illuminate\Http\Request;
use App\Models\Attendance;
use Illuminate\Support\Carbon;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use App\Models\CorrectionRequestBreak;

class AttendanceController extends Controller
{
    /**
     * 当日の勤務状態・現在日時を取得し、打刻画面を表示する。
     *
     * @return View 勤怠登録画面
     */
    public function index(): View
    {
        $user = auth()->user();

        $today = now()->toDateString();

        $attendance = $user->attendances()->whereDate('work_date', $today)->first();

        if ($attendance === null) {
            $status = '勤務外';
        } elseif ($attendance->clock_out_at !== null) {
            $status = '退勤済';
        } elseif ($attendance->attendance_breaks()->whereNull('break_end_at')->exists()) {
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

    /**
     * ログインユーザーの出勤・休憩開始・休憩終了・退勤を打刻する。
     *
     * @param AttendanceRequest $request 検証済みの打刻リクエスト
     * @return RedirectResponse 勤怠登録画面へのリダイレクト
     */
    public function store(AttendanceRequest $request): RedirectResponse
    {
        $user = auth()->user();
        $today = now()->toDateString();

        $attendance = $user->attendances()
            ->whereDate('work_date', $today)
            ->first();

        // 出勤打刻
        if ($request->input('action') === 'clock_in') {
            if ($attendance !== null) {
                return redirect('/attendance');
            }
            $attendance = Attendance::create([
                'user_id' => $user->id,
                'work_date' => $today,
                'clock_in_at' => now(),
            ]);
            // 休憩打刻
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
            // 休憩終了打刻
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


            // 退勤打刻
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

    /**
     * 指定月の本人の勤怠を取得し、月次勤怠一覧を表示する。
     *
     * @param Request $request 表示対象月を受け取るリクエスト
     * @return View 月次勤怠一覧画面
     */
    public function list(Request $request): View
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
                // 休憩時間を秒で合計する
                $breaks = $attendance->attendance_breaks;

                // 終了していない休憩があるか確認する
                $hasUnfinishedBreak = $breaks->contains(
                    function (AttendanceBreak $break): bool {
                        return $break->break_end_at === null;
                    }
                );

                // 終了した休憩だけを対象に、休憩時間を秒で合計する
                $totalBreakSeconds = $breaks
                    ->whereNotNull('break_end_at')
                    ->sum(function (AttendanceBreak $break): int {
                        return (int) $break->break_start_at
                            ->diffInSeconds($break->break_end_at);
                    });

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


    /**
     * 本人の勤怠詳細を表示する。
     * 承認待ちの修正申請がある場合は、申請内容を表示する。
     *
     * @param int $id 表示対象の勤怠ID
     * @return View 勤怠詳細画面
     */

    public function show(int $id): View
    {
        $user = auth()->user();
        $attendance = $user->attendances()
            ->with('attendance_breaks')
            ->findOrFail($id);

        $application = $attendance->correction_requests()
            ->where('status', 'pending')
            ->first();


        // 承認待ち申請がない場合の表示
        if ($application === null) {

            $clockIn = $attendance->clock_in_at->format('H:i');
            $clockOut = $attendance->clock_out_at?->format('H:i');
            $comment = $attendance->memo ?? '';

            $breaks = $attendance->attendance_breaks
                ->map(function (AttendanceBreak $break): array {
                    return [
                        'break_in' => $break->break_start_at->format('H:i'),
                        'break_out' => $break->break_end_at?->format('H:i') ?? '',
                    ];
                })
                ->all();

            // 承認待ちがある場合
        } else {
            $clockIn = $application->requested_clock_in_at->format('H:i');
            $clockOut = $application->requested_clock_out_at->format('H:i') ?? '';
            $comment = $application->memo;

            $breaks = $application->correction_request_breaks
                ->map(function (CorrectionRequestBreak $break): array {
                    return [
                        'break_in' => $break->requested_break_start_at->format('H:i'),
                        'break_out' => $break->requested_break_end_at->format('H:i'),
                    ];
                })
                ->all();
        }

        $data = [
            'id' => $attendance->id,
            'year' => $attendance->work_date->format('Y年'),
            'date' => $attendance->work_date->format('n月j日'),
            'clock_in' => $clockIn,
            'clock_out' => $clockOut,
            'comment' => $comment,
            'application' => $application,
            'breaks' => $breaks,
        ];

        return view('user.user-detail', compact('user', 'data'));
    }
}