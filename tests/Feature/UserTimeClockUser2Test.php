<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\UserTimeClock;
use Tests\TestCase;

/**
 * Time Clock Tests for User ID 2
 * 
 * Tests comprehensive scenarios for a single user including:
 * - Multiple day-in/out cycles
 * - Multiple breaks
 * - Overlap detection and validation
 * 
 * ⚠️ RefreshDatabase is DISABLED - data will persist for analysis
 */
class UserTimeClockUser2Test extends TestCase
{
    // RefreshDatabase is DISABLED to persist test data
    // use RefreshDatabase;

    protected User $user;
    protected int $userId = 2;
    protected int $shopId = 1;
    protected string $testDate = '2026-01-20';

    protected function setUp(): void
    {
        parent::setUp();

        // Ensure user ID 2 exists
        $this->user = User::find($this->userId);
        if (!$this->user) {
            $this->user = User::factory()->create(['id' => $this->userId]);
        }
    }

    /**
     * Test 1: First day-in for user 2
     */
    public function test_user_2_first_day_in(): void
    {
        echo "\n[Test 1] Creating first day-in for user 2 at 06:00\n";

        $response = $this->postJson('/api/time-clock', [
            'shop_id' => $this->shopId,
            'user_id' => $this->userId,
            'clock_date' => $this->testDate,
            'time' => '06:00:00',
            'shift_start' => '08:00:00',
            'shift_end' => '23:00:00',
            'type' => 'day_in',
            'buffer_time' => 180,
        ]);

        $response->assertStatus(201);
        echo "✓ Day-in created successfully\n";
    }

    /**
     * Test 2: First day-out for user 2
     */
    public function test_user_2_first_day_out(): void
    {
        echo "\n[Test 2] Creating first day-out for user 2 at 10:00\n";

        $response = $this->postJson('/api/time-clock', [
            'shop_id' => $this->shopId,
            'user_id' => $this->userId,
            'clock_date' => $this->testDate,
            'time' => '10:00:00',
            'type' => 'day_out',
            'buffer_time' => 180,
        ]);

        $response->assertStatus(201);
        echo "✓ Day-out created successfully\n";
    }

    /**
     * Test 3: Second day-in for user 2
     */
    public function test_user_2_second_day_in(): void
    {
        echo "\n[Test 3] Creating second day-in for user 2 at 11:00\n";

        $response = $this->postJson('/api/time-clock', [
            'shop_id' => $this->shopId,
            'user_id' => $this->userId,
            'clock_date' => $this->testDate,
            'time' => '11:00:00',
            'type' => 'day_in',
            'buffer_time' => 180,
        ]);

        $response->assertStatus(201);
        echo "✓ Second day-in created successfully\n";
    }

    /**
     * Test 4: First break start
     */
    public function test_user_2_first_break_start(): void
    {
        echo "\n[Test 4] Creating first break start for user 2 at 13:00\n";

        $response = $this->postJson('/api/time-clock', [
            'shop_id' => $this->shopId,
            'user_id' => $this->userId,
            'clock_date' => $this->testDate,
            'time' => '13:00:00',
            'type' => 'break_start',
            'buffer_time' => 180,
        ]);

        $response->assertStatus(201);
        echo "✓ Break start created successfully\n";
    }

    /**
     * Test 5: First break end
     */
    public function test_user_2_first_break_end(): void
    {
        echo "\n[Test 5] Creating first break end for user 2 at 13:30\n";

        $response = $this->postJson('/api/time-clock', [
            'shop_id' => $this->shopId,
            'user_id' => $this->userId,
            'clock_date' => $this->testDate,
            'time' => '13:30:00',
            'type' => 'break_end',
            'buffer_time' => 180,
        ]);

        $response->assertStatus(201);
        echo "✓ Break end created successfully\n";
    }

    /**
     * Test 6: Second day-out for user 2
     */
    public function test_user_2_second_day_out(): void
    {
        echo "\n[Test 6] Creating second day-out for user 2 at 15:00\n";

        $response = $this->postJson('/api/time-clock', [
            'shop_id' => $this->shopId,
            'user_id' => $this->userId,
            'clock_date' => $this->testDate,
            'time' => '15:00:00',
            'type' => 'day_out',
            'buffer_time' => 180,
        ]);

        $response->assertStatus(201);
        echo "✓ Second day-out created successfully\n";
    }

    /**
     * Test 7: Third day-in for user 2
     */
    public function test_user_2_third_day_in(): void
    {
        echo "\n[Test 7] Creating third day-in for user 2 at 16:00\n";

        $response = $this->postJson('/api/time-clock', [
            'shop_id' => $this->shopId,
            'user_id' => $this->userId,
            'clock_date' => $this->testDate,
            'time' => '16:00:00',
            'type' => 'day_in',
            'buffer_time' => 180,
        ]);

        $response->assertStatus(201);
        echo "✓ Third day-in created successfully\n";
    }

