<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;
use App\Models\Attendance;
use App\Models\AttendanceBreak;

class AdminAttendanceDetailTest extends TestCase
{
    use RefreshDatabase;
    public function test_選択した勤怠の詳細が正しく表示されるか(): void
    {
        $admin = User::factory()->create([
            'admin_status' => true,
        ]);

        $user = User::factory()->create([
            'name' => '田中花子',
            'admin_status' => false,
        ]);

        $attendance = Attendance::create([
            'user_id' => $user->id,
            'work_date' => '2026-10-22',
            'clock_in_at' => '2026-10-22 08:30:00',
            'clock_out_at' => '2026-10-22 17:00:00',
        ]);

        AttendanceBreak::create([
            'attendance_id' => $attendance->id,
            'break_start_at' => '2026-10-22 12:00:00',
            'break_end_at' => '2026-10-22 12:30:00',
        ]);

        $response = $this->actingAs($admin)
            ->get('/admin/attendance/' . $attendance->id);

        $response->assertStatus(200);
        $response->assertSee('田中花子');
        $response->assertSee('08:30');
        $response->assertSee('17:00');
        $response->assertSee('12:00');
        $response->assertSee('12:30');
        $response->assertSee('2026年');
        $response->assertSee('10月22日');
    }

    public function test_出勤時刻が退勤時刻より後の場合エラーになるか(): void
    {
        $admin = User::factory()->create([
            'admin_status' => true,
        ]);

        $user = User::factory()->create([
            'admin_status' => false,
        ]);

        $attendance = Attendance::create([
            'user_id' => $user->id,
            'work_date' => '2026-10-22',
            'clock_in_at' => '2026-10-22 08:30:00',
            'clock_out_at' => '2026-10-22 17:00:00',
        ]);

        $response = $this->actingAs($admin)
            ->post('/attendance/' . $attendance->id, [
                'new_clock_in' => '18:00',
                'new_clock_out' => '17:00',
                'comment' => '打刻修正のため',
            ]);

        $response->assertSessionHasErrors([
            'new_clock_out' => '出勤時間もしくは退勤時間が不適切な値です',
        ]);
    }

    public function test_休憩開始時刻が退勤時刻より後の場合エラーになるか(): void
    {
        $admin = User::factory()->create([
            'admin_status' => true,
        ]);

        $user = User::factory()->create([
            'admin_status' => false,
        ]);

        $attendance = Attendance::create([
            'user_id' => $user->id,
            'work_date' => '2026-10-22',
            'clock_in_at' => '2026-10-22 08:30:00',
            'clock_out_at' => '2026-10-22 17:00:00',
        ]);

        $response = $this->actingAs($admin)
            ->post('/attendance/' . $attendance->id, [
                'new_clock_in' => '08:30',
                'new_clock_out' => '17:00',
                'new_break_in' => ['18:00'],
                'new_break_out' => ['18:30'],
                'comment' => '打刻修正のため',
            ]);

        $response->assertSessionHasErrors([
            'new_break_in.0' => '休憩時間が不適切な値です',
        ]);
    }

    public function test_休憩終了時刻が退勤時刻より後の場合エラーになるか(): void
    {
        $admin = User::factory()->create([
            'admin_status' => true,
        ]);

        $user = User::factory()->create([
            'admin_status' => false,
        ]);

        $attendance = Attendance::create([
            'user_id' => $user->id,
            'work_date' => '2026-10-22',
            'clock_in_at' => '2026-10-22 08:30:00',
            'clock_out_at' => '2026-10-22 17:00:00',
        ]);

        $response = $this->actingAs($admin)
            ->post('/attendance/' . $attendance->id, [
                'new_clock_in' => '08:30',
                'new_clock_out' => '17:00',
                'new_break_in' => ['16:00'],
                'new_break_out' => ['18:00'],
                'comment' => '打刻修正のため',
            ]);

        $response->assertSessionHasErrors([
            'new_break_out.0' => '休憩時間もしくは退勤時間が不適切な値です',
        ]);
    }

    public function test_備考が未入力の場合エラーになるか(): void
    {
        $admin = User::factory()->create([
            'admin_status' => true,
        ]);

        $user = User::factory()->create([
            'admin_status' => false,
        ]);

        $attendance = Attendance::create([
            'user_id' => $user->id,
            'work_date' => '2026-10-22',
            'clock_in_at' => '2026-10-22 08:30:00',
            'clock_out_at' => '2026-10-22 17:00:00',
        ]);

        $response = $this->actingAs($admin)
            ->post('/attendance/' . $attendance->id, [
                'new_clock_in' => '08:30',
                'new_clock_out' => '17:00',
                'new_break_in' => ['12:00'],
                'new_break_out' => ['13:00'],
                'comment' => '',
            ]);

        $response->assertSessionHasErrors([
            'comment' => '備考を記入してください',
        ]);
    }
}
