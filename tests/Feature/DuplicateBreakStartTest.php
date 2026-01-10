<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\UserTimeClock;
use Tests\TestCase;

/**
 * Test that duplicate break_start is blocked
 */
class DuplicateBreakStartTest extends TestCase
{
    protected ?User $user = null;
    protected int $userId = 105;
    protected int $shopId = 1;
    protected string $testDate = '2026-01-10';

    protected function setUp(): void
    {
        parent::setUp();
        UserTimeClock::where('user_id', $this->userId)->delete();
        $this->user = User::find($this->userId) ?? User::factory()->create(['id' => $this->userId]);
    }

    /**
     * Test: Cannot add second break_start without break_end (after midnight)
     */
    public function test_cannot_add_second_break_start_after_midnight(): void
    {
        echo "\n[Test] Cannot add second break_start without ending first\n";

        // Create timeline
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
            'time' => '17:00',
            'type' => 'break_start',
            'buffer_time' => 3,
        ])->assertStatus(201);

        $this->postJson('/api/time-clock', [
            'shop_id' => $this->shopId,
            'user_id' => $this->userId,
            'clock_date' => $this->testDate,
            'time' => '20:00',
            'type' => 'break_end',
            'buffer_time' => 3,
        ])->assertStatus(201);

        // First after-midnight break_start at 01:15 (after closing 17:00-20:00 break)
        $this->postJson('/api/time-clock', [
            'shop_id' => $this->shopId,
            'user_id' => $this->userId,
            'clock_date' => $this->testDate,
            'time' => '01:15',
            'type' => 'break_start',
            'buffer_time' => 3,
        ])->assertStatus(201);

        echo "✓ Timeline: 13:30 day-in, 17:00-20:00 break, 01:15 break-start\n";

        // Try to add ANOTHER break_start at 01:30 (should fail - previous break still open!)
        $response = $this->postJson('/api/time-clock', [
            'shop_id' => $this->shopId,
            'user_id' => $this->userId,
            'clock_date' => $this->testDate,
            'time' => '01:30',
            'type' => 'break_start',
            'buffer_time' => 3,
        ]);

        if ($response->status() !== 422) {
            echo "✗ FAILED - Second break_start was allowed!\n";
            echo "Response: " . json_encode($response->json()) . "\n";
        }

        $response->assertStatus(422);
        echo "✓ Second break_start at 01:30 correctly blocked (first break still open) ✓\n";
    }

    /**
     * Test: CAN add second break_start AFTER closing first
     */
    public function test_can_add_second_break_after_closing_first(): void
    {
        echo "\n[Test] CAN add second break after closing first\n";

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

        // First break
        $this->postJson('/api/time-clock', [
            'shop_id' => $this->shopId,
            'user_id' => $this->userId,
            'clock_date' => $this->testDate,
            'time' => '17:00',
            'type' => 'break_start',
            'buffer_time' => 3,
        ])->assertStatus(201);

        $this->postJson('/api/time-clock', [
            'shop_id' => $this->shopId,
            'user_id' => $this->userId,
            'clock_date' => $this->testDate,
            'time' => '18:00',
            'type' => 'break_end',
            'buffer_time' => 3,
        ])->assertStatus(201);

        // Second break (after closing first)
        $response = $this->postJson('/api/time-clock', [
            'shop_id' => $this->shopId,
            'user_id' => $this->userId,
            'clock_date' => $this->testDate,
            'time' => '01:15',
            'type' => 'break_start',
            'buffer_time' => 3,
        ]);

        $response->assertStatus(201);
        echo "✓ Second break_start at 01:15 allowed (first break closed) ✓\n";
    }
}
