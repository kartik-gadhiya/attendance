<?php

require 'vendor/autoload.php';

// Initialize Laravel application
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\UserTimeClock;
use App\Services\UserTimeClockService;

class AdvancedOverlapValidationTest
{
    private $service;
    private $userId = 6; // New user for isolation
    private $shopId = 1;
    private $testDate = '2026-01-08';
    private $testsPassed = 0;
    private $testsFailed = 0;
    private $testsAttempted = 0;

    public function __construct()
    {
        $this->service = new UserTimeClockService();
    }

    public function run()
    {
        echo "\n";
        echo str_repeat("=", 120) . "\n";
        echo "ADVANCED OVERLAP VALIDATION TEST - RIGOROUS BOUNDARY TESTING\n";
        echo str_repeat("=", 120) . "\n";

        $this->clearRecords();

        echo "\n📋 TEST SUITE 1: Precise Boundary Overlap Detection\n";
        echo str_repeat("-", 120) . "\n";
        $this->testBoundaryOverlaps();

        echo "\n📋 TEST SUITE 2: Break Range Validation\n";
        echo str_repeat("-", 120) . "\n";
        $this->testBreakRangeValidation();

        echo "\n📋 TEST SUITE 3: Multi-Shift Overlap Prevention\n";
        echo str_repeat("-", 120) . "\n";
        $this->testMultiShiftOverlap();

        echo "\n📋 TEST SUITE 4: Extreme Time Combinations\n";
        echo str_repeat("-", 120) . "\n";
        $this->testExtremeTimeCombinations();

        // Display results
        $this->displayRecords();
        $this->printSummary();
    }

    private function clearRecords()
    {
        $count = UserTimeClock::where('user_id', $this->userId)
            ->where('date_at', $this->testDate)
            ->delete();
        
        if ($count > 0) {
            echo "Cleared $count records from previous tests\n";
        }
    }

    private function testBoundaryOverlaps()
    {
        echo "\nTest 1.1: Create primary break 10:00-11:00\n";
        $this->create('day_in', '09:00');
        $this->create('break_start', '10:00');
        $this->create('break_end', '11:00');

        echo "\nTest 1.2: Attempt to create break at exact end time (should fail)\n";
        $this->testAttempt('break_start', '11:00', false, 'Already existing break_end at 11:00');

        echo "\nTest 1.3: Attempt to create break 1 second before end (should fail)\n";
        $this->testAttempt('break_start', '10:59', false, 'Overlaps with 10:00-11:00 break');

        echo "\nTest 1.4: Attempt to create break 1 second after end (should succeed)\n";
        $this->create('break_start', '11:01');
        $this->create('break_end', '11:30');

        echo "\nTest 1.5: Verify no overlaps between 10:00-11:00 and 11:01-11:30\n";
        $this->verifyNoOverlaps();
    }

    private function testBreakRangeValidation()
    {
        echo "\nTest 2.1: Create shift 12:00-17:00\n";
        $this->create('day_out', '11:30');
        $this->create('day_in', '12:00');

        echo "\nTest 2.2: Create two breaks with 1-minute gap\n";
        $this->create('break_start', '12:30');
        $this->create('break_end', '13:00');
        
        echo "\nTest 2.3: Try to create overlapping break (should fail)\n";
        $this->testAttempt('break_start', '12:45', false, 'Overlaps with existing break');

        echo "\nTest 2.4: Create next break with exact gap match\n";
        $this->create('break_start', '13:01');
        $this->create('break_end', '13:30');

        echo "\nTest 2.5: Try to create overlapping break starting during existing break\n";
        $this->testAttempt('break_start', '13:15', false, 'Overlaps with 13:01-13:30 break');

        echo "\nTest 2.6: Verify all breaks are properly separated\n";
        $this->verifyNoOverlaps();

        $this->create('day_out', '17:00');
    }

