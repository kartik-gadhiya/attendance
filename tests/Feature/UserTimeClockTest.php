<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\UserTimeClock;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;


/**
 * ⚠️ WARNING: RefreshDatabase is DISABLED
 * 
 * This means test data WILL BE STORED in the database and NOT automatically cleaned up.
 * 
 * IMPORTANT:
 * - Make sure you're using a SEPARATE TEST DATABASE (not production!)
 * - Data will persist after tests run for analysis
 * - Manually clean up data when done: php artisan migrate:fresh
 * - Re-run tests will create duplicate/conflicting data
 * 
 * To enable automatic cleanup again, uncomment the line below:
 * use RefreshDatabase;
 */
class UserTimeClockTest extends TestCase
{
    // use RefreshDatabase;  // COMMENTED OUT to persist data for analysis

    protected User $user;
    protected int $shopId = 1;
    protected string $testDate = '2026-01-15';

    protected function setUp(): void
    {
        parent::setUp();

        // Create a test user
        $this->user = User::factory()->create();
    }

    /**
     * Test basic day-in entry (first entry of the day)
     */
    public function test_day_in_creates_successfully_with_shift_times(): void
    {
        $response = $this->postJson('/api/time-clock', [
            'shop_id' => $this->shopId,
            'user_id' => $this->user->id,
            'clock_date' => $this->testDate,
            'time' => '06:00:00',
            'shift_start' => '08:00:00',
            'shift_end' => '23:00:00',
            'type' => 'day_in',
            'buffer_time' => 180,
        ]);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
            ]);

        $this->assertDatabaseHas('user_time_clock', [
            'shop_id' => $this->shopId,
            'user_id' => $this->user->id,
            'date_at' => $this->testDate,
            'time_at' => '06:00:00',
            'type' => 'day_in',
        ]);
    }

    /**
     * Test day-out uses stored shift times
     */
    public function test_day_out_reuses_shift_times_from_first_entry(): void
    {
        // First: day-in
        $this->postJson('/api/time-clock', [
            'shop_id' => $this->shopId,
            'user_id' => $this->user->id,
            'clock_date' => $this->testDate,
            'time' => '06:00:00',
            'shift_start' => '08:00:00',
            'shift_end' => '23:00:00',
            'type' => 'day_in',
            'buffer_time' => 180,
        ]);

        // Then: day-out
        $response = $this->postJson('/api/time-clock', [
            'shop_id' => $this->shopId,
            'user_id' => $this->user->id,
            'clock_date' => $this->testDate,
            'time' => '10:00:00',
            'type' => 'day_out',
            'buffer_time' => 180,
        ]);

        $response->assertStatus(201)
            ->assertJson(['success' => true]);

        // Verify shift times match first entry
        $dayOut = UserTimeClock::where('type', 'day_out')->first();
        $this->assertEquals('08:00:00', $dayOut->shift_start->format('H:i:s'));
        $this->assertEquals('23:00:00', $dayOut->shift_end->format('H:i:s'));
    }

    /**
     * Test multiple day-in and day-out cycles in one day
     */
    public function test_multiple_day_in_out_cycles_in_single_day(): void
    {
        $entries = [
            ['time' => '06:00:00', 'type' => 'day_in', 'shift_start' => '08:00:00', 'shift_end' => '23:00:00'],
            ['time' => '10:00:00', 'type' => 'day_out'],
            ['time' => '12:00:00', 'type' => 'day_in'],
            ['time' => '15:00:00', 'type' => 'day_out'],
            ['time' => '16:00:00', 'type' => 'day_in'],
            ['time' => '22:00:00', 'type' => 'day_out'],
        ];

        foreach ($entries as $entry) {
            $data = array_merge([
                'shop_id' => $this->shopId,
                'user_id' => $this->user->id,
                'clock_date' => $this->testDate,
                'buffer_time' => 180,
            ], $entry);

            $response = $this->postJson('/api/time-clock', $data);
            $response->assertStatus(201);
        }

        // Verify all 6 entries were created
        $this->assertDatabaseCount('user_time_clock', 6);
    }

    /**
     * Test multiple breaks within a shift
     */
    public function test_multiple_breaks_within_shift(): void
    {
        // Day-in first
        $this->postJson('/api/time-clock', [
            'shop_id' => $this->shopId,
            'user_id' => $this->user->id,
            'clock_date' => $this->testDate,
            'time' => '08:00:00',
            'shift_start' => '08:00:00',
            'shift_end' => '23:00:00',
            'type' => 'day_in',
            'buffer_time' => 180,
        ]);

        // Multiple breaks
        $breaks = [
            ['time' => '10:00:00', 'type' => 'break_start'],
            ['time' => '10:15:00', 'type' => 'break_end'],
            ['time' => '13:00:00', 'type' => 'break_start'],
            ['time' => '14:00:00', 'type' => 'break_end'],
            ['time' => '17:00:00', 'type' => 'break_start'],
            ['time' => '17:30:00', 'type' => 'break_end'],
        ];

        foreach ($breaks as $break) {
            $data = array_merge([
                'shop_id' => $this->shopId,
                'user_id' => $this->user->id,
                'clock_date' => $this->testDate,
                'buffer_time' => 180,
            ], $break);

            $response = $this->postJson('/api/time-clock', $data);
            $response->assertStatus(201);
        }

        // Verify all breaks were created (1 day-in + 6 break entries)
        $this->assertDatabaseCount('user_time_clock', 7);
    }

    /**
     * Test overlapping day-in with existing event fails
     */
    public function test_overlapping_day_in_fails(): void
    {
        // Create first day-in at 09:00
        $this->postJson('/api/time-clock', [
            'shop_id' => $this->shopId,
            'user_id' => $this->user->id,
            'clock_date' => $this->testDate,
            'time' => '09:00:00',
            'shift_start' => '08:00:00',
            'shift_end' => '23:00:00',
            'type' => 'day_in',
            'buffer_time' => 180,
        ]);

        // Try to create another day-in at the same time
        $response = $this->postJson('/api/time-clock', [
            'shop_id' => $this->shopId,
            'user_id' => $this->user->id,
            'clock_date' => $this->testDate,
            'time' => '09:00:00',
            'type' => 'day_in',
            'buffer_time' => 180,
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
            ]);

        // Only one entry should exist
        $this->assertDatabaseCount('user_time_clock', 1);
    }

    /**
     * Test day-in during break period fails
     */
    public function test_day_in_during_break_period_fails(): void
    {
        // Create day-in
        $this->postJson('/api/time-clock', [
            'shop_id' => $this->shopId,
            'user_id' => $this->user->id,
            'clock_date' => $this->testDate,
            'time' => '08:00:00',
            'shift_start' => '08:00:00',
            'shift_end' => '23:00:00',
            'type' => 'day_in',
            'buffer_time' => 180,
        ]);

        // Create break: 10:00 - 11:00
        $this->postJson('/api/time-clock', [
            'shop_id' => $this->shopId,
            'user_id' => $this->user->id,
            'clock_date' => $this->testDate,
            'time' => '10:00:00',
            'type' => 'break_start',
            'buffer_time' => 180,
        ]);

        $this->postJson('/api/time-clock', [
            'shop_id' => $this->shopId,
            'user_id' => $this->user->id,
            'clock_date' => $this->testDate,
            'time' => '11:00:00',
            'type' => 'break_end',
            'buffer_time' => 180,
        ]);

        // Try to add day-in during break (10:30)
        $response = $this->postJson('/api/time-clock', [
            'shop_id' => $this->shopId,
            'user_id' => $this->user->id,
            'clock_date' => $this->testDate,
            'time' => '10:30:00',
            'type' => 'day_in',
            'buffer_time' => 180,
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
            ]);
    }

    /**
     * Test overlapping break with existing break fails
     */
    public function test_overlapping_break_fails(): void
    {
        // Create day-in
        $this->postJson('/api/time-clock', [
            'shop_id' => $this->shopId,
            'user_id' => $this->user->id,
            'clock_date' => $this->testDate,
            'time' => '08:00:00',
            'shift_start' => '08:00:00',
            'shift_end' => '23:00:00',
            'type' => 'day_in',
            'buffer_time' => 180,
        ]);

        // Create first break: 12:00 - 13:00
        $this->postJson('/api/time-clock', [
            'shop_id' => $this->shopId,
            'user_id' => $this->user->id,
            'clock_date' => $this->testDate,
            'time' => '12:00:00',
            'type' => 'break_start',
            'buffer_time' => 180,
        ]);

        $this->postJson('/api/time-clock', [
            'shop_id' => $this->shopId,
            'user_id' => $this->user->id,
            'clock_date' => $this->testDate,
            'time' => '13:00:00',
            'type' => 'break_end',
            'buffer_time' => 180,
        ]);

        // Try to add overlapping break_start at 12:30
        $response = $this->postJson('/api/time-clock', [
            'shop_id' => $this->shopId,
            'user_id' => $this->user->id,
            'clock_date' => $this->testDate,
            'time' => '12:30:00',
            'type' => 'break_start',
            'buffer_time' => 180,
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
            ]);
    }

    /**
     * Test break end without break start fails
     */
    public function test_break_end_without_break_start_fails(): void
    {
        // Create day-in
        $this->postJson('/api/time-clock', [
            'shop_id' => $this->shopId,
            'user_id' => $this->user->id,
            'clock_date' => $this->testDate,
            'time' => '08:00:00',
            'shift_start' => '08:00:00',
            'shift_end' => '23:00:00',
            'type' => 'day_in',
            'buffer_time' => 180,
        ]);

        // Try to add break_end without break_start
        $response = $this->postJson('/api/time-clock', [
            'shop_id' => $this->shopId,
            'user_id' => $this->user->id,
            'clock_date' => $this->testDate,
            'time' => '10:00:00',
            'type' => 'break_end',
            'buffer_time' => 180,
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
            ]);
    }

    /**
     * Test entry outside buffer time fails
     */
    public function test_entry_outside_buffer_time_fails(): void
    {
        // Try to create day-in at 02:00 (more than 3 hours before 08:00 shift)
        $response = $this->postJson('/api/time-clock', [
            'shop_id' => $this->shopId,
            'user_id' => $this->user->id,
            'clock_date' => $this->testDate,
            'time' => '02:00:00',
            'shift_start' => '08:00:00',
            'shift_end' => '23:00:00',
            'type' => 'day_in',
            'buffer_time' => 180,
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
            ]);

        $this->assertDatabaseCount('user_time_clock', 0);
    }

    /**
     * Test midnight crossing shift
     */
    public function test_midnight_crossing_shift(): void
    {
        // Create day-in at 23:00 with shift crossing midnight
        $this->postJson('/api/time-clock', [
            'shop_id' => $this->shopId,
            'user_id' => $this->user->id,
            'clock_date' => $this->testDate,
            'time' => '23:00:00',
            'shift_start' => '22:00:00',
            'shift_end' => '02:00:00',
            'type' => 'day_in',
            'buffer_time' => 180,
        ]);

        // Day-out after midnight (01:20)
        $response = $this->postJson('/api/time-clock', [
            'shop_id' => $this->shopId,
            'user_id' => $this->user->id,
            'clock_date' => $this->testDate,
            'time' => '01:20:00',
            'type' => 'day_out',
            'buffer_time' => 180,
        ]);

        $response->assertStatus(201);

        // Verify formated_date_time is next day
        $dayOut = UserTimeClock::where('type', 'day_out')->first();
        $this->assertEquals($this->testDate, $dayOut->date_at->format('Y-m-d'));

        // The formated_date_time should be next day
        $expectedFormattedDate = date('Y-m-d', strtotime($this->testDate . ' +1 day'));
        $this->assertEquals($expectedFormattedDate, $dayOut->formated_date_time->format('Y-m-d'));
    }

    /**
     * Test complete day scenario with multiple cycles and breaks
     */
    public function test_complete_day_scenario_with_multiple_entries(): void
    {
        $entries = [
            // First cycle: 06:00 - 10:00
            ['time' => '06:00:00', 'type' => 'day_in', 'shift_start' => '08:00:00', 'shift_end' => '23:00:00'],
            ['time' => '07:00:00', 'type' => 'break_start'],
            ['time' => '07:15:00', 'type' => 'break_end'],
            ['time' => '10:00:00', 'type' => 'day_out'],

            // Second cycle: 12:00 - 18:00
            ['time' => '12:00:00', 'type' => 'day_in'],
            ['time' => '14:00:00', 'type' => 'break_start'],
            ['time' => '14:30:00', 'type' => 'break_end'],
            ['time' => '18:00:00', 'type' => 'day_out'],

            // Third cycle: 19:00 - 22:00
            ['time' => '19:00:00', 'type' => 'day_in'],
            ['time' => '20:00:00', 'type' => 'break_start'],
            ['time' => '20:15:00', 'type' => 'break_end'],
            ['time' => '22:00:00', 'type' => 'day_out'],
        ];

        foreach ($entries as $entry) {
            $data = array_merge([
                'shop_id' => $this->shopId,
                'user_id' => $this->user->id,
                'clock_date' => $this->testDate,
                'buffer_time' => 180,
            ], $entry);

            $response = $this->postJson('/api/time-clock', $data);

            $response->assertStatus(201)
                ->assertJson(['success' => true]);
        }

        // Verify all 12 entries were created
        $this->assertDatabaseCount('user_time_clock', 12);

        // Verify we have 3 day-in and 3 day-out entries
        $this->assertEquals(3, UserTimeClock::where('type', 'day_in')->count());
        $this->assertEquals(3, UserTimeClock::where('type', 'day_out')->count());
        $this->assertEquals(3, UserTimeClock::where('type', 'break_start')->count());
        $this->assertEquals(3, UserTimeClock::where('type', 'break_end')->count());
    }

    /**
     * Test validation response structure
     */
    public function test_validation_error_response_structure(): void
    {
        // Try to create entry outside buffer time
        $response = $this->postJson('/api/time-clock', [
            'shop_id' => $this->shopId,
            'user_id' => $this->user->id,
            'clock_date' => $this->testDate,
            'time' => '01:00:00',
            'shift_start' => '08:00:00',
            'shift_end' => '17:00:00',
            'type' => 'day_in',
            'buffer_time' => 180,
        ]);

        $response->assertStatus(422)
            ->assertJsonStructure([
                'success',
                'message',
            ])
            ->assertJson([
                'success' => false,
            ]);
    }

    /**
     * Test buffer time allows entries within 3 hours before shift
     */
    public function test_buffer_time_allows_entry_3_hours_before_shift(): void
    {
        // Shift starts at 08:00, buffer is 180 min (3 hours)
        // So earliest allowed is 05:00
        $response = $this->postJson('/api/time-clock', [
            'shop_id' => $this->shopId,
            'user_id' => $this->user->id,
            'clock_date' => $this->testDate,
            'time' => '05:00:00',
            'shift_start' => '08:00:00',
            'shift_end' => '17:00:00',
            'type' => 'day_in',
            'buffer_time' => 180,
        ]);

        $response->assertStatus(201)
            ->assertJson(['success' => true]);
    }

    /**
     * Test buffer time allows entries within 3 hours after shift
     */
    public function test_buffer_time_allows_entry_3_hours_after_shift(): void
    {
        // First create day-in
        $this->postJson('/api/time-clock', [
            'shop_id' => $this->shopId,
            'user_id' => $this->user->id,
            'clock_date' => $this->testDate,
            'time' => '08:00:00',
            'shift_start' => '08:00:00',
            'shift_end' => '17:00:00',
            'type' => 'day_in',
            'buffer_time' => 180,
        ]);

        // Shift ends at 17:00, buffer is 180 min (3 hours)
        // So latest allowed is 20:00
        $response = $this->postJson('/api/time-clock', [
            'shop_id' => $this->shopId,
            'user_id' => $this->user->id,
            'clock_date' => $this->testDate,
            'time' => '20:00:00',
            'type' => 'day_out',
            'buffer_time' => 180,
        ]);

        $response->assertStatus(201)
            ->assertJson(['success' => true]);
    }
}
