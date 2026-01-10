<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\UserTimeClock;
use Tests\TestCase;

/**
 * Test cross-midnight day-out scenario
 */
class CrossMidnightDayOutTest extends TestCase
{
    protected ?User $user = null;
    protected int $userId = 103;
    protected int $shopId = 1;
    protected string $testDate = '2026-01-10';

    protected function setUp(): void
    {
        parent::setUp();
        UserTimeClock::where('user_id', $this->userId)->delete();
        $this->user = User::find($this->userId) ?? User::factory()->create(['id' => $this->userId]);
    }

    /**
     * Test: Can do day-out after midnight
     */
    public function test_can_do_day_out_after_midnight(): void
    {
        echo "\n[Test] Can do day-out at 01:30 AM (after midnight)\n";

        // day-in at 13:00 (1 PM)
        $this->postJson('/api/time-clock', [
            'shop_id' => $this->shopId,
            'user_id' => $this->userId,
            'clock_date' => $this->testDate,
            'time' => '13:00',
            'shift_start' => '08:00',
            'shift_end' => '23:00',
            'type' => 'day_in',
            'buffer_time' => 3,
        ])->assertStatus(201);
        echo "✓ day-in at 13:00\n";

        // day-out at 01:30 (next day - within 3hr buffer after 23:00)
        $response = $this->postJson('/api/time-clock', [
            'shop_id' => $this->shopId,
            'user_id' => $this->userId,
            'clock_date' => $this->testDate,
            'time' => '01:30',
            'type' => 'day_out',
            'buffer_time' => 3,
        ]);

        if ($response->status() !== 201) {
            echo "✗ Failed to add day-out at 01:30\n";
            echo "Response: " . json_encode($response->json()) . "\n";
        }

        $response->assertStatus(201);
        echo "✓ day-out at 01:30 AM (next day) - WORKS! ✓\n";
    }

    /**
     * Test: Can do day-out after midnight with breaks
     */
    public function test_can_do_day_out_after_midnight_with_breaks(): void
    {
        echo "\n[Test] Can do day-out at 01:30 with breaks before\n";

        $this->postJson('/api/time-clock', [
            'shop_id' => $this->shopId,
            'user_id' => $this->userId,
            'clock_date' => $this->testDate,
            'time' => '13:00',
            'shift_start' => '08:00',
            'shift_end' => '23:00',
            'type' => 'day_in',
            'buffer_time' => 3,
        ])->assertStatus(201);

        // Add breaks
        $this->postJson('/api/time-clock', [
            'shop_id' => $this->shopId,
            'user_id' => $this->userId,
            'clock_date' => $this->testDate,
            'time' => '15:00',
            'type' => 'break_start',
            'buffer_time' => 3,
        ])->assertStatus(201);

        $this->postJson('/api/time-clock', [
            'shop_id' => $this->shopId,
            'user_id' => $this->userId,
            'clock_date' => $this->testDate,
            'time' => '15:30',
            'type' => 'break_end',
            'buffer_time' => 3,
        ])->assertStatus(201);
        echo "✓ Added break 15:00-15:30\n";

        // day-out at 01:30 (after midnight, after break)
        $response = $this->postJson('/api/time-clock', [
            'shop_id' => $this->shopId,
            'user_id' => $this->userId,
            'clock_date' => $this->testDate,
            'time' => '01:30',
            'type' => 'day_out',
            'buffer_time' => 3,
        ]);

        $response->assertStatus(201);
        echo "✓ day-out at 01:30 AM with breaks - WORKS! ✓\n";
    }

    /**
     * Test: Cannot do day-out beyond buffer (after 02:00)
     */
    public function test_cannot_do_day_out_beyond_buffer(): void
    {
        echo "\n[Test] Cannot do day-out at 02:30 (beyond 3hr buffer)\n";

        $this->postJson('/api/time-clock', [
            'shop_id' => $this->shopId,
            'user_id' => $this->userId,
            'clock_date' => $this->testDate,
            'time' => '13:00',
            'shift_start' => '08:00',
            'shift_end' => '23:00',
            'type' => 'day_in',
            'buffer_time' => 3,
        ])->assertStatus(201);

        // Try day-out at 02:30 (beyond buffer: 23:00 + 3hrs = 02:00)
        $response = $this->postJson('/api/time-clock', [
            'shop_id' => $this->shopId,
            'user_id' => $this->userId,
            'clock_date' => $this->testDate,
            'time' => '02:30',
            'type' => 'day_out',
            'buffer_time' => 3,
        ]);

        $response->assertStatus(422);
        echo "✓ day-out at 02:30 correctly rejected (beyond buffer)\n";
    }
}
