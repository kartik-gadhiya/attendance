<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\UserTimeClock;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Test retroactive break entry after midnight
 */
class RetroactiveBreakAfterMidnightTest extends TestCase
{
    protected ?User $user = null;
    protected int $userId = 104;
    protected int $shopId = 1;
    protected string $testDate = '2026-01-10';

    protected function setUp(): void
    {
        parent::setUp();
        UserTimeClock::where('user_id', $this->userId)->delete();
        $this->user = User::find($this->userId) ?? User::factory()->create(['id' => $this->userId]);
    }

    /**
     * Test: Add retroactive break after midnight (before existing day-out)
     */
    public function test_add_retroactive_break_after_midnight(): void
    {
        echo "\n[Test] Add retroactive break at 01:15 (before day-out at 01:45)\n";

        // Create shift timeline
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
            'time' => '14:00',
            'type' => 'break_start',
            'buffer_time' => 3,
        ])->assertStatus(201);

        $this->postJson('/api/time-clock', [
            'shop_id' => $this->shopId,
            'user_id' => $this->userId,
            'clock_date' => $this->testDate,
            'time' => '15:00',
            'type' => 'break_end',
            'buffer_time' => 3,
        ])->assertStatus(201);

        $this->postJson('/api/time-clock', [
            'shop_id' => $this->shopId,
            'user_id' => $this->userId,
            'clock_date' => $this->testDate,
            'time' => '16:00',
            'type' => 'break_start',
            'buffer_time' => 3,
        ])->assertStatus(201);

        $this->postJson('/api/time-clock', [
            'shop_id' => $this->shopId,
            'user_id' => $this->userId,
            'clock_date' => $this->testDate,
            'time' => '17:00',
            'type' => 'break_end',
            'buffer_time' => 3,
        ])->assertStatus(201);

        // Add day-out at 01:45 (after midnight)
        $this->postJson('/api/time-clock', [
            'shop_id' => $this->shopId,
            'user_id' => $this->userId,
            'clock_date' => $this->testDate,
            'time' => '01:45',
            'type' => 'day_out',
            'buffer_time' => 3,
        ])->assertStatus(201);

        echo "✓ Created timeline: 13:30 day-in, breaks at 14-15 & 16-17, 01:45 day-out\n";

        // NOW try to add retroactive break at 01:15 (before 01:45 day-out)
        $response = $this->postJson('/api/time-clock', [
            'shop_id' => $this->shopId,
            'user_id' => $this->userId,
            'clock_date' => $this->testDate,
            'time' => '01:15',
            'type' => 'break_start',
            'buffer_time' => 3,
        ]);

        if ($response->status() !== 201) {
            echo "✗ Failed to add break at 01:15\n";
            echo "Response: " . json_encode($response->json()) . "\n";
        }

        $response->assertStatus(201);
        echo "✓ Added retroactive break at 01:15 (between 17:00 and 01:45) ✓\n";
    }
}
