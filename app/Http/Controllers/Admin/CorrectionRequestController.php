<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CorrectionRequest;
use Illuminate\Support\Facades\DB;

class CorrectionRequestController extends Controller
{
    /**
     * 管理者用の修正申請一覧を表示する
     */
    public function index()
    {
        abort_unless(auth()->user()->admin_status, 403);

        $applications = CorrectionRequest::whereHas('attendance.user', function ($query) {
            $query->where('admin_status', false);
        })
            ->with('attendance.user')
            ->get();

        // Bladeの表示項目に合わせる
        foreach ($applications as $application) {
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
        }
        return view('admin.admin-application-list', compact('applications'));
    }


    /**
     * 管理者用の修正申請詳細を表示する
     */
    public function show($id)
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
     * 修正申請を承認する
     */
    public function approve($id)
    {
        abort_unless(auth()->user()->admin_status, 403);

        $application = CorrectionRequest::with([
            'attendance',
            'correction_request_breaks',
        ])->findOrFail($id);

        if ($application->status === 'approved') {
            return redirect('/stamp_correction_request/approve/' . $application->id);
        }

        DB::transaction(function () use ($application) {
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
