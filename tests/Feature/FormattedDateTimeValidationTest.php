<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\UserTimeClock;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * Test that formatted_date_time is used exclusively for validation
 * This ensures accurate handling of shifts that cross midnight
 */
class FormattedDateTimeValidationTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected int $shopId = 1;
    protected int $userId = 600;
    protected string $testDate = '2026-01-11';

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create(['id' => $this->userId]);
    }

    /**
     * Test: Shift spanning midnight (06:00 to 02:00 next day)
     * User should be able to add records from 06:00 AM on 11th until 02:00 AM on 12th
     * 
     * Expected behavior:
     * - Day In at 06:00 on 11th → formated_date_time: 2026-01-11 06:00:00
     * - Break Start at 13:00 on 11th → formated_date_time: 2026-01-11 13:00:00
     * - Break End at 14:00 on 11th → formated_date_time: 2026-01-11 14:00:00
     * - Break Start at 23:30 on 11th → formated_date_time: 2026-01-11 23:30:00
     * - Break End at 00:30 on 12th → formated_date_time: 2026-01-12 00:30:00 ✓ (correctly next day)
     * - Day Out at 01:30 on 12th → formated_date_time: 2026-01-12 01:30:00
     */
    public function test_shift_spanning_midnight_with_buffer()
    {
        // Scenario: 06:00 to 02:00 (next day) with 3-hour buffer
        $entries = [
            ['type' => 'day_in', 'time' => '06:00:00', 'expected' => 'success'],
            ['type' => 'break_start', 'time' => '13:00:00', 'expected' => 'success'],
            ['type' => 'break_end', 'time' => '14:00:00', 'expected' => 'success'],
            ['type' => 'break_start', 'time' => '23:30:00', 'expected' => 'success'],
            ['type' => 'break_end', 'time' => '00:30:00', 'expected' => 'success'], // Next day
            ['type' => 'day_out', 'time' => '01:30:00', 'expected' => 'success'],   // Next day
        ];

        foreach ($entries as $entry) {
            $response = $this->postJson('/api/time-clock', [
                'shop_id' => $this->shopId,
                'user_id' => $this->userId,
                'type' => $entry['type'],
                'clock_date' => $this->testDate,
                'time' => $entry['time'],
                'shift_start' => '06:00',
                'shift_end' => '02:00',
                'buffer_time' => 3,
            ]);

            if ($entry['expected'] === 'success') {
                $this->assertEquals(200, $response->status(),
                    "Entry {$entry['type']} at {$entry['time']} should succeed. Response: " . $response->getContent());
            } else {
                $this->assertNotEquals(200, $response->status(),
                    "Entry {$entry['type']} at {$entry['time']} should fail. Response: " . $response->getContent());
            }
        }

        // Verify all 6 entries were saved
        $totalEntries = UserTimeClock::where('shop_id', $this->shopId)
            ->where('user_id', $this->userId)
            ->count();
        $this->assertEquals(6, $totalEntries, "All 6 entries should be saved with midnight-crossing shift");

        // Verify formated_date_time values are correct
        // Get the break_end that occurred at 00:30 (after midnight)
        $breakEnd = UserTimeClock::where('shop_id', $this->shopId)
            ->where('user_id', $this->userId)
            ->where('type', 'break_end')
            ->orderByDesc('formated_date_time')  // Latest break_end should be the 00:30 one
            ->first();

        // The 00:30 break_end should have formated_date_time on 12th, not 11th
        $this->assertStringContainsString('2026-01-12', $breakEnd->formated_date_time,
            "Break end at 00:30 should be recorded as 2026-01-12 (next day) in formated_date_time");
    }

    /**
     * Test: Records added after midnight are correctly stored in formated_date_time
     * 
     * Attendance date: 11th
     * Record created after midnight (00:15 on 12th) should be stored as:
     * - date_at: 2026-01-11 (same date_at as shift start)
     * - formated_date_time: 2026-01-12 00:15:00 (correctly next day)
     */
    public function test_records_after_midnight_use_correct_formatted_date_time()
    {
        // Day In at 22:00 on 11th
        $this->postJson('/api/time-clock', [
            'shop_id' => $this->shopId,
            'user_id' => $this->userId,
            'type' => 'day_in',
            'clock_date' => $this->testDate,
            'time' => '22:00:00',
            'shift_start' => '22:00',
            'shift_end' => '06:00',
            'buffer_time' => 1,
        ]);

        // Break Start at 23:00 on 11th
        $this->postJson('/api/time-clock', [
            'shop_id' => $this->shopId,
            'user_id' => $this->userId,
            'type' => 'break_start',
            'clock_date' => $this->testDate,
            'time' => '23:00:00',
            'shift_start' => '22:00',
            'shift_end' => '06:00',
            'buffer_time' => 1,
        ]);

        // Break End at 00:15 on 12th (after midnight)
        $response = $this->postJson('/api/time-clock', [
            'shop_id' => $this->shopId,
            'user_id' => $this->userId,
            'type' => 'break_end',
            'clock_date' => $this->testDate,  // Still 11th for clock_date
            'time' => '00:15:00',             // But time is 00:15 (next day)
            'shift_start' => '22:00',
            'shift_end' => '06:00',
            'buffer_time' => 1,
        ]);

        $this->assertEquals(200, $response->status(),
            "Break end at 00:15 should be accepted. Response: " . $response->getContent());

        // Verify formated_date_time is correctly set to 12th
        $breakEnd = UserTimeClock::where('shop_id', $this->shopId)
            ->where('user_id', $this->userId)
            ->where('type', 'break_end')
            ->first();

        $this->assertNotNull($breakEnd, "Break end record should exist");
        $this->assertEquals('2026-01-11', $breakEnd->date_at instanceof \Carbon\Carbon ? $breakEnd->date_at->format('Y-m-d') : $breakEnd->date_at,
            "date_at should still be 11th (shift date)");
        $this->assertStringContainsString('2026-01-12 00:15', $breakEnd->formated_date_time,
            "formated_date_time should be 2026-01-12 00:15 (next day with correct time)");
    }

    /**
     * Test: Buffer time validation uses formated_date_time
     * 
     * Scenario: Buffer extends to next day
     * Shift: 23:00 to 06:00 with 2-hour buffer
     * Allowed window: 21:00 on 11th to 08:00 on 12th
     * 
     * Should accept records from 21:00 on 11th through 08:00 on 12th
     */
    public function test_buffer_time_respects_formatted_date_time()
    {
        $testDate = '2026-01-11';
        $userId = 601;
        $shopId = 1;

        User::factory()->create(['id' => $userId]);

        // Day In at 23:00 on 11th (within buffer: 21:00-08:00 next day)
        $response = $this->postJson('/api/time-clock', [
            'shop_id' => $shopId,
            'user_id' => $userId,
            'type' => 'day_in',
            'clock_date' => $testDate,
            'time' => '23:00:00',
            'shift_start' => '23:00',
            'shift_end' => '06:00',
            'buffer_time' => 2,  // 2 hours = 120 minutes
        ]);

        $this->assertEquals(200, $response->status(),
            "Day in at 23:00 should be within buffer. Response: " . $response->getContent());

        // Debug: Check what was saved for day_in
        $savedDayIn = UserTimeClock::where('shop_id', $shopId)
            ->where('user_id', $userId)
            ->where('type', 'day_in')
            ->first();
        $this->assertNotNull($savedDayIn, "Day in should be saved");
        // Verify formated_date_time is correct
        $this->assertStringContainsString('2026-01-11 23:00', $savedDayIn->formated_date_time,
            "Day in formated_date_time should be 2026-01-11 23:00: " . $savedDayIn->formated_date_time);

        // Day Out at 05:30 on 12th (within buffer: ends at 08:00 on 12th)
        $response = $this->postJson('/api/time-clock', [
            'shop_id' => $shopId,
            'user_id' => $userId,
            'type' => 'day_out',
            'clock_date' => $testDate,
            'time' => '05:30:00',
            'shift_start' => '23:00',
            'shift_end' => '06:00',
            'buffer_time' => 2,  // Buffer extends to 08:00 on 12th
        ]);

        $this->assertEquals(200, $response->status(),
            "Day out at 05:30 should be within buffer (extends to 08:00). Response: " . $response->getContent());

        // Debug: Check what was saved for day_out
        $savedDayOut = UserTimeClock::where('shop_id', $shopId)
            ->where('user_id', $userId)
            ->where('type', 'day_out')
            ->first();
        $this->assertNotNull($savedDayOut, "Day out should be saved");
        // Verify formated_date_time is correct
        $this->assertStringContainsString('2026-01-12 05:30', $savedDayOut->formated_date_time,
            "Day out formated_date_time should be 2026-01-12 05:30: " . $savedDayOut->formated_date_time);

        // Verify both records exist
        $totalEntries = UserTimeClock::where('shop_id', $shopId)
            ->where('user_id', $userId)
            ->count();
        $this->assertEquals(2, $totalEntries, "Both day_in and day_out should be recorded");
    }

    /**
     * Test: Multiple shifts on different dates with correct formated_date_time isolation
     */
    public function test_multiple_shifts_on_different_dates()
    {
        // First shift on 11th
        $this->postJson('/api/time-clock', [
            'shop_id' => $this->shopId,
            'user_id' => $this->userId,
            'type' => 'day_in',
            'clock_date' => '2026-01-11',
            'time' => '08:00:00',
            'shift_start' => '08:00',
            'shift_end' => '16:00',
            'buffer_time' => 1,
        ]);

        $this->postJson('/api/time-clock', [
            'shop_id' => $this->shopId,
            'user_id' => $this->userId,
            'type' => 'day_out',
            'clock_date' => '2026-01-11',
            'time' => '16:00:00',
            'shift_start' => '08:00',
            'shift_end' => '16:00',
            'buffer_time' => 1,
        ]);

        // Second shift on 12th
        $response = $this->postJson('/api/time-clock', [
            'shop_id' => $this->shopId,
            'user_id' => $this->userId,
            'type' => 'day_in',
            'clock_date' => '2026-01-12',
            'time' => '08:00:00',
            'shift_start' => '08:00',
            'shift_end' => '16:00',
            'buffer_time' => 1,
        ]);

        $this->assertEquals(200, $response->status(),
            "Day in on new date should be accepted");

        // Verify both shifts are recorded correctly
        $totalEntries = UserTimeClock::where('shop_id', $this->shopId)
            ->where('user_id', $this->userId)
            ->count();
        $this->assertEquals(3, $totalEntries, "All 3 entries should be recorded");

        // Verify each is on correct date in formated_date_time
        $shift1Records = UserTimeClock::where('shop_id', $this->shopId)
            ->where('user_id', $this->userId)
            ->where('date_at', '2026-01-11')
            ->count();

        $shift2Records = UserTimeClock::where('shop_id', $this->shopId)
            ->where('user_id', $this->userId)
            ->where('date_at', '2026-01-12')
            ->count();

        $this->assertEquals(2, $shift1Records, "2 entries on 11th");
        $this->assertEquals(1, $shift2Records, "1 entry on 12th");
    }
}
