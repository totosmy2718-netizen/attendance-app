<?php

namespace App\Http\Controllers;

use App\Models\CorrectionRequest;
use Illuminate\Support\Facades\DB;
use App\Models\CorrectionRequestBreak;
use App\Http\Requests\CorrectionRequest as CorrectionFormRequest;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Database\Eloquent\Builder;

class CorrectionRequestController extends Controller
{
    /**
     * 本人の勤怠に対する修正申請と申請休憩を保存する。
     *
     * @param CorrectionFormRequest $request 検証済みの修正申請リクエスト
     * @param int $id 修正対象の勤怠ID
     * @return RedirectResponse 勤怠詳細画面へのリダイレクト
     */
    public function store(CorrectionFormRequest $request, int $id): RedirectResponse
    {
        $user = auth()->user();
        $attendance = $user->attendances()->findOrFail($id);

        if (
            $attendance->correction_requests()
                ->where('status', 'pending')->exists()
        ) {
            return redirect('/attendance/' . $attendance->id);
        }

        // 勤務日と入力された時刻を結合
        $workDate = $attendance->work_date->toDateString();
        $newClockIn = $request->input('new_clock_in');
        $clockIn = $workDate . ' ' . $newClockIn;

        $newClockOut = $request->input('new_clock_out');
        $clockOut = $workDate . ' ' . $newClockOut;


        // 修正申請と紐づく休憩を全部保存か不備があれば全部取り消す
        DB::transaction(function () use ($attendance, $clockIn, $clockOut, $request, $workDate): void {

            // 修正申請勤怠を保存
            $application = CorrectionRequest::create([
                'attendance_id' => $attendance->id,
                'requested_clock_in_at' => $clockIn,
                'requested_clock_out_at' => $clockOut,
                'memo' => $request->input('comment'),
                'status' => 'pending',
            ]);


            // 修正申請休憩を取得と保存
            foreach ($request->input('new_break_in', []) as $index => $breakIn) {
                $breakOut = $request->input('new_break_out.' . $index);

                if ($breakIn === null && $breakOut === null) {
                    continue;
                }

                CorrectionRequestBreak::create([
                    'correction_request_id' => $application->id,
                    'requested_break_start_at' => $workDate . ' ' . $breakIn,
                    'requested_break_end_at' => $workDate . ' ' . $breakOut,
                ]);
            }
        });
        return redirect('/attendance/' . $attendance->id);
    }

    /**
     * 本人の修正申請を取得し、申請一覧画面を表示する。
     *
     * @return View 申請一覧画面
     */
    public function index(): View
    {
        $user = auth()->user();
        $applications = CorrectionRequest::whereHas('attendance', function (Builder $query) use ($user): void {
            $query->where('user_id', $user->id);
        })
            ->with('attendance')->get();

        $formattedApplications = $applications
            ->map(function (CorrectionRequest $application): array {
                if ($application->status === 'pending') {
                    $approvalStatus = '承認待ち';
                } else {
                    $approvalStatus = '承認済み';
                }

                return [
                    'id' => $application->id,
                    'approval_status' => $approvalStatus,
                    'date' => $application->attendance->work_date->format('Y/m/d'),
                    'application_date' => $application->created_at->format('Y/m/d'),
                    'comment' => $application->memo,
                ];
            })
            ->all();

        return view(
            'user.user-application-list',
            compact('user', 'formattedApplications')
        );

    }
    /**
     * 本人の申請IDに対応する勤怠詳細画面へ移動する。
     *
     * @param int $id 表示対象の修正申請ID
     * @return RedirectResponse 勤怠詳細画面へのリダイレクト
     */
    public function show(int $id): RedirectResponse
    {
        $user = auth()->user();
        $application = CorrectionRequest::whereHas('attendance', function (Builder $query) use ($user): void {
            $query->where('user_id', $user->id);
        })
            ->findOrFail($id);

        return redirect('/attendance/' . $application->attendance_id);
    }
}