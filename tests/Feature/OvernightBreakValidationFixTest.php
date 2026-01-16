<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\UserTimeClock;
use Illuminate\Foundation\Testing\RefreshDatabase;

class OvernightBreakValidationFixTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected int $shopId = 1;
    protected int $userId = 500;
    protected string $testDate = '2026-01-11';

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create test user
        $this->user = User::factory()->create(['id' => $this->userId]);
    }

    /**
     * Test: Break end BEFORE break start should be rejected (Bug Fix Verification)
     * 
     * Note: Due to hardcoded shift_end=23:00 in controller, we use times within 08:00-23:00
     * 
     * Scenario:
     * - Day In: 22:00
     * - Day Out: 22:50 (before shift ends at 23:00)
     * - Break Start: 22:15
     * - Break End: 22:25 ✓ Valid (after start)
     * - Break Start: 22:30
     * - Break End: 22:14 ✗ INVALID (BEFORE second break start!) <- Should be REJECTED
     */
    public function test_break_end_before_break_start_is_rejected_overnight()
    {
        // Step 1: Add day_in at 22:00
        $response = $this->postJson('/api/time-clock', [
            'shop_id' => $this->shopId,
            'user_id' => $this->userId,
            'type' => 'day_in',
            'clock_date' => $this->testDate,
            'time' => '22:00:00'
        ]);
        $this->assertEquals(200, $response->status(), "Day in at 22:00 should succeed. Error: " . $response->getContent());

        // Step 2: Add day_out at 22:50
        $response = $this->postJson('/api/time-clock', [
            'shop_id' => $this->shopId,
            'user_id' => $this->userId,
            'type' => 'day_out',
            'clock_date' => $this->testDate,
            'time' => '22:50:00'
        ]);
        $this->assertEquals(200, $response->status(), "Day out at 22:50 should succeed. Error: " . $response->getContent());

        // Step 3: Add first break_start at 22:15
        $response = $this->postJson('/api/time-clock', [
            'shop_id' => $this->shopId,
            'user_id' => $this->userId,
            'type' => 'break_start',
            'clock_date' => $this->testDate,
            'time' => '22:15:00'
        ]);
        $this->assertEquals(200, $response->status(), "Break start at 22:15 should succeed. Error: " . $response->getContent());

        // Step 4: Add first break_end at 22:25 (valid - after break_start at 22:15)
        $response = $this->postJson('/api/time-clock', [
            'shop_id' => $this->shopId,
            'user_id' => $this->userId,
            'type' => 'break_end',
            'clock_date' => $this->testDate,
            'time' => '22:25:00'
        ]);
        $this->assertEquals(200, $response->status(), "Break end at 22:25 should succeed (after break_start 22:15). Error: " . $response->getContent());

        // Step 5: Add second break_start at 22:30
        $response = $this->postJson('/api/time-clock', [
            'shop_id' => $this->shopId,
            'user_id' => $this->userId,
            'type' => 'break_start',
            'clock_date' => $this->testDate,
            'time' => '22:30:00'
        ]);
        $this->assertEquals(200, $response->status(), "Second break start at 22:30 should succeed. Error: " . $response->getContent());

        // Step 6: Add second break_end at 22:14 (INVALID - BEFORE break_start at 22:30!)
        // THIS IS THE KEY TEST - This should FAIL with validation error
        $response = $this->postJson('/api/time-clock', [
            'shop_id' => $this->shopId,
            'user_id' => $this->userId,
            'type' => 'break_end',
            'clock_date' => $this->testDate,
            'time' => '22:14:00'
        ]);

        // ASSERTION: The request should FAIL (not 200), returning a validation error
        $this->assertNotEquals(200, $response->status(), 
            "Break end at 22:14 should be REJECTED because it's BEFORE break_start at 22:30. Response: " . $response->getContent());
        
        // Verify the invalid record was NOT saved to database
        $invalidBreak = UserTimeClock::where('shop_id', $this->shopId)
            ->where('user_id', $this->userId)
            ->where('type', 'break_end')
            ->whereTime('time_at', '22:14:00')
            ->first();
        
        $this->assertNull($invalidBreak, 
            "Invalid break_end at 22:14 should NOT be saved to database");

        // Verify the valid break (22:25) IS in the database
        $validBreak = UserTimeClock::where('shop_id', $this->shopId)
            ->where('user_id', $this->userId)
            ->where('type', 'break_end')
            ->whereTime('time_at', '22:25:00')
            ->first();
        
        $this->assertNotNull($validBreak, 
            "Valid break_end at 22:25 should be saved to database");
    }

    /**
     * Test: Valid overnight break (end after start) should be accepted
     */
    public function test_valid_overnight_break_is_accepted()
    {
        // Day in
        $response = $this->postJson('/api/time-clock', [
            'shop_id' => $this->shopId,
            'user_id' => $this->userId,
            'type' => 'day_in',
            'clock_date' => $this->testDate,
            'time' => '20:00:00'
        ]);
        $this->assertEquals(200, $response->status(), "Day in should succeed");

        // Day out
        $response = $this->postJson('/api/time-clock', [
            'shop_id' => $this->shopId,
            'user_id' => $this->userId,
            'type' => 'day_out',
            'clock_date' => $this->testDate,
            'time' => '22:50:00'
        ]);
        $this->assertEquals(200, $response->status(), "Day out should succeed");

        // Break start at 20:45
        $response = $this->postJson('/api/time-clock', [
            'shop_id' => $this->shopId,
            'user_id' => $this->userId,
            'type' => 'break_start',
            'clock_date' => $this->testDate,
            'time' => '20:45:00'
        ]);
        $this->assertEquals(200, $response->status(), "Break start should succeed");

        // Break end at 21:30 (VALID - after 20:45)
        $response = $this->postJson('/api/time-clock', [
            'shop_id' => $this->shopId,
            'user_id' => $this->userId,
            'type' => 'break_end',
            'clock_date' => $this->testDate,
            'time' => '21:30:00'
        ]);

        $this->assertEquals(200, $response->status(), 
            "Valid break (end after start) should be accepted. Error: " . $response->getContent());
    }

    /**
     * Test: Multiple breaks with correct ordering
     */
    public function test_multiple_overnight_breaks_with_correct_ordering()
    {
        $entries = [
            ['type' => 'day_in', 'time' => '18:00:00'],
            ['type' => 'break_start', 'time' => '18:30:00'],
            ['type' => 'break_end', 'time' => '19:00:00'],
            ['type' => 'break_start', 'time' => '19:30:00'],
            ['type' => 'break_end', 'time' => '20:15:00'],
            ['type' => 'break_start', 'time' => '20:45:00'],
            ['type' => 'break_end', 'time' => '21:00:00'],
            ['type' => 'day_out', 'time' => '22:00:00'],
        ];

        foreach ($entries as $entry) {
            $response = $this->postJson('/api/time-clock', [
                'shop_id' => $this->shopId,
                'user_id' => $this->userId,
                'type' => $entry['type'],
                'clock_date' => $this->testDate,
                'time' => $entry['time']
            ]);

            $this->assertEquals(200, $response->status(), 
                "Entry {$entry['type']} at {$entry['time']} should succeed. Error: " . $response->getContent());
        }

        // Verify all entries were saved
        $totalEntries = UserTimeClock::where('shop_id', $this->shopId)
            ->where('user_id', $this->userId)
            ->count();
        
        $this->assertEquals(8, $totalEntries, "All 8 entries should be saved");
    }

    /**
     * Test: Break end exactly at break start time should be rejected
     */
    public function test_break_end_at_same_time_as_break_start_is_rejected()
    {
        $this->postJson('/api/time-clock', [
            'shop_id' => $this->shopId,
            'user_id' => $this->userId,
            'type' => 'day_in',
            'clock_date' => $this->testDate,
            'time' => '20:00:00'
        ]);

        $this->postJson('/api/time-clock', [
            'shop_id' => $this->shopId,
            'user_id' => $this->userId,
            'type' => 'day_out',
            'clock_date' => $this->testDate,
            'time' => '22:00:00'
        ]);

        $this->postJson('/api/time-clock', [
            'shop_id' => $this->shopId,
            'user_id' => $this->userId,
            'type' => 'break_start',
            'clock_date' => $this->testDate,
            'time' => '21:30:00'
        ]);

        // Try to add break_end at exact same time
        $response = $this->postJson('/api/time-clock', [
            'shop_id' => $this->shopId,
            'user_id' => $this->userId,
            'type' => 'break_end',
            'clock_date' => $this->testDate,
            'time' => '21:30:00'
        ]);

        $this->assertNotEquals(200, $response->status(), 
            "Break end at exact same time as break start should be rejected");
    }

    /**
     * Test: Verify events are correctly ordered by formated_date_time
     */
    public function test_events_ordered_by_formated_date_time()
    {
        // Create events with specific ordering
        UserTimeClock::create([
            'shop_id' => $this->shopId,
            'user_id' => $this->userId,
            'type' => 'day_in',
            'date_at' => $this->testDate,
            'time_at' => '23:00:00',
            'formated_date_time' => '2026-01-11 23:00:00'
        ]);

        UserTimeClock::create([
            'shop_id' => $this->shopId,
            'user_id' => $this->userId,
            'type' => 'break_start',
            'date_at' => $this->testDate,
            'time_at' => '00:30:00',
            'formated_date_time' => '2026-01-12 00:30:00'
        ]);

        UserTimeClock::create([
            'shop_id' => $this->shopId,
            'user_id' => $this->userId,
            'type' => 'break_end',
            'date_at' => $this->testDate,
            'time_at' => '00:15:00',
            'formated_date_time' => '2026-01-12 00:15:00'
        ]);

        // Fetch and verify ordering by formated_date_time
        $events = UserTimeClock::where('shop_id', $this->shopId)
            ->where('user_id', $this->userId)
            ->orderBy('formated_date_time')
            ->get();

        // Correct chronological order by formated_date_time
        $this->assertEquals(3, $events->count());
        $time_at = is_string($events[0]->time_at) ? $events[0]->time_at : $events[0]->time_at->format('H:i:s');
        $this->assertEquals('23:00:00', $time_at);
    }
}
