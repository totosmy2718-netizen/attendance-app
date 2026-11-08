<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;
use App\Models\Attendance;
use App\Models\AttendanceBreak;
use Illuminate\Support\Carbon;

class AdminStaffAttendanceTest extends TestCase
{
    use RefreshDatabase;
    public function test_全一般ユーザーの氏名とメールアドレスが表示されるか(): void
    {
        $admin = User::factory()->create([
            'admin_status' => true,
        ]);

        $user1 = User::factory()->create([
            'name' => '山田太郎',
            'email' => 'test@test.com',
            'admin_status' => false,
        ]);

        $user2 = User::factory()->create([
            'name' => '小林花子',
            'email' => 'exam@test.com',
            'admin_status' => false,
        ]);

        $response = $this->actingAs($admin)->get('/admin/staff/list');

        $response->assertStatus(200);
        $response->assertSee('山田太郎');
        $response->assertSee('test@test.com');
        $response->assertSee('小林花子');
        $response->assertSee('exam@test.com');
    }

    public function test_選択したスタッフの月次勤怠が正しく表示されるか(): void
    {
        $this->travelTo(Carbon::parse('2026-10-22 18:00:00'));

        $admin = User::factory()->create([
            'admin_status' => true,
        ]);

        $user = User::factory()->create([
            'name' => '山田太郎',
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
            'break_end_at' => '2026-10-22 13:00:00',
        ]);

        $response = $this->actingAs($admin)
            ->get('/admin/attendance/staff/' . $user->id);

        $response->assertStatus(200);
        $response->assertSee('山田太郎さんの勤怠');
        $response->assertSee('2026/10');
        $response->assertSee('10/22(木)');
        $response->assertSee('08:30');
        $response->assertSee('17:00');
        $response->assertSee('1:00');
        $response->assertSee('7:30');
    }

    public function test_前月を押すと選択したスタッフの前月の勤怠が表示されるか(): void
    {
        $this->travelTo(Carbon::parse('2026-10-22 18:00:00'));

        $admin = User::factory()->create([
            'admin_status' => true,
        ]);

        $user = User::factory()->create([
            'admin_status' => false,
        ]);

        Attendance::create([
            'user_id' => $user->id,
            'work_date' => '2026-09-22',
            'clock_in_at' => '2026-09-22 09:00:00',
            'clock_out_at' => '2026-09-22 18:00:00',
        ]);

        $url = '/admin/attendance/staff/' . $user->id;

        $response = $this->actingAs($admin)->get($url);

        $response->assertStatus(200);
        $response->assertSee('href="?date=2026-09"', false);

        $response = $this->get($url . '?date=2026-09');

        $response->assertStatus(200);
        $response->assertSee('2026/09');
        $response->assertSee('09/22(火)');
        $response->assertSee('09:00');
        $response->assertSee('18:00');
    }

    public function test_翌月を押すと選択したスタッフの翌月の勤怠が表示されるか(): void
    {
        $this->travelTo(Carbon::parse('2026-10-22 18:00:00'));

        $admin = User::factory()->create([
            'admin_status' => true,
        ]);

        $user = User::factory()->create([
            'admin_status' => false,
        ]);

        Attendance::create([
            'user_id' => $user->id,
            'work_date' => '2026-11-22',
            'clock_in_at' => '2026-11-22 09:00:00',
            'clock_out_at' => '2026-11-22 18:00:00',
        ]);

        $url = '/admin/attendance/staff/' . $user->id;

        $response = $this->actingAs($admin)->get($url);

        $response->assertStatus(200);
        $response->assertSee('href="?date=2026-11"', false);

        $response = $this->get($url . '?date=2026-11');

        $response->assertStatus(200);
        $response->assertSee('2026/11');
        $response->assertSee('11/22(日)');
        $response->assertSee('09:00');
        $response->assertSee('18:00');
    }

    public function test_月次一覧の詳細から対象の勤怠詳細へ遷移できるか(): void
    {
        $this->travelTo(Carbon::parse('2026-10-22 18:00:00'));

        $admin = User::factory()->create([
            'admin_status' => true,
        ]);

        $user = User::factory()->create([
            'name' => '山田太郎',
            'admin_status' => false,
        ]);

        $attendance = Attendance::create([
            'user_id' => $user->id,
            'work_date' => '2026-10-22',
            'clock_in_at' => '2026-10-22 08:30:00',
            'clock_out_at' => '2026-10-22 17:00:00',
        ]);

        $response = $this->actingAs($admin)
            ->get('/admin/attendance/staff/' . $user->id);

        $response->assertStatus(200);
        $response->assertSee(
            'href="' . url('/attendance/' . $attendance->id) . '"',
            false
        );

        $response = $this->get('/attendance/' . $attendance->id);

        $response->assertRedirect('/admin/attendance/' . $attendance->id);

        $response = $this->get('/admin/attendance/' . $attendance->id);

        $response->assertStatus(200);
        $response->assertSee('山田太郎');
        $response->assertSee('2026年');
        $response->assertSee('10月22日');
        $response->assertSee('08:30');
        $response->assertSee('17:00');
    }
}