    private function testMultiShiftOverlap()
    {
        echo "\nTest 3.1: Create first shift with breaks\n";
        $this->create('day_in', '18:00');
        $this->create('break_start', '18:30');
        $this->create('break_end', '18:45');
        $this->create('day_out', '19:00');

        echo "\nTest 3.2: Verify no lingering overlap with previous shift\n";
        $this->verifyNoOverlaps();

        echo "\nTest 3.3: Create second shift immediately after first\n";
        $this->create('day_in', '19:00');
        $this->create('break_start', '19:30');
        $this->create('break_end', '19:45');
        $this->create('day_out', '20:00');

        echo "\nTest 3.4: Verify no overlap between consecutive shifts\n";
        $this->verifyNoOverlaps();

        echo "\nTest 3.5: Attempt to create break in first shift timing (should fail - shift over)\n";
        $this->testAttempt('break_start', '18:20', false, 'No active shift for this time');
    }

    private function testExtremeTimeCombinations()
    {
        echo "\nTest 4.1: Create ultra-short break (1 minute)\n";
        $this->create('day_in', '20:30');
        $this->create('break_start', '20:40');
        $this->create('break_end', '20:41');

        echo "\nTest 4.2: Create ultra-long break (4 hours)\n";
        $this->create('break_start', '20:42');
        $this->create('break_end', '00:42');

        echo "\nTest 4.3: Try to create event in the middle of 4-hour break (should fail)\n";
        $this->testAttempt('break_start', '22:42', false, 'Overlaps with long break');

        echo "\nTest 4.4: End shift and verify no overlaps\n";
        $this->create('day_out', '00:43');
        $this->verifyNoOverlaps();

        echo "\nTest 4.5: Create back-to-back breaks with no gap\n";
        $this->create('day_in', '01:00');
        $this->create('break_start', '01:30');
        $this->create('break_end', '01:45');
        $this->create('break_start', '01:45');
        $this->create('break_end', '02:00');
        $this->create('day_out', '03:00');

        echo "\nTest 4.6: Verify back-to-back breaks have no overlap\n";
        $this->verifyNoOverlaps();
    }

    private function create($type, $time)
    {
        try {
            $data = [
                'shop_id' => $this->shopId,
                'user_id' => $this->userId,
                'clock_date' => $this->testDate,
                'time' => $time,
                'type' => $type,
                'comment' => "Test - $type at $time",
                'updated_from' => 'T',
                'buffer_time' => 3,
                'shift_start' => '08:00',
                'shift_end' => '23:00',
                'created_from' => 'T',
            ];

            $result = match($type) {
                'day_in' => $this->service->dayInAdd($data),
                'day_out' => $this->service->dayOutAdd($data),
                'break_start' => $this->service->breakStartAdd($data),
                'break_end' => $this->service->breakEndAdd($data),
                default => ['status' => false, 'message' => 'Invalid type'],
            };

            if ($result['status']) {
                echo "  ✓ $type at $time created successfully\n";
                $this->testsPassed++;
            } else {
                echo "  ✗ $type at $time FAILED: {$result['message']}\n";
                $this->testsFailed++;
            }
            $this->testsAttempted++;
        } catch (\Exception $e) {
            echo "  ✗ $type at $time ERROR: {$e->getMessage()}\n";
            $this->testsFailed++;
            $this->testsAttempted++;
        }
    }

    private function testAttempt($type, $time, $shouldSucceed, $reason)
    {
        try {
            $data = [
                'shop_id' => $this->shopId,
                'user_id' => $this->userId,
                'clock_date' => $this->testDate,
                'time' => $time,
                'type' => $type,
                'comment' => "Test attempt - $type at $time",
                'updated_from' => 'T',
                'buffer_time' => 3,
                'shift_start' => '08:00',
                'shift_end' => '23:00',
                'created_from' => 'T',
            ];

            $result = match($type) {
                'day_in' => $this->service->dayInAdd($data),
                'day_out' => $this->service->dayOutAdd($data),
                'break_start' => $this->service->breakStartAdd($data),
                'break_end' => $this->service->breakEndAdd($data),
                default => ['status' => false, 'message' => 'Invalid type'],
            };

            $success = $result['status'] === $shouldSucceed;

            if ($success) {
                echo "  ✓ Correctly " . ($shouldSucceed ? "allowed" : "rejected") . ": $reason\n";
                if (!$shouldSucceed) {
                    echo "    Reason: {$result['message']}\n";
                }
                $this->testsPassed++;
            } else {
                echo "  ✗ Incorrectly " . ($result['status'] ? "allowed" : "rejected") . ": $reason\n";
                echo "    Message: {$result['message']}\n";
                $this->testsFailed++;
            }
            $this->testsAttempted++;
        } catch (\Exception $e) {
            echo "  ✗ Test error: {$e->getMessage()}\n";
            $this->testsFailed++;
            $this->testsAttempted++;
        }
    }

