<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\AttendanceBreak;
use Illuminate\Contracts\View\View;

class AttendanceReportController extends Controller
{
    /**
     * ログインユーザーの過去6か月の勤怠統計を表示する。
     *
     * @return View マイ勤怠レポート画面
     */
    public function index(): View
    {
        $currentMonth = now()->startOfMonth();
        $startMonth = $currentMonth->copy()->subMonths(5);
        $endMonth = $currentMonth->copy()->endOfMonth();

        // 本人の勤怠と休憩をまとめて取得する
        $attendances = auth()->user()->attendances()
            ->with('attendance_breaks')
            ->whereBetween('work_date', [
                $startMonth->toDateString(),
                $endMonth->toDateString(),
            ])
            ->get();

        // 退勤とすべての休憩が終了している勤怠を集計する
        $dailyRecords = $attendances
            ->filter(function (Attendance $attendance): bool {
                return $attendance->clock_in_at !== null
                    && $attendance->clock_out_at !== null
                    && !$attendance->attendance_breaks->contains(
                        function (AttendanceBreak $break): bool {
                            return $break->break_end_at === null;
                        }
                    );
            })
            ->map(function (Attendance $attendance): array {
                $breakSeconds = $attendance->attendance_breaks
                    ->sum(function (AttendanceBreak $break): int {
                        return (int) $break->break_start_at
                            ->diffInSeconds($break->break_end_at);
                    });

                $workSeconds = max(
                    0,
                    (int) $attendance->clock_in_at
                        ->diffInSeconds($attendance->clock_out_at)
                    - $breakSeconds
                );

                return [
                    'month' => $attendance->work_date->format('Y-m'),
                    'work_seconds' => $workSeconds,
                    'overtime_seconds' => max(
                        0,
                        $workSeconds - 8 * 3600
                    ),
                    'is_late' => $attendance->clock_in_at
                        ->format('H:i:s') > '09:00:00',
                    'is_early_leave' => $attendance->clock_out_at
                        ->format('H:i:s') < '18:00:00',
                    'is_long_work' => $workSeconds > 10 * 3600,
                ];
            });

        $totalWorkSeconds = (int) $dailyRecords->sum('work_seconds');
        $totalOvertimeSeconds = (int) $dailyRecords
            ->sum('overtime_seconds');
        $workingDays = $dailyRecords->count();

        // 基本サマリー
        $summary = [
            'total_work_minutes' => intdiv($totalWorkSeconds, 60),
            'total_overtime_minutes' => intdiv(
                $totalOvertimeSeconds,
                60
            ),
            'avg_work_minutes' => $workingDays > 0
                ? intdiv($totalWorkSeconds, $workingDays * 60)
                : 0,
        ];

        // 勤怠がない月も含め、古い月から6か月分を作る
        $monthlyTrend = collect(range(0, 5))
            ->map(function (int $offset) use ($startMonth, $dailyRecords): array {
                $month = $startMonth->copy()->addMonths($offset);

                $monthlyRecords = $dailyRecords->where(
                    'month',
                    $month->format('Y-m')
                );

                return [
                    'month' => $month->format('Y/m'),
                    'work_minutes' => intdiv(
                        (int) $monthlyRecords->sum('work_seconds'),
                        60
                    ),
                    'overtime_minutes' => intdiv(
                        (int) $monthlyRecords->sum('overtime_seconds'),
                        60
                    ),
                ];
            })
            ->all();

        // 異常検知は当月分を集計する
        $currentMonthRecords = $dailyRecords->where(
            'month',
            $currentMonth->format('Y-m')
        );

        $anomalies = [
            'late_count' => $currentMonthRecords
                ->where('is_late', true)
                ->count(),
            'early_leave_count' => $currentMonthRecords
                ->where('is_early_leave', true)
                ->count(),
            'long_work_count' => $currentMonthRecords
                ->where('is_long_work', true)
                ->count(),
        ];

        return view('reports.index', compact(
            'summary',
            'monthlyTrend',
            'anomalies'
        ));
    }
}