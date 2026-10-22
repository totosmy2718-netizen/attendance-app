<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\AttendanceBreak;
use App\Models\User;
use App\Models\Attendance;
use Illuminate\Support\Carbon;

class AttendanceListTest extends TestCase
{
    use RefreshDatabase;
    /**
     * A basic feature test example.
     */
    public function test_自分が行った勤怠情報が全て表示されるか(): void
    {
        $this->travelTo(Carbon::parse('2026-10-22 08:30:00'));

        $user = User::factory()->create([
            'admin_status' => false,
        ]);

        $attendance22 = Attendance::create([
            'user_id' => $user->id,
            'work_date' => '2026-10-22',
            'clock_in_at' => '2026-10-22 08:30:00',
            'clock_out_at' => '2026-10-22 17:00:00',
        ]);

        $attendance21 = Attendance::create([
            'user_id' => $user->id,
            'work_date' => '2026-10-21',
            'clock_in_at' => '2026-10-21 09:00:00',
            'clock_out_at' => '2026-10-21 18:00:00',
        ]);

        AttendanceBreak::create([
            'attendance_id' => $attendance22->id,
            'break_start_at' => '2026-10-22 12:00:00',
            'break_end_at' => '2026-10-22 13:00:00',
        ]);

        AttendanceBreak::create([
            'attendance_id' => $attendance21->id,
            'break_start_at' => '2026-10-21 12:00:00',
            'break_end_at' => '2026-10-21 12:30:00',
        ]);

        $response = $this->actingAs($user)->get('/attendance/list');

        $response->assertStatus(200);

        $response->assertSee('08:30');
        $response->assertSee('17:00');
        $response->assertSee('10/22(木)');
        $response->assertSee('1:00');
        $response->assertSee('7:30');

        $response->assertSee('09:00');
        $response->assertSee('18:00');
        $response->assertSee('10/21(水)');
        $response->assertSee('0:30');
        $response->assertSee('8:30');
    }

    public function test_一覧を開いたときに当月が表示されるか(): void
    {
        $this->travelTo(Carbon::parse('2026-10-22 08:30:00'));

        $user = User::factory()->create([
            'admin_status' => false,
        ]);

        $response = $this->actingAs($user)->get('/attendance/list');

        $response->assertStatus(200);
        $response->assertSee('2026/10');
    }

    public function test_前月を押すと前月の勤怠が表示されるか(): void
    {
        $this->travelTo(Carbon::parse('2026-10-22 08:30:00'));

        $user = User::factory()->create([
            'admin_status' => false,
        ]);

        Attendance::create([
            'user_id' => $user->id,
            'work_date' => '2026-09-22',
            'clock_in_at' => '2026-09-22 08:30:00',
            'clock_out_at' => '2026-09-22 17:00:00',
        ]);

        $response = $this->actingAs($user)->get('/attendance/list');
        $response->assertStatus(200);
        $response->assertSee('href="?date=2026-09"', false);

        $response = $this->get('/attendance/list?date=2026-09');

        $response->assertStatus(200);
        $response->assertSee('2026/09');
        $response->assertSee('08:30');
        $response->assertSee('17:00');
        $response->assertSee('09/22(火)');
    }

    public function test_翌月を押すと翌月の勤怠が表示されるか(): void
    {
        $this->travelTo(Carbon::parse('2026-10-22 08:30:00'));

        $user = User::factory()->create([
            'admin_status' => false,
        ]);

        Attendance::create([
            'user_id' => $user->id,
            'work_date' => '2026-11-22',
            'clock_in_at' => '2026-11-22 08:30:00',
            'clock_out_at' => '2026-11-22 17:00:00',
        ]);

        $response = $this->actingAs($user)->get('/attendance/list');
        $response->assertStatus(200);
        $response->assertSee('href="?date=2026-11"', false);

        $response = $this->get('/attendance/list?date=2026-11');

        $response->assertStatus(200);
        $response->assertSee('2026/11');
        $response->assertSee('08:30');
        $response->assertSee('17:00');
        $response->assertSee('11/22(日)');
    }

    public function test_出勤時刻が一覧で確認できるか(): void
    {
        $this->travelTo(Carbon::parse('2026-10-22 08:30:00'));

        $user = User::factory()->create([
            'admin_status' => false,
        ]);

        $response = $this->actingAs($user)->post('/attendance', [
            'action' => 'clock_in',
        ]);

        $response->assertRedirect('/attendance');

        $response = $this->get('/attendance/list');

        $response->assertStatus(200);
        $response->assertSee('08:30');
        $response->assertSee('10/22(木)');
    }

    public function test_休憩時刻が一覧で確認できるか(): void
    {
        $this->travelTo(Carbon::parse('2026-10-22 08:30:00'));

        $user = User::factory()->create([
            'admin_status' => false,
        ]);

        $response = $this->actingAs($user)->post('/attendance', [
            'action' => 'clock_in',
        ]);

        $this->travelTo(Carbon::parse('2026-10-22 12:00:00'));

        $response = $this->actingAs($user)->post('/attendance', [
            'action' => 'break_in',
        ]);

        $this->travelTo(Carbon::parse('2026-10-22 13:00:00'));

        $response = $this->actingAs($user)->post('/attendance', [
            'action' => 'break_out',
        ]);

        $response = $this->get('/attendance/list');

        $response->assertStatus(200);
        $response->assertSee('1:00');
        $response->assertSee('10/22(木)');
    }

    public function test_退勤時刻が一覧で確認できるか(): void
    {
        $this->travelTo(Carbon::parse('2026-10-22 08:30:00'));

        $user = User::factory()->create([
            'admin_status' => false,
        ]);

        $response = $this->actingAs($user)->post('/attendance', [
            'action' => 'clock_in',
        ]);

        $this->travelTo(Carbon::parse('2026-10-22 17:00:00'));

        $response = $this->actingAs($user)->post('/attendance', [
            'action' => 'clock_out',
        ]);

        $response->assertRedirect('/attendance');

        $response = $this->get('/attendance/list');

        $response->assertStatus(200);
        $response->assertSee('17:00');
        $response->assertSee('10/22(木)');
    }
}