    private function verifyNoOverlaps()
    {
        $records = UserTimeClock::where('user_id', $this->userId)
            ->where('date_at', $this->testDate)
            ->orderBy('formated_date_time')
            ->get();

        if ($records->isEmpty()) {
            echo "  ⚠ No records to verify\n";
            return;
        }

        $prevTime = null;
        $hasOverlap = false;

        foreach ($records as $record) {
            $currTime = $record->formated_date_time;
            if ($prevTime && $currTime <= $prevTime) {
                echo "  ✗ OVERLAP DETECTED: $prevTime >= $currTime\n";
                $hasOverlap = true;
            }
            $prevTime = $currTime;
        }

        if (!$hasOverlap) {
            echo "  ✓ Overlap verification passed - all times properly sequenced\n";
            $this->testsPassed++;
        } else {
            $this->testsFailed++;
        }
        $this->testsAttempted++;
    }

    private function displayRecords()
    {
        echo "\n";
        echo str_repeat("=", 120) . "\n";
        echo "FINAL RECORD STATE - USER $this->userId\n";
        echo str_repeat("=", 120) . "\n";

        $records = UserTimeClock::where('user_id', $this->userId)
            ->where('date_at', $this->testDate)
            ->orderBy('formated_date_time')
            ->get();

        if ($records->isEmpty()) {
            echo "No records found.\n";
            return;
        }

        echo sprintf("%-5s | %-12s | %-10s | %-28s\n", "ID", "Type", "Time", "Formatted DateTime");
        echo str_repeat("-", 120) . "\n";

        $prevTime = null;
        $overlapCount = 0;

        foreach ($records as $r) {
            $currTime = $r->formated_date_time;
            $overlap = "";

            if ($prevTime && $currTime <= $prevTime) {
                $overlap = " ⚠ OVERLAP";
                $overlapCount++;
            }

            echo sprintf("%-5d | %-12s | %-10s | %-28s%s\n",
                $r->id,
                $r->type,
                $r->time_at->format('H:i:s'),
                $r->formated_date_time,
                $overlap
            );

            $prevTime = $currTime;
        }

        echo str_repeat("=", 120) . "\n";
        echo "Total Records: " . count($records) . "\n";
        if ($overlapCount > 0) {
            echo "⚠ OVERLAPS DETECTED: $overlapCount\n";
        } else {
            echo "✓ NO OVERLAPS DETECTED\n";
        }
    }

    private function printSummary()
    {
        echo "\n";
        echo str_repeat("=", 120) . "\n";
        echo "ADVANCED VALIDATION TEST SUMMARY\n";
        echo str_repeat("=", 120) . "\n";
        echo "Tests Attempted: " . $this->testsAttempted . "\n";
        echo "Passed: " . $this->testsPassed . " ✓\n";
        echo "Failed: " . $this->testsFailed . " ✗\n";

        $passRate = $this->testsAttempted > 0 
            ? round(($this->testsPassed / $this->testsAttempted) * 100, 2) 
            : 0;
        echo "Pass Rate: $passRate%\n";
        echo str_repeat("=", 120) . "\n\n";

        if ($this->testsFailed === 0) {
            echo "✅ ALL VALIDATION TESTS PASSED - NO OVERLAPS DETECTED\n\n";
        } else {
            echo "⚠️  SOME TESTS FAILED - REVIEW DETAILS ABOVE\n\n";
        }
    }
}

// Run tests
$tester = new AdvancedOverlapValidationTest();
$tester->run();
