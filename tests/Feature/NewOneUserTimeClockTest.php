<?php

namespace Tests\Feature;

use App\Models\UserTimeClock;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NewOneUserTimeClockTest extends TestCase
{
    use RefreshDatabase;

    protected $endpoint = '/api/time-clock-new-one';
    protected $shopId = 1;
    protected $userId;

    protected function setUp(): void
    {
        parent::setUp();

        // Create a test user
        $user = \App\Models\User::factory()->create();
        $this->userId = $user->id;
    }

    /**
     * Helper to create a time clock event request.
     */
    private function makeRequest(array $overrides = []): array
    {
        return array_merge([
            'shop_id' => $this->shopId,
            'user_id' => $this->userId,
            'clock_date' => Carbon::today()->format('Y-m-d'),
            'time' => '08:00',
            'type' => 'day_in',
            'comment' => 'Test event',
            'shift_start' => '08:00',
            'shift_end' => '23:00',
            'buffer_time' => 3,
            'created_from' => 'A',
        ], $overrides);
    }

    /** @test */
    public function it_stores_day_in_as_first_event_successfully()
    {
        $response = $this->postJson($this->endpoint, $this->makeRequest([
            'time' => '06:00',
            'type' => 'day_in',
        ]));

        $response->assertStatus(200)
            ->assertJson([
                'status' => true,
                'code' => 0,
                'message' => 'Attendance saved successfully.',
            ]);

        $this->assertDatabaseHas('user_time_clock', [
            'user_id' => $this->userId,
            'type' => 'day_in',
            'time_at' => '06:00:00',
        ]);
    }

    /** @test */
    public function it_rejects_event_with_time_before_last_event()
    {
        // First event at 06:00
        $this->postJson($this->endpoint, $this->makeRequest([
            'time' => '06:00',
            'type' => 'day_in',
        ]));

        // Try to add event at 05:30 (before 06:00)
        $response = $this->postJson($this->endpoint, $this->makeRequest([
            'time' => '05:30',
            'type' => 'break_start',
        ]));

        $response->assertStatus(422)
            ->assertJson([
                'status' => false,
                'code' => 422,
            ])
            ->assertJsonPath('message', fn($message) => str_contains($message, 'Event time must be after the last recorded event'));
    }

    /** @test */
    public function it_validates_chronological_order_for_break_events()
    {
        // Day In at 06:00
        $this->postJson($this->endpoint, $this->makeRequest([
            'time' => '06:00',
            'type' => 'day_in',
        ]));

        // Break Start at 10:00 - should succeed
        $response = $this->postJson($this->endpoint, $this->makeRequest([
            'time' => '10:00',
            'type' => 'break_start',
        ]));
        $response->assertStatus(200);

        // Break End at 09:00 (before break start) - should fail
        $response = $this->postJson($this->endpoint, $this->makeRequest([
            'time' => '09:00',
            'type' => 'break_end',
        ]));

        $response->assertStatus(422);
    }

    /** @test */
    public function it_rejects_duplicate_day_in_within_active_shift()
    {
        // First Day In
        $this->postJson($this->endpoint, $this->makeRequest([
            'time' => '06:00',
            'type' => 'day_in',
        ]));

        // Second Day In without Day Out - should fail
        $response = $this->postJson($this->endpoint, $this->makeRequest([
            'time' => '07:00',
            'type' => 'day_in',
        ]));

        $response->assertStatus(422)
            ->assertJsonFragment(['message' => 'Please record Day Out before starting a new shift.']);
    }

    /** @test */
    public function it_requires_day_in_before_break_start()
    {
        $response = $this->postJson($this->endpoint, $this->makeRequest([
            'time' => '10:00',
            'type' => 'break_start',
        ]));

        $response->assertStatus(422)
            ->assertJsonFragment(['message' => 'Day In must be recorded before Break Start.']);
    }

    /** @test */
    public function it_requires_break_start_before_break_end()
    {
        // Day In
        $this->postJson($this->endpoint, $this->makeRequest([
            'time' => '06:00',
            'type' => 'day_in',
        ]));

        // Try Break End without Break Start
        $response = $this->postJson($this->endpoint, $this->makeRequest([
            'time' => '10:00',
            'type' => 'break_end',
        ]));

        $response->assertStatus(422)
            ->assertJsonFragment(['message' => 'Break Start must be recorded before Break End.']);
    }

    /** @test */
    public function it_requires_break_to_be_closed_before_day_out()
    {
        // Day In
        $this->postJson($this->endpoint, $this->makeRequest([
            'time' => '06:00',
            'type' => 'day_in',
        ]));

        // Break Start
        $this->postJson($this->endpoint, $this->makeRequest([
            'time' => '10:00',
            'type' => 'break_start',
        ]));

        // Try Day Out without Break End
        $response = $this->postJson($this->endpoint, $this->makeRequest([
            'time' => '20:00',
            'type' => 'day_out',
        ]));

        $response->assertStatus(422)
            ->assertJsonFragment(['message' => 'Please end the break before recording Day Out.']);
    }

    /** @test */
    public function it_prevents_starting_new_break_without_ending_previous()
    {
        // Day In
        $this->postJson($this->endpoint, $this->makeRequest([
            'time' => '06:00',
            'type' => 'day_in',
        ]));

        // First Break Start
        $this->postJson($this->endpoint, $this->makeRequest([
            'time' => '10:00',
            'type' => 'break_start',
        ]));

        // Try another Break Start without ending first
        $response = $this->postJson($this->endpoint, $this->makeRequest([
            'time' => '12:00',
            'type' => 'break_start',
        ]));

        $response->assertStatus(422)
            ->assertJsonFragment(['message' => 'Please end the current break before starting a new one.']);
    }

    /** @test */
    public function it_handles_post_midnight_time_correctly()
    {
        $today = Carbon::parse('2026-01-12');

        // Day In at 06:00
        $this->postJson($this->endpoint, $this->makeRequest([
            'clock_date' => $today->format('Y-m-d'),
            'time' => '06:00',
            'type' => 'day_in',
        ]));

        // Day Out at 00:50 (12:50 AM - post midnight)
        $response = $this->postJson($this->endpoint, $this->makeRequest([
            'clock_date' => $today->format('Y-m-d'),
            'time' => '00:50',
            'type' => 'day_out',
        ]));

        $response->assertStatus(200);

        // Verify database records
        $dayOut = UserTimeClock::where('type', 'day_out')->first();

        // date_at should be original date
        $this->assertEquals($today->format('Y-m-d'), $dayOut->date_at->format('Y-m-d'));

        // formated_date_time should be next day
        $this->assertEquals($today->addDay()->format('Y-m-d'), $dayOut->formated_date_time->format('Y-m-d'));
        $this->assertEquals('00:50:00', $dayOut->time_at->format('H:i:s'));
    }

    /** @test */
    public function it_rejects_time_outside_shift_window()
    {
        // Try event at 04:59 (before 05:00 window start)
        $response = $this->postJson($this->endpoint, $this->makeRequest([
            'time' => '04:59',
            'type' => 'day_in',
        ]));

        $response->assertStatus(422)
            ->assertJsonPath('message', fn($message) => str_contains($message, 'Event time must be between'));
    }

    /** @test */
    public function it_allows_time_at_shift_window_boundaries()
    {
        // At window start (05:00)
        $response = $this->postJson($this->endpoint, $this->makeRequest([
            'time' => '05:00',
            'type' => 'day_in',
        ]));
        $response->assertStatus(200);

        // Clean up for next test
        UserTimeClock::truncate();

        // At window end (02:00) - post midnight
        $this->postJson($this->endpoint, $this->makeRequest([
            'time' => '06:00',
            'type' => 'day_in',
        ]));

        $response = $this->postJson($this->endpoint, $this->makeRequest([
            'time' => '02:00',
            'type' => 'day_out',
        ]));
        $response->assertStatus(200);
    }

    /** @test */
    public function it_completes_full_workflow_successfully()
    {
        // Day In at 06:00 AM
        $response = $this->postJson($this->endpoint, $this->makeRequest([
            'time' => '06:00',
            'type' => 'day_in',
        ]));
        $response->assertStatus(200);

        // Break Start at 12:00 PM
        $response = $this->postJson($this->endpoint, $this->makeRequest([
            'time' => '12:00',
            'type' => 'break_start',
        ]));
        $response->assertStatus(200);

        // Break End at 13:00 (1:00 PM)
        $response = $this->postJson($this->endpoint, $this->makeRequest([
            'time' => '13:00',
            'type' => 'break_end',
        ]));
        $response->assertStatus(200);

        // Day Out at 22:00 (10:00 PM)
        $response = $this->postJson($this->endpoint, $this->makeRequest([
            'time' => '22:00',
            'type' => 'day_out',
        ]));
        $response->assertStatus(200);

        // Verify all events are stored in chronological order
        $events = UserTimeClock::where('user_id', $this->userId)
            ->orderBy('formated_date_time', 'asc')
            ->get();

        $this->assertCount(4, $events);
        $this->assertEquals('day_in', $events[0]->type);
        $this->assertEquals('break_start', $events[1]->type);
        $this->assertEquals('break_end', $events[2]->type);
        $this->assertEquals('day_out', $events[3]->type);
    }

    /** @test */
    public function it_handles_multiple_breaks_correctly()
    {
        // Day In
        $this->postJson($this->endpoint, $this->makeRequest([
            'time' => '06:00',
            'type' => 'day_in',
        ]));

        // First Break
        $this->postJson($this->endpoint, $this->makeRequest([
            'time' => '10:00',
            'type' => 'break_start',
        ]));
        $this->postJson($this->endpoint, $this->makeRequest([
            'time' => '10:30',
            'type' => 'break_end',
        ]));

        // Second Break
        $this->postJson($this->endpoint, $this->makeRequest([
            'time' => '15:00',
            'type' => 'break_start',
        ]));
        $this->postJson($this->endpoint, $this->makeRequest([
            'time' => '15:30',
            'type' => 'break_end',
        ]));

        // Day Out
        $response = $this->postJson($this->endpoint, $this->makeRequest([
            'time' => '22:00',
            'type' => 'day_out',
        ]));

        $response->assertStatus(200);

        // Count only for this specific user (each test has its own user)
        $this->assertEquals(6, UserTimeClock::where('user_id', $this->userId)->count());
    }

    /** @test */
    public function it_allows_multiple_shifts_in_same_day()
    {
        $today = Carbon::parse('2026-01-12');

        // First shift: 5:00 AM - 12:00 PM
        $this->postJson($this->endpoint, $this->makeRequest([
            'clock_date' => $today->format('Y-m-d'),
            'time' => '05:00',
            'type' => 'day_in',
        ]))->assertStatus(200);

        $this->postJson($this->endpoint, $this->makeRequest([
            'clock_date' => $today->format('Y-m-d'),
            'time' => '12:00',
            'type' => 'day_out',
        ]))->assertStatus(200);

        // Second shift: 1:00 PM - 3:00 PM
        $this->postJson($this->endpoint, $this->makeRequest([
            'clock_date' => $today->format('Y-m-d'),
            'time' => '13:00',
            'type' => 'day_in',
        ]))->assertStatus(200);

        $this->postJson($this->endpoint, $this->makeRequest([
            'clock_date' => $today->format('Y-m-d'),
            'time' => '15:00',
            'type' => 'day_out',
        ]))->assertStatus(200);

        // Third shift with break crossing midnight
        $this->postJson($this->endpoint, $this->makeRequest([
            'clock_date' => $today->format('Y-m-d'),
            'time' => '20:00',
            'type' => 'day_in',
        ]))->assertStatus(200);

        $this->postJson($this->endpoint, $this->makeRequest([
            'clock_date' => $today->format('Y-m-d'),
            'time' => '23:30',
            'type' => 'break_start',
        ]))->assertStatus(200);

        // Break end at 12:20 AM (post-midnight)
        $this->postJson($this->endpoint, $this->makeRequest([
            'clock_date' => $today->format('Y-m-d'),
            'time' => '00:20',
            'type' => 'break_end',
        ]))->assertStatus(200);

        // Day out at 1:30 AM (post-midnight)
        $this->postJson($this->endpoint, $this->makeRequest([
            'clock_date' => $today->format('Y-m-d'),
            'time' => '01:30',
            'type' => 'day_out',
        ]))->assertStatus(200);

        // Verify all 8 events stored correctly
        $events = UserTimeClock::where('user_id', $this->userId)
            ->where('date_at', $today->format('Y-m-d'))
            ->orderBy('formated_date_time', 'asc')
            ->get();

        $this->assertCount(8, $events);

        // Verify sequence
        $this->assertEquals('day_in', $events[0]->type);
        $this->assertEquals('day_out', $events[1]->type);
        $this->assertEquals('day_in', $events[2]->type);
        $this->assertEquals('day_out', $events[3]->type);
        $this->assertEquals('day_in', $events[4]->type);
        $this->assertEquals('break_start', $events[5]->type);
        $this->assertEquals('break_end', $events[6]->type);
        $this->assertEquals('day_out', $events[7]->type);
    }
}
