<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\UserTimeClock;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * Test: Break End validation with multiple breaks and cross-midnight scenarios
 * 
 * Ensure that break_end is properly validated to:
 * 1. Match the correct break_start (last unclosed break)
 * 2. Be chronologically after its paired break_start
 * 3. Not allow break_end before a later break_start
 */
class BreakEndValidationFixTest extends TestCase
{
    use RefreshDatabase;

    protected int $shopId = 1;
    protected int $userId = 5;
    protected string $testDate = '2026-01-11';

    protected function setUp(): void
    {
        parent::setUp();
        User::factory()->create(['id' => $this->userId]);
    }

    /**
     * Test Case: Multiple breaks with cross-midnight boundaries
     * 
     * Scenario:
     * - Day In at 23:00 on 11th
     * - Day Out at 01:00 on 12th
     * - Break Start at 23:45 on 11th
     * - Break End at 00:15 on 12th (valid - after break_start)
     * - Break Start at 00:30 on 12th (valid - new break)
     * - Break End at 00:14 on 12th (INVALID - before the 00:30 break_start)
     * 
     * Expected: Entry 6 should be REJECTED with error
     */
    public function test_break_end_with_multiple_breaks_overnight()
    {
        $shift_start = '06:00';
        $shift_end = '22:00';
        $buffer_time = 3;

        // Step 1: Add Day In at 23:00
        $response = $this->postJson('/api/time-clock', [
            'shop_id' => $this->shopId,
            'user_id' => $this->userId,
            'type' => 'day_in',
            'clock_date' => $this->testDate,
            'time' => '23:00:00',
            'shift_start' => $shift_start,
            'shift_end' => $shift_end,
            'buffer_time' => $buffer_time,
        ]);
        $this->assertEquals(200, $response->status(), 
            "Step 1 - Day In at 23:00 should be accepted. Response: " . $response->getContent());

        // Step 2: Add Day Out at 01:00 (next day)
        $response = $this->postJson('/api/time-clock', [
            'shop_id' => $this->shopId,
            'user_id' => $this->userId,
            'type' => 'day_out',
            'clock_date' => $this->testDate,
            'time' => '01:00:00',
            'shift_start' => $shift_start,
            'shift_end' => $shift_end,
            'buffer_time' => $buffer_time,
        ]);
        $this->assertEquals(200, $response->status(),
            "Step 2 - Day Out at 01:00 should be accepted. Response: " . $response->getContent());

        // Step 3: Add Break Start at 23:45
        $response = $this->postJson('/api/time-clock', [
            'shop_id' => $this->shopId,
            'user_id' => $this->userId,
            'type' => 'break_start',
            'clock_date' => $this->testDate,
            'time' => '23:45:00',
            'shift_start' => $shift_start,
            'shift_end' => $shift_end,
            'buffer_time' => $buffer_time,
        ]);
        $this->assertEquals(200, $response->status(),
            "Step 3 - Break Start at 23:45 should be accepted. Response: " . $response->getContent());

        // Step 4: Add Break End at 00:15 (next day) - valid
        $response = $this->postJson('/api/time-clock', [
            'shop_id' => $this->shopId,
            'user_id' => $this->userId,
            'type' => 'break_end',
            'clock_date' => $this->testDate,
            'time' => '00:15:00',
            'shift_start' => $shift_start,
            'shift_end' => $shift_end,
            'buffer_time' => $buffer_time,
        ]);
        $this->assertEquals(200, $response->status(),
            "Step 4 - Break End at 00:15 should be accepted (after 23:45 break_start). Response: " . $response->getContent());

        // Step 5: Add Break Start at 00:30 (next day) - new break
        $response = $this->postJson('/api/time-clock', [
            'shop_id' => $this->shopId,
            'user_id' => $this->userId,
            'type' => 'break_start',
            'clock_date' => $this->testDate,
            'time' => '00:30:00',
            'shift_start' => $shift_start,
            'shift_end' => $shift_end,
            'buffer_time' => $buffer_time,
        ]);
        $this->assertEquals(200, $response->status(),
            "Step 5 - Break Start at 00:30 should be accepted (new break). Response: " . $response->getContent());

        // Step 6: Add Break End at 00:14 (next day) - SHOULD FAIL
        // The break_start at 00:30 is still open
        // Trying to add break_end at 00:14 means break_end < break_start
        // This violates the rule: break_end must be after break_start
        $response = $this->postJson('/api/time-clock', [
            'shop_id' => $this->shopId,
            'user_id' => $this->userId,
            'type' => 'break_end',
            'clock_date' => $this->testDate,
            'time' => '00:14:00',
            'shift_start' => $shift_start,
            'shift_end' => $shift_end,
            'buffer_time' => $buffer_time,
        ]);
        $this->assertNotEquals(200, $response->status(),
            "Step 6 - Break End at 00:14 should be REJECTED (before the 00:30 break_start). Response: " . $response->getContent());
        
        // Verify the error message indicates the issue (must be after break_start)
        $responseData = json_decode($response->getContent(), true);
        $this->assertStringContainsString('must be after', strtolower($responseData['message'] ?? ''),
            "Error message should indicate break_end must be after break_start");

        // Verify only 5 entries were saved (step 6 should not be saved)
        $totalEntries = UserTimeClock::where('shop_id', $this->shopId)
            ->where('user_id', $this->userId)
            ->count();
        $this->assertEquals(5, $totalEntries,
            "Only 5 entries should be saved (step 6 should be rejected). Current count: $totalEntries");
    }

