<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\UserTimeClock;
use Tests\TestCase;

/**
 * Test the EXACT scenario from user's SQL data
 * Reproducing their issue with multiple breaks
 */
class UserSqlScenarioTest extends TestCase
{
    protected ?User $user = null;
    protected int $userId = 2; // Same as user's data
    protected int $shopId = 1;
    protected string $testDate = '2026-01-05';

    protected function setUp(): void
    {
        parent::setUp();

        // Clean ALL previous test data for this user
        UserTimeClock::where('user_id', $this->userId)->delete();

        // Ensure user exists
        $this->user = User::find($this->userId);
        if (!$this->user) {
            $this->user = User::factory()->create(['id' => $this->userId]);
        }
    }

    /**
     * Test: Reproduce EXACT scenario from user's SQL
     */
    public function test_exact_user_scenario_with_second_break(): void
    {
        echo "\n[Test] Reproducing user's exact scenario with second break\n";

        // === FIRST SHIFT ===
        echo "\n--- First Shift ---\n";

        // day_in at 05:00
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
        echo "✓ day_in at 05:00\n";

        // break_start at 07:00
        $this->postJson('/api/time-clock', [
            'shop_id' => $this->shopId,
            'user_id' => $this->userId,
            'clock_date' => $this->testDate,
            'time' => '07:00',
            'type' => 'break_start',
            'buffer_time' => 3,
        ])->assertStatus(201);
        echo "✓ break_start at 07:00\n";

        // break_end at 08:00
        $this->postJson('/api/time-clock', [
            'shop_id' => $this->shopId,
            'user_id' => $this->userId,
            'clock_date' => $this->testDate,
            'time' => '08:00',
            'type' => 'break_end',
            'buffer_time' => 3,
        ])->assertStatus(201);
        echo "✓ break_end at 08:00\n";

        // day_out at 08:00 (same time as break_end)
        $this->postJson('/api/time-clock', [
            'shop_id' => $this->shopId,
            'user_id' => $this->userId,
            'clock_date' => $this->testDate,
            'time' => '08:00',
            'type' => 'day_out',
            'buffer_time' => 3,
        ])->assertStatus(201);
        echo "✓ day_out at 08:00\n";

        // === SECOND SHIFT ===
        echo "\n--- Second Shift ---\n";

        // day_in at 08:01
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
        echo "✓ day_in at 08:01\n";

        // FIRST break: 08:30-09:00
        $this->postJson('/api/time-clock', [
            'shop_id' => $this->shopId,
            'user_id' => $this->userId,
            'clock_date' => $this->testDate,
            'time' => '08:30',
            'type' => 'break_start',
            'buffer_time' => 3,
        ])->assertStatus(201);
        echo "✓ break_start at 08:30 (first break)\n";

        $this->postJson('/api/time-clock', [
            'shop_id' => $this->shopId,
            'user_id' => $this->userId,
            'clock_date' => $this->testDate,
            'time' => '09:00',
            'type' => 'break_end',
            'buffer_time' => 3,
        ])->assertStatus(201);
        echo "✓ break_end at 09:00\n";

        // === NOW TRY SECOND BREAK ===
        echo "\n--- Attempting Second Break (THIS IS WHERE IT FAILS) ---\n";

        // SECOND break: 10:00-10:30
        $response = $this->postJson('/api/time-clock', [
            'shop_id' => $this->shopId,
            'user_id' => $this->userId,
            'clock_date' => $this->testDate,
            'time' => '10:00',
            'type' => 'break_start',
            'buffer_time' => 3,
        ]);

        // Debug: Show what error we're getting
        if ($response->status() !== 201) {
            echo "✗ FAILED - Second break_start at 10:00 rejected\n";
            echo "Response: " . json_encode($response->json()) . "\n";

            // Check current state
            $events = UserTimeClock::where('user_id', $this->userId)
                ->where('date_at', $this->testDate)
                ->orderBy('time_at')
                ->get(['time_at', 'type']);

            echo "\nCurrent events in database:\n";
            foreach ($events as $event) {
                echo "  {$event->time_at} → {$event->type}\n";
            }
        }

        $response->assertStatus(201);
        echo "✓ break_start at 10:00 (second break) - SHOULD WORK\n";

        // Complete second break
        $this->postJson('/api/time-clock', [
            'shop_id' => $this->shopId,
            'user_id' => $this->userId,
            'clock_date' => $this->testDate,
            'time' => '10:30',
            'type' => 'break_end',
            'buffer_time' => 3,
        ])->assertStatus(201);
        echo "✓ break_end at 10:30\n";

        // day_out at 12:00
        $this->postJson('/api/time-clock', [
            'shop_id' => $this->shopId,
            'user_id' => $this->userId,
            'clock_date' => $this->testDate,
            'time' => '12:00',
            'type' => 'day_out',
            'buffer_time' => 3,
        ])->assertStatus(201);
        echo "✓ day_out at 12:00\n";

        // Verify final count
        $count = UserTimeClock::where('user_id', $this->userId)
            ->where('date_at', $this->testDate)
            ->count();

        echo "\n✓ Total records: $count (should be 10)\n";
        $this->assertEquals(10, $count);
    }
}
