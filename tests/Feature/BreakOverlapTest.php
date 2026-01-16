<?php

namespace Tests\Feature;

use App\Models\UserTimeClock;
use App\Services\UserTimeClockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class BreakOverlapTest extends TestCase
{
    use RefreshDatabase;

    private $service;
    private $userId = 5;
    private $shopId = 1;
    private $date = '2026-01-11';

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new UserTimeClockService();
        
        // Create a test user
        \App\Models\User::create([
            'id' => $this->userId,
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => bcrypt('password'),
            'email_verified_at' => now(),
        ]);
    }

    /** @test */
    public function test_simple_overnight_shift()
    {
        // Add day_in at 23:00
        $dayInResult = $this->service->dayInAdd([
            'user_id' => $this->userId,
            'shop_id' => $this->shopId,
            'clock_date' => $this->date,
            'time' => '23:00',
            'comment' => 'Test shift start',
        ]);
        $this->assertTrue($dayInResult['status']);

        // Add day_out at 01:00 (next day)
        $dayOutResult = $this->service->dayOutAdd([
            'user_id' => $this->userId,
            'shop_id' => $this->shopId,
            'clock_date' => $this->date,
            'time' => '01:00',
            'comment' => 'Test shift end',
        ]);
        $this->assertTrue($dayOutResult['status']);

        $this->assertCount(2, UserTimeClock::all());
    }

    /** @test */
    public function test_add_first_break()
    {
        // Setup
        $this->service->dayInAdd([
            'user_id' => $this->userId,
            'shop_id' => $this->shopId,
            'clock_date' => $this->date,
            'time' => '23:00',
        ]);
        $this->service->dayOutAdd([
            'user_id' => $this->userId,
            'shop_id' => $this->shopId,
            'clock_date' => $this->date,
            'time' => '01:00',
        ]);

        // Add first break (23:45 to 00:15)
        $breakStart1 = $this->service->breakStartAdd([
            'user_id' => $this->userId,
            'shop_id' => $this->shopId,
            'clock_date' => $this->date,
            'time' => '23:45',
        ]);
        $this->assertTrue($breakStart1['status']);

        $breakEnd1 = $this->service->breakEndAdd([
            'user_id' => $this->userId,
            'shop_id' => $this->shopId,
            'clock_date' => $this->date,
            'time' => '00:15',
        ]);
        $this->assertTrue($breakEnd1['status']);
    }

    /** @test */
    public function test_block_overlapping_break_start()
    {
        // Setup
        $this->service->dayInAdd([
            'user_id' => $this->userId,
            'shop_id' => $this->shopId,
            'clock_date' => $this->date,
            'time' => '23:00',
        ]);
        $this->service->dayOutAdd([
            'user_id' => $this->userId,
            'shop_id' => $this->shopId,
            'clock_date' => $this->date,
            'time' => '01:00',
        ]);

        // Add first break (23:45 to 00:15)
        $this->service->breakStartAdd([
            'user_id' => $this->userId,
            'shop_id' => $this->shopId,
            'clock_date' => $this->date,
            'time' => '23:45',
        ]);
        $this->service->breakEndAdd([
            'user_id' => $this->userId,
            'shop_id' => $this->shopId,
            'clock_date' => $this->date,
            'time' => '00:15',
        ]);

        // Try to add overlapping break_start at 00:05
        $overlapResult = $this->service->breakStartAdd([
            'user_id' => $this->userId,
            'shop_id' => $this->shopId,
            'clock_date' => $this->date,
            'time' => '00:05',
        ]);
        
        $this->assertFalse($overlapResult['status']);
        $this->assertStringContainsString('falls within', $overlapResult['message']);
    }

    /** @test */
    public function test_allow_non_overlapping_second_break()
    {
        // Setup
        $this->service->dayInAdd([
            'user_id' => $this->userId,
            'shop_id' => $this->shopId,
            'clock_date' => $this->date,
            'time' => '23:00',
        ]);
        $this->service->dayOutAdd([
            'user_id' => $this->userId,
            'shop_id' => $this->shopId,
            'clock_date' => $this->date,
            'time' => '01:00',
        ]);

        // Add first break (23:45 to 00:15)
        $this->service->breakStartAdd([
            'user_id' => $this->userId,
            'shop_id' => $this->shopId,
            'clock_date' => $this->date,
            'time' => '23:45',
        ]);
        $this->service->breakEndAdd([
            'user_id' => $this->userId,
            'shop_id' => $this->shopId,
            'clock_date' => $this->date,
            'time' => '00:15',
        ]);

        // Add second break (00:30 to 00:45) - should succeed
        $breakStart2 = $this->service->breakStartAdd([
            'user_id' => $this->userId,
            'shop_id' => $this->shopId,
            'clock_date' => $this->date,
            'time' => '00:30',
        ]);
        $this->assertTrue($breakStart2['status']);

        $breakEnd2 = $this->service->breakEndAdd([
            'user_id' => $this->userId,
            'shop_id' => $this->shopId,
            'clock_date' => $this->date,
            'time' => '00:45',
        ]);
        $this->assertTrue($breakEnd2['status']);
    }

    /** @test */
    public function test_block_break_overlapping_both_existing_breaks()
    {
        // Setup
        $this->service->dayInAdd([
            'user_id' => $this->userId,
            'shop_id' => $this->shopId,
            'clock_date' => $this->date,
            'time' => '23:00',
        ]);
        $this->service->dayOutAdd([
            'user_id' => $this->userId,
            'shop_id' => $this->shopId,
            'clock_date' => $this->date,
            'time' => '01:00',
        ]);

        // Add first break 23:45-00:15
        $this->service->breakStartAdd([
            'user_id' => $this->userId,
            'shop_id' => $this->shopId,
            'clock_date' => $this->date,
            'time' => '23:45',
        ]);
        $this->service->breakEndAdd([
            'user_id' => $this->userId,
            'shop_id' => $this->shopId,
            'clock_date' => $this->date,
            'time' => '00:15',
        ]);

        // Add second break 00:30-00:45
        $this->service->breakStartAdd([
            'user_id' => $this->userId,
            'shop_id' => $this->shopId,
            'clock_date' => $this->date,
            'time' => '00:30',
        ]);
        $this->service->breakEndAdd([
            'user_id' => $this->userId,
            'shop_id' => $this->shopId,
            'clock_date' => $this->date,
            'time' => '00:45',
        ]);

        // Try to add break 23:30-00:50 (spans both breaks)
        $overlapResult = $this->service->breakStartAdd([
            'user_id' => $this->userId,
            'shop_id' => $this->shopId,
            'clock_date' => $this->date,
            'time' => '23:30',
        ]);
        
        $this->assertFalse($overlapResult['status']);
    }

    /** @test */
    public function test_exact_boundary_break_start_allowed()
    {
        // Setup
        $this->service->dayInAdd([
            'user_id' => $this->userId,
            'shop_id' => $this->shopId,
            'clock_date' => $this->date,
            'time' => '23:00',
        ]);
        $this->service->dayOutAdd([
            'user_id' => $this->userId,
            'shop_id' => $this->shopId,
            'clock_date' => $this->date,
            'time' => '01:00',
        ]);

        // Add first break 23:45-00:15
        $this->service->breakStartAdd([
            'user_id' => $this->userId,
            'shop_id' => $this->shopId,
            'clock_date' => $this->date,
            'time' => '23:45',
        ]);
        $this->service->breakEndAdd([
            'user_id' => $this->userId,
            'shop_id' => $this->shopId,
            'clock_date' => $this->date,
            'time' => '00:15',
        ]);

        // Add break_start at exact boundary (00:15) - should succeed
        $boundaryResult = $this->service->breakStartAdd([
            'user_id' => $this->userId,
            'shop_id' => $this->shopId,
            'clock_date' => $this->date,
            'time' => '00:15',
        ]);
        
        $this->assertTrue($boundaryResult['status']);
    }

    /** @test */
    public function test_block_overlapping_break_end()
    {
        // Setup
        $this->service->dayInAdd([
            'user_id' => $this->userId,
            'shop_id' => $this->shopId,
            'clock_date' => $this->date,
            'time' => '23:00',
        ]);
        $this->service->dayOutAdd([
            'user_id' => $this->userId,
            'shop_id' => $this->shopId,
            'clock_date' => $this->date,
            'time' => '02:00',
        ]);

        // Add first break 23:45-00:15
        $this->service->breakStartAdd([
            'user_id' => $this->userId,
            'shop_id' => $this->shopId,
            'clock_date' => $this->date,
            'time' => '23:45',
        ]);
        $this->service->breakEndAdd([
            'user_id' => $this->userId,
            'shop_id' => $this->shopId,
            'clock_date' => $this->date,
            'time' => '00:15',
        ]);

        // Try to add a new break starting at 00:30, but end it at 00:10 (should fail)
        $breakStart2 = $this->service->breakStartAdd([
            'user_id' => $this->userId,
            'shop_id' => $this->shopId,
            'clock_date' => $this->date,
            'time' => '00:30',
        ]);
        $this->assertTrue($breakStart2['status']);

        // Try to end it at 00:10 - this should fail because end < start
        $breakEnd2 = $this->service->breakEndAdd([
            'user_id' => $this->userId,
            'shop_id' => $this->shopId,
            'clock_date' => $this->date,
            'time' => '00:10',
        ]);
        $this->assertFalse($breakEnd2['status']);
    }

    /** @test */
    public function test_multiple_non_overlapping_breaks()
    {
        // Setup
        $this->service->dayInAdd([
            'user_id' => $this->userId,
            'shop_id' => $this->shopId,
            'clock_date' => $this->date,
            'time' => '23:00',
        ]);
        $this->service->dayOutAdd([
            'user_id' => $this->userId,
            'shop_id' => $this->shopId,
            'clock_date' => $this->date,
            'time' => '02:00',
        ]);

        // Add three non-overlapping breaks
        $this->service->breakStartAdd([
            'user_id' => $this->userId,
            'shop_id' => $this->shopId,
            'clock_date' => $this->date,
            'time' => '23:15',
        ]);
        $this->service->breakEndAdd([
            'user_id' => $this->userId,
            'shop_id' => $this->shopId,
            'clock_date' => $this->date,
            'time' => '23:30',
        ]);

        $this->service->breakStartAdd([
            'user_id' => $this->userId,
            'shop_id' => $this->shopId,
            'clock_date' => $this->date,
            'time' => '00:15',
        ]);
        $this->service->breakEndAdd([
            'user_id' => $this->userId,
            'shop_id' => $this->shopId,
            'clock_date' => $this->date,
            'time' => '00:30',
        ]);

        $this->service->breakStartAdd([
            'user_id' => $this->userId,
            'shop_id' => $this->shopId,
            'clock_date' => $this->date,
            'time' => '01:15',
        ]);
        $result = $this->service->breakEndAdd([
            'user_id' => $this->userId,
            'shop_id' => $this->shopId,
            'clock_date' => $this->date,
            'time' => '01:30',
        ]);

        $this->assertTrue($result['status']);
        $this->assertCount(8, UserTimeClock::all()); // 2 day + 6 breaks
    }
}