    /**
     * Test: Break End correctly pairs with most recent unclosed Break Start
     * 
     * Ensure that when multiple breaks exist, break_end pairs with the 
     * correct (most recent unclosed) break_start, not some other one.
     */
    public function test_break_end_pairs_with_correct_break_start()
    {
        $shift_start = '08:00';
        $shift_end = '17:00';
        $buffer_time = 2;

        // Add Day In
        $this->postJson('/api/time-clock', [
            'shop_id' => $this->shopId,
            'user_id' => $this->userId,
            'type' => 'day_in',
            'clock_date' => $this->testDate,
            'time' => '08:00:00',
            'shift_start' => $shift_start,
            'shift_end' => $shift_end,
            'buffer_time' => $buffer_time,
        ]);

        // Break 1: 09:00 - 10:00
        $this->postJson('/api/time-clock', [
            'shop_id' => $this->shopId,
            'user_id' => $this->userId,
            'type' => 'break_start',
            'clock_date' => $this->testDate,
            'time' => '09:00:00',
            'shift_start' => $shift_start,
            'shift_end' => $shift_end,
            'buffer_time' => $buffer_time,
        ]);

        $this->postJson('/api/time-clock', [
            'shop_id' => $this->shopId,
            'user_id' => $this->userId,
            'type' => 'break_end',
            'clock_date' => $this->testDate,
            'time' => '10:00:00',
            'shift_start' => $shift_start,
            'shift_end' => $shift_end,
            'buffer_time' => $buffer_time,
        ]);

        // Break 2: 12:00 - 13:00
        $this->postJson('/api/time-clock', [
            'shop_id' => $this->shopId,
            'user_id' => $this->userId,
            'type' => 'break_start',
            'clock_date' => $this->testDate,
            'time' => '12:00:00',
            'shift_start' => $shift_start,
            'shift_end' => $shift_end,
            'buffer_time' => $buffer_time,
        ]);

        $this->postJson('/api/time-clock', [
            'shop_id' => $this->shopId,
            'user_id' => $this->userId,
            'type' => 'break_end',
            'clock_date' => $this->testDate,
            'time' => '13:00:00',
            'shift_start' => $shift_start,
            'shift_end' => $shift_end,
            'buffer_time' => $buffer_time,
        ]);

        // Break 3: 14:00 - (open, not yet closed)
        $response = $this->postJson('/api/time-clock', [
            'shop_id' => $this->shopId,
            'user_id' => $this->userId,
            'type' => 'break_start',
            'clock_date' => $this->testDate,
            'time' => '14:00:00',
            'shift_start' => $shift_start,
            'shift_end' => $shift_end,
            'buffer_time' => $buffer_time,
        ]);

        $this->assertEquals(200, $response->status(), 
            "Break Start at 14:00 should be saved. Response: " . $response->getContent());

        // Trying to add break_end at 13:30 should FAIL
        // Because break_start at 14:00 is still open (it's the most recent one)
        // 13:30 < 14:00, so this is invalid
        $response = $this->postJson('/api/time-clock', [
            'shop_id' => $this->shopId,
            'user_id' => $this->userId,
            'type' => 'break_end',
            'clock_date' => $this->testDate,
            'time' => '13:30:00',
            'shift_start' => $shift_start,
            'shift_end' => $shift_end,
            'buffer_time' => $buffer_time,
        ]);

        $this->assertNotEquals(200, $response->status(),
            "Break End at 13:30 should be REJECTED (before the 14:00 break_start which is still open)");

        // But break_end at 14:30 should be accepted
        $response = $this->postJson('/api/time-clock', [
            'shop_id' => $this->shopId,
            'user_id' => $this->userId,
            'type' => 'break_end',
            'clock_date' => $this->testDate,
            'time' => '14:30:00',
            'shift_start' => $shift_start,
            'shift_end' => $shift_end,
            'buffer_time' => $buffer_time,
        ]);

        $this->assertEquals(200, $response->status(),
            "Break End at 14:30 should be accepted (after the 14:00 break_start)");

        // And break_end at 15:00 should be accepted for break_start 12:00
        // (since break_start 14:00 is now closed)
        // Wait, no - we need a new break_start at 15:00, then end it
        $this->postJson('/api/time-clock', [
            'shop_id' => $this->shopId,
            'user_id' => $this->userId,
            'type' => 'break_start',
            'clock_date' => $this->testDate,
            'time' => '15:00:00',
            'shift_start' => $shift_start,
            'shift_end' => $shift_end,
            'buffer_time' => $buffer_time,
        ]);

        $response = $this->postJson('/api/time-clock', [
            'shop_id' => $this->shopId,
            'user_id' => $this->userId,
            'type' => 'break_end',
            'clock_date' => $this->testDate,
            'time' => '15:30:00',
            'shift_start' => $shift_start,
            'shift_end' => $shift_end,
            'buffer_time' => $buffer_time,
        ]);

        $this->assertEquals(200, $response->status(),
            "Break End at 15:30 should be accepted (after the 15:00 break_start)");
    }
}
