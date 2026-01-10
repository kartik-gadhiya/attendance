<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\UserTimeClock;
use Tests\TestCase;

/**
 * Test timeline-based validation with retroactive entries
 */
class TimelineValidationTest extends TestCase
{
    protected ?User $user = null;
    protected int $userId = 99;
    protected int $shopId = 1;
    protected string $testDate = '2026-01-10';

    protected function setUp(): void
    {
        parent::setUp();
        UserTimeClock::where('user_id', $this->userId)->delete();
        $this->user = User::find($this->userId) ?? User::factory()->create(['id' => $this->userId]);
    }

    /**
     * Test: Add second break AFTER day-out (retroactive)
     */
    public function test_add_second_break_retroactively(): void
    {
        echo "\n[Test] Add second break retroactively (after day-out exists)\n";

        // Create initial timeline
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

        $this->postJson('/api/time-clock', [
            'shop_id' => $this->shopId,
            'user_id' => $this->userId,
            'clock_date' => $this->testDate,
            'time' => '17:00',
            'type' => 'day_out',
            'buffer_time' => 3,
        ])->assertStatus(201);

        echo "✓ Initial timeline: 08:00 day-in, 08:30-09:00 break, 17:00 day-out\n";

        // NOW ADD SECOND BREAK RETROACTIVELY (between 09:00 and 17:00)
        $response1 = $this->postJson('/api/time-clock', [
            'shop_id' => $this->shopId,
            'user_id' => $this->userId,
            'clock_date' => $this->testDate,
            'time' => '10:00',
            'type' => 'break_start',
            'buffer_time' => 3,
        ]);

        $response1->assertStatus(201);
        echo "✓ Added break-start at 10:00 (retroactively)\n";

        $response2 = $this->postJson('/api/time-clock', [
            'shop_id' => $this->shopId,
            'user_id' => $this->userId,
            'clock_date' => $this->testDate,
            'time' => '11:00',
            'type' => 'break_end',
            'buffer_time' => 3,
        ]);

        $response2->assertStatus(201);
        echo "✓ Added break-end at 11:00 (retroactively)\n";

        // Verify all 6 records
        $count = UserTimeClock::where('user_id', $this->userId)
            ->where('date_at', $this->testDate)
            ->count();

        $this->assertEquals(6, $count);
        echo "✓ Total records: 6\n";
    }

    /**
     * Test: Cannot add overlapping break
     */
    public function test_cannot_add_overlapping_break(): void
    {
        echo "\n[Test] Cannot add overlapping break\n";

        // Create timeline
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

        $this->postJson('/api/time-clock', [
            'shop_id' => $this->shopId,
            'user_id' => $this->userId,
            'clock_date' => $this->testDate,
            'time' => '11:00',
            'type' => 'break_end',
            'buffer_time' => 3,
        ])->assertStatus(201);

        echo "✓ Created: 08:00 day-in, 10:00-11:00 break\n";

        // Try to add overlapping break at 10:30 (overlaps with 10:00-11:00)
        $response = $this->postJson('/api/time-clock', [
            'shop_id' => $this->shopId,
            'user_id' => $this->userId,
            'clock_date' => $this->testDate,
            'time' => '10:30',
            'type' => 'break_start',
            'buffer_time' => 3,
        ]);

        $response->assertStatus(422);
        echo "✓ Overlapping break at 10:30 correctly rejected\n";
    }

    /**
     * Test: Can add break before existing break
     */
    public function test_can_add_break_before_existing_break(): void
    {
        echo "\n[Test] Can add break BEFORE existing break\n";

        // Create timeline with second break first
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

        // Add second break first (chronologically)
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
            'time' => '14:30',
            'type' => 'break_end',
            'buffer_time' => 3,
        ])->assertStatus(201);

        echo "✓ Created: 08:00 day-in, 14:00-14:30 break\n";

        // NOW add FIRST break (earlier in timeline)
        $response1 = $this->postJson('/api/time-clock', [
            'shop_id' => $this->shopId,
            'user_id' => $this->userId,
            'clock_date' => $this->testDate,
            'time' => '10:00',
            'type' => 'break_start',
            'buffer_time' => 3,
        ]);

        $response1->assertStatus(201);
        echo "✓ Added earlier break-start at 10:00\n";

        $response2 = $this->postJson('/api/time-clock', [
            'shop_id' => $this->shopId,
            'user_id' => $this->userId,
            'clock_date' => $this->testDate,
            'time' => '10:30',
            'type' => 'break_end',
            'buffer_time' => 3,
        ]);

        if ($response2->status() !== 201) {
            echo "✗ Failed to add break-end at 10:30\n";
            echo "Response: " . json_encode($response2->json()) . "\n";
        }

        $response2->assertStatus(201);
        echo "✓ Added earlier break-end at 10:30\n";

        // Verify all 6 records
        $records = UserTimeClock::where('user_id', $this->userId)
            ->where('date_at', $this->testDate)
            ->orderBy('time_at')
            ->get(['time_at', 'type']);

        echo "\nCreated records:\n";
        foreach ($records as $record) {
            echo "  {$record->time_at} → {$record->type}\n";
        }

        $count = $records->count();
        $this->assertEquals(5, $count);
        echo "✓ Total records: 5 (2 breaks added out of order)\n";
    }

    /**
     * Test: Cannot add break-start after day-out
     */
    public function test_cannot_add_break_after_day_out(): void
    {
        echo "\n[Test] Cannot add break AFTER day-out time\n";

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
            'time' => '17:00',
            'type' => 'day_out',
            'buffer_time' => 3,
        ])->assertStatus(201);

        echo "✓ Created: 08:00 day-in, 17:00 day-out\n";

        // Try to add break AFTER 17:00 day-out
        $response = $this->postJson('/api/time-clock', [
            'shop_id' => $this->shopId,
            'user_id' => $this->userId,
            'clock_date' => $this->testDate,
            'time' => '18:00',
            'type' => 'break_start',
            'buffer_time' => 3,
        ]);

        $response->assertStatus(422);
        echo "✓ Break after day-out correctly rejected\n";
    }
}
