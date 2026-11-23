<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class AttendanceReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_未ログインの場合はログイン画面へ移動する(): void
    {
        $response = $this->get('/attendance/report');

        $response->assertRedirect('/login');
    }

    public function test_本人の勤怠統計が正しく表示される(): void
    {
        // シーダーと集計の基準月を固定する
        $this->travelTo(Carbon::parse('2026-11-23 10:00:00'));

        $this->seed();

        $user = User::where('email', 'user1@example.com')
            ->firstOrFail();

        $response = $this->actingAs($user)
            ->get('/attendance/report');

        $response->assertOk();
        $response->assertViewIs('reports.index');

        $response->assertViewHas('summary', [
            'total_work_minutes' => 744 * 60,
            'total_overtime_minutes' => 10 * 60,
            'avg_work_minutes' => 8 * 60 + 5,
        ]);

        $response->assertViewHas('monthlyTrend', [
            [
                'month' => '2026/06',
                'work_minutes' => 120 * 60,
                'overtime_minutes' => 0,
            ],
            [
                'month' => '2026/07',
                'work_minutes' => 120 * 60,
                'overtime_minutes' => 0,
            ],
            [
                'month' => '2026/08',
                'work_minutes' => 120 * 60,
                'overtime_minutes' => 0,
            ],
            [
                'month' => '2026/09',
                'work_minutes' => 120 * 60,
                'overtime_minutes' => 0,
            ],
            [
                'month' => '2026/10',
                'work_minutes' => 120 * 60,
                'overtime_minutes' => 0,
            ],
            [
                'month' => '2026/11',
                'work_minutes' => 144 * 60,
                'overtime_minutes' => 10 * 60,
            ],
        ]);

        $response->assertViewHas('anomalies', [
            'late_count' => 2,
            'early_leave_count' => 1,
            'long_work_count' => 1,
        ]);
    }

    public function test_勤怠がなくてもエラーにならずゼロが表示される(): void
    {
        $this->travelTo(Carbon::parse('2026-11-23 10:00:00'));

        $user = User::factory()->create([
            'admin_status' => false,
            'email_verified_at' => now(),
        ]);

        $response = $this->actingAs($user)
            ->get('/attendance/report');

        $response->assertOk();

        $response->assertViewHas('summary', [
            'total_work_minutes' => 0,
            'total_overtime_minutes' => 0,
            'avg_work_minutes' => 0,
        ]);

        $expectedMonths = collect(range(6, 11))
            ->map(function (int $month): array {
                return [
                    'month' => sprintf('2026/%02d', $month),
                    'work_minutes' => 0,
                    'overtime_minutes' => 0,
                ];
            })
            ->all();

        $response->assertViewHas('monthlyTrend', $expectedMonths);

        $response->assertViewHas('anomalies', [
            'late_count' => 0,
            'early_leave_count' => 0,
            'long_work_count' => 0,
        ]);
    }
}