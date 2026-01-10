<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\UserTimeClock;
use Tests\TestCase;

/**
 * Test duplicate timestamp prevention
 */
class DuplicateTimestampTest extends TestCase
{
    protected ?User $user = null;
    protected int $userId = 100;
    protected int $shopId = 1;
    protected string $testDate = '2026-01-10';

    protected function setUp(): void
    {
        parent::setUp();
        UserTimeClock::where('user_id', $this->userId)->delete();
        $this->user = User::find($this->userId) ?? User::factory()->create(['id' => $this->userId]);
    }

    /**
     * Test: Cannot add two events at same time
     */
    public function test_cannot_add_duplicate_timestamp(): void
    {
        echo "\n[Test] Cannot add events at duplicate timestamps\n";

        // Create initial event
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
        echo "✓ Created day-in at 08:00\n";

        // Try to add another event at the SAME TIME
        $response = $this->postJson('/api/time-clock', [
            'shop_id' => $this->shopId,
            'user_id' => $this->userId,
            'clock_date' => $this->testDate,
            'time' => '08:00',
            'type' => 'break_start',
            'buffer_time' => 3,
        ]);

        $response->assertStatus(422);
        $response->assertJsonFragment(['message' => 'Cannot add event: Another event already exists at this exact time.']);
        echo "✓ Duplicate timestamp at 08:00 correctly rejected\n";
    }

    /**
     * Test: User's exact scenario - break_end and day_in at 11:30
     */
    public function test_user_scenario_duplicate_at_1130(): void
    {
        echo "\n[Test] User scenario: Cannot add day-in at same time as break-end\n";

        // Set up timeline
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
            'time' => '11:15',
            'type' => 'break_start',
            'buffer_time' => 3,
        ])->assertStatus(201);

        $this->postJson('/api/time-clock', [
            'shop_id' => $this->shopId,
            'user_id' => $this->userId,
            'clock_date' => $this->testDate,
            'time' => '11:30',
            'type' => 'break_end',
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

        echo "✓ Created timeline: 08:01 day-in, 11:15-11:30 break, 12:00 day-out\n";

        // NOW try to add day-in at 11:30 (same as break-end)
        $response = $this->postJson('/api/time-clock', [
            'shop_id' => $this->shopId,
            'user_id' => $this->userId,
            'clock_date' => $this->testDate,
            'time' => '11:30',
            'type' => 'day_in',
            'buffer_time' => 3,
        ]);

        $response->assertStatus(422);
        echo "✓ day-in at 11:30 correctly rejected (duplicate with break-end)\n";
    }

    /**
     * Test: Events 1 minute apart should work
     */
    public function test_events_one_minute_apart_allowed(): void
    {
        echo "\n[Test] Events 1 minute apart should be allowed\n";

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
            'time' => '10:00',
            'type' => 'break_start',
            'buffer_time' => 3,
        ])->assertStatus(201);

        // Break end at 10:01 (1 minute later)
        $response = $this->postJson('/api/time-clock', [
            'shop_id' => $this->shopId,
            'user_id' => $this->userId,
            'clock_date' => $this->testDate,
            'time' => '10:01',
            'type' => 'break_end',
            'buffer_time' => 3,
        ]);

        $response->assertStatus(201);
        echo "✓ Events 1 minute apart allowed (10:00 and 10:01)\n";
    }
}
