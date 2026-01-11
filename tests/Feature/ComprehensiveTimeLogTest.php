<?php

namespace Tests\Feature;

use App\Models\UserTimeClock;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ComprehensiveTimeLogTest extends TestCase
{
    use RefreshDatabase;

    protected $endpoint = '/api/time-clock';
    protected $shopId = 1;
    protected $userId;

    protected function setUp(): void
    {
        parent::setUp();

        // Create a test user with ID 3
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
            'clock_date' => '2026-01-01',
            'time' => '08:00',
            'type' => 'day_in',
            'shift_start' => '08:00',
            'shift_end' => '23:00',
            'buffer_time' => 3, // 3 hours as integer
        ], $overrides);
    }

    /** @test */
    public function it_completes_full_workflow_for_2026_01_01_with_midnight_crossing_break()
    {
        $date = '2026-01-01';

        // Day In at 08:00
        $this->postJson($this->endpoint, $this->makeRequest([
            'clock_date' => $date,
            'time' => '08:00',
            'type' => 'day_in',
        ]))->assertStatus(201);

        // Break Start at 09:00
        $this->postJson($this->endpoint, $this->makeRequest([
            'clock_date' => $date,
            'time' => '09:00',
            'type' => 'break_start',
        ]))->assertStatus(201);

        // Break End at 10:00
        $this->postJson($this->endpoint, $this->makeRequest([
            'clock_date' => $date,
            'time' => '10:00',
            'type' => 'break_end',
        ]))->assertStatus(201);

        // Day Out at 12:00
        $this->postJson($this->endpoint, $this->makeRequest([
            'clock_date' => $date,
            'time' => '12:00',
            'type' => 'day_out',
        ]))->assertStatus(201);

        // Day In at 13:00 (1:00 PM)
        $this->postJson($this->endpoint, $this->makeRequest([
            'clock_date' => $date,
            'time' => '13:00',
            'type' => 'day_in',
        ]))->assertStatus(201);

        // Day Out at 14:00 (2:00 PM)
        $this->postJson($this->endpoint, $this->makeRequest([
            'clock_date' => $date,
            'time' => '14:00',
            'type' => 'day_out',
        ]))->assertStatus(201);

        // Day In at 15:00 (3:00 PM)
        $this->postJson($this->endpoint, $this->makeRequest([
            'clock_date' => $date,
            'time' => '15:00',
            'type' => 'day_in',
        ]))->assertStatus(201);

        // Break Start at 16:00 (4:00 PM)
        $this->postJson($this->endpoint, $this->makeRequest([
            'clock_date' => $date,
            'time' => '16:00',
            'type' => 'break_start',
        ]))->assertStatus(201);

        // Break End at 17:00 (5:00 PM)
        $this->postJson($this->endpoint, $this->makeRequest([
            'clock_date' => $date,
            'time' => '17:00',
            'type' => 'break_end',
        ]))->assertStatus(201);

        // Break Start at 20:00 (8:00 PM)
        $this->postJson($this->endpoint, $this->makeRequest([
            'clock_date' => $date,
            'time' => '20:00',
            'type' => 'break_start',
        ]))->assertStatus(201);

        // Break End at 21:00 (9:00 PM)
        $this->postJson($this->endpoint, $this->makeRequest([
            'clock_date' => $date,
            'time' => '21:00',
            'type' => 'break_end',
        ]))->assertStatus(201);

        // Break Start at 23:30 (11:30 PM)
        $this->postJson($this->endpoint, $this->makeRequest([
            'clock_date' => $date,
            'time' => '23:30',
            'type' => 'break_start',
        ]))->assertStatus(201);

        // Break End at 00:30 (12:30 AM next day) - CRITICAL TEST
        $response = $this->postJson($this->endpoint, $this->makeRequest([
            'clock_date' => $date,
            'time' => '00:30',
            'type' => 'break_end',
        ]));
        $response->assertStatus(201);

        // Day Out at 01:00 (1:00 AM next day) - CRITICAL TEST
        $response = $this->postJson($this->endpoint, $this->makeRequest([
            'clock_date' => $date,
            'time' => '01:00',
            'type' => 'day_out',
        ]));
        $response->assertStatus(201);

        // Verify all 14 events stored
        $count = UserTimeClock::where('user_id', $this->userId)
            ->where('date_at', $date)
            ->count();
        $this->assertEquals(14, $count);
    }

    /** @test */
    public function it_completes_same_workflow_for_2026_01_02_for_consistency()
    {
        $date = '2026-01-02';

        // Same sequence as 2026-01-01
        $this->postJson($this->endpoint, $this->makeRequest(['clock_date' => $date, 'time' => '08:00', 'type' => 'day_in']))->assertStatus(201);
        $this->postJson($this->endpoint, $this->makeRequest(['clock_date' => $date, 'time' => '09:00', 'type' => 'break_start']))->assertStatus(201);
        $this->postJson($this->endpoint, $this->makeRequest(['clock_date' => $date, 'time' => '10:00', 'type' => 'break_end']))->assertStatus(201);
        $this->postJson($this->endpoint, $this->makeRequest(['clock_date' => $date, 'time' => '12:00', 'type' => 'day_out']))->assertStatus(201);
        $this->postJson($this->endpoint, $this->makeRequest(['clock_date' => $date, 'time' => '13:00', 'type' => 'day_in']))->assertStatus(201);
        $this->postJson($this->endpoint, $this->makeRequest(['clock_date' => $date, 'time' => '14:00', 'type' => 'day_out']))->assertStatus(201);
        $this->postJson($this->endpoint, $this->makeRequest(['clock_date' => $date, 'time' => '15:00', 'type' => 'day_in']))->assertStatus(201);
        $this->postJson($this->endpoint, $this->makeRequest(['clock_date' => $date, 'time' => '16:00', 'type' => 'break_start']))->assertStatus(201);
        $this->postJson($this->endpoint, $this->makeRequest(['clock_date' => $date, 'time' => '17:00', 'type' => 'break_end']))->assertStatus(201);
        $this->postJson($this->endpoint, $this->makeRequest(['clock_date' => $date, 'time' => '20:00', 'type' => 'break_start']))->assertStatus(201);
        $this->postJson($this->endpoint, $this->makeRequest(['clock_date' => $date, 'time' => '21:00', 'type' => 'break_end']))->assertStatus(201);
        $this->postJson($this->endpoint, $this->makeRequest(['clock_date' => $date, 'time' => '23:30', 'type' => 'break_start']))->assertStatus(201);
        $this->postJson($this->endpoint, $this->makeRequest(['clock_date' => $date, 'time' => '00:30', 'type' => 'break_end']))->assertStatus(201);
        $this->postJson($this->endpoint, $this->makeRequest(['clock_date' => $date, 'time' => '01:00', 'type' => 'day_out']))->assertStatus(201);

        $count = UserTimeClock::where('user_id', $this->userId)->where('date_at', $date)->count();
        $this->assertEquals(14, $count);
    }

    /** @test */
    public function it_prevents_retroactive_break_entries_on_2026_01_03()
    {
        $date = '2026-01-03';

        // Day In at 05:00
        $this->postJson($this->endpoint, $this->makeRequest([
            'clock_date' => $date,
            'time' => '05:00',
            'type' => 'day_in',
        ]))->assertStatus(201);

        // First break: 06:00 - 07:00
        $this->postJson($this->endpoint, $this->makeRequest([
            'clock_date' => $date,
            'time' => '06:00',
            'type' => 'break_start',
        ]))->assertStatus(201);

        $this->postJson($this->endpoint, $this->makeRequest([
            'clock_date' => $date,
            'time' => '07:00',
            'type' => 'break_end',
        ]))->assertStatus(201);

        // Try to add a break that would be in the past (before the last event at 07:00)
        // This should be rejected because events must be chronological
        $response = $this->postJson($this->endpoint, $this->makeRequest([
            'clock_date' => $date,
            'time' => '06:30',
            'type' => 'break_start',
        ]));

        // Should fail - can't add events before the last recorded event
        $response->assertStatus(422);
    }

    /** @test */
    public function it_handles_multiple_shifts_with_midnight_crossing_breaks_on_2026_01_03()
    {
        $date = '2026-01-03';

        // First shift
        $this->postJson($this->endpoint, $this->makeRequest(['clock_date' => $date, 'time' => '05:00', 'type' => 'day_in']))->assertStatus(201);
        $this->postJson($this->endpoint, $this->makeRequest(['clock_date' => $date, 'time' => '12:00', 'type' => 'day_out']))->assertStatus(201);

        // Second shift
        $this->postJson($this->endpoint, $this->makeRequest(['clock_date' => $date, 'time' => '15:00', 'type' => 'day_in']))->assertStatus(201);

        // Break 18:00-19:00
        $this->postJson($this->endpoint, $this->makeRequest(['clock_date' => $date, 'time' => '18:00', 'type' => 'break_start']))->assertStatus(201);
        $this->postJson($this->endpoint, $this->makeRequest(['clock_date' => $date, 'time' => '19:00', 'type' => 'break_end']))->assertStatus(201);

        // Midnight-crossing break 23:30-00:30
        $this->postJson($this->endpoint, $this->makeRequest(['clock_date' => $date, 'time' => '23:30', 'type' => 'break_start']))->assertStatus(201);
        $this->postJson($this->endpoint, $this->makeRequest(['clock_date' => $date, 'time' => '00:30', 'type' => 'break_end']))->assertStatus(201);

        // Day Out at 02:00 (within buffer)
        $this->postJson($this->endpoint, $this->makeRequest(['clock_date' => $date, 'time' => '02:00', 'type' => 'day_out']))->assertStatus(201);

        $count = UserTimeClock::where('user_id', $this->userId)->where('date_at', $date)->count();
        $this->assertEquals(8, $count);
    }

    /** @test */
    public function it_tests_buffer_boundary_edge_cases_on_2026_01_04()
    {
        $date = '2026-01-04';

        // Day In at 05:00 (buffer start - shift 08:00, buffer 3hrs = 05:00)
        $this->postJson($this->endpoint, $this->makeRequest([
            'clock_date' => $date,
            'time' => '05:00',
            'type' => 'day_in',
        ]))->assertStatus(201);

        // Day Out at 12:00
        $this->postJson($this->endpoint, $this->makeRequest([
            'clock_date' => $date,
            'time' => '12:00',
            'type' => 'day_out',
        ]))->assertStatus(201);

        // Day In at 13:00
        $this->postJson($this->endpoint, $this->makeRequest([
            'clock_date' => $date,
            'time' => '13:00',
            'type' => 'day_in',
        ]))->assertStatus(201);

        // Day Out at 02:00 next day (buffer end - shift 23:00, buffer 3hrs = 02:00)
        $response = $this->postJson($this->endpoint, $this->makeRequest([
            'clock_date' => $date,
            'time' => '02:00',
            'type' => 'day_out',
        ]));
        $response->assertStatus(201);

        $count = UserTimeClock::where('user_id', $this->userId)->where('date_at', $date)->count();
        $this->assertEquals(4, $count);
    }

    /** @test */
    public function it_accepts_01_20_am_within_buffer_window()
    {
        $date = '2026-01-07';

        // First create a day-in to establish shift times
        $this->postJson($this->endpoint, $this->makeRequest([
            'clock_date' => $date,
            'time' => '08:00',
            'type' => 'day_in',
        ]))->assertStatus(201);

        // Day out at 01:20 AM - THIS IS THE CRITICAL TEST
        // Shift 08:00-23:00, buffer 3hrs = 05:00 to 02:00 next day
        // 01:20 AM should be ACCEPTED
        $response = $this->postJson($this->endpoint, $this->makeRequest([
            'clock_date' => $date,
            'time' => '01:20',
            'type' => 'day_out',
        ]));

        $response->assertStatus(201);
        $response->assertJsonFragment(['success' => true]);
    }

    /** @test */
    public function it_rejects_time_outside_buffer_window()
    {
        $date = '2026-01-08';

        // Try day-in at 04:59 (before buffer start of 05:00)
        $response = $this->postJson($this->endpoint, $this->makeRequest([
            'clock_date' => $date,
            'time' => '04:59',
            'type' => 'day_in',
        ]));

        $response->assertStatus(422);
    }

    /** @test */
    public function it_rejects_day_out_at_02_01_outside_buffer_window()
    {
        $date = '2026-01-09';

        // Day In
        $this->postJson($this->endpoint, $this->makeRequest([
            'clock_date' => $date,
            'time' => '08:00',
            'type' => 'day_in',
        ]))->assertStatus(201);

        // Try day-out at 02:01 (after buffer end of 02:00)
        $response = $this->postJson($this->endpoint, $this->makeRequest([
            'clock_date' => $date,
            'time' => '02:01',
            'type' => 'day_out',
        ]));

        $response->assertStatus(422);
    }
}
