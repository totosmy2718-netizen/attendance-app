<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Models\AttendanceBreak;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class AttendanceRecordResource extends JsonResource
{
    /**
     * 勤怠データをAPIのレスポンス用に整形する。
     *
     * @param Request $request APIリクエスト
     * @return array<string, mixed> レスポンスの各項目
     */
    public function toArray(Request $request): array
    {
        $breaks = $this->attendance_breaks;

        $hasUnfinishedBreak = $breaks->contains(
            function (AttendanceBreak $break): bool {
                return $break->break_end_at === null;
            }
        );
        // 合計時間の初期値を用意
        $totalBreakTime = null;
        $totalTime = null;

        // 全休憩が終了している場合に計算
        if (!$hasUnfinishedBreak) {
            $totalBreakSeconds = (int) $breaks->sum(
                function (AttendanceBreak $break): int {
                    return (int) $break->break_start_at
                        ->diffInSeconds($break->break_end_at);
                }
            );

            // 秒数を文字列にする
            $totalBreakTime = sprintf(
                '%02d:%02d',
                intdiv($totalBreakSeconds, 3600),
                intdiv($totalBreakSeconds % 3600, 60)
            );

            // 出勤と退勤があるか
            if (
                $this->clock_in_at !== null
                && $this->clock_out_at !== null
            ) {
                // 休憩時間を引く
                $totalSeconds = (int) $this->clock_in_at
                    ->diffInSeconds($this->clock_out_at)
                    - $totalBreakSeconds;

                $totalTime = sprintf(
                    '%02d:%02d',
                    intdiv($totalSeconds, 3600),
                    intdiv($totalSeconds % 3600, 60)
                );
            }
        }

        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'user' => new UserResource($this->whenLoaded('user')),
            'date' => $this->work_date->format('Y-m-d'),
            'clock_in' => $this->clock_in_at?->format('H:i:s'),
            'clock_out' => $this->clock_out_at?->format('H:i:s'),
            'total_time' => $totalTime,
            'total_break_time' => $totalBreakTime,
            'comment' => $this->memo,
            'breaks' => $this->when(
                $request->isMethod('GET')
                && $request->route('attendanceRecord') !== null,
                function (): AnonymousResourceCollection {
                    return AttendanceBreakResource::collection(
                        $this->whenLoaded('attendance_breaks')
                    );
                }
            ),
        ];
    }
}