    /**
     * Test 8: Second break start
     */
    public function test_user_2_second_break_start(): void
    {
        echo "\n[Test 8] Creating second break start for user 2 at 18:00\n";

        $response = $this->postJson('/api/time-clock', [
            'shop_id' => $this->shopId,
            'user_id' => $this->userId,
            'clock_date' => $this->testDate,
            'time' => '18:00:00',
            'type' => 'break_start',
            'buffer_time' => 180,
        ]);

        $response->assertStatus(201);
        echo "✓ Second break start created successfully\n";
    }

    /**
     * Test 9: Second break end
     */
    public function test_user_2_second_break_end(): void
    {
        echo "\n[Test 9] Creating second break end for user 2 at 18:15\n";

        $response = $this->postJson('/api/time-clock', [
            'shop_id' => $this->shopId,
            'user_id' => $this->userId,
            'clock_date' => $this->testDate,
            'time' => '18:15:00',
            'type' => 'break_end',
            'buffer_time' => 180,
        ]);

        $response->assertStatus(201);
        echo "✓ Second break end created successfully\n";
    }

    /**
     * Test 10: Third day-out for user 2
     */
    public function test_user_2_third_day_out(): void
    {
        echo "\n[Test 10] Creating third day-out for user 2 at 20:00\n";

        $response = $this->postJson('/api/time-clock', [
            'shop_id' => $this->shopId,
            'user_id' => $this->userId,
            'clock_date' => $this->testDate,
            'time' => '20:00:00',
            'type' => 'day_out',
            'buffer_time' => 180,
        ]);

        $response->assertStatus(201);
        echo "✓ Third day-out created successfully\n";
    }

    /**
     * Test 11: Fourth day-in for user 2
     */
    public function test_user_2_fourth_day_in(): void
    {
        echo "\n[Test 11] Creating fourth day-in for user 2 at 21:00\n";

        $response = $this->postJson('/api/time-clock', [
            'shop_id' => $this->shopId,
            'user_id' => $this->userId,
            'clock_date' => $this->testDate,
            'time' => '21:00:00',
            'type' => 'day_in',
            'buffer_time' => 180,
        ]);

        $response->assertStatus(201);
        echo "✓ Fourth day-in created successfully\n";
    }

    /**
     * Test 12: Third break start
     */
    public function test_user_2_third_break_start(): void
    {
        echo "\n[Test 12] Creating third break start for user 2 at 21:30\n";

        $response = $this->postJson('/api/time-clock', [
            'shop_id' => $this->shopId,
            'user_id' => $this->userId,
            'clock_date' => $this->testDate,
            'time' => '21:30:00',
            'type' => 'break_start',
            'buffer_time' => 180,
        ]);

        $response->assertStatus(201);
        echo "✓ Third break start created successfully\n";
    }

    /**
     * Test 13: Third break end
     */
    public function test_user_2_third_break_end(): void
    {
        echo "\n[Test 13] Creating third break end for user 2 at 21:45\n";

        $response = $this->postJson('/api/time-clock', [
            'shop_id' => $this->shopId,
            'user_id' => $this->userId,
            'clock_date' => $this->testDate,
            'time' => '21:45:00',
            'type' => 'break_end',
            'buffer_time' => 180,
        ]);

        $response->assertStatus(201);
        echo "✓ Third break end created successfully\n";
    }

    /**
     * Test 14: Fourth and final day-out for user 2
     */
    public function test_user_2_fourth_day_out(): void
    {
        echo "\n[Test 14] Creating fourth day-out for user 2 at 22:30\n";

        $response = $this->postJson('/api/time-clock', [
            'shop_id' => $this->shopId,
            'user_id' => $this->userId,
            'clock_date' => $this->testDate,
            'time' => '22:30:00',
            'type' => 'day_out',
            'buffer_time' => 180,
        ]);

        $response->assertStatus(201);
        echo "✓ Fourth day-out created successfully\n";
    }

    /**
     * Test 15: OVERLAP TEST - Try to add day-in at existing time (should FAIL)
     */
    public function test_user_2_overlapping_day_in_fails(): void
    {
        echo "\n[Test 15] Attempting overlapping day-in at 06:00 (should FAIL)\n";

        $response = $this->postJson('/api/time-clock', [
            'shop_id' => $this->shopId,
            'user_id' => $this->userId,
            'clock_date' => $this->testDate,
            'time' => '06:00:00', // Same as first day-in
            'type' => 'day_in',
            'buffer_time' => 180,
        ]);

        $response->assertStatus(422)
            ->assertJson(['success' => false]);

        echo "✓ Overlapping day-in correctly rejected\n";
    }

