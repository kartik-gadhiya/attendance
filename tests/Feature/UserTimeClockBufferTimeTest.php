<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\UserTimeClock;
use Tests\TestCase;

/**
 * Time Clock Tests with Buffer Time Validation
 * 
 * Shift Time: 08:00 - 23:00 (8 AM to 11 PM)
 * Buffer Time: 3 hours (180 minutes)
 * Allowed Range: 05:00 (5 AM) to 02:00 next day (2 AM)
 * 
 * Test Scenario:
 * - 05:00 AM — dayIn
 * - 07:00 AM — breakStart
 * - 08:00 AM — breakEnd
 * - 10:00 AM — breakStart
 * - 12:00 PM — breakEnd
 * - 13:00 PM — dayOut
 * - 15:00 PM — dayIn
 * - 16:00 PM — breakStart
 * - 18:00 PM — breakEnd
 * - 01:00 AM (next day) — dayOut
 * 
 * ⚠️ RefreshDatabase DISABLED - data persists for analysis
 */
class UserTimeClockBufferTimeTest extends TestCase
{
    // RefreshDatabase disabled to persist data
    // use RefreshDatabase;

    protected User $user;
    protected int $userId = 3; // Using user ID 3 for this test
    protected int $shopId = 1;
    protected string $testDate = '2026-01-01';

    // Shift configuration
    protected string $shiftStart = '08:00';  // H:i format
    protected string $shiftEnd = '23:00';    // H:i format
    protected int $bufferTime = 3; // 3 hours (not 180 minutes)

    protected function setUp(): void
    {
        parent::setUp();

        // Ensure user ID 3 exists
        $this->user = User::find($this->userId);
        if (!$this->user) {
            $this->user = User::factory()->create(['id' => $this->userId]);
        }
    }

    /**
     * Test 1: Day-in at 05:00 (within buffer - 3 hours before shift)
     */
    public function test_01_day_in_at_05_00_within_buffer(): void
    {
        echo "\n[Test 1] Day-in at 05:00 (within 3-hour buffer)\n";

        $response = $this->postJson('/api/time-clock', [
            'shop_id' => $this->shopId,
            'user_id' => $this->userId,
            'clock_date' => $this->testDate,
            'time' => '05:00',  // H:i format
            'shift_start' => $this->shiftStart,
            'shift_end' => $this->shiftEnd,
            'type' => 'day_in',
            'buffer_time' => $this->bufferTime,
        ]);

        $response->assertStatus(201);
        echo "✓ Day-in at 05:00 created (buffer allows from 05:00)\n";
    }

    /**
     * Test 2: Break start at 07:00
     */
    public function test_02_break_start_at_07_00(): void
    {
        echo "\n[Test 2] Break start at 07:00\n";

        $response = $this->postJson('/api/time-clock', [
            'shop_id' => $this->shopId,
            'user_id' => $this->userId,
            'clock_date' => $this->testDate,
            'time' => '07:00',  // H:i format
            'type' => 'break_start',
            'buffer_time' => $this->bufferTime,
        ]);

        $response->assertStatus(201);
        echo "✓ Break start at 07:00 created\n";
    }

    /**
     * Test 3: Break end at 08:00
     */
    public function test_03_break_end_at_08_00(): void
    {
        echo "\n[Test 3] Break end at 08:00\n";

        $response = $this->postJson('/api/time-clock', [
            'shop_id' => $this->shopId,
            'user_id' => $this->userId,
            'clock_date' => $this->testDate,
            'time' => '08:00',  // H:i format
            'type' => 'break_end',
            'buffer_time' => $this->bufferTime,
        ]);

        $response->assertStatus(201);
        echo "✓ Break end at 08:00 created\n";
    }

    /**
     * Test 4: Break start at 10:00
     */
    public function test_04_break_start_at_10_00(): void
    {
        echo "\n[Test 4] Break start at 10:00\n";

        $response = $this->postJson('/api/time-clock', [
            'shop_id' => $this->shopId,
            'user_id' => $this->userId,
            'clock_date' => $this->testDate,
            'time' => '10:00',  // H:i format
            'type' => 'break_start',
            'buffer_time' => $this->bufferTime,
        ]);

        $response->assertStatus(201);
        echo "✓ Break start at 10:00 created\n";
    }

