<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;
use App\Models\Attendance;
use Illuminate\Support\Carbon;
use App\Models\AttendanceBreak;

class AttendanceDetailTest extends TestCase
{
    use RefreshDatabase;
    public function test_詳細を押すとその日の詳細画面に遷移するか(): void
    {
        $this->travelTo(Carbon::parse('2026-10-22 17:30:00'));

        $user = User::factory()->create([
            'admin_status' => false,
        ]);

        $attendance = Attendance::create([
            'user_id' => $user->id,
            'work_date' => '2026-10-22',
            'clock_in_at' => '2026-10-22 08:30:00',
            'clock_out_at' => '2026-10-22 17:00:00',
        ]);

        $response = $this->actingAs($user)->get('/attendance/list');

        $response->assertStatus(200);
        $response->assertSee(
            'href="' . url('/attendance/' . $attendance->id) . '"',
            false
        );

        $response = $this->get('/attendance/' . $attendance->id);

        $response->assertStatus(200);
        $response->assertSee('2026年');
        $response->assertSee('08:30');
        $response->assertSee('17:00');
        $response->assertSee('10月22日');
    }

    public function test_詳細画面の名前がログインユーザーの名前になっているか(): void
    {
        $this->travelTo(Carbon::parse('2026-10-22 17:30:00'));

        $user = User::factory()->create([
            'admin_status' => false,
            'name' => '田中太郎',
        ]);

        $attendance = Attendance::create([
            'user_id' => $user->id,
            'work_date' => '2026-10-22',
            'clock_in_at' => '2026-10-22 08:30:00',
            'clock_out_at' => '2026-10-22 17:00:00',
        ]);

        $response = $this->actingAs($user)
            ->get('/attendance/' . $attendance->id);

        $response->assertStatus(200);
        $response->assertSee('田中太郎');
    }

    public function test_詳細画面の日付が選択した日付になっているか(): void
    {
        $this->travelTo(Carbon::parse('2026-10-26 17:30:00'));

        $user = User::factory()->create([
            'admin_status' => false,
        ]);

        $attendance = Attendance::create([
            'user_id' => $user->id,
            'work_date' => '2026-10-22',
            'clock_in_at' => '2026-10-22 08:30:00',
            'clock_out_at' => '2026-10-22 17:00:00',
        ]);

        $response = $this->actingAs($user)
            ->get('/attendance/' . $attendance->id);

        $response->assertStatus(200);
        $response->assertSee('2026年');
        $response->assertSee('10月22日');
    }

    public function test_詳細画面の出勤・退勤時間が打刻の内容と同じか(): void
    {
        $this->travelTo(Carbon::parse('2026-10-26 17:30:00'));

        $user = User::factory()->create([
            'admin_status' => false,
        ]);

        $attendance = Attendance::create([
            'user_id' => $user->id,
            'work_date' => '2026-10-22',
            'clock_in_at' => '2026-10-22 08:30:00',
            'clock_out_at' => '2026-10-22 17:00:00',
        ]);

        $response = $this->actingAs($user)
            ->get('/attendance/' . $attendance->id);

        $response->assertStatus(200);
        $response->assertSee('08:30');
        $response->assertSee('17:00');
    }

    public function test_詳細画面の休憩時刻が打刻の内容と同じか(): void
    {
        $this->travelTo(Carbon::parse('2026-10-26 17:30:00'));

        $user = User::factory()->create([
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

        $response = $this->actingAs($user)
            ->get('/attendance/' . $attendance->id);

        $response->assertStatus(200);
        $response->assertSee('12:00');
        $response->assertSee('12:30');
    }
}
