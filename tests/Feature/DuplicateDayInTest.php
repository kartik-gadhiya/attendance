<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\UserTimeClock;
use Tests\TestCase;

/**
 * Test user's exact scenario - duplicate day-in at 09:30
 */
class DuplicateDayInTest extends TestCase
{
    protected ?User $user = null;
    protected int $userId = 101;
    protected int $shopId = 1;
    protected string $testDate = '2026-01-10';

    protected function setUp(): void
    {
        parent::setUp();
        UserTimeClock::where('user_id', $this->userId)->delete();
        $this->user = User::find($this->userId) ?? User::factory()->create(['id' => $this->userId]);
    }

    /**
     * Test: Cannot add second day-in without day-out
     */
    public function test_cannot_add_second_day_in_without_day_out(): void
    {
        echo "\n[Test] Cannot add second day-in without day-out first\n";

        // Timeline: 08:01 day-in, 08:30-09:00 break
        $this->postJson('/api/time-clock', [
            'shop_id' => $this->shopId,
            'user_id' => $this->userId,
            'clock_date' => $this->testDate,
            'time' => '08:01',
            'shift_start' => '08:00',
            'shift_end' => '23:00',
            'type' => 'day_in',
            'buffer_time' => 3,
        ])->assertStatus(201);
        echo "✓ day-in at 08:01\n";

        $this->postJson('/api/time-clock', [
            'shop_id' => $this->shopId,
            'user_id' => $this->userId,
            'clock_date' => $this->testDate,
            'time' => '08:30',
            'type' => 'break_start',
            'buffer_time' => 3,
        ])->assertStatus(201);

        $this->postJson('/api/time-clock', [
            'shop_id' => $this->shopId,
            'user_id' => $this->userId,
            'clock_date' => $this->testDate,
            'time' => '09:00',
            'type' => 'break_end',
            'buffer_time' => 3,
        ])->assertStatus(201);
        echo "✓ break 08:30-09:00\n";

        // NOW try to add ANOTHER day-in at 09:30 (should be rejected!)
        $response = $this->postJson('/api/time-clock', [
            'shop_id' => $this->shopId,
            'user_id' => $this->userId,
            'clock_date' => $this->testDate,
            'time' => '09:30',
            'type' => 'day_in',
            'buffer_time' => 3,
        ]);

        if ($response->status() !== 422) {
            echo "✗ FAILED - day-in at 09:30 was allowed (should be rejected!)\n";
            echo "Response: " . json_encode($response->json()) . "\n";
        }

        $response->assertStatus(422);
        echo "✓ day-in at 09:30 correctly rejected (active day-in exists)\n";
    }

    /**
     * Test: Can add day-in AFTER day-out
     */
    public function test_can_add_day_in_after_day_out(): void
    {
        echo "\n[Test] CAN add day-in after day-out\n";

        $this->postJson('/api/time-clock', [
            'shop_id' => $this->shopId,
            'user_id' => $this->userId,
            'clock_date' => $this->testDate,
            'time' => '08:00',
            'shift_start' => '08:00',
            'shift_end' => '23:00',
            'type' => 'day_in',
            'buffer_time' => 3,
        ])->assertStatus(201);

        $this->postJson('/api/time-clock', [
            'shop_id' => $this->shopId,
            'user_id' => $this->userId,
            'clock_date' => $this->testDate,
            'time' => '12:00',
            'type' => 'day_out',
            'buffer_time' => 3,
        ])->assertStatus(201);
        echo "✓ First shift: 08:00-12:00\n";

        // Second shift
        $response = $this->postJson('/api/time-clock', [
            'shop_id' => $this->shopId,
            'user_id' => $this->userId,
            'clock_date' => $this->testDate,
            'time' => '13:00',
            'type' => 'day_in',
            'buffer_time' => 3,
        ]);

        $response->assertStatus(201);
        echo "✓ Second day-in at 13:00 allowed (after day-out)\n";
    }
}
