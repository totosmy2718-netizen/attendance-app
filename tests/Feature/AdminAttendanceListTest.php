<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\Attendance;
use App\Models\AttendanceBreak;
use App\Models\User;
use Illuminate\Support\Carbon;
class AdminAttendanceListTest extends TestCase
{
    use RefreshDatabase;

    public function test_その日の全ユーザーの勤怠情報が正確に確認できるか(): void
    {
        $this->travelTo(Carbon::parse('2026-10-15 17:00:00'));

        $admin = User::factory()->create([
            'admin_status' => true,
        ]);

        $user = User::factory()->create([
            'name' => '山田太郎',
            'admin_status' => false,
        ]);

        $secondUser = User::factory()->create([
            'name' => '田中花子',
            'admin_status' => false,
        ]);

        $attendance1 = Attendance::create([
            'user_id' => $user->id,
            'work_date' => '2026-10-15',
            'clock_in_at' => '2026-10-15 08:30:00',
            'clock_out_at' => '2026-10-15 17:00:00',
        ]);

        $attendance2 = Attendance::create([
            'user_id' => $secondUser->id,
            'work_date' => '2026-10-15',
            'clock_in_at' => '2026-10-15 08:00:00',
            'clock_out_at' => '2026-10-15 17:30:00',
        ]);

        AttendanceBreak::create([
            'attendance_id' => $attendance1->id,
            'break_start_at' => '2026-10-15 12:00:00',
            'break_end_at' => '2026-10-15 13:00:00',
        ]);

        AttendanceBreak::create([
            'attendance_id' => $attendance2->id,
            'break_start_at' => '2026-10-15 12:30:00',
            'break_end_at' => '2026-10-15 13:30:00',
        ]);

        $response = $this->actingAs($admin)
            ->get('/admin/attendance/list');

        $response->assertStatus(200);

        $response->assertSee('山田太郎');
        $response->assertSee('08:30');
        $response->assertSee('17:00');
        $response->assertSee('1:00');
        $response->assertSee('7:30');

        $response->assertSee('田中花子');
        $response->assertSee('08:00');
        $response->assertSee('17:30');
        $response->assertSee('8:30');
    }

    public function test_一覧を開いたときに現在の日付が表示されるか(): void
    {
        $this->travelTo(Carbon::parse('2026-10-15 17:00:00'));

        $admin = User::factory()->create([
            'admin_status' => true,
        ]);

        $response = $this->actingAs($admin)
            ->get('/admin/attendance/list');

        $response->assertStatus(200);
        $response->assertSee('2026/10/15');
    }


    public function test_前日を押すと前日の勤怠情報が表示されるか(): void
    {
        $this->travelTo(Carbon::parse('2026-10-15 17:00:00'));

        $admin = User::factory()->create([
            'admin_status' => true,
        ]);

        $user = User::factory()->create([
            'name' => '山田太郎',
            'admin_status' => false,
        ]);

        Attendance::create([
            'user_id' => $user->id,
            'work_date' => '2026-10-14',
            'clock_in_at' => '2026-10-14 08:30:00',
            'clock_out_at' => '2026-10-14 17:00:00',
        ]);

        $response = $this->actingAs($admin)
            ->get('/admin/attendance/list');

        $response->assertStatus(200);
        $response->assertSee('href="?date=2026-10-14"', false);

        $response = $this->get(
            '/admin/attendance/list?date=2026-10-14'
        );

        $response->assertStatus(200);
        $response->assertSee('2026/10/14');
        $response->assertSee('山田太郎');
        $response->assertSee('08:30');
        $response->assertSee('17:00');
    }


    public function test_翌日を押すと翌日の勤怠情報が表示されるか(): void
    {
        $this->travelTo(Carbon::parse('2026-10-15 17:00:00'));

        $admin = User::factory()->create([
            'admin_status' => true,
        ]);

        $user = User::factory()->create([
            'name' => '山田太郎',
            'admin_status' => false,
        ]);

        Attendance::create([
            'user_id' => $user->id,
            'work_date' => '2026-10-16',
            'clock_in_at' => '2026-10-16 08:30:00',
            'clock_out_at' => '2026-10-16 17:00:00',
        ]);

        $response = $this->actingAs($admin)
            ->get('/admin/attendance/list');

        $response->assertStatus(200);
        $response->assertSee('href="?date=2026-10-16"', false);

        $response = $this->get(
            '/admin/attendance/list?date=2026-10-16'
        );

        $response->assertStatus(200);
        $response->assertSee('2026/10/16');
        $response->assertSee('山田太郎');
        $response->assertSee('08:30');
        $response->assertSee('17:00');
    }
}
