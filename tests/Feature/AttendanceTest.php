<?php

namespace Tests\Feature;

use App\Models\AttendanceBreak;
use App\Models\User;
use App\Models\Attendance;
use Illuminate\Support\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AttendanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_日時情報がUIと同じ形式で出力されるか(): void
    {
        $this->travelTo(Carbon::parse('2026-10-15 08:30:00'));

        $user = User::factory()->create([
            'admin_status' => false,
        ]);

        $response = $this->actingAs($user)->get('/attendance');

        $response->assertStatus(200);
        $response->assertSee('2026年10月15日(木)');
        $response->assertSee('08:30');


    }

    public function test_勤務外の場合勤務ステータスが正しく表示されるか(): void
    {

        $user = User::factory()->create([
            'admin_status' => false,
        ]);

        $response = $this->actingAs($user)->get('/attendance');

        $response->assertStatus(200);

        $response->assertSee('勤務外');

    }

    public function test_出勤中の場合勤務ステータスが正しく表示されるか(): void
    {

        $this->travelTo(Carbon::parse('2026-10-15 08:30:00'));
        $user = User::factory()->create([
            'admin_status' => false,
        ]);

        Attendance::create([
            'user_id' => $user->id,
            'work_date' => '2026-10-15',
            'clock_in_at' => '2026-10-15 08:30:00',
            'clock_out_at' => null,
        ]);

        $response = $this->actingAs($user)->get('/attendance');

        $response->assertStatus(200);
        $response->assertSee('出勤中');

    }

    public function test_休憩中の場合勤務ステータスが正しく表示されるか(): void
    {

        $this->travelTo(Carbon::parse('2026-10-15 12:30:00'));
        $user = User::factory()->create([
            'admin_status' => false,
        ]);

        $attendance = Attendance::create([
            'user_id' => $user->id,
            'work_date' => '2026-10-15',
            'clock_in_at' => '2026-10-15 08:30:00',
            'clock_out_at' => null,
        ]);

        AttendanceBreak::create([
            'attendance_id' => $attendance->id,
            'break_start_at' => '2026-10-15 12:00:00',
            'break_end_at' => null,
        ]);

        $response = $this->actingAs($user)->get('/attendance');

        $response->assertStatus(200);
        $response->assertSee('休憩中');

    }

    public function test_退勤済の場合勤務ステータスが正しく表示されるか(): void
    {

        $this->travelTo(Carbon::parse('2026-10-15 18:30:00'));
        $user = User::factory()->create([
            'admin_status' => false,
        ]);

        $attendance = Attendance::create([
            'user_id' => $user->id,
            'work_date' => '2026-10-15',
            'clock_in_at' => '2026-10-15 08:30:00',
            'clock_out_at' => '2026-10-15 17:00:00',
        ]);

        $response = $this->actingAs($user)->get('/attendance');

        $response->assertStatus(200);
        $response->assertSee('退勤済');

    }

    public function test_出勤ボタンが正しく機能するか(): void
    {
        $this->travelTo(Carbon::parse('2026-10-15 08:30:00'));

        $user = User::factory()->create([
            'admin_status' => false,
        ]);

        $response = $this->actingAs($user)->get('/attendance');

        $response->assertStatus(200);
        $response->assertSee('value="clock_in"', false);

        $response = $this->post('/attendance', [
            'action' => 'clock_in',
        ]);

        $response->assertRedirect('/attendance');

        $this->assertDatabaseHas('attendances', [
            'user_id' => $user->id,
            'clock_in_at' => '2026-10-15 08:30:00',
        ]);

        $attendance = $user->attendances()->first();

        $this->assertNotNull($attendance);
        $this->assertSame(
            '2026-10-15',
            $attendance->work_date->toDateString()
        );

        $response = $this->get('/attendance');

        $response->assertStatus(200);
        $response->assertSee('出勤中');
    }

    public function test_出勤は一日一回のみできるか(): void
    {
        $this->travelTo(Carbon::parse('2026-10-15 18:30:00'));

        $user = User::factory()->create([
            'admin_status' => false,
        ]);

        Attendance::create([
            'user_id' => $user->id,
            'work_date' => '2026-10-15',
            'clock_in_at' => '2026-10-15 08:30:00',
            'clock_out_at' => '2026-10-15 17:00:00',
        ]);

        $response = $this->actingAs($user)->get('/attendance');

        $response->assertDontSee('value="clock_in"', false);
    }

    public function test_休憩ボタンが正しく機能するか(): void
    {
        $this->travelTo(Carbon::parse('2026-10-15 12:30:00'));

        $user = User::factory()->create([
            'admin_status' => false,
        ]);

        $attendance = Attendance::create([
            'user_id' => $user->id,
            'work_date' => '2026-10-15',
            'clock_in_at' => '2026-10-15 08:30:00',
            'clock_out_at' => null,
        ]);

        $response = $this->actingAs($user)->get('/attendance');

        $response->assertStatus(200);
        $response->assertSee('value="break_in"', false);

        $response = $this->post('/attendance', [
            'action' => 'break_in',
        ]);

        $response->assertRedirect('/attendance');

        $this->assertDatabaseHas('attendance_breaks', [
            'attendance_id' => $attendance->id,
            'break_start_at' => '2026-10-15 12:30:00',
            'break_end_at' => null
        ]);

        $response = $this->get('/attendance');

        $response->assertStatus(200);
        $response->assertSee('休憩中');
    }

    public function test_休憩は1日に何回もできるか(): void
    {
        $this->travelTo(Carbon::parse('2026-10-15 17:00:00'));

        $user = User::factory()->create([
            'admin_status' => false,
        ]);

        $attendance = Attendance::create([
            'user_id' => $user->id,
            'work_date' => '2026-10-15',
            'clock_in_at' => '2026-10-15 08:30:00',
        ]);

        $response = $this->actingAs($user)->get('/attendance');

        $response = $this->post('/attendance', [
            'action' => 'break_in',
        ]);

        $this->travelTo(Carbon::parse('2026-10-15 17:30:00'));

        $response = $this->post('/attendance', [
            'action' => 'break_out',
        ]);

        $response = $this->get('/attendance');

        $response->assertStatus(200);
        $response->assertSee('value="break_in"', false);
    }

    public function test_休憩戻ボタンが正しく機能するか(): void
    {
        $this->travelTo(Carbon::parse('2026-10-15 12:00:00'));

        $user = User::factory()->create([
            'admin_status' => false,
        ]);

        $attendance = Attendance::create([
            'user_id' => $user->id,
            'work_date' => '2026-10-15',
            'clock_in_at' => '2026-10-15 08:30:00',
        ]);

        $response = $this->actingAs($user)->get('/attendance');

        $response = $this->post('/attendance', [
            'action' => 'break_in',
        ]);

        $response = $this->get('/attendance');
        $response->assertSee('value="break_out"', false);

        $this->travelTo(Carbon::parse('2026-10-15 13:00:00'));

        $response = $this->post('/attendance', [
            'action' => 'break_out',
        ]);

        $response = $this->get('/attendance');

        $this->assertDatabaseHas('attendance_breaks', [
            'attendance_id' => $attendance->id,
            'break_start_at' => '2026-10-15 12:00:00',
            'break_end_at' => '2026-10-15 13:00:00',
        ]);
        $response->assertStatus(200);
        $response->assertSee('出勤中');
    }

    public function test_休憩戻は1日に何回もできるか(): void
    {
        $this->travelTo(Carbon::parse('2026-10-15 12:00:00'));

        $user = User::factory()->create([
            'admin_status' => false,
        ]);

        Attendance::create([
            'user_id' => $user->id,
            'work_date' => '2026-10-15',
            'clock_in_at' => '2026-10-15 08:30:00',
        ]);

        $response = $this->actingAs($user)->get('/attendance');

        $response = $this->post('/attendance', [
            'action' => 'break_in',
        ]);

        $this->travelTo(Carbon::parse('2026-10-15 13:00:00'));

        $response = $this->post('/attendance', [
            'action' => 'break_out',
        ]);

        $this->travelTo(Carbon::parse('2026-10-15 15:00:00'));

        $response = $this->post('/attendance', [
            'action' => 'break_in',
        ]);

        $response = $this->get('/attendance');

        $response->assertStatus(200);
        $response->assertSee('value="break_out"', false);
    }

    public function test_退勤ボタンが正しく機能するか(): void
    {
        $this->travelTo(Carbon::parse('2026-10-15 17:00:00'));

        $user = User::factory()->create([
            'admin_status' => false,
        ]);

        $attendance = Attendance::create([
            'user_id' => $user->id,
            'work_date' => '2026-10-15',
            'clock_in_at' => '2026-10-15 08:30:00',
            'clock_out_at' => null,
        ]);

        $response = $this->actingAs($user)->get('/attendance');
        $response->assertStatus(200);
        $response->assertSee('value="clock_out"', false);

        $response = $this->post('/attendance', [
            'action' => 'clock_out',
        ]);

        $response->assertRedirect('/attendance');

        $this->assertDatabaseHas('attendances', [
            'id' => $attendance->id,
            'clock_out_at' => '2026-10-15 17:00:00',
        ]);

        $response = $this->get('/attendance');
        $response->assertStatus(200);
        $response->assertSee('退勤済');
        $response->assertSee('お疲れ様でした。');
    }

}