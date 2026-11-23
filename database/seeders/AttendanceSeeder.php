<?php

namespace Database\Seeders;

use App\Models\Attendance;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class AttendanceSeeder extends Seeder
{
    /**
     * 全ユーザーの過去6か月分の勤怠と休憩を作成する。
     *
     * @return void
     */
    public function run(): void
    {
        $currentMonth = now()->startOfMonth();

        User::all()->each(function (User $user) use ($currentMonth): void {
            for ($monthsAgo = 5; $monthsAgo >= 0; $monthsAgo--) {
                $month = $currentMonth->copy()->subMonths($monthsAgo);

                $isUser1CurrentMonth =
                    $user->email === 'user1@example.com'
                    && $monthsAgo === 0;

                $dayCount = $isUser1CurrentMonth ? 17 : 15;
                $workDate = $month->copy();
                $createdDays = 0;

                while ($createdDays < $dayCount) {
                    if ($workDate->isWeekday()) {
                        $clockIn = '09:00:00';
                        $clockOut = '18:00:00';

                        if ($isUser1CurrentMonth) {
                            if ($createdDays >= 10 && $createdDays <= 12) {
                                // 残業：3日
                                $clockOut = '20:00:00';
                            } elseif ($createdDays >= 13 && $createdDays <= 14) {
                                // 遅刻：2日
                                $clockIn = '09:30:00';
                            } elseif ($createdDays === 15) {
                                // 早退：1日
                                $clockOut = '17:00:00';
                            } elseif ($createdDays === 16) {
                                // 長時間労働：1日
                                $clockIn = '08:00:00';
                                $clockOut = '21:00:00';
                            }
                        }

                        $this->createAttendance(
                            $user,
                            $workDate,
                            $clockIn,
                            $clockOut
                        );

                        $createdDays++;
                    }

                    $workDate->addDay();
                }
            }
        });
    }

    /**
     * 指定日の勤怠と12時から13時の休憩を保存する。
     *
     * @param User $user 対象ユーザー
     * @param Carbon $workDate 勤務日
     * @param string $clockIn 出勤時刻
     * @param string $clockOut 退勤時刻
     * @return void
     */
    private function createAttendance(
        User $user,
        Carbon $workDate,
        string $clockIn,
        string $clockOut
    ): void {
        $date = $workDate->toDateString();

        $attendance = Attendance::updateOrCreate(
            [
                'user_id' => $user->id,
                'work_date' => $date,
            ],
            [
                'clock_in_at' => $date . ' ' . $clockIn,
                'clock_out_at' => $date . ' ' . $clockOut,
            ]
        );

        $attendance->attendance_breaks()->updateOrCreate(
            [
                'break_start_at' => $date . ' 12:00:00',
            ],
            [
                'break_end_at' => $date . ' 13:00:00',
            ]
        );
    }
}