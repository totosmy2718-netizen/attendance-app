<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;
use App\Models\Attendance;
use Illuminate\Support\Carbon;

class CorrectionRequestListTest extends TestCase
{
    use RefreshDatabase;
    public function test_承認待ちにユーザーの申請がすべて表示されるか(): void
    {
        $this->travelTo(Carbon::parse('2026-10-15 17:00:00'));

        $user = User::factory()->create([
            'admin_status' => false,
        ]);

        $attendance = Attendance::create([
            'user_id' => $user->id,
            'work_date' => '2026-10-15',
            'clock_in_at' => '2026-10-15 08:30:00',
            'clock_out_at' => '2026-10-15 17:00:00',
        ]);

        $response = $this->actingAs($user)
            ->post('/attendance/' . $attendance->id, [
                'new_clock_in' => '09:00',
                'new_clock_out' => '17:00',
                'comment' => '出勤時間修正',
            ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect('/attendance/' . $attendance->id);

        $secondAttendance = Attendance::create([
            'user_id' => $user->id,
            'work_date' => '2026-10-14',
            'clock_in_at' => '2026-10-14 08:30:00',
            'clock_out_at' => '2026-10-14 17:00:00',
        ]);

        $response = $this->post('/attendance/' . $secondAttendance->id, [
            'new_clock_in' => '08:30',
            'new_clock_out' => '18:00',
            'comment' => '退勤時間修正',
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect('/attendance/' . $secondAttendance->id);

        $response = $this->get('/stamp_correction_request/list');

        $response->assertStatus(200);
        $response->assertSee('承認待ち');
        $response->assertSee('2026/10/15');
        $response->assertSee('出勤時間修正');
        $response->assertSee('2026/10/14');
        $response->assertSee('退勤時間修正');
    }


    public function test_各申請の詳細から勤怠詳細画面に遷移するか(): void
    {
        $this->travelTo(Carbon::parse('2026-10-15 17:00:00'));

        $user = User::factory()->create([
            'admin_status' => false,
        ]);

        $attendance = Attendance::create([
            'user_id' => $user->id,
            'work_date' => '2026-10-14',
            'clock_in_at' => '2026-10-14 08:30:00',
            'clock_out_at' => '2026-10-14 17:00:00',
        ]);

        $response = $this->actingAs($user)
            ->post('/attendance/' . $attendance->id, [
                'new_clock_in' => '09:00',
                'new_clock_out' => '18:00',
                'comment' => '打刻時間修正',
            ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect('/attendance/' . $attendance->id);

        $application = $attendance->correction_requests()->firstOrFail();

        $response = $this->get('/stamp_correction_request/list');

        $response->assertStatus(200);
        $response->assertSee(
            'href="' . url('/application/' . $application->id) . '"',
            false
        );

        $response = $this->get('/application/' . $application->id);

        $response->assertRedirect('/attendance/' . $attendance->id);

        $response = $this->get('/attendance/' . $attendance->id);

        $response->assertStatus(200);
        $response->assertSee('2026年');
        $response->assertSee('10月14日');
        $response->assertSee('09:00');
        $response->assertSee('18:00');
        $response->assertSee('打刻時間修正');
    }
}