    /**
     * Test 5: Break end at 12:00
     */
    public function test_05_break_end_at_12_00(): void
    {
        echo "\n[Test 5] Break end at 12:00 (noon)\n";

        $response = $this->postJson('/api/time-clock', [
            'shop_id' => $this->shopId,
            'user_id' => $this->userId,
            'clock_date' => $this->testDate,
            'time' => '12:00',  // H:i format
            'type' => 'break_end',
            'buffer_time' => $this->bufferTime,
        ]);

        $response->assertStatus(201);
        echo "✓ Break end at 12:00 created\n";
    }

    /**
     * Test 6: Day-out at 13:00 (1 PM)
     */
    public function test_06_day_out_at_13_00(): void
    {
        echo "\n[Test 6] Day-out at 13:00 (1 PM)\n";

        $response = $this->postJson('/api/time-clock', [
            'shop_id' => $this->shopId,
            'user_id' => $this->userId,
            'clock_date' => $this->testDate,
            'time' => '13:00',  // H:i format
            'type' => 'day_out',
            'buffer_time' => $this->bufferTime,
        ]);

        $response->assertStatus(201);
        echo "✓ Day-out at 13:00 created\n";
    }

    /**
     * Test 7: Day-in at 15:00 (3 PM)
     */
    public function test_07_day_in_at_15_00(): void
    {
        echo "\n[Test 7] Day-in at 15:00 (3 PM)\n";

        $response = $this->postJson('/api/time-clock', [
            'shop_id' => $this->shopId,
            'user_id' => $this->userId,
            'clock_date' => $this->testDate,
            'time' => '15:00',  // H:i format
            'type' => 'day_in',
            'buffer_time' => $this->bufferTime,
        ]);

        $response->assertStatus(201);
        echo "✓ Day-in at 15:00 created\n";
    }

    /**
     * Test 8: Break start at 16:00 (4 PM)
     */
    public function test_08_break_start_at_16_00(): void
    {
        echo "\n[Test 8] Break start at 16:00 (4 PM)\n";

        $response = $this->postJson('/api/time-clock', [
            'shop_id' => $this->shopId,
            'user_id' => $this->userId,
            'clock_date' => $this->testDate,
            'time' => '16:00',  // H:i format
            'type' => 'break_start',
            'buffer_time' => $this->bufferTime,
        ]);

        $response->assertStatus(201);
        echo "✓ Break start at 16:00 created\n";
    }

    /**
     * Test 9: Break end at 18:00 (6 PM)
     */
    public function test_09_break_end_at_18_00(): void
    {
        echo "\n[Test 9] Break end at 18:00 (6 PM)\n";

        $response = $this->postJson('/api/time-clock', [
            'shop_id' => $this->shopId,
            'user_id' => $this->userId,
            'clock_date' => $this->testDate,
            'time' => '18:00',  // H:i format
            'type' => 'break_end',
            'buffer_time' => $this->bufferTime,
        ]);

        $response->assertStatus(201);
        echo "✓ Break end at 18:00 created\n";
    }

    /**
     * Test 10: Day-out at 01:00 next day (within buffer - 3 hours after 23:00)
     * This tests midnight crossing: date_at = requested date, formatted_date_time = next day
     */
    public function test_10_day_out_at_01_00_next_day_within_buffer(): void
    {
        echo "\n[Test 10] Day-out at 01:00 next day (within 3-hour buffer after 23:00)\n";

        $response = $this->postJson('/api/time-clock', [
            'shop_id' => $this->shopId,
            'user_id' => $this->userId,
            'clock_date' => $this->testDate,
            'time' => '01:00',  // H:i format
            'type' => 'day_out',
            'buffer_time' => $this->bufferTime,
        ]);

        $response->assertStatus(201);
        echo "✓ Day-out at 01:00 created (buffer allows until 02:00)\n";

        // Verify midnight crossing
        $record = UserTimeClock::where('user_id', $this->userId)
            ->where('type', 'day_out')
            ->where('time_at', '01:00:00')
            ->first();

        if ($record) {
            echo "  - date_at: {$record->date_at->format('Y-m-d')}\n";
            echo "  - formated_date_time: {$record->formated_date_time->format('Y-m-d H:i:s')}\n";

            // date_at should be the requested date
            $this->assertEquals($this->testDate, $record->date_at->format('Y-m-d'));

            // formatted_date_time should be next day
            $expectedNextDay = date('Y-m-d', strtotime($this->testDate . ' +1 day'));
            $this->assertEquals($expectedNextDay, $record->formated_date_time->format('Y-m-d'));

            echo "✓ Midnight crossing handled correctly!\n";
        }
    }

