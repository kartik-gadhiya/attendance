<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\UserTimeClock;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserTimeClockEditTest extends TestCase
{
    protected $endpoint = '/api/time-clock';
    protected $shopId = 1;
    protected $userId = 2;

    /**
     * Test editing event to valid time between neighbors
     */
    public function test_edit_event_to_valid_time_between_neighbors()
    {
        // Get record ID 14 (day_in at 05:00, next event at 07:15)
        $event = UserTimeClock::find(14);
        $this->assertNotNull($event);

        // Edit to 06:00 (between 05:00 and 07:15)
        $response = $this->postJson("{$this->endpoint}/{$event->id}", [
            'shop_id' => $this->shopId,
            'user_id' => $this->userId,
            'clock_date' => '2026-01-07',
            'time' => '06:00',
            'type' => 'day_in',
            'shift_start' => '08:00',
            'shift_end' => '23:00',
            'buffer_time' => 3,
        ]);

        $response->assertStatus(200);
        $response->assertJsonFragment(['success' => true]);

        // Verify the time was updated - time_at is a Carbon object
        $event->refresh();
        $this->assertEquals('06:00:00', $event->time_at->format('H:i:s'));
    }

    /**
     * Test editing event to overlap with previous event (should fail)
     */
    public function test_edit_event_overlaps_with_previous_event()
    {
        // Get record ID 18 (break_start at 07:15, previous event is day_in somewhere before)
        $event = UserTimeClock::find(18);
        $this->assertNotNull($event);

        // Get the previous event to know its time
        $previousEvents = UserTimeClock::where('user_id', $this->userId)
            ->where('date_at', $event->date_at)
            ->where('formated_date_time', '<', $event->formated_date_time)
            ->orderBy('formated_date_time', 'desc')
            ->first();

        // Try to edit to same time or earlier than previous event
        if ($previousEvents) {
            $previousTime = \Carbon\Carbon::parse($previousEvents->time_at)->format('H:i');

            $response = $this->postJson("{$this->endpoint}/{$event->id}", [
                'shop_id' => $this->shopId,
                'user_id' => $this->userId,
                'clock_date' => '2026-01-07',
                'time' => $previousTime,
                'type' => 'break_start',
                'shift_start' => '08:00',
                'shift_end' => '23:00',
                'buffer_time' => 3,
            ]);

            $response->assertStatus(422);
            $response->assertJsonFragment(['success' => false]);
            $this->assertTrue(
                str_contains(strtolower($response->json('message')), 'after previous')
            );
        } else {
            $this->markTestSkipped('No previous event found');
        }
    }

    /**
     * Test editing event to overlap with next event (should fail)
     */
    public function test_edit_event_overlaps_with_next_event()
    {
        // Get record ID 16 (break_start)
        $event = UserTimeClock::find(16);
        $this->assertNotNull($event);

        // Get the next event to know its time
        $nextEvent = UserTimeClock::where('user_id', $this->userId)
            ->where('date_at', $event->date_at)
            ->where('formated_date_time', '>', $event->formated_date_time)
            ->orderBy('formated_date_time', 'asc')
            ->first();

        // Try to edit to same time or later than next event
        if ($nextEvent) {
            $nextTime = \Carbon\Carbon::parse($nextEvent->time_at)->format('H:i');

            $response = $this->postJson("{$this->endpoint}/{$event->id}", [
                'shop_id' => $this->shopId,
                'user_id' => $this->userId,
                'clock_date' => '2026-01-07',
                'time' => $nextTime,
                'type' => 'break_start',
                'shift_start' => '08:00',
                'shift_end' => '23:00',
                'buffer_time' => 3,
            ]);

            $response->assertStatus(422);
            $response->assertJsonFragment(['success' => false]);
            $this->assertTrue(
                str_contains(strtolower($response->json('message')), 'before next')
            );
        } else {
            $this->markTestSkipped('No next event found');
        }
    }

    /**
     * Test editing day_in to earlier time within buffer
     */
    public function test_edit_day_in_to_earlier_time_within_buffer()
    {
        // Get record ID 14 (day_in at 05:00, buffer allows from 05:00)
        $event = UserTimeClock::find(14);
        $this->assertNotNull($event);

        // Edit to 05:30 (within buffer, before next event)
        $response = $this->postJson("{$this->endpoint}/{$event->id}", [
            'shop_id' => $this->shopId,
            'user_id' => $this->userId,
            'clock_date' => '2026-01-07',
            'time' => '05:30',
            'type' => 'day_in',
            'shift_start' => '08:00',
            'shift_end' => '23:00',
            'buffer_time' => 3,
        ]);

        $response->assertStatus(200);
        $response->assertJsonFragment(['success' => true]);
    }

    /**
     * Test editing day_out to later time within buffer
     */
    public function test_edit_day_out_to_later_time_within_buffer()
    {
        // Get record ID 15 (day_out at 12:00)
        $event = UserTimeClock::find(15);
        $this->assertNotNull($event);

        // Edit to 13:00 (later time, within buffer)
        $response = $this->postJson("{$this->endpoint}/{$event->id}", [
            'shop_id' => $this->shopId,
            'user_id' => $this->userId,
            'clock_date' => '2026-01-07',
            'time' => '13:00',
            'type' => 'day_out',
            'shift_start' => '08:00',
            'shift_end' => '23:00',
            'buffer_time' => 3,
        ]);

        $response->assertStatus(200);
        $response->assertJsonFragment(['success' => true]);
    }

    /**
     * Test editing break_start maintaining pair integrity
     */
    public function test_edit_break_start_maintains_pair_integrity()
    {
        // Get record ID 16 (break_start at 10:00, break_end at 10:15)
        $event = UserTimeClock::find(16);
        $this->assertNotNull($event);

        // Edit to 10:05 (between previous 07:30 and next 10:15)
        $response = $this->postJson("{$this->endpoint}/{$event->id}", [
            'shop_id' => $this->shopId,
            'user_id' => $this->userId,
            'clock_date' => '2026-01-07',
            'time' => '10:05',
            'type' => 'break_start',
            'shift_start' => '08:00',
            'shift_end' => '23:00',
            'buffer_time' => 3,
        ]);

        $response->assertStatus(200);
        $response->assertJsonFragment(['success' => true]);
    }

    /**
     * Test editing event that crosses midnight
     */
    public function test_edit_event_crosses_midnight()
    {
        // Get record ID 6 (day_in at 00:30 next day, next event at 00:45)
        $event = UserTimeClock::find(6);
        $this->assertNotNull($event);

        // Edit to 00:35 (between 00:30 and 00:45, after midnight)
        $response = $this->postJson("{$this->endpoint}/{$event->id}", [
            'shop_id' => $this->shopId,
            'user_id' => $this->userId,
            'clock_date' => '2026-01-07',
            'time' => '00:35',
            'type' => 'day_in',
            'shift_start' => '08:00',
            'shift_end' => '23:00',
            'buffer_time' => 3,
        ]);

        $response->assertStatus(200);
        $response->assertJsonFragment(['success' => true]);
    }

    /**
     * Test editing to time outside buffer window (should fail)
     */
    public function test_edit_to_time_outside_buffer_window()
    {
        // Get record ID 14 (day_in at 05:00)
        $event = UserTimeClock::find(14);
        $this->assertNotNull($event);

        // Try to edit to 04:30 (outside buffer - before 05:00)
        $response = $this->postJson("{$this->endpoint}/{$event->id}", [
            'shop_id' => $this->shopId,
            'user_id' => $this->userId,
            'clock_date' => '2026-01-07',
            'time' => '04:30',
            'type' => 'day_in',
            'shift_start' => '08:00',
            'shift_end' => '23:00',
            'buffer_time' => 3,
        ]);

        $response->assertStatus(422);
        $response->assertJsonFragment(['success' => false]);
        $this->assertTrue(
            str_contains(strtolower($response->json('message')), 'buffer')
        );
    }

    /**
     * Test editing non-existent event (should fail with 404)
     */
    public function test_edit_non_existent_event()
    {
        $response = $this->postJson("{$this->endpoint}/99999", [
            'shop_id' => $this->shopId,
            'user_id' => $this->userId,
            'clock_date' => '2026-01-07',
            'time' => '10:00',
            'type' => 'day_in',
            'shift_start' => '08:00',
            'shift_end' => '23:00',
            'buffer_time' => 3,
        ]);

        $response->assertStatus(404);
        $response->assertJsonFragment(['success' => false]);
    }

    /**
     * Test editing multiple events in sequence
     */
    public function test_edit_multiple_events_in_sequence()
    {
        // Edit break_start ID 20 from 10:20 to 10:22
        $response1 = $this->postJson("{$this->endpoint}/20", [
            'shop_id' => $this->shopId,
            'user_id' => $this->userId,
            'clock_date' => '2026-01-07',
            'time' => '10:22',
            'type' => 'break_start',
            'shift_start' => '08:00',
            'shift_end' => '23:00',
            'buffer_time' => 3,
        ]);
        $response1->assertStatus(200);

        // Edit break_end ID 21 from 11:20 to 11:25
        $response2 = $this->postJson("{$this->endpoint}/21", [
            'shop_id' => $this->shopId,
            'user_id' => $this->userId,
            'clock_date' => '2026-01-07',
            'time' => '11:25',
            'type' => 'break_end',
            'shift_start' => '08:00',
            'shift_end' => '23:00',
            'buffer_time' => 3,
        ]);
        $response2->assertStatus(200);

        // Verify both updates - time_at is a Carbon object
        $this->assertEquals('10:22:00', UserTimeClock::find(20)->time_at->format('H:i:s'));
        $this->assertEquals('11:25:00', UserTimeClock::find(21)->time_at->format('H:i:s'));
    }

    /**
     * Test editing event with exact neighbor times (boundary test)
     */
    public function test_edit_event_exact_neighbor_boundary()
    {
        // Get record ID 17 (break_end at 10:15)
        // Previous is 16 (break_start at 10:00)
        // Next is 20 (break_start at 10:20)

        $event = UserTimeClock::find(17);
        $this->assertNotNull($event);

        // Edit to 10:10 (valid - between 10:00 and 10:20)
        $response = $this->postJson("{$this->endpoint}/{$event->id}", [
            'shop_id' => $this->shopId,
            'user_id' => $this->userId,
            'clock_date' => '2026-01-07',
            'time' => '10:10',
            'type' => 'break_end',
            'shift_start' => '08:00',
            'shift_end' => '23:00',
            'buffer_time' => 3,
        ]);

        $response->assertStatus(200);
        $response->assertJsonFragment(['success' => true]);
    }
}
