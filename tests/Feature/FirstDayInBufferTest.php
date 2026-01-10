<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\UserTimeClock;
use Tests\TestCase;

/**
 * Test first day-in buffer validation
 */
class FirstDayInBufferTest extends TestCase
{
    protected ?User $user = null;
    protected int $userId = 107;
    protected int $shopId = 1;
    protected string $testDate = '2026-01-11';

    protected function setUp(): void
    {
        parent::setUp();
        UserTimeClock::where('user_id', $this->userId)->delete();
        $this->user = User::find($this->userId) ?? User::factory()->create(['id' => $this->userId]);
    }

    /**
     * Test: Cannot add day-in before buffer window (01:59 before 05:00)
     */
    public function test_cannot_add_day_in_before_buffer_window(): void
    {
        echo "\n[Test] Cannot add day-in at 01:59 (before 05:00 buffer start)\n";

        // Try to add day-in at 01:59 (shift starts at 08:00, buffer 3hrs, earliest = 05:00)
        $response = $this->postJson('/api/time-clock', [
            'shop_id' => $this->shopId,
            'user_id' => $this->userId,
            'clock_date' => $this->testDate,
            'time' => '01:59',
            'type' => 'day_in',
            'shift_start' => '08:00',
            'shift_end' => '23:00',
            'buffer_time' => 3,
        ]);

        if ($response->status() !== 422) {
            echo "✗ FAILED - day-in at 01:59 was allowed!\n";
            echo "Response: " . json_encode($response->json()) . "\n";
        }

        $response->assertStatus(422);
        echo "✓ day-in at 01:59 correctly rejected (before 05:00) ✓\n";
    }

    /**
     * Test: Can add day-in at buffer window start (05:00)
     */
    public function test_can_add_day_in_at_buffer_start(): void
    {
        echo "\n[Test] CAN add day-in at 05:00 (buffer window start)\n";

        $response = $this->postJson('/api/time-clock', [
            'shop_id' => $this->shopId,
            'user_id' => $this->userId,
            'clock_date' => $this->testDate,
            'time' => '05:00',
            'type' => 'day_in',
            'shift_start' => '08:00',
            'shift_end' => '23:00',
            'buffer_time' => 3,
        ]);

        $response->assertStatus(201);
        echo "✓ day-in at 05:00 allowed (earliest allowed time) ✓\n";
    }

    /**
     * Test: Cannot add day-in at 04:59 (1 minute before buffer)
     */
    public function test_cannot_add_day_in_one_minute_before_buffer(): void
    {
        echo "\n[Test] Cannot add day-in at 04:59 (1 minute before buffer)\n";

        $response = $this->postJson('/api/time-clock', [
            'shop_id' => $this->shopId,
            'user_id' => $this->userId,
            'clock_date' => $this->testDate,
            'time' => '04:59',
            'type' => 'day_in',
            'shift_start' => '08:00',
            'shift_end' => '23:00',
            'buffer_time' => 3,
        ]);

        $response->assertStatus(422);
        echo "✓ day-in at 04:59 correctly rejected ✓\n";
    }

    /**
     * Test: Can add day-in at shift start time
     */
    public function test_can_add_day_in_at_shift_start(): void
    {
        echo "\n[Test] CAN add day-in at 08:00 (shift start time)\n";

        $response = $this->postJson('/api/time-clock', [
            'shop_id' => $this->shopId,
            'user_id' => $this->userId,
            'clock_date' => $this->testDate,
            'time' => '08:00',
            'type' => 'day_in',
            'shift_start' => '08:00',
            'shift_end' => '23:00',
            'buffer_time' => 3,
        ]);

        $response->assertStatus(201);
        echo "✓ day-in at 08:00 allowed (shift start) ✓\n";
    }
}