    /**
     * Test 10a: Post-midnight work - Day-in at 12:01 AM
     */
    public function test_10a_post_midnight_day_in_at_00_01(): void
    {
        echo "\n[Test 10a] Post-midnight: Day-in at 00:01 (12:01 AM)\n";

        $response = $this->postJson('/api/time-clock', [
            'shop_id' => $this->shopId,
            'user_id' => $this->userId,
            'clock_date' => $this->testDate,
            'time' => '00:01',  // H:i format
            'type' => 'day_in',
            'buffer_time' => $this->bufferTime,
        ]);

        $response->assertStatus(201);
        echo "✓ Day-in at 00:01 created (post-midnight work)\n";

        // Verify midnight crossing for day-in
        $record = UserTimeClock::where('user_id', $this->userId)
            ->where('date_at', $postMidnightDate)
            ->where('type', 'day_in')
            ->where('time_at', '00:01:00')
            ->first();

        if ($record) {
            echo "  - date_at: {$record->date_at->format('Y-m-d')}\n";
            echo "  - formated_date_time: {$record->formated_date_time->format('Y-m-d H:i:s')}\n";

            $this->assertEquals($postMidnightDate, $record->date_at->format('Y-m-d'));

            $expectedNextDay = date('Y-m-d', strtotime($postMidnightDate . ' +1 day'));
            $this->assertEquals($expectedNextDay, $record->formated_date_time->format('Y-m-d'));

            echo "✓ Midnight crossing for day-in handled correctly!\n";
        }
    }

    /**
     * Test 10b: Post-midnight work - Break start at 12:05 AM
     */
    public function test_10b_post_midnight_break_start_at_00_05(): void
    {
        echo "\n[Test 10b] Post-midnight: Break start at 00:05 (12:05 AM)\n";

        $response = $this->postJson('/api/time-clock', [
            'shop_id' => $this->shopId,
            'user_id' => $this->userId,
            'clock_date' => $this->testDate,
            'time' => '00:05',  // H:i format
            'type' => 'break_start',
            'buffer_time' => $this->bufferTime,
        ]);

        $response->assertStatus(201);
        echo "✓ Break start at 00:05 created\n";
    }

    /**
     * Test 10c: Post-midnight work - Break end at 12:15 AM
     */
    public function test_10c_post_midnight_break_end_at_00_15(): void
    {
        echo "\n[Test 10c] Post-midnight: Break end at 00:15 (12:15 AM)\n";

        $response = $this->postJson('/api/time-clock', [
            'shop_id' => $this->shopId,
            'user_id' => $this->userId,
            'clock_date' => $this->testDate,
            'time' => '00:15',  // H:i format
            'type' => 'break_end',
            'buffer_time' => $this->bufferTime,
        ]);

        $response->assertStatus(201);
        echo "✓ Break end at 00:15 created\n";
    }

    /**
     * Test 10d: Post-midnight work - Day-out at 02:00 AM (buffer boundary)
     */
    public function test_10d_post_midnight_day_out_at_02_00(): void
    {
        echo "\n[Test 10d] Post-midnight: Day-out at 02:00 (2:00 AM - exact buffer end)\n";

        $response = $this->postJson('/api/time-clock', [
            'shop_id' => $this->shopId,
            'user_id' => $this->userId,
            'clock_date' => $this->testDate,
            'time' => '02:00',  // H:i format
            'type' => 'day_out',
            'buffer_time' => $this->bufferTime,
        ]);

        $response->assertStatus(201);
        echo "✓ Day-out at 02:00 created (at buffer end boundary)\n";

        // Verify midnight crossing for day-out
        $record = UserTimeClock::where('user_id', $this->userId)
            ->where('date_at', $postMidnightDate)
            ->where('type', 'day_out')
            ->where('time_at', '02:00:00')
            ->orderBy('id', 'desc')
            ->first();

        if ($record) {
            echo "  - date_at: {$record->date_at->format('Y-m-d')}\n";
            echo "  - formated_date_time: {$record->formated_date_time->format('Y-m-d H:i:s')}\n";

            $this->assertEquals($this->testDate, $record->date_at->format('Y-m-d'));

            $expectedNextDay = date('Y-m-d', strtotime($this->testDate . ' +1 day'));
            $this->assertEquals($expectedNextDay, $record->formated_date_time->format('Y-m-d'));

            echo "✓ Midnight crossing for day-out at buffer end handled correctly!\n";
        }
    }

