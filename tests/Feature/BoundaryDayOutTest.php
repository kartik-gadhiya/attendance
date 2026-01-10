<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\UserTimeClock;
use Tests\TestCase;

/**
 * Test day-out at boundary time (buffer limit)
 */
class BoundaryDayOutTest extends TestCase
{
    protected ?User $user = null;
    protected int $userId = 106;
    protected int $shopId = 1;
    protected string $testDate = '2026-01-10';

    protected function setUp(): void
    {
        parent::setUp();
        UserTimeClock::where('user_id', $this->userId)->delete();
        $this->user = User::find($this->userId) ?? User::factory()->create(['id' => $this->userId]);
    }

    /**
     * Test: Can do day-out at boundary (1:55 AM, limit is 2:00 AM)
     */
    public function test_can_do_day_out_at_boundary(): void
    {
        echo "\n[Test] Can do day-out at 01:55 (5 minutes before buffer limit)\n";

        // Create shift
        $this->postJson('/api/time-clock', [
            'shop_id' => $this->shopId,
            'user_id' => $this->userId,
            'clock_date' => $this->testDate,
            'time' => '13:30',
            'shift_start' => '08:00',
            'shift_end' => '23:00',
            'type' => 'day_in',
            'buffer_time' => 3,
        ])->assertStatus(201);

        $this->postJson('/api/time-clock', [
            'shop_id' => $this->shopId,
            'user_id' => $this->userId,
            'clock_date' => $this->testDate,
            'time' => '01:55',
            'type' => 'day_out',
            'buffer_time' => 3,
        ])->assertStatus(201);

        echo "✓ day-out at 01:55 allowed (within buffer: 23:00 + 3hr = 02:00) ✓\n";
    }

    /**
     * Test: Can do day-out at exact buffer limit (2:00 AM)
     */
    public function test_can_do_day_out_at_exact_limit(): void
    {
        echo "\n[Test] Can do day-out at exactly 02:00 (buffer limit)\n";

        $this->postJson('/api/time-clock', [
            'shop_id' => $this->shopId,
            'user_id' => $this->userId,
            'clock_date' => $this->testDate,
            'time' => '13:30',
            'shift_start' => '08:00',
            'shift_end' => '23:00',
            'type' => 'day_in',
            'buffer_time' => 3,
        ])->assertStatus(201);

        $response = $this->postJson('/api/time-clock', [
            'shop_id' => $this->shopId,
            'user_id' => $this->userId,
            'clock_date' => $this->testDate,
            'time' => '02:00',
            'type' => 'day_out',
            'buffer_time' => 3,
        ]);

        $response->assertStatus(201);
        echo "✓ day-out at 02:00 allowed (exact buffer limit) ✓\n";
    }

    /**
     * Test: Cannot do day-out beyond buffer limit (2:01 AM)
     */
    public function test_cannot_do_day_out_beyond_limit(): void
    {
        echo "\n[Test] Cannot do day-out at 02:01 (beyond buffer limit)\n";

        $this->postJson('/api/time-clock', [
            'shop_id' => $this->shopId,
            'user_id' => $this->userId,
            'clock_date' => $this->testDate,
            'time' => '13:30',
            'shift_start' => '08:00',
            'shift_end' => '23:00',
            'type' => 'day_in',
            'buffer_time' => 3,
        ])->assertStatus(201);

        $response = $this->postJson('/api/time-clock', [
            'shop_id' => $this->shopId,
            'user_id' => $this->userId,
            'clock_date' => $this->testDate,
            'time' => '02:01',
            'type' => 'day_out',
            'buffer_time' => 3,
        ]);

        $response->assertStatus(422);
        echo "✓ day-out at 02:01 correctly rejected (beyond 02:00 limit) ✓\n";
    }
}
