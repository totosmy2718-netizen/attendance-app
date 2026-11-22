<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\AttendanceBreak;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminAttendanceCsvTest extends TestCase
{
    use RefreshDatabase;

    public function test_スタッフの月次勤怠をCSVでダウンロードできるか(): void
    {
        $admin = User::factory()->create([
            'admin_status' => true,
        ]);

        $user = User::factory()->create([
            'admin_status' => false,
        ]);

        $attendance = Attendance::create([
            'user_id' => $user->id,
            'work_date' => '2026-11-02',
            'clock_in_at' => '2026-11-02 09:00:00',
            'clock_out_at' => '2026-11-02 17:00:00',
        ]);

        AttendanceBreak::create([
            'attendance_id' => $attendance->id,
            'break_start_at' => '2026-11-02 12:00:00',
            'break_end_at' => '2026-11-02 13:00:00',
        ]);

        $response = $this->actingAs($admin)
            ->post('/export', [
                'user_id' => $user->id,
                'year_month' => '2026-11',
            ]);

        $response->assertStatus(200);

        $response->assertDownload(
            'attendance_' . $user->id . '_2026-11.csv'
        );

        $response->assertSee('日付,出勤,退勤,休憩,合計', false);
        $response->assertSee('11/02(月),09:00,17:00,1:00,7:00', false);
    }

    public function test_指定したスタッフと月の勤怠だけがCSVに出力されるか(): void
    {
        $admin = User::factory()->create([
            'admin_status' => true,
        ]);

        $user = User::factory()->create([
            'admin_status' => false,
        ]);

        $otherUser = User::factory()->create([
            'admin_status' => false,
        ]);

        // 出力対象
        Attendance::create([
            'user_id' => $user->id,
            'work_date' => '2026-11-02',
            'clock_in_at' => '2026-11-02 09:00:00',
            'clock_out_at' => '2026-11-02 17:00:00',
        ]);

        // 同じスタッフだが、対象外の月
        Attendance::create([
            'user_id' => $user->id,
            'work_date' => '2026-10-02',
            'clock_in_at' => '2026-10-02 06:00:00',
            'clock_out_at' => '2026-10-02 14:00:00',
        ]);

        // 同じ月だが、別のスタッフ
        Attendance::create([
            'user_id' => $otherUser->id,
            'work_date' => '2026-11-02',
            'clock_in_at' => '2026-11-02 11:00:00',
            'clock_out_at' => '2026-11-02 19:00:00',
        ]);

        $response = $this->actingAs($admin)
            ->post('/export', [
                'user_id' => $user->id,
                'year_month' => '2026-11',
            ]);

        $response->assertStatus(200);
        $response->assertSee('11/02(月),09:00,17:00', false);
        $response->assertDontSee('06:00,14:00', false);
        $response->assertDontSee('11:00,19:00', false);
    }

    public function test_一般ユーザーはCSVを出力できないか(): void
    {
        $user = User::factory()->create([
            'admin_status' => false,
        ]);

        $response = $this->actingAs($user)
            ->post('/export', [
                'user_id' => $user->id,
                'year_month' => '2026-11',
            ]);

        $response->assertForbidden();
    }
}