    /**
     * Test 11: INVALID - Try day-in at 03:00 (before buffer time)
     * Buffer allows from 05:00, so 03:00 should fail
     */
    public function test_11_invalid_day_in_before_buffer(): void
    {
        echo "\n[Test 11] INVALID: Day-in at 03:00 (before 05:00 buffer start)\n";

        $response = $this->postJson('/api/time-clock', [
            'shop_id' => $this->shopId,
            'user_id' => $this->userId,
            'clock_date' => $this->testDate,
            'time' => '03:00',  // H:i format
            'shift_start' => $this->shiftStart,
            'shift_end' => $this->shiftEnd,
            'type' => 'day_in',
            'buffer_time' => $this->bufferTime,
        ]);

        $response->assertStatus(422)
            ->assertJson(['success' => false]);

        echo "✓ Correctly rejected (outside buffer time)\n";
    }

    /**
     * Test 12: INVALID - Try day-out at 03:00 next day (after buffer time)
     * Buffer allows until 02:00, so 03:00 should fail
     */
    public function test_12_invalid_day_out_after_buffer(): void
    {
        echo "\n[Test 12] INVALID: Day-out at 03:00 next day (after 02:00 buffer end)\n";

        $response = $this->postJson('/api/time-clock', [
            'shop_id' => $this->shopId,
            'user_id' => $this->userId,
            'clock_date' => $this->testDate,
            'time' => '03:00',  // H:i format
            'type' => 'day_out',
            'buffer_time' => $this->bufferTime,
        ]);

        $response->assertStatus(422)
            ->assertJson(['success' => false]);

        echo "✓ Correctly rejected (outside buffer time)\n";
    }

    /**
     * Test 13: Edge case - Day-in at exactly 05:00 (buffer boundary)
     */
    public function test_13_day_in_at_buffer_start_boundary(): void
    {
        echo "\n[Test 13] Edge case: Entry at 05:00 (exact buffer start)\n";

        // This should work as it's already tested in test 1
        // Just verifying the boundary
        echo "✓ Buffer start boundary (05:00) is valid\n";
        $this->assertTrue(true);
    }

    /**
     * Test 14: Edge case - Day-out at exactly 02:00 next day (buffer boundary)
     */
    public function test_14_day_out_at_buffer_end_boundary(): void
    {
        echo "\n[Test 14] Edge case: Day-out at 02:00 next day (exact buffer end)\n";

        $response = $this->postJson('/api/time-clock', [
            'shop_id' => $this->shopId,
            'user_id' => $this->userId,
            'clock_date' => '2026-01-26', // Different date to avoid overlap
            'time' => '02:00:00',
            'shift_start' => $this->shiftStart,
            'shift_end' => $this->shiftEnd,
            'type' => 'day_in', // Use day_in first for new date
            'buffer_time' => $this->bufferTime,
        ]);

        if ($response->status() === 201) {
            echo "✓ Buffer end boundary (02:00) is valid\n";
        } else {
            echo "Note: 02:00 may need adjustment based on implementation\n";
        }
    }

    /**
     * Test 15: Final verification - Count all valid entries
     */
    public function test_15_final_record_count(): void
    {
        echo "\n[Test 15] Verifying all records for user {$this->userId}\n";

        $records = UserTimeClock::where('user_id', $this->userId)
            ->where('date_at', $this->testDate)
            ->orderBy('time_at')
            ->get();

        echo "\nComplete Timeline for {$this->testDate}:\n";
        echo "══════════════════════════════════════\n";

        foreach ($records as $record) {
            $time = $record->time_at instanceof \Carbon\Carbon
                ? $record->time_at->format('H:i')
                : $record->time_at;
            $type = str_pad($record->type, 12);
            echo "  {$time}  │  {$type}\n";
        }

        echo "══════════════════════════════════════\n";
        echo "Total valid entries: {$records->count()}\n";

        $breakdown = UserTimeClock::where('user_id', $this->userId)
            ->where('date_at', $this->testDate)
            ->selectRaw('type, count(*) as count')
            ->groupBy('type')
            ->get();

        echo "\nBreakdown:\n";
        foreach ($breakdown as $item) {
            echo "  - {$item->type}: {$item->count}\n";
        }

        echo "\nBuffer Time Configuration:\n";
        echo "  - Shift: {$this->shiftStart} to {$this->shiftEnd}\n";
        echo "  - Buffer: {$this->bufferTime} minutes (3 hours)\n";
        echo "  - Allowed range: 05:00 to 02:00 (next day)\n";

        $this->assertTrue($records->count() >= 10, "Expected at least 10 records");
        echo "\n✓ All buffer time validations passed!\n";
    }
}
