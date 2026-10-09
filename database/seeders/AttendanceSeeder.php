<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Attendance;
use App\Models\AttendanceBreak;

class AttendanceSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $users = User::all();
        $dates = ['2026-10-05', '2026-10-06'];

        foreach ($users as $user) {
            foreach ($dates as $date) {
                $exists = Attendance::where('user_id', $user->id)
                    ->where('work_date', $date)
                    ->exists();

                if ($exists) {
                    continue;
                }
                $attendance = Attendance::create([
                    'user_id' => $user->id,
                    'work_date' => $date,
                    'clock_in_at' => $date . ' 08:00:00',
                    'clock_out_at' => $date . ' 17:00:00',
                ]);

                $attendance_break = AttendanceBreak::create([
                    'attendance_id' => $attendance->id,
                    'break_start_at' => $date . ' 12:00:00',
                    'break_end_at' => $date . ' 13:00:00',
                ]);
            }
        }
    }
}