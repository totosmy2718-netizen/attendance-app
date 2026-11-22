<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Support\Carbon;
use App\Models\Attendance;
use Illuminate\Contracts\View\View;
use App\Http\Requests\AttendanceMonthRequest;
use App\Http\Requests\ExportAttendanceRequest;
use Illuminate\Http\Response;

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
     * @param AttendanceMonthRequest $request 表示対象月を受け取るリクエスト
     * @param int $id 表示対象のスタッフID
     * @return View スタッフ別月次勤怠一覧画面
     */
    public function show(AttendanceMonthRequest $request, int $id): View
    {
        abort_unless(auth()->user()->admin_status, 403);

        $user = User::where('admin_status', false)->findOrFail($id);

        $month = $request->input('date', now()->format('Y-m'));
        $date = Carbon::parse($month . '-01');

        $previousMonth = $date->copy()->subMonth()->format('Y-m');
        $nextMonth = $date->copy()->addMonth()->format('Y-m');

        $formattedAttendanceRecords =
            $this->buildMonthlyAttendanceRecords($user, $date);

        return view('admin.staff-attendance-list', compact(
            'user',
            'date',
            'previousMonth',
            'nextMonth',
            'formattedAttendanceRecords'
        ));
    }

    /**
     * 指定スタッフの選択月の勤怠をCSVで出力する。
     *
     * @param ExportAttendanceRequest $request CSV出力リクエスト
     * @return Response CSVのダウンロードレスポンス
     */

    public function export(ExportAttendanceRequest $request): Response
    {
        $validated = $request->validated();

        $user = User::where('admin_status', false)
            ->findOrFail($validated['user_id']);

        $date = Carbon::parse($validated['year_month'] . '-01');

        $formattedAttendanceRecords =
            $this->buildMonthlyAttendanceRecords($user, $date);

        $handle = fopen('php://temp', 'r+');

        fputcsv(
            $handle,
            ['日付', '出勤', '退勤', '休憩', '合計'],
            ',',
            '"',
            ''
        );
        // 1日分ずつ勤怠データを作る
        foreach ($formattedAttendanceRecords as $record) {
            $breakTime = $record['total_break_time'] !== ''
                ? Carbon::parse($record['total_break_time'])->format('G:i')
                : '';

            $workTime = $record['total_time'] !== ''
                ? Carbon::parse($record['total_time'])->format('G:i')
                : '';

            fputcsv(
                $handle,
                [
                    $record['date'],
                    $record['clock_in'],
                    $record['clock_out'],
                    $breakTime,
                    $workTime,
                ],
                ',',
                '"',
                ''
            );
        }
        rewind($handle);

        $csvData = stream_get_contents($handle);

        fclose($handle);

        $fileName = 'attendance_' . $user->id . '_' . $date->format('Y-m') . '.csv';

        return response($csvData)
            ->header('Content-Type', 'text/csv; charset=UTF-8')
            ->header('Content-Disposition', 'attachment; filename="' . $fileName . '"');
    }

    /**
     * 指定スタッフ・月の勤怠一覧データを作成する。
     *
     * @param User $user 対象スタッフ
     * @param Carbon $date 対象月の初日
     * @return array<int, array<string, int|string|null>> 日付ごとの勤怠データ
     */
    private function buildMonthlyAttendanceRecords(User $user, Carbon $date): array
    {
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

        return $formattedAttendanceRecords;
    }


}