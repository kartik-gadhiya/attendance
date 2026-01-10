<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\UserTimeClock;
use Tests\TestCase;

/**
 * State-Based Validation Tests
 * Tests the new state validation rules
 */
class StateValidationTest extends TestCase
{
    protected User $user;
    protected int $userId = 10;
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
     * Test 1: First day-in should work
     */
    public function test_01_first_day_in_succeeds(): void
    {
        echo "\n[Test 1] First day-in should succeed\n";

        $response = $this->postJson('/api/time-clock', [
            'shop_id' => $this->shopId,
            'user_id' => $this->userId,
            'clock_date' => $this->testDate,
            'time' => '08:00',
            'shift_start' => '08:00',
            'shift_end' => '23:00',
            'type' => 'day_in',
            'buffer_time' => 3,
        ]);

        $response->assertStatus(201);
        echo "✓ First day-in successful\n";
    }

    /**
     * Test 2: Duplicate day-in should fail
     */
    public function test_02_duplicate_day_in_fails(): void
    {
        echo "\n[Test 2] Duplicate day-in should fail\n";

        // First day-in
        $this->postJson('/api/time-clock', [
            'shop_id' => $this->shopId,
            'user_id' => $this->userId,
            'clock_date' => $this->testDate,
            'time' => '08:00',
            'shift_start' => '08:00',
            'shift_end' => '23:00',
            'type' => 'day_in',
            'buffer_time' => 3,
        ]);

        // Duplicate day-in
        $response = $this->postJson('/api/time-clock', [
            'shop_id' => $this->shopId,
            'user_id' => $this->userId,
            'clock_date' => $this->testDate,
            'time' => '08:30',
            'type' => 'day_in',
            'buffer_time' => 3,
        ]);

        $response->assertStatus(422);
        echo "✓ Duplicate day-in correctly rejected\n";
    }

    /**
     * Test 3: Break without day-in should fail
     */
    public function test_03_break_without_day_in_fails(): void
    {
        echo "\n[Test 3] Break without day-in should fail\n";

        $response = $this->postJson('/api/time-clock', [
            'shop_id' => $this->shopId,
            'user_id' => $this->userId,
            'clock_date' => $this->testDate,
            'time' => '09:00',
            'type' => 'break_start',
            'buffer_time' => 3,
        ]);

        $response->assertStatus(422);
        echo "✓ Break without day-in correctly rejected\n";
    }

    /**
     * Test 4: Valid flow: day-in, break-start, break-end
     */
    public function test_04_valid_break_flow(): void
    {
        echo "\n[Test 4] Valid flow: day-in → break-start → break-end\n";

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
        ]);

        // Break start
        $response1 = $this->postJson('/api/time-clock', [
            'shop_id' => $this->shopId,
            'user_id' => $this->userId,
            'clock_date' => $this->testDate,
            'time' => '09:00',
            'type' => 'break_start',
            'buffer_time' => 3,
        ]);

        // Break end
        $response2 = $this->postJson('/api/time-clock', [
            'shop_id' => $this->shopId,
            'user_id' => $this->userId,
            'clock_date' => $this->testDate,
            'time' => '09:15',
            'type' => 'break_end',
            'buffer_time' => 3,
        ]);

        $response1->assertStatus(201);
        $response2->assertStatus(201);
        echo "✓ Valid break flow successful\n";
    }

    /**
     * Test 5: Break-end before break-start should fail
     */
    public function test_05_break_end_before_start_fails(): void
    {
        echo "\n[Test 5] Break-end before break-start should fail\n";

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
        ]);

        // Break start at 09:00
        $this->postJson('/api/time-clock', [
            'shop_id' => $this->shopId,
            'user_id' => $this->userId,
            'clock_date' => $this->testDate,
            'time' => '09:00',
            'type' => 'break_start',
            'buffer_time' => 3,
        ]);

        // Break end at 08:45 (before start!)
        $response = $this->postJson('/api/time-clock', [
            'shop_id' => $this->shopId,
            'user_id' => $this->userId,
            'clock_date' => $this->testDate,
            'time' => '08:45',
            'type' => 'break_end',
            'buffer_time' => 3,
        ]);

        $response->assertStatus(422);
        echo "✓ Break-end before start correctly rejected\n";
    }

    /**
     * Test 6: Day-out with open break should fail
     */
    public function test_06_day_out_with_open_break_fails(): void
    {
        echo "\n[Test 6] Day-out with open break should fail\n";

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
        ]);

        // Break start
        $this->postJson('/api/time-clock', [
            'shop_id' => $this->shopId,
            'user_id' => $this->userId,
            'clock_date' => $this->testDate,
            'time' => '09:00',
            'type' => 'break_start',
            'buffer_time' => 3,
        ]);

        // Day-out without ending break
        $response = $this->postJson('/api/time-clock', [
            'shop_id' => $this->shopId,
            'user_id' => $this->userId,
            'clock_date' => $this->testDate,
            'time' => '10:00',
            'type' => 'day_out',
            'buffer_time' => 3,
        ]);

        $response->assertStatus(422);
        echo "✓ Day-out with open break correctly rejected\n";
    }

    /**
     * Test 7: Complete valid flow
     */
    public function test_07_complete_valid_flow(): void
    {
        echo "\n[Test 7] Complete valid flow\n";

        // Day-in at 08:00
        $r1 = $this->postJson('/api/time-clock', [
            'shop_id' => $this->shopId,
            'user_id' => $this->userId,
            'clock_date' => $this->testDate,
            'time' => '08:00',
            'shift_start' => '08:00',
            'shift_end' => '23:00',
            'type' => 'day_in',
            'buffer_time' => 3,
        ]);

        // Break start at 09:00
        $r2 = $this->postJson('/api/time-clock', [
            'shop_id' => $this->shopId,
            'user_id' => $this->userId,
            'clock_date' => $this->testDate,
            'time' => '09:00',
            'type' => 'break_start',
            'buffer_time' => 3,
        ]);

        // Break end at 09:15
        $r3 = $this->postJson('/api/time-clock', [
            'shop_id' => $this->shopId,
            'user_id' => $this->userId,
            'clock_date' => $this->testDate,
            'time' => '09:15',
            'type' => 'break_end',
            'buffer_time' => 3,
        ]);

        // Day-out at 10:00
        $r4 = $this->postJson('/api/time-clock', [
            'shop_id' => $this->shopId,
            'user_id' => $this->userId,
            'clock_date' => $this->testDate,
            'time' => '10:00',
            'type' => 'day_out',
            'buffer_time' => 3,
        ]);

        $r1->assertStatus(201);
        $r2->assertStatus(201);
        $r3->assertStatus(201);
        $r4->assertStatus(201);

        echo "✓ Complete flow successful: 08:00 day-in → 09:00 break → 09:15 break-end → 10:00 day-out\n";

        // Verify all 4 records in database
        $count = UserTimeClock::where('user_id', $this->userId)
            ->where('date_at', $this->testDate)
            ->count();

        $this->assertEquals(4, $count);
        echo "✓ All 4 records stored in database\n";
    }
}
