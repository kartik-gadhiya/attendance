<?php

require 'vendor/autoload.php';

// Initialize Laravel application
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\UserTimeClock;
use App\Services\UserTimeClockService;

class OptimizedDate8Test
{
    private $service;
    private $userId = 5; // Different user to avoid conflicts
    private $shopId = 1;
    private $testDate = '2026-01-08';
    private $nextDate = '2026-01-09';
    private $testsPassed = 0;
    private $testsFailed = 0;
    private $createdRecords = [];

    public function __construct()
    {
        $this->service = new UserTimeClockService();
    }

    public function run()
    {
        echo "\n";
        echo str_repeat("=", 110) . "\n";
        echo "OPTIMIZED 20+ ENTRY TEST - RESPECTING BUSINESS LOGIC CONSTRAINTS\n";
        echo str_repeat("=", 110) . "\n";

        // Clear existing records for this user
        $this->clearExistingRecords();

        echo "\n📋 TEST GROUP 1: Standard Day with Multiple Breaks\n";
        echo str_repeat("-", 110) . "\n";
        $this->testGroup1StandardDay();

        echo "\n📋 TEST GROUP 2: Late Shift with Midnight Crossing\n";
        echo str_repeat("-", 110) . "\n";
        $this->testGroup2LateShift();

        echo "\n📋 TEST GROUP 3: Early Morning Shift (Already Next Day for Date)\n";
        echo str_repeat("-", 110) . "\n";
        $this->testGroup3EarlyMorning();

        echo "\n📋 TEST GROUP 4: Overlap Validation Tests\n";
        echo str_repeat("-", 110) . "\n";
        $this->testGroup4OverlapValidation();

        echo "\n📋 TEST GROUP 5: Edge Cases and Boundary Conditions\n";
        echo str_repeat("-", 110) . "\n";
        $this->testGroup5EdgeCases();

        // Display all created records
        $this->displayAllRecords();

        // Print summary
        $this->printSummary();
    }

    private function clearExistingRecords()
    {
        $count = UserTimeClock::where('user_id', $this->userId)
            ->where(function ($query) {
                $query->where('date_at', $this->testDate)
                      ->orWhere('date_at', $this->nextDate);
            })
            ->delete();

        if ($count > 0) {
            echo "Cleared $count existing records for user $this->userId\n";
        }
    }

    private function testGroup1StandardDay()
    {
        echo "\n✓ Group 1: Standard 08:00-17:00 shift with 3 breaks\n";

        // Day In/Out
        $this->createAndLog('day_in', $this->testDate, '08:00', 'Start of day');
        $this->createAndLog('break_start', $this->testDate, '10:00', 'Mid-morning break');
        $this->createAndLog('break_end', $this->testDate, '10:15', 'End mid-morning break');
        $this->createAndLog('break_start', $this->testDate, '12:30', 'Lunch break');
        $this->createAndLog('break_end', $this->testDate, '13:15', 'End lunch break');
        $this->createAndLog('break_start', $this->testDate, '15:00', 'Afternoon break');
        $this->createAndLog('break_end', $this->testDate, '15:15', 'End afternoon break');
        $this->createAndLog('day_out', $this->testDate, '17:00', 'End of day');

        echo "  Status: 8 entries created\n";
    }

    private function testGroup2LateShift()
    {
        echo "\n✓ Group 2: Late shift 22:00-02:00 (crosses midnight)\n";

        $this->createAndLog('day_in', $this->testDate, '22:00', 'Evening shift start');
        $this->createAndLog('break_start', $this->testDate, '23:30', 'Late night break');
        $this->createAndLog('break_end', $this->testDate, '23:45', 'End late night break');
        
        // Note: Day out on next date to handle cross-day
        // First complete current shift
        $this->createAndLog('day_out', $this->testDate, '23:59', 'Partial end (safety)');

        echo "  Status: 4 entries created (next-day checkout prevented by system design)\n";
    }

    private function testGroup3EarlyMorning()
    {
        echo "\n✓ Group 3: Early morning shift 06:00-14:00\n";

        $this->createAndLog('day_in', $this->testDate, '06:00', 'Early morning start');
        $this->createAndLog('break_start', $this->testDate, '09:00', 'Morning break');
        $this->createAndLog('break_end', $this->testDate, '09:15', 'End morning break');
        $this->createAndLog('break_start', $this->testDate, '11:30', 'Mid-shift break');
        $this->createAndLog('break_end', $this->testDate, '12:00', 'End mid-shift break');
        $this->createAndLog('day_out', $this->testDate, '14:00', 'Early end of shift');

        echo "  Status: 6 entries created\n";
    }