    /**
     * Test 16: OVERLAP TEST - Try to add break during existing break (should FAIL)
     */
    public function test_user_2_overlapping_break_fails(): void
    {
        echo "\n[Test 16] Attempting break start during existing break 13:00-13:30 (should FAIL)\n";

        $response = $this->postJson('/api/time-clock', [
            'shop_id' => $this->shopId,
            'user_id' => $this->userId,
            'clock_date' => $this->testDate,
            'time' => '13:15:00', // During first break
            'type' => 'break_start',
            'buffer_time' => 180,
        ]);

        $response->assertStatus(422)
            ->assertJson(['success' => false]);

        echo "✓ Overlapping break correctly rejected\n";
    }

    /**
     * Test 17: OVERLAP TEST - Try to add day-out at existing time (should FAIL)
     */
    public function test_user_2_overlapping_day_out_fails(): void
    {
        echo "\n[Test 17] Attempting overlapping day-out at 10:00 (should FAIL)\n";

        $response = $this->postJson('/api/time-clock', [
            'shop_id' => $this->shopId,
            'user_id' => $this->userId,
            'clock_date' => $this->testDate,
            'time' => '10:00:00', // Same as first day-out
            'type' => 'day_out',
            'buffer_time' => 180,
        ]);

        $response->assertStatus(422)
            ->assertJson(['success' => false]);

        echo "✓ Overlapping day-out correctly rejected\n";
    }

    /**
     * Test 18: OVERLAP TEST - Try to add event during break period (should FAIL)
     */
    public function test_user_2_event_during_break_fails(): void
    {
        echo "\n[Test 18] Attempting day-in during break period 18:00-18:15 (should FAIL)\n";

        $response = $this->postJson('/api/time-clock', [
            'shop_id' => $this->shopId,
            'user_id' => $this->userId,
            'clock_date' => $this->testDate,
            'time' => '18:10:00', // During second break
            'type' => 'day_in',
            'buffer_time' => 180,
        ]);

        $response->assertStatus(422)
            ->assertJson(['success' => false]);

        echo "✓ Event during break correctly rejected\n";
    }

    /**
     * Test 19: OVERLAP TEST - Try break end during another break (should FAIL)
     */
    public function test_user_2_break_end_during_break_fails(): void
    {
        echo "\n[Test 19] Attempting break end during existing break (should FAIL)\n";

        $response = $this->postJson('/api/time-clock', [
            'shop_id' => $this->shopId,
            'user_id' => $this->userId,
            'clock_date' => $this->testDate,
            'time' => '21:40:00', // During third break
            'type' => 'break_end',
            'buffer_time' => 180,
        ]);

        $response->assertStatus(422)
            ->assertJson(['success' => false]);

        echo "✓ Break end during break correctly rejected\n";
    }

    /**
     * Test 20: Final verification - Count all records for user 2
     */
    public function test_user_2_final_record_count(): void
    {
        echo "\n[Test 20] Verifying final record count for user 2\n";

        $count = UserTimeClock::where('user_id', $this->userId)
            ->where('date_at', $this->testDate)
            ->count();

        echo "Total records for user 2 on {$this->testDate}: {$count}\n";
        echo "Expected: 14 valid entries (4 day-in, 4 day-out, 3 break-start, 3 break-end)\n";

        // Breakdown
        $dayInCount = UserTimeClock::where('user_id', $this->userId)
            ->where('date_at', $this->testDate)
            ->where('type', 'day_in')
            ->count();

        $dayOutCount = UserTimeClock::where('user_id', $this->userId)
            ->where('date_at', $this->testDate)
            ->where('type', 'day_out')
            ->count();

        $breakStartCount = UserTimeClock::where('user_id', $this->userId)
            ->where('date_at', $this->testDate)
            ->where('type', 'break_start')
            ->count();

        $breakEndCount = UserTimeClock::where('user_id', $this->userId)
            ->where('date_at', $this->testDate)
            ->where('type', 'break_end')
            ->count();

        echo "  - Day-in: {$dayInCount}\n";
        echo "  - Day-out: {$dayOutCount}\n";
        echo "  - Break-start: {$breakStartCount}\n";
        echo "  - Break-end: {$breakEndCount}\n";

        $this->assertTrue($count >= 14, "Expected at least 14 records for user 2");
        echo "\n✓ All records created and stored successfully!\n";
    }
}
