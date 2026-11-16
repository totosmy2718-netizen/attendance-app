<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CorrectionRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;

class CorrectionRequestController extends Controller
{
    /**
     * 全一般ユーザーの修正申請を取得し、申請一覧を表示する。
     *
     * @return View 管理者用の申請一覧画面
     */
    public function index(): View
    {
        abort_unless(auth()->user()->admin_status, 403);

        $applications = CorrectionRequest::whereHas(
            'attendance.user',
            function (Builder $query): void {
                $query->where('admin_status', false);
            }
        )
            ->with('attendance.user')
            ->get();

        // Bladeの表示項目に合わせる
        $applications->each(function (CorrectionRequest $application): void {
            if ($application->status === 'pending') {
                $application->approval_status = '承認待ち';
            } else {
                $application->approval_status = '承認済み';
            }

            $application->comment = $application->memo;
            $application->application_date = $application->created_at;

            $application->setRelation(
                'user',
                $application->attendance->user
            );

            $attendance = $application->attendance;
            $attendance->date = $attendance->work_date;

            $application->setRelation('AttendanceRecord', $attendance);
        });
        return view('admin.admin-application-list', compact('applications'));
    }


    /**
     * 指定した修正申請の詳細を表示する。
     *
     * @param int $id 表示対象の修正申請ID
     * @return View 管理者用の修正申請詳細画面
     */
    public function show(int $id): View
    {
        abort_unless(auth()->user()->admin_status, 403);

        $application = CorrectionRequest::with([
            'attendance.user',
            'correction_request_breaks',
        ])->findOrFail($id);

        $user = $application->attendance->user;

        // Bladeの表示項目に合わせる
        $application->new_date = $application->attendance->work_date;
        $application->new_clock_in = $application->requested_clock_in_at->format('H:i');
        $application->new_clock_out = $application->requested_clock_out_at->format('H:i');
        $application->comment = $application->memo;

        foreach ($application->correction_request_breaks as $break) {
            $break->break_in = $break->requested_break_start_at;
            $break->break_out = $break->requested_break_end_at;
        }

        $application->setRelation(
            'proposalBreaks',
            $application->correction_request_breaks
        );

        if ($application->status === 'pending') {
            $application->approval_status = '承認待ち';
        } else {
            $application->approval_status = '承認済み';
        }
        return view('admin.admin-application-detail', compact(
            'user',
            'application'
        ));
    }

    /**
     * 修正申請を承認し、勤怠の出退勤時刻・休憩・備考へ反映する。
     *
     * @param int $id 承認対象の修正申請ID
     * @return RedirectResponse 修正申請詳細画面へのリダイレクト
     */
    public function approve(int $id): RedirectResponse
    {
        abort_unless(auth()->user()->admin_status, 403);

        $application = CorrectionRequest::with([
            'attendance',
            'correction_request_breaks',
        ])->findOrFail($id);

        if ($application->status === 'approved') {
            return redirect('/stamp_correction_request/approve/' . $application->id);
        }

        DB::transaction(function () use ($application): void {
            $attendance = $application->attendance;

            $attendance->update([
                'clock_in_at' => $application->requested_clock_in_at,
                'clock_out_at' => $application->requested_clock_out_at,
                'memo' => $application->memo,
            ]);

            // 保存されている休憩を申請された休憩に置き換え
            $attendance->attendance_breaks()->delete();

            foreach ($application->correction_request_breaks as $break) {
                $attendance->attendance_breaks()->create([
                    'break_start_at' => $break->requested_break_start_at,
                    'break_end_at' => $break->requested_break_end_at,
                ]);
            }

            $application->update([
                'status' => 'approved',
            ]);
        });
        return redirect('/stamp_correction_request/approve/' . $application->id);
    }
}
