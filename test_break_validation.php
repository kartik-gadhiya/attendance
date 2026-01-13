<?php

require 'vendor/autoload.php';

// Initialize Laravel application
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\UserTimeClock;
use App\Services\UserTimeClockService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class BreakValidationTester
{
    protected UserTimeClockService $service;
    protected int $shopId = 1;
    protected int $userId = 12; // Use existing user
    protected int $testCount = 0;
    protected int $passedCount = 0;

    public function __construct()
    {
        $this->service = new UserTimeClockService('en');
    }

    public function run()
    {
        echo "\n" . str_repeat("=", 80) . "\n";
        echo "BREAK VALIDATION TEST SUITE\n";
        echo str_repeat("=", 80) . "\n";

        // Clean up test data
        $this->cleanupTestData();

        // Test Scenario 1: Basic break within same-day shift
        echo "\n\n--- TEST SCENARIO 1: Basic Break (8AM-11PM Shift) ---\n";
        $this->testBasicBreak();

        // Test Scenario 2: Multiple breaks in one shift
        echo "\n\n--- TEST SCENARIO 2: Multiple Breaks in One Shift ---\n";
        $this->testMultipleBreaks();

        // Test Scenario 3: Break crossing midnight
        echo "\n\n--- TEST SCENARIO 3: Break Crossing Midnight (11:30 PM - 12:30 AM) ---\n";
        $this->testMidnightBreak();

        // Test Scenario 4: Complex shift with multiple shifts and breaks (Sample Data)
        echo "\n\n--- TEST SCENARIO 4: Complex Daily Schedule (Multiple Shifts & Breaks) ---\n";
        $this->testComplexSchedule();

        // Test Scenario 5: Edge cases
        echo "\n\n--- TEST SCENARIO 5: Edge Cases & Validation Errors ---\n";
        $this->testEdgeCases();

        // Test Scenario 6: Dynamic shift times
        echo "\n\n--- TEST SCENARIO 6: Dynamic Shift Times ---\n";
        $this->testDynamicShifts();

        // Summary
        $this->printSummary();
    }

    protected function testBasicBreak()
    {
        $testDate = '2026-01-01';
        $this->cleanupDate($testDate);

        $tests = [
            ['type' => 'day_in', 'time' => '08:00', 'expected' => true, 'desc' => 'Day In at 8:00 AM'],
            ['type' => 'break_start', 'time' => '09:00', 'expected' => true, 'desc' => 'Break Start at 9:00 AM'],
            ['type' => 'break_end', 'time' => '10:00', 'expected' => true, 'desc' => 'Break End at 10:00 AM (CRITICAL TEST)'],
            ['type' => 'day_out', 'time' => '23:00', 'expected' => true, 'desc' => 'Day Out at 11:00 PM'],
        ];

        foreach ($tests as $test) {
            $result = $this->addEntry($testDate, $test['type'], $test['time'], '08:00', '23:00');
            $this->assertTest(
                $result['status'] === $test['expected'],
                $test['desc'],
                $result
            );
        }
    }

    protected function testMultipleBreaks()
    {
        $testDate = '2026-01-02';
        $this->cleanupDate($testDate);

        $tests = [
            ['type' => 'day_in', 'time' => '08:00', 'expected' => true, 'desc' => 'Day In at 8:00 AM'],
            ['type' => 'break_start', 'time' => '09:00', 'expected' => true, 'desc' => 'Break 1 Start at 9:00 AM'],
            ['type' => 'break_end', 'time' => '09:30', 'expected' => true, 'desc' => 'Break 1 End at 9:30 AM'],
            ['type' => 'break_start', 'time' => '12:00', 'expected' => true, 'desc' => 'Break 2 Start at 12:00 PM'],
            ['type' => 'break_end', 'time' => '13:00', 'expected' => true, 'desc' => 'Break 2 End at 1:00 PM'],
            ['type' => 'break_start', 'time' => '16:00', 'expected' => true, 'desc' => 'Break 3 Start at 4:00 PM'],
            ['type' => 'break_end', 'time' => '16:30', 'expected' => true, 'desc' => 'Break 3 End at 4:30 PM'],
            ['type' => 'day_out', 'time' => '23:00', 'expected' => true, 'desc' => 'Day Out at 11:00 PM'],
        ];

        foreach ($tests as $test) {
            $result = $this->addEntry($testDate, $test['type'], $test['time'], '08:00', '23:00');
            $this->assertTest(
                $result['status'] === $test['expected'],
                $test['desc'],
                $result
            );
        }
    }

    protected function testMidnightBreak()
    {
        $testDate = '2026-01-03';
        $this->cleanupDate($testDate);

        $tests = [
            ['type' => 'day_in', 'time' => '22:00', 'expected' => true, 'desc' => 'Day In at 10:00 PM'],
            ['type' => 'break_start', 'time' => '23:30', 'expected' => true, 'desc' => 'Break Start at 11:30 PM'],
            ['type' => 'break_end', 'time' => '00:30', 'expected' => true, 'desc' => 'Break End at 12:30 AM (MIDNIGHT CROSSING)'],
            ['type' => 'day_out', 'time' => '01:00', 'expected' => true, 'desc' => 'Day Out at 1:00 AM (NEXT DAY)'],
        ];

        foreach ($tests as $test) {
            $result = $this->addEntry($testDate, $test['type'], $test['time'], '22:00', '02:00');
            $this->assertTest(
                $result['status'] === $test['expected'],
                $test['desc'],
                $result
            );
        }
    }

    protected function testComplexSchedule()
    {
        // Sample data from requirements
        $testDate = '2026-01-04';
        $this->cleanupDate($testDate);

        $tests = [
            ['type' => 'day_in', 'time' => '08:00', 'shift_start' => '08:00', 'shift_end' => '23:00', 'expected' => true, 'desc' => 'Shift 1: Day In at 8:00 AM'],
            ['type' => 'break_start', 'time' => '09:00', 'shift_start' => '08:00', 'shift_end' => '23:00', 'expected' => true, 'desc' => 'Shift 1: Break Start at 9:00 AM'],
            ['type' => 'break_end', 'time' => '10:00', 'shift_start' => '08:00', 'shift_end' => '23:00', 'expected' => true, 'desc' => 'Shift 1: Break End at 10:00 AM'],
            ['type' => 'day_out', 'time' => '12:00', 'shift_start' => '08:00', 'shift_end' => '23:00', 'expected' => true, 'desc' => 'Shift 1: Day Out at 12:00 PM'],

            ['type' => 'day_in', 'time' => '13:00', 'shift_start' => '08:00', 'shift_end' => '23:00', 'expected' => true, 'desc' => 'Shift 2: Day In at 1:00 PM'],
            ['type' => 'day_out', 'time' => '14:00', 'shift_start' => '08:00', 'shift_end' => '23:00', 'expected' => true, 'desc' => 'Shift 2: Day Out at 2:00 PM'],

            ['type' => 'day_in', 'time' => '15:00', 'shift_start' => '08:00', 'shift_end' => '23:00', 'expected' => true, 'desc' => 'Shift 3: Day In at 3:00 PM'],
            ['type' => 'break_start', 'time' => '16:00', 'shift_start' => '08:00', 'shift_end' => '23:00', 'expected' => true, 'desc' => 'Shift 3: Break Start at 4:00 PM'],
            ['type' => 'break_end', 'time' => '17:00', 'shift_start' => '08:00', 'shift_end' => '23:00', 'expected' => true, 'desc' => 'Shift 3: Break End at 5:00 PM'],
            ['type' => 'break_start', 'time' => '20:00', 'shift_start' => '08:00', 'shift_end' => '23:00', 'expected' => true, 'desc' => 'Shift 3: Break Start at 8:00 PM'],
            ['type' => 'break_end', 'time' => '21:00', 'shift_start' => '08:00', 'shift_end' => '23:00', 'expected' => true, 'desc' => 'Shift 3: Break End at 9:00 PM'],
            ['type' => 'break_start', 'time' => '23:30', 'shift_start' => '08:00', 'shift_end' => '23:00', 'expected' => true, 'desc' => 'Shift 3: Break Start at 11:30 PM'],
            ['type' => 'break_end', 'time' => '00:30', 'shift_start' => '08:00', 'shift_end' => '23:00', 'expected' => true, 'desc' => 'Shift 3: Break End at 12:30 AM (NEXT DAY - CRITICAL)'],
            ['type' => 'day_out', 'time' => '01:00', 'shift_start' => '08:00', 'shift_end' => '23:00', 'expected' => true, 'desc' => 'Shift 3: Day Out at 1:00 AM (NEXT DAY)'],
        ];

        foreach ($tests as $test) {
            $shift_start = $test['shift_start'] ?? '08:00';
            $shift_end = $test['shift_end'] ?? '23:00';
            $result = $this->addEntry($testDate, $test['type'], $test['time'], $shift_start, $shift_end);
            $this->assertTest(
                $result['status'] === $test['expected'],
                $test['desc'],
                $result
            );
        }
    }

    protected function testEdgeCases()
    {
        $testDate = '2026-01-05';
        $this->cleanupDate($testDate);

        // First establish a valid break
        $this->addEntry($testDate, 'day_in', '08:00', '08:00', '23:00');
        $this->addEntry($testDate, 'break_start', '09:00', '08:00', '23:00');

        $tests = [
            ['type' => 'break_end', 'time' => '08:59', 'expected' => false, 'desc' => 'Break End BEFORE Break Start (should fail)'],
            ['type' => 'break_end', 'time' => '09:00', 'expected' => false, 'desc' => 'Break End AT Break Start (should fail)'],
            ['type' => 'break_end', 'time' => '09:01', 'expected' => true, 'desc' => 'Break End just after Break Start (should pass)'],
        ];

        foreach ($tests as $test) {
            // Clean between tests
            if ($test['expected'] === false) {
                $result = $this->addEntry($testDate, $test['type'], $test['time'], '08:00', '23:00');
            } else {
                // For passing tests, clean and rebuild
                $this->cleanupDate($testDate);
                $this->addEntry($testDate, 'day_in', '08:00', '08:00', '23:00');
                $this->addEntry($testDate, 'break_start', '09:00', '08:00', '23:00');
                $result = $this->addEntry($testDate, $test['type'], $test['time'], '08:00', '23:00');
            }
            $this->assertTest(
                $result['status'] === $test['expected'],
                $test['desc'],
                $result
            );
        }
    }

    protected function testDynamicShifts()
    {
        $testDate = '2026-01-06';
        $this->cleanupDate($testDate);

        // Early morning shift (5:00 AM - 9:00 PM)
        echo "\n  Sub-test: Early Morning Shift (5:00 AM - 9:00 PM)\n";
        $tests1 = [
            ['type' => 'day_in', 'time' => '05:00', 'expected' => true, 'desc' => 'Day In at 5:00 AM'],
            ['type' => 'break_start', 'time' => '07:00', 'expected' => true, 'desc' => 'Break Start at 7:00 AM'],
            ['type' => 'break_end', 'time' => '08:00', 'expected' => true, 'desc' => 'Break End at 8:00 AM'],
            ['type' => 'day_out', 'time' => '21:00', 'expected' => true, 'desc' => 'Day Out at 9:00 PM'],
        ];

        foreach ($tests1 as $test) {
            $result = $this->addEntry($testDate, $test['type'], $test['time'], '05:00', '21:00');
            $this->assertTest(
                $result['status'] === $test['expected'],
                '  ' . $test['desc'],
                $result
            );
        }

        // Night shift crossing midnight (5:00 PM - 2:00 AM next day)
        $testDate2 = '2026-01-07';
        $this->cleanupDate($testDate2);
        echo "\n  Sub-test: Night Shift Crossing Midnight (5:00 PM - 2:00 AM)\n";
        $tests2 = [
            ['type' => 'day_in', 'time' => '17:00', 'expected' => true, 'desc' => 'Day In at 5:00 PM'],
            ['type' => 'break_start', 'time' => '21:00', 'expected' => true, 'desc' => 'Break Start at 9:00 PM'],
            ['type' => 'break_end', 'time' => '22:00', 'expected' => true, 'desc' => 'Break End at 10:00 PM'],
            ['type' => 'break_start', 'time' => '23:30', 'expected' => true, 'desc' => 'Break Start at 11:30 PM'],
            ['type' => 'break_end', 'time' => '00:30', 'expected' => true, 'desc' => 'Break End at 12:30 AM (NEXT DAY)'],
            ['type' => 'day_out', 'time' => '02:00', 'expected' => true, 'desc' => 'Day Out at 2:00 AM (NEXT DAY)'],
        ];

        foreach ($tests2 as $test) {
            $result = $this->addEntry($testDate2, $test['type'], $test['time'], '17:00', '02:00');
            $this->assertTest(
                $result['status'] === $test['expected'],
                '  ' . $test['desc'],
                $result
            );
        }
    }

    protected function addEntry(string $date, string $type, string $time, string $shiftStart, string $shiftEnd): array
    {
        $data = [
            'shop_id' => $this->shopId,
            'user_id' => $this->userId,
            'clock_date' => $date,
            'time' => $time,
            'shift_start' => $shiftStart,
            'shift_end' => $shiftEnd,
            'type' => $type,
            'buffer_time' => 3,
            'created_from' => 'T',
        ];

        if ($type === 'day_in') {
            return $this->service->dayInAdd($data);
        } elseif ($type === 'day_out') {
            return $this->service->dayOutAdd($data);
        } elseif ($type === 'break_start') {
            return $this->service->breakStartAdd($data);
        } elseif ($type === 'break_end') {
            return $this->service->breakEndAdd($data);
        }

        return ['status' => false, 'message' => 'Invalid type'];
    }

    protected function assertTest(bool $condition, string $testName, array $result): void
    {
        $this->testCount++;

        if ($condition) {
            $this->passedCount++;
            echo "✓ PASS: {$testName}\n";
        } else {
            echo "✗ FAIL: {$testName}\n";
            echo "  Error: {$result['message']}\n";
        }
    }

    protected function cleanupTestData(): void
    {
        UserTimeClock::where('user_id', $this->userId)->delete();
    }

    protected function cleanupDate(string $date): void
    {
        UserTimeClock::where('user_id', $this->userId)
            ->where('date_at', $date)
            ->delete();
    }

    protected function printSummary(): void
    {
        echo "\n" . str_repeat("=", 80) . "\n";
        echo "TEST SUMMARY\n";
        echo str_repeat("=", 80) . "\n";
        echo "Total Tests: {$this->testCount}\n";
        echo "Passed: {$this->passedCount}\n";
        echo "Failed: " . ($this->testCount - $this->passedCount) . "\n";
        echo "Pass Rate: " . number_format(($this->passedCount / $this->testCount * 100), 2) . "%\n";
        echo str_repeat("=", 80) . "\n\n";
    }
}

// Run tests
$tester = new BreakValidationTester();
$tester->run();
