<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\UserTimeClock;
use Tests\TestCase;

/**
 * Test the exact scenario from user's data
 * Tests break from 10:00 AM to 11:00 AM
 */
class BreakOverlapFixTest extends TestCase
{
    protected ?User $user = null;
    protected int $userId = 20;
    protected int $shopId = 1;
    protected string $testDate = '2026-01-10';

    protected function setUp(): void
    {
        parent::setUp();

        // Clean previous test data
        UserTimeClock::where('user_id', $this->userId)->delete();

        // Ensure user exists
        $this->user = User::find($this->userId);
        if (!$this->user) {
            $this->user = User::factory()->create(['id' => $this->userId]);
        }
    }

    /**
     * Test: Break from 10:00 to 11:00 should work
     */
    public function test_break_from_10_to_11_works(): void
    {
        echo "\n[Test] Break from 10:00 AM to 11:00 AM should work\n";

        // Day-in at 08:00
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
        echo "✓ Day-in at 08:00\n";

        // Break start at 10:00
        $this->postJson('/api/time-clock', [
            'shop_id' => $this->shopId,
            'user_id' => $this->userId,
            'clock_date' => $this->testDate,
            'time' => '10:00',
            'type' => 'break_start',
            'buffer_time' => 3,
        ])->assertStatus(201);
        echo "✓ Break start at 10:00\n";

        // Break end at 11:00 - THIS SHOULD WORK NOW
        $response = $this->postJson('/api/time-clock', [
            'shop_id' => $this->shopId,
            'user_id' => $this->userId,
            'clock_date' => $this->testDate,
            'time' => '11:00',
            'type' => 'break_end',
            'buffer_time' => 3,
        ]);

        $response->assertStatus(201);
        echo "✓ Break end at 11:00 (FIXED!)\n";

        // Verify all 3 records
        $count = UserTimeClock::where('user_id', $this->userId)
            ->where('date_at', $this->testDate)
            ->count();

        $this->assertEquals(3, $count);
        echo "✓ All 3 records created successfully\n";
    }

    /**
     * Test: Multiple breaks in same day
     */
    public function test_multiple_breaks_in_day(): void
    {
        echo "\n[Test] Multiple breaks should work\n";

        // Day-in
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

        // First break: 10:00-10:30
        $this->postJson('/api/time-clock', [
            'shop_id' => $this->shopId,
            'user_id' => $this->userId,
            'clock_date' => $this->testDate,
            'time' => '10:00',
            'type' => 'break_start',
            'buffer_time' => 3,
        ])->assertStatus(201);

        $this->postJson('/api/time-clock', [
            'shop_id' => $this->shopId,
            'user_id' => $this->userId,
            'clock_date' => $this->testDate,
            'time' => '10:30',
            'type' => 'break_end',
            'buffer_time' => 3,
        ])->assertStatus(201);
        echo "✓ First break: 10:00-10:30\n";

        // Second break: 14:00-14:15
        $this->postJson('/api/time-clock', [
            'shop_id' => $this->shopId,
            'user_id' => $this->userId,
            'clock_date' => $this->testDate,
            'time' => '14:00',
            'type' => 'break_start',
            'buffer_time' => 3,
        ])->assertStatus(201);

        $this->postJson('/api/time-clock', [
            'shop_id' => $this->shopId,
            'user_id' => $this->userId,
            'clock_date' => $this->testDate,
            'time' => '14:15',
            'type' => 'break_end',
            'buffer_time' => 3,
        ])->assertStatus(201);
        echo "✓ Second break: 14:00-14:15\n";

        // Day-out
        $this->postJson('/api/time-clock', [
            'shop_id' => $this->shopId,
            'user_id' => $this->userId,
            'clock_date' => $this->testDate,
            'time' => '17:00',
            'type' => 'day_out',
            'buffer_time' => 3,
        ])->assertStatus(201);
        echo "✓ Day-out at 17:00\n";

        // Verify all 6 records
        $count = UserTimeClock::where('user_id', $this->userId)
            ->where('date_at', $this->testDate)
            ->count();

        $this->assertEquals(6, $count);
        echo "✓ All 6 records created (day-in, 2 breaks, day-out)\n";
    }

    /**
     * Test: Break overlap detection still works
     */
    public function test_overlapping_break_is_rejected(): void
    {
        echo "\n[Test] Overlapping breaks should still be rejected\n";

        // Day-in
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

        // First break: 10:00-11:00
        $this->postJson('/api/time-clock', [
            'shop_id' => $this->shopId,
            'user_id' => $this->userId,
            'clock_date' => $this->testDate,
            'time' => '10:00',
            'type' => 'break_start',
            'buffer_time' => 3,
        ])->assertStatus(201);

        $this->postJson('/api/time-clock', [
            'shop_id' => $this->shopId,
            'user_id' => $this->userId,
            'clock_date' => $this->testDate,
            'time' => '11:00',
            'type' => 'break_end',
            'buffer_time' => 3,
        ])->assertStatus(201);

        // Try to start another break at 10:30 (overlaps with first break)
        $response = $this->postJson('/api/time-clock', [
            'shop_id' => $this->shopId,
            'user_id' => $this->userId,
            'clock_date' => $this->testDate,
            'time' => '10:30',
            'type' => 'break_start',
            'buffer_time' => 3,
        ]);

        $response->assertStatus(422);
        echo "✓ Overlapping break correctly rejected\n";
    }
}
