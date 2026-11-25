<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\IndexAttendanceRecordRequest;
use App\Http\Resources\AttendanceRecordResource;
use App\Models\Attendance;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Carbon;

class AttendanceRecordController extends Controller
{
    /**
     * 検索条件に一致する勤怠をページ単位で取得する。
     *
     * @param IndexAttendanceRecordRequest $request 一覧取得リクエスト
     * @return AnonymousResourceCollection 整形済みの勤怠一覧
     */
    public function index(
        IndexAttendanceRecordRequest $request
    ): AnonymousResourceCollection {
        $validated = $request->validated();

        $attendances = Attendance::with(['user', 'attendance_breaks'])
            // ユーザーIDがあれば絞り込む
            ->when(
                isset($validated['user_id']),
                function (Builder $query) use ($validated): void {
                    $query->where('user_id', $validated['user_id']);
                }
            )
            // 日付があれば絞り込む
            ->when(
                isset($validated['date']),
                function (Builder $query) use ($validated): void {
                    $query->whereDate('work_date', $validated['date']);
                }
            )
            // 月があれば、その月の範囲に絞り込む
            ->when(
                isset($validated['month']),
                function (Builder $query) use ($validated): void {
                    $month = Carbon::parse($validated['month'] . '-01');

                    $query->whereBetween('work_date', [
                        $month->toDateString(),
                        $month->copy()->endOfMonth()->toDateString(),
                    ]);
                }
            )
            ->latest('work_date')
            ->paginate(
                (int) ($validated['per_page'] ?? 20),
                ['*'],
                'page',
                (int) ($validated['page'] ?? 1)
            );

        return AttendanceRecordResource::collection($attendances);
    }
}