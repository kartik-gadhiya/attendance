<?php

require 'vendor/autoload.php';

// Initialize Laravel application
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\UserTimeClock;
use App\Services\UserTimeClockService;

class TestDate8Entries
{
    private $service;
    private $userId = 4;
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
        echo str_repeat("=", 100) . "\n";
        echo "20 ENTRY TEST SUITE - DATE 2026-01-08 WITH OVERLAPPING & CROSS-DAY SCENARIOS\n";
        echo str_repeat("=", 100) . "\n";

        // Clear existing records for this date
        $this->clearExistingRecords();

        echo "\n📋 SCENARIO 1: Basic Day In/Day Out Entry\n";
        echo str_repeat("-", 100) . "\n";
        $this->testScenario1BasicDayInOut();

        echo "\n📋 SCENARIO 2: Overlapping Break Times\n";
        echo str_repeat("-", 100) . "\n";
        $this->testScenario2OverlappingBreaks();

        echo "\n📋 SCENARIO 3: Cross-Day Entry (Next Day Day Out)\n";
        echo str_repeat("-", 100) . "\n";
        $this->testScenario3CrossDayEntry();

        echo "\n📋 SCENARIO 4: Multiple Breaks Within Day\n";
        echo str_repeat("-", 100) . "\n";
        $this->testScenario4MultipleBreaaks();

        echo "\n📋 SCENARIO 5: Edge Cases - Midnight Crossings\n";
        echo str_repeat("-", 100) . "\n";
        $this->testScenario5MidnightCrossings();

        // Display all created records
        $this->displayAllRecords();

