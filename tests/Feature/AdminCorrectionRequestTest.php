<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Attendance;
use App\Models\CorrectionRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\CorrectionRequestBreak;
use App\Models\AttendanceBreak;
use Tests\TestCase;

class AdminCorrectionRequestTest extends TestCase
{
    use RefreshDatabase;
    public function test_承認待ち申請が全て表示されるか(): void
    {
        $admin = User::factory()->create([
            'admin_status' => true,
        ]);

        $user1 = User::factory()->create([
            'name' => '山田太郎',
            'admin_status' => false,
        ]);

        $user2 = User::factory()->create([
            'name' => '小林花子',
            'admin_status' => false,
        ]);

        $attendance1 = Attendance::create([
            'user_id' => $user1->id,
            'work_date' => '2026-10-22',
            'clock_in_at' => '2026-10-22 08:30:00',
            'clock_out_at' => '2026-10-22 17:00:00',
        ]);

        $attendance2 = Attendance::create([
            'user_id' => $user2->id,
            'work_date' => '2026-10-21',
            'clock_in_at' => '2026-10-21 09:00:00',
            'clock_out_at' => '2026-10-21 17:00:00',
        ]);

        CorrectionRequest::create([
            'attendance_id' => $attendance1->id,
            'requested_clock_in_at' => '2026-10-22 09:00:00',
            'requested_clock_out_at' => '2026-10-22 17:00:00',
            'memo' => '出勤時刻の修正',
            'status' => 'pending',
        ]);

        CorrectionRequest::create([
            'attendance_id' => $attendance2->id,
            'requested_clock_in_at' => '2026-10-21 09:00:00',
            'requested_clock_out_at' => '2026-10-21 18:00:00',
            'memo' => '退勤時刻の修正',
            'status' => 'pending',
        ]);

        $response = $this->actingAs($admin)
            ->get('/stamp_correction_request/list');

        $response->assertStatus(200);
        $response->assertSee('山田太郎');
        $response->assertSee('小林花子');
        $response->assertSee('2026/10/22');
        $response->assertSee('2026/10/21');
        $response->assertSee('出勤時刻の修正');
        $response->assertSee('退勤時刻の修正');
        $response->assertSee('承認待ち');
    }

    public function test_全ユーザーの承認済み申請が表示されるか(): void
    {
        $admin = User::factory()->create([
            'admin_status' => true,
        ]);

        $user1 = User::factory()->create([
            'name' => '山田太郎',
            'admin_status' => false,
        ]);

        $user2 = User::factory()->create([
            'name' => '小林花子',
            'admin_status' => false,
        ]);

        $attendance1 = Attendance::create([
            'user_id' => $user1->id,
            'work_date' => '2026-10-22',
            'clock_in_at' => '2026-10-22 08:30:00',
            'clock_out_at' => '2026-10-22 17:00:00',
        ]);

        $attendance2 = Attendance::create([
            'user_id' => $user2->id,
            'work_date' => '2026-10-21',
            'clock_in_at' => '2026-10-21 09:00:00',
            'clock_out_at' => '2026-10-21 17:00:00',
        ]);

        CorrectionRequest::create([
            'attendance_id' => $attendance1->id,
            'requested_clock_in_at' => '2026-10-22 09:00:00',
            'requested_clock_out_at' => '2026-10-22 17:00:00',
            'memo' => '出勤時刻の修正',
            'status' => 'approved',
        ]);

        CorrectionRequest::create([
            'attendance_id' => $attendance2->id,
            'requested_clock_in_at' => '2026-10-21 09:00:00',
            'requested_clock_out_at' => '2026-10-21 18:00:00',
            'memo' => '退勤時刻の修正',
            'status' => 'approved',
        ]);

        $response = $this->actingAs($admin)
            ->get('/stamp_correction_request/list');

        $response->assertStatus(200);
        $response->assertSee('山田太郎');
        $response->assertSee('小林花子');
        $response->assertSee('2026/10/22');
        $response->assertSee('2026/10/21');
        $response->assertSee('出勤時刻の修正');
        $response->assertSee('退勤時刻の修正');
        $response->assertSee('承認済み');
    }

    public function test_修正申請の詳細内容が正しく表示されるか(): void
    {
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

        $application = CorrectionRequest::create([
            'attendance_id' => $attendance->id,
            'requested_clock_in_at' => '2026-10-22 09:00:00',
            'requested_clock_out_at' => '2026-10-22 18:00:00',
            'memo' => '打刻時刻の修正',
            'status' => 'pending',
        ]);

        CorrectionRequestBreak::create([
            'correction_request_id' => $application->id,
            'requested_break_start_at' => '2026-10-22 12:15:00',
            'requested_break_end_at' => '2026-10-22 13:15:00',
        ]);

        $response = $this->actingAs($admin)
            ->get('/stamp_correction_request/approve/' . $application->id);

        $response->assertStatus(200);
        $response->assertSee('山田太郎');
        $response->assertSee('2026年');
        $response->assertSee('10月22日');
        $response->assertSee('09:00');
        $response->assertSee('18:00');
        $response->assertSee('12:15');
        $response->assertSee('13:15');
        $response->assertSee('打刻時刻の修正');
    }

    public function test_修正申請を承認すると勤怠と申請状態が更新されるか(): void
    {
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

        // ユーザーが修正申請する
        $response = $this->actingAs($user)
            ->post('/attendance/' . $attendance->id, [
                'new_clock_in' => '09:00',
                'new_clock_out' => '18:00',
                'new_break_in' => ['12:15'],
                'new_break_out' => ['13:15'],
                'comment' => '打刻時刻の修正',
            ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect('/attendance/' . $attendance->id);

        $application = $attendance->correction_requests()->firstOrFail();

        $response = $this->actingAs($admin)
            ->get('/stamp_correction_request/list');

        $response->assertStatus(200);
        $response->assertSee('山田太郎');
        $response->assertSee('打刻時刻の修正');

        $url = '/stamp_correction_request/approve/' . $application->id;

        $response = $this->get($url);
        $response->assertStatus(200);
        $response->assertSee('打刻時刻の修正');

        $response = $this->post($url);

        $response->assertRedirect($url);

        $this->assertDatabaseHas('attendances', [
            'id' => $attendance->id,
            'clock_in_at' => '2026-10-22 09:00:00',
            'clock_out_at' => '2026-10-22 18:00:00',
            'memo' => '打刻時刻の修正',
        ]);

        $this->assertDatabaseHas('attendance_breaks', [
            'attendance_id' => $attendance->id,
            'break_start_at' => '2026-10-22 12:15:00',
            'break_end_at' => '2026-10-22 13:15:00',
        ]);

        $this->assertSame(1, $attendance->attendance_breaks()->count());

        $this->assertDatabaseHas('correction_requests', [
            'id' => $application->id,
            'status' => 'approved',
        ]);

        $response = $this->get('/stamp_correction_request/list');

        $response->assertStatus(200);
        $response->assertSee('打刻時刻の修正');
        $response->assertSee('承認済み');

        $response = $this->actingAs($user)
            ->get('/attendance/' . $attendance->id);

        $response->assertStatus(200);
        $response->assertSee('09:00');
        $response->assertSee('18:00');
        $response->assertSee('12:15');
        $response->assertSee('13:15');
        $response->assertSee('打刻時刻の修正');

        // 一般ユーザーの承認済み一覧にも表示される
        $response = $this->get('/stamp_correction_request/list');

        $response->assertStatus(200);
        $response->assertSee('打刻時刻の修正');
        $response->assertSee('承認済み');
    }
}
