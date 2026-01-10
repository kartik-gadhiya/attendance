<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\UserTimeClock;
use Tests\TestCase;

/**
 * Test range-based validation - user's exact requirement
 */
class RangeValidationTest extends TestCase
{
    protected ?User $user = null;
    protected int $userId = 102;
    protected int $shopId = 1;
    protected string $testDate = '2026-01-10';

    protected function setUp(): void
    {
        parent::setUp();
        UserTimeClock::where('user_id', $this->userId)->delete();
        $this->user = User::find($this->userId) ?? User::factory()->create(['id' => $this->userId]);
    }

    /**
     * Test: Cannot add entry within blocked range
     */
    public function test_cannot_add_entry_in_blocked_range(): void
    {
        echo "\n[Test] Cannot add entry within blocked range (05:00-08:00)\n";

        // Create first shift: 05:00-08:00
        $this->postJson('/api/time-clock', [
            'shop_id' => $this->shopId,
            'user_id' => $this->userId,
            'clock_date' => $this->testDate,
            'time' => '05:00',
            'shift_start' => '08:00',
            'shift_end' => '23:00',
            'type' => 'day_in',
            'buffer_time' => 3,
        ])->assertStatus(201);

        $this->postJson('/api/time-clock', [
            'shop_id' => $this->shopId,
            'user_id' => $this->userId,
            'clock_date' => $this->testDate,
            'time' => '08:00',
            'type' => 'day_out',
            'buffer_time' => 3,
        ])->assertStatus(201);
        echo "✓ Created shift: 05:00-08:00\n";

        // Try to add day-in at 07:00 (inside 05:00-08:00 range)
        $response = $this->postJson('/api/time-clock', [
            'shop_id' => $this->shopId,
            'user_id' => $this->userId,
            'clock_date' => $this->testDate,
            'time' => '07:00',
            'type' => 'day_in',
            'buffer_time' => 3,
        ]);

        $response->assertStatus(422);
        echo "✓ Entry at 07:00 correctly rejected (falls in 05:00-08:00 range)\n";
    }

    /**
     * Test: Can add entry in gap between ranges
     */
    public function test_can_add_entry_in_gap(): void
    {
        echo "\n[Test] CAN add entry in gap (08:00-09:00)\n";

        // Create shifts: 05:00-08:00 and 09:00-12:00
        $this->postJson('/api/time-clock', [
            'shop_id' => $this->shopId,
            'user_id' => $this->userId,
            'clock_date' => $this->testDate,
            'time' => '05:00',
            'shift_start' => '08:00',
            'shift_end' => '23:00',
            'type' => 'day_in',
            'buffer_time' => 3,
        ])->assertStatus(201);

        $this->postJson('/api/time-clock', [
            'shop_id' => $this->shopId,
            'user_id' => $this->userId,
            'clock_date' => $this->testDate,
            'time' => '08:00',
            'type' => 'day_out',
            'buffer_time' => 3,
        ])->assertStatus(201);

        $this->postJson('/api/time-clock', [
            'shop_id' => $this->shopId,
            'user_id' => $this->userId,
            'clock_date' => $this->testDate,
            'time' => '09:00',
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

        echo "✓ Created ranges: 05:00-08:00 and 09:00-12:00\n";

        // Add shift in gap: 08:15-08:45
        $r1 = $this->postJson('/api/time-clock', [
            'shop_id' => $this->shopId,
            'user_id' => $this->userId,
            'clock_date' => $this->testDate,
            'time' => '08:15',
            'type' => 'day_in',
            'buffer_time' => 3,
        ]);

        $r2 = $this->postJson('/api/time-clock', [
            'shop_id' => $this->shopId,
            'user_id' => $this->userId,
            'clock_date' => $this->testDate,
            'time' => '08:45',
            'type' => 'day_out',
            'buffer_time' => 3,
        ]);

        if ($r2->status() !== 201) {
            echo "✗ Failed to add day-out at 08:45\n";
            echo "Response: " . json_encode($r2->json()) . "\n";
        }

        $r1->assertStatus(201);
        $r2->assertStatus(201);
        echo "✓ Added shift in gap: 08:15-08:45 ✓\n";
    }

    /**
     * Test: User's exact scenario - cannot add 09:30
     */
    public function test_user_scenario_0930_blocked(): void
    {
        echo "\n[Test] User scenario: Cannot add 09:30 in 08:01-12:00 range\n";

        // Create shift: 08:01-12:00
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

        $this->postJson('/api/time-clock', [
            'shop_id' => $this->shopId,
            'user_id' => $this->userId,
            'clock_date' => $this->testDate,
            'time' => '12:00',
            'type' => 'day_out',
            'buffer_time' => 3,
        ])->assertStatus(201);
        echo "✓ Created shift: 08:01-12:00\n";

        // Try to add day-in at 09:30 (inside range)
        $response = $this->postJson('/api/time-clock', [
            'shop_id' => $this->shopId,
            'user_id' => $this->userId,
            'clock_date' => $this->testDate,
            'time' => '09:30',
            'type' => 'day_in',
            'buffer_time' => 3,
        ]);

        if ($response->status() !== 422) {
            echo "✗ FAILED - 09:30 was allowed!\n";
            echo "Response: " . json_encode($response->json()) . "\n";
        }

        $response->assertStatus(422);
        echo "✓ Entry at 09:30 correctly blocked (falls in 08:01-12:00 range) ✓\n";
    }
}