        // Print summary
        $this->printSummary();
    }

    private function clearExistingRecords()
    {
        $count = UserTimeClock::where('user_id', $this->userId)
            ->where('date_at', $this->testDate)
            ->delete();

        if ($count > 0) {
            echo "Cleared $count existing records for $this->testDate\n";
        }
    }

    private function testScenario1BasicDayInOut()
    {
        echo "\nTest 1.1: Day In at 08:00, Day Out at 17:00\n";
        $dayIn = $this->createEntry($this->testDate, '08:00', 'day_in');
        $dayOut = $this->createEntry($this->testDate, '17:00', 'day_out');

        if ($dayIn['status'] && $dayOut['status']) {
            echo "✓ PASS: Basic day in/out created successfully\n";
            $this->testsPassed++;
        } else {
            echo "✗ FAIL: " . ($dayIn['message'] ?? $dayOut['message']) . "\n";
            $this->testsFailed++;
        }

        echo "\nTest 1.2: Day In at 06:00, Day Out at 18:30\n";
        $dayIn2 = $this->createEntry($this->testDate, '06:00', 'day_in');
        $dayOut2 = $this->createEntry($this->testDate, '18:30', 'day_out');

        if ($dayIn2['status'] && $dayOut2['status']) {
            echo "✓ PASS: Extended day in/out created successfully\n";
            $this->testsPassed++;
        } else {
            echo "✗ FAIL: " . ($dayIn2['message'] ?? $dayOut2['message']) . "\n";
            $this->testsFailed++;
        }

        echo "\nTest 1.3: Day In at 09:00, Day Out at 16:00\n";
        $dayIn3 = $this->createEntry($this->testDate, '09:00', 'day_in');
        $dayOut3 = $this->createEntry($this->testDate, '16:00', 'day_out');

        if ($dayIn3['status'] && $dayOut3['status']) {
            echo "✓ PASS: Another day in/out created successfully\n";
            $this->testsPassed++;
        } else {
            echo "✗ FAIL: " . ($dayIn3['message'] ?? $dayOut3['message']) . "\n";
            $this->testsFailed++;
        }
    }

    private function testScenario2OverlappingBreaks()
    {
        echo "\nTest 2.1: Non-overlapping breaks (12:00-12:30)\n";
        $breakStart = $this->createEntry($this->testDate, '12:00', 'break_start');
        $breakEnd = $this->createEntry($this->testDate, '12:30', 'break_end');

        if ($breakStart['status'] && $breakEnd['status']) {
            echo "✓ PASS: Non-overlapping breaks created successfully\n";
            $this->testsPassed++;
        } else {
            echo "✗ FAIL: " . ($breakStart['message'] ?? $breakEnd['message']) . "\n";
            $this->testsFailed++;
        }

        echo "\nTest 2.2: Another break pair (14:00-14:30)\n";
        $breakStart2 = $this->createEntry($this->testDate, '14:00', 'break_start');
        $breakEnd2 = $this->createEntry($this->testDate, '14:30', 'break_end');

        if ($breakStart2['status'] && $breakEnd2['status']) {
            echo "✓ PASS: Another break pair created successfully\n";
            $this->testsPassed++;
        } else {
            echo "✗ FAIL: " . ($breakStart2['message'] ?? $breakEnd2['message']) . "\n";
            $this->testsFailed++;
        }

        echo "\nTest 2.3: Intentional overlapping break (should fail - overlapping with 14:00-14:30)\n";
        $overlapBreakStart = $this->createEntry($this->testDate, '14:15', 'break_start');
        
        if (!$overlapBreakStart['status']) {
            echo "✓ PASS: System correctly rejected overlapping break\n";
            echo "  Reason: " . $overlapBreakStart['message'] . "\n";
            $this->testsPassed++;
        } else {
            echo "✗ FAIL: System should have rejected overlapping break\n";
            $this->testsFailed++;
        }

        echo "\nTest 2.4: Valid break at 15:30\n";
        $breakStart3 = $this->createEntry($this->testDate, '15:30', 'break_start');
        $breakEnd3 = $this->createEntry($this->testDate, '16:00', 'break_end');

        if ($breakStart3['status'] && $breakEnd3['status']) {
            echo "✓ PASS: Break pair at 15:30-16:00 created successfully\n";
            $this->testsPassed++;
        } else {
            echo "✗ FAIL: " . ($breakStart3['message'] ?? $breakEnd3['message']) . "\n";
            $this->testsFailed++;
        }
    }

    private function testScenario3CrossDayEntry()
    {
        echo "\nTest 3.1: Evening shift with day out on next day (23:00-23:59)\n";
        $dayIn = $this->createEntry($this->testDate, '22:00', 'day_in');
        
        if ($dayIn['status']) {
            echo "✓ PASS: Day in at 22:00 created\n";
            $this->testsPassed++;
        } else {
            echo "✗ FAIL: " . $dayIn['message'] . "\n";
            $this->testsFailed++;
        }

        echo "\nTest 3.2: Break start at 23:30\n";
        $breakStart = $this->createEntry($this->testDate, '23:30', 'break_start');
        
        if ($breakStart['status']) {
            echo "✓ PASS: Break start at 23:30 created\n";
            $this->testsPassed++;
        } else {
            echo "✗ FAIL: " . $breakStart['message'] . "\n";
            $this->testsFailed++;
        }

        echo "\nTest 3.3: Break end at 23:45 (midnight crossing)\n";
        $breakEnd = $this->createEntry($this->testDate, '23:45', 'break_end');
        
        if ($breakEnd['status']) {
            echo "✓ PASS: Break end at 23:45 created\n";
            $this->testsPassed++;
        } else {
            echo "✗ FAIL: " . $breakEnd['message'] . "\n";
            $this->testsFailed++;
        }

        echo "\nTest 3.4: Day out at 00:30 (next day)\n";
        $dayOut = $this->createEntry($this->nextDate, '00:30', 'day_out');
        
        if ($dayOut['status']) {
            echo "✓ PASS: Day out at 00:30 (next day) created\n";
            $this->testsPassed++;
        } else {
            echo "✗ FAIL: " . $dayOut['message'] . "\n";
            $this->testsFailed++;
        }
    }

    private function testScenario4MultipleBreaaks()
    {
        echo "\nTest 4.1: Multiple breaks throughout the day\n";
        
        $times = [
            ['start' => '10:00', 'end' => '10:15'],
            ['start' => '13:00', 'end' => '13:45'],
            ['start' => '16:00', 'end' => '16:15'],
        ];

        foreach ($times as $idx => $pair) {
            echo "  Break pair " . ($idx + 1) . ": {$pair['start']}-{$pair['end']}\n";
            $start = $this->createEntry($this->testDate, $pair['start'], 'break_start');
            $end = $this->createEntry($this->testDate, $pair['end'], 'break_end');

            if ($start['status'] && $end['status']) {
                echo "    ✓ Created successfully\n";
                $this->testsPassed++;
            } else {
                echo "    ✗ Failed: " . ($start['message'] ?? $end['message']) . "\n";
                $this->testsFailed++;
            }
        }
    }

    private function testScenario5MidnightCrossings()
    {
        echo "\nTest 5.1: Late night shift starting before midnight\n";
        $dayIn = $this->createEntry($this->testDate, '20:00', 'day_in');
        
        if ($dayIn['status']) {
            echo "✓ PASS: Day in at 20:00 created\n";
            $this->testsPassed++;
        } else {
            echo "✗ FAIL: " . $dayIn['message'] . "\n";
            $this->testsFailed++;
        }

        echo "\nTest 5.2: Break starting before midnight (23:00) and ending after (00:10)\n";
        $breakStart = $this->createEntry($this->testDate, '23:00', 'break_start');
        
        if ($breakStart['status']) {
            echo "✓ PASS: Break start at 23:00 created\n";
            $this->testsPassed++;
        } else {
            echo "✗ FAIL: " . $breakStart['message'] . "\n";
            $this->testsFailed++;
        }

        $breakEnd = $this->createEntry($this->testDate, '23:15', 'break_end');
        
        if ($breakEnd['status']) {
            echo "✓ PASS: Break end at 23:15 created\n";
            $this->testsPassed++;
        } else {
            echo "✗ FAIL: " . $breakEnd['message'] . "\n";
            $this->testsFailed++;
        }

        echo "\nTest 5.3: Day out at 01:00 (next day midnight crossing)\n";
        $dayOut = $this->createEntry($this->nextDate, '01:00', 'day_out');
        
        if ($dayOut['status']) {
            echo "✓ PASS: Day out at 01:00 (next day) created\n";
            $this->testsPassed++;
        } else {
            echo "✗ FAIL: " . $dayOut['message'] . "\n";
            $this->testsFailed++;
        }
    }

    private function createEntry($date, $time, $type)
    {
        try {
            $data = [
                'shop_id' => $this->shopId,
                'user_id' => $this->userId,
                'clock_date' => $date,
                'time' => $time,
                'type' => $type,
                'comment' => "Test entry - $type at $time",
                'updated_from' => 'T',
                'buffer_time' => 3,
                'shift_start' => '08:00',
                'shift_end' => '23:00',
                'created_from' => 'T',
            ];

            // Call the appropriate method based on type
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
                return ['status' => true, 'record' => $result['record'] ?? null];
            } else {
                return ['status' => false, 'message' => $result['message'] ?? 'Unknown error'];
            }
        } catch (\Exception $e) {
            return ['status' => false, 'message' => $e->getMessage()];
        }
    }

    private function displayAllRecords()
    {
        echo "\n";
        echo str_repeat("=", 100) . "\n";
        echo "ALL CREATED RECORDS\n";
        echo str_repeat("=", 100) . "\n";

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
        echo str_repeat("-", 100) . "\n";

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

        echo str_repeat("=", 100) . "\n";
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
        echo str_repeat("=", 100) . "\n";
        echo "TEST SUMMARY\n";
        echo str_repeat("=", 100) . "\n";
        echo "Total Tests: " . ($this->testsPassed + $this->testsFailed) . "\n";
        echo "Passed: " . $this->testsPassed . " ✓\n";
        echo "Failed: " . $this->testsFailed . " ✗\n";

        $total = $this->testsPassed + $this->testsFailed;
        $passRate = $total > 0 ? round(($this->testsPassed / $total) * 100, 2) : 0;
        echo "Pass Rate: $passRate%\n";
        echo str_repeat("=", 100) . "\n\n";
    }
}

// Run tests
$tester = new TestDate8Entries();
$tester->run();
