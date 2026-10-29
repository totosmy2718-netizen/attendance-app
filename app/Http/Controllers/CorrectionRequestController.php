<?php

namespace App\Http\Controllers;

use App\Models\CorrectionRequest;
use Illuminate\Support\Facades\DB;
use App\Models\CorrectionRequestBreak;
use App\Http\Requests\CorrectionRequest as CorrectionFormRequest;

class CorrectionRequestController extends Controller
{
    public function store(CorrectionFormRequest $request, $id)
    {
        $user = auth()->user();
        $attendance = $user->attendances()->findOrFail($id);

        if (
            $attendance->correction_requests()
                ->where('status', 'pending')->exists()
        ) {
            return redirect('/attendance/' . $attendance->id);
        }

        /**
         * 勤務日と入力された時刻を結合
         */
        $workDate = $attendance->work_date->toDateString();
        $newClockIn = $request->input('new_clock_in');
        $clockIn = $workDate . ' ' . $newClockIn;

        $newClockOut = $request->input('new_clock_out');
        $clockOut = $workDate . ' ' . $newClockOut;


        /**
         * 修正申請と紐づく休憩を全部保存か不備があれば全部取り消す
         */

        DB::transaction(function () use ($attendance, $clockIn, $clockOut, $request, $workDate) {


            /**
             * 修正申請勤怠を保存
             */
            $application = CorrectionRequest::create([
                'attendance_id' => $attendance->id,
                'requested_clock_in_at' => $clockIn,
                'requested_clock_out_at' => $clockOut,
                'memo' => $request->input('comment'),
                'status' => 'pending',
            ]);


            /**
             * 修正申請休憩を取得と保存
             */
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
}