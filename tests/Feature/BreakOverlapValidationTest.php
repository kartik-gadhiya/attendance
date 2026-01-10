<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\UserTimeClock;
use Tests\TestCase;

/**
 * Test break overlap validation
 */
class BreakOverlapValidationTest extends TestCase
{
    protected ?User $user = null;
    protected int $userId = 108;
    protected int $shopId = 1;
    protected string $testDate = '2026-01-12';

    protected function setUp(): void
    {
        parent::setUp();
        UserTimeClock::where('user_id', $this->userId)->delete();
        $this->user = User::find($this->userId) ?? User::factory()->create(['id' => $this->userId]);
    }

    /**
     * Test: Cannot add duplicate break_start at same time
     */
    public function test_cannot_add_duplicate_break_start_at_same_time(): void
    {
        echo "\n[Test] Cannot add duplicate break_start at 08:00\n";

        // Create day-in
        $this->postJson('/api/time-clock', [
            'shop_id' => $this->shopId,
            'user_id' => $this->userId,
            'clock_date' => $this->testDate,
            'time' => '07:00',
            'type' => 'day_in',
            'shift_start' => '08:00',
            'shift_end' => '23:00',
            'buffer_time' => 3,
        ])->assertStatus(201);

        // Add first break_start at 08:00
        $this->postJson('/api/time-clock', [
            'shop_id' => $this->shopId,
            'user_id' => $this->userId,
            'clock_date' => $this->testDate,
            'time' => '08:00',
            'type' => 'break_start',
            'buffer_time' => 3,
        ])->assertStatus(201);

        echo "✓ First break_start at 08:00 added\n";

        // Try to add ANOTHER break_start at 08:00 (duplicate time)
        $response = $this->postJson('/api/time-clock', [
            'shop_id' => $this->shopId,
            'user_id' => $this->userId,
            'clock_date' => $this->testDate,
            'time' => '08:00',
            'type' => 'break_start',
            'buffer_time' => 3,
        ]);

        if ($response->status() !== 422) {
            echo "✗ FAILED - Duplicate break_start was allowed!\n";
            echo "Response: " . json_encode($response->json()) . "\n";
        }

        $response->assertStatus(422);
        echo "✓ Duplicate break_start correctly rejected ✓\n";
    }

    /**
     * Test: Cannot add break that overlaps with existing break
     */
    public function test_cannot_add_overlapping_break(): void
    {
        echo "\n[Test] Cannot add break overlapping with 09:00-10:00\n";

        $this->postJson('/api/time-clock', [
            'shop_id' => $this->shopId,
            'user_id' => $this->userId,
            'clock_date' => $this->testDate,
            'time' => '07:00',
            'type' => 'day_in',
            'shift_start' => '08:00',
            'shift_end' => '23:00',
            'buffer_time' => 3,
        ])->assertStatus(201);

        // Add complete break: 09:00-10:00
        $this->postJson('/api/time-clock', [
            'shop_id' => $this->shopId,
            'user_id' => $this->userId,
            'clock_date' => $this->testDate,
            'time' => '09:00',
            'type' => 'break_start',
            'buffer_time' => 3,
        ])->assertStatus(201);

        $this->postJson('/api/time-clock', [
            'shop_id' => $this->shopId,
            'user_id' => $this->userId,
            'clock_date' => $this->testDate,
            'time' => '10:00',
            'type' => 'break_end',
            'buffer_time' => 3,
        ])->assertStatus(201);

        echo "✓ Added break 09:00-10:00\n";

        // Try to add break_start at 09:30 (inside existing break)
        $response = $this->postJson('/api/time-clock', [
            'shop_id' => $this->shopId,
            'user_id' => $this->userId,
            'clock_date' => $this->testDate,
            'time' => '09:30',
            'type' => 'break_start',
            'buffer_time' => 3,
        ]);

        $response->assertStatus(422);
        echo "✓ Break at 09:30 (inside 09:00-10:00) correctly rejected ✓\n";
    }

    /**
     * Test: CAN add break after previous break ends
     */
    public function test_can_add_break_after_previous_break_ends(): void
    {
        echo "\n[Test] CAN add break after 09:00-10:00\n";

        $this->postJson('/api/time-clock', [
            'shop_id' => $this->shopId,
            'user_id' => $this->userId,
            'clock_date' => $this->testDate,
            'time' => '07:00',
            'type' => 'day_in',
            'shift_start' => '08:00',
            'shift_end' => '23:00',
            'buffer_time' => 3,
        ])->assertStatus(201);

        // First break: 09:00-10:00
        $this->postJson('/api/time-clock', [
            'shop_id' => $this->shopId,
            'user_id' => $this->userId,
            'clock_date' => $this->testDate,
            'time' => '09:00',
            'type' => 'break_start',
            'buffer_time' => 3,
        ])->assertStatus(201);

        $this->postJson('/api/time-clock', [
            'shop_id' => $this->shopId,
            'user_id' => $this->userId,
            'clock_date' => $this->testDate,
            'time' => '10:00',
            'type' => 'break_end',
            'buffer_time' => 3,
        ])->assertStatus(201);

        // Second break: 11:00-12:00 (after first break)
        $r1 = $this->postJson('/api/time-clock', [
            'shop_id' => $this->shopId,
            'user_id' => $this->userId,
            'clock_date' => $this->testDate,
            'time' => '11:00',
            'type' => 'break_start',
            'buffer_time' => 3,
        ]);

        $r2 = $this->postJson('/api/time-clock', [
            'shop_id' => $this->shopId,
            'user_id' => $this->userId,
            'clock_date' => $this->testDate,
            'time' => '12:00',
            'type' => 'break_end',
            'buffer_time' => 3,
        ]);

        $r1->assertStatus(201);
        $r2->assertStatus(201);
        echo "✓ Second break 11:00-12:00 allowed (after first break) ✓\n";
    }
}