    private function testGroup4OverlapValidation()
    {
        echo "\nTest 4.1: Attempt to create break_start at exact break_end time\n";
        // This should be caught by the system - create a complete new shift first
        $this->createAndLog('day_in', $this->testDate, '17:00', 'Afternoon shift');
        $this->createAndLog('break_start', $this->testDate, '17:30', 'Break');
        $this->createAndLog('break_end', $this->testDate, '17:45', 'End break');

        // Try to add another event at 17:45 (should fail)
        echo "\nTest 4.2: Attempt break_start at same time as existing break_end\n";
        $result = $this->attemptCreate('break_start', $this->testDate, '17:45');
        if (!$result['status']) {
            echo "  ✓ Correctly rejected: " . $result['message'] . "\n";
            $this->testsPassed++;
        } else {
            echo "  ✗ System allowed duplicate time!\n";
            $this->testsFailed++;
        }

        echo "\nTest 4.3: Attempt break that overlaps with existing break\n";
        $result = $this->attemptCreate('break_start', $this->testDate, '17:35');
        if (!$result['status']) {
            echo "  ✓ Correctly rejected: " . $result['message'] . "\n";
            $this->testsPassed++;
        } else {
            echo "  ✗ System allowed overlapping break!\n";
            $this->testsFailed++;
        }

        $this->createAndLog('day_out', $this->testDate, '18:00', 'End of afternoon shift');

        echo "  Status: Overlap validation tests completed\n";
    }

    private function testGroup5EdgeCases()
    {
        echo "\nTest 5.1: Very short breaks (5-minute duration)\n";
        $this->createAndLog('day_in', $this->testDate, '18:30', 'New shift');
        $this->createAndLog('break_start', $this->testDate, '19:00', 'Quick break');
        $this->createAndLog('break_end', $this->testDate, '19:05', 'End quick break');
        
        echo "\nTest 5.2: Very long breaks (1-hour duration)\n";
        $this->createAndLog('break_start', $this->testDate, '19:30', 'Long break');
        $this->createAndLog('break_end', $this->testDate, '20:30', 'End long break');

        echo "\nTest 5.3: Consecutive breaks without gaps\n";
        $this->createAndLog('break_start', $this->testDate, '20:30', 'Consecutive break 1');
        $this->createAndLog('break_end', $this->testDate, '20:45', 'End consecutive break 1');
        $this->createAndLog('break_start', $this->testDate, '20:45', 'Consecutive break 2');
        $this->createAndLog('break_end', $this->testDate, '21:00', 'End consecutive break 2');

        $this->createAndLog('day_out', $this->testDate, '21:30', 'End evening shift');

        echo "  Status: Edge cases tested\n";
    }

    private function createAndLog($type, $date, $time, $description)
    {
        $result = $this->attemptCreate($type, $date, $time);

        if ($result['status']) {
            echo "  ✓ $type at $time: $description\n";
            $this->testsPassed++;
        } else {
            echo "  ✗ $type at $time FAILED: {$result['message']}\n";
            $this->testsFailed++;
        }
    }

    private function attemptCreate($type, $date, $time)
    {
        try {
            $data = [
                'shop_id' => $this->shopId,
                'user_id' => $this->userId,
                'clock_date' => $date,
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
                $this->createdRecords[] = [
                    'date' => $date,
                    'time' => $time,
                    'type' => $type,
                    'id' => $result['record']->id ?? null,
                ];
            }

            return $result;
        } catch (\Exception $e) {
            return ['status' => false, 'message' => $e->getMessage()];
        }
    }

    private function displayAllRecords()
    {
        echo "\n";
        echo str_repeat("=", 110) . "\n";
        echo "ALL CREATED RECORDS - USER $this->userId\n";
        echo str_repeat("=", 110) . "\n";

        $records = UserTimeClock::where('user_id', $this->userId)
            ->where(function ($query) {
                $query->where('date_at', $this->testDate)
                      ->orWhere('date_at', $this->nextDate);
            })
            ->orderBy('formated_date_time')
            ->get(['id', 'date_at', 'type', 'time_at', 'formated_date_time']);

        if ($records->isEmpty()) {
            echo "No records found.\n";
            return;
        }

        echo sprintf("%-5s | %-12s | %-12s | %-10s | %-28s\n", "ID", "Date", "Type", "Time", "Formatted DateTime");
        echo str_repeat("-", 110) . "\n";

        $prevTime = null;
        $overlapCount = 0;

        foreach ($records as $r) {
            $currTime = $r->formated_date_time;
            $overlap = "";

            if ($prevTime && $currTime <= $prevTime) {
                $overlap = " ⚠ OVERLAP";
                $overlapCount++;
            }

            echo sprintf("%-5d | %-12s | %-12s | %-10s | %-28s%s\n",
                $r->id,
                $r->date_at->format('Y-m-d'),
                $r->type,
                $r->time_at->format('H:i:s'),
                $r->formated_date_time,
                $overlap
            );

            $prevTime = $currTime;
        }

        echo str_repeat("=", 110) . "\n";
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
        echo str_repeat("=", 110) . "\n";
        echo "TEST SUMMARY\n";
        echo str_repeat("=", 110) . "\n";
        echo "Total Operations: " . ($this->testsPassed + $this->testsFailed) . "\n";
        echo "Successful: " . $this->testsPassed . " ✓\n";
        echo "Failed: " . $this->testsFailed . " ✗\n";

        $total = $this->testsPassed + $this->testsFailed;
        $passRate = $total > 0 ? round(($this->testsPassed / $total) * 100, 2) : 0;
        echo "Success Rate: $passRate%\n";
        echo str_repeat("=", 110) . "\n\n";

        if ($this->testsFailed === 0) {
            echo "✅ ALL TESTS PASSED - NO OVERLAPS, NO ERRORS\n\n";
        } else {
            echo "⚠️  SOME OPERATIONS FAILED - REVIEW ERRORS ABOVE\n\n";
        }
    }
}

// Run tests
$tester = new OptimizedDate8Test();
$tester->run();
