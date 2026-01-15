<?php

require 'vendor/autoload.php';

// Initialize Laravel application
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\UserTimeClock;
use App\Services\UserTimeClockService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class EditOverlapTester
{
    protected UserTimeClockService $service;
    protected int $userId = 4;
    protected string $testDate = '2026-01-07';
    protected int $testCount = 0;
    protected int $passCount = 0;
    protected $originalRecords = [];

    public function __construct()
    {
        $this->service = new UserTimeClockService('en');
    }

    public function run()
    {
        echo "\n" . str_repeat("=", 100) . "\n";
        echo "EDIT OPERATION - TIME OVERLAP TEST SUITE\n";
        echo "User ID: {$this->userId} | Date: {$this->testDate}\n";
        echo str_repeat("=", 100) . "\n";

        // Fetch and display original records
        $this->displayOriginalRecords();

        // Test scenarios
        echo "\n\n--- TEST SCENARIO 1: Valid Edit - Change Time to Non-Overlapping Position ---\n";
        $this->testValidEdit();

        echo "\n\n--- TEST SCENARIO 2: Invalid Edit - Move to Overlapping Position ---\n";
        $this->testOverlappingEdit();

        echo "\n\n--- TEST SCENARIO 3: Edge Case - Edit to Same Time as Another Event ---\n";
        $this->testDuplicateTimeEdit();

        echo "\n\n--- TEST SCENARIO 4: Break Range Edit - Ensure No Overlap with Other Breaks ---\n";
        $this->testBreakRangeEdit();

        echo "\n\n--- TEST SCENARIO 5: Multiple Overlaps - Complex Edit Scenarios ---\n";
        $this->testComplexOverlapScenarios();

        // Summary
        $this->printSummary();
    }

    protected function displayOriginalRecords()
    {
        echo "\n📋 ORIGINAL RECORDS:\n";
        echo str_repeat("-", 100) . "\n";

        $records = UserTimeClock::where('user_id', $this->userId)
            ->where('date_at', $this->testDate)
            ->orderBy('time_at')
            ->get();

        $this->originalRecords = $records;

        echo sprintf(
            "%-6s | %-12s | %-10s | %-20s | %-20s\n",
            "ID",
            "Type",
            "Time",
            "Date_Time",
            "Formated_Date_Time"
        );
        echo str_repeat("-", 100) . "\n";

        foreach ($records as $record) {
            echo sprintf(
                "%-6d | %-12s | %-10s | %-20s | %-20s\n",
                $record->id,
                $record->type,
                $record->time_at->format('H:i'),
                $record->date_time,
                $record->formated_date_time
            );
        }
    }

    protected function testValidEdit()
    {
        // Find a break_start that can be safely moved
        $breakStart = UserTimeClock::where('user_id', $this->userId)
            ->where('date_at', $this->testDate)
            ->where('type', 'break_start')
            ->where('id', 185) // Specific record
            ->first();

        if (!$breakStart) {
            echo "✗ Could not find break_start with ID 185\n";
            return;
        }

        $currentTime = $breakStart->time_at->format('H:i');
        $newTime = '12:06'; // Change from 12:05 to 12:06 (before current break_end at 12:07)

        echo "\nAttempt 1: Move break_start (ID 185) from {$currentTime} to {$newTime}\n";

        $data = [
            'shop_id' => $breakStart->shop_id,
            'user_id' => $breakStart->user_id,
            'clock_date' => $breakStart->date_at->format('Y-m-d'),
            'time' => $newTime,
            'type' => $breakStart->type,
            'comment' => 'Edit test - valid move',
            'updated_from' => 'T',
        ];

        $result = $this->service->updateEvent($breakStart->id, $data);
        $this->assertEdit($result['status'] === true, "Valid edit - move to non-overlapping time", $result);

        // Restore original
        if ($result['status']) {
            $this->restoreRecord($breakStart->id, $breakStart->time_at->format('H:i'));
        }
    }

    protected function testOverlappingEdit()
    {
        // Try to move a break_start to overlap with its break_end
        $breakStart = UserTimeClock::where('user_id', $this->userId)
            ->where('date_at', $this->testDate)
            ->where('type', 'break_start')
            ->where('id', 186) // Has break_end at 17:00
            ->first();

        if (!$breakStart) {
            echo "✗ Could not find break_start with ID 186\n";
            return;
        }

        $currentTime = $breakStart->time_at->format('H:i');
        $newTime = '17:05'; // Try to move AFTER its break_end at 17:00

        echo "\nAttempt 1: Try to move break_start (ID 186) from {$currentTime} to {$newTime} (AFTER its break_end)\n";

        $data = [
            'shop_id' => $breakStart->shop_id,
            'user_id' => $breakStart->user_id,
            'clock_date' => $breakStart->date_at->format('Y-m-d'),
            'time' => $newTime,
            'type' => $breakStart->type,
            'comment' => 'Edit test - overlap attempt',
            'updated_from' => 'T',
        ];

        $result = $this->service->updateEvent($breakStart->id, $data);
        $this->assertEdit(
            $result['status'] === false,
            "Invalid edit - reject overlap (break_start after break_end)",
            $result
        );

        // Try to move a day_in to overlap with a break
        $dayIn = UserTimeClock::where('user_id', $this->userId)
            ->where('date_at', $this->testDate)
            ->where('type', 'day_in')
            ->where('id', 184)
            ->first();

        if ($dayIn) {
            echo "\nAttempt 2: Try to move day_in (ID 184) from 11:10 to 12:06 (overlaps with break)\n";
            echo "  Existing break: 12:05-12:07\n";

            $data = [
                'shop_id' => $dayIn->shop_id,
                'user_id' => $dayIn->user_id,
                'clock_date' => $dayIn->date_at->format('Y-m-d'),
                'time' => '12:06',
                'type' => $dayIn->type,
                'comment' => 'Edit test - overlap with break',
                'updated_from' => 'T',
            ];

            $result = $this->service->updateEvent($dayIn->id, $data);
            $this->assertEdit(
                $result['status'] === false,
                "Invalid edit - reject day_in overlapping with break",
                $result
            );
        }
    }

    protected function testDuplicateTimeEdit()
    {
        // Try to move a record to the exact time of another record
        $record1 = UserTimeClock::where('user_id', $this->userId)
            ->where('date_at', $this->testDate)
            ->where('id', 185)
            ->first();

        $record2 = UserTimeClock::where('user_id', $this->userId)
            ->where('date_at', $this->testDate)
            ->where('id', 186)
            ->first();

        if ($record1 && $record2) {
            $time2 = $record2->time_at->format('H:i');

            echo "\nAttempt: Move record (ID {$record1->id}) to same time as record (ID {$record2->id}) at {$time2}\n";

            $data = [
                'shop_id' => $record1->shop_id,
                'user_id' => $record1->user_id,
                'clock_date' => $record1->date_at->format('Y-m-d'),
                'time' => $time2,
                'type' => $record1->type,
                'comment' => 'Duplicate time test',
                'updated_from' => 'T',
            ];

            $result = $this->service->updateEvent($record1->id, $data);
            $this->assertEdit(
                $result['status'] === false,
                "Invalid edit - reject duplicate timestamp",
                $result
            );
        }
    }

    protected function testBreakRangeEdit()
    {
        // Edit a break to not overlap with another break's range
        echo "\nTest 1: Edit break_end (ID 196) to a non-overlapping time\n";
        echo "  Current breaks: 12:05-12:07, 12:10-17:00, 17:31-18:00+\n";

        $breakEnd = UserTimeClock::where('user_id', $this->userId)
            ->where('date_at', $this->testDate)
            ->where('id', 196)
            ->first();

        if ($breakEnd) {
            // Try to move to 00:32 (after paired break_start at 00:30, still in midnight range)
            $data = [
                'shop_id' => $breakEnd->shop_id,
                'user_id' => $breakEnd->user_id,
                'clock_date' => $breakEnd->date_at->format('Y-m-d'),
                'time' => '00:32',
                'type' => $breakEnd->type,
                'comment' => 'Break range edit test',
                'updated_from' => 'T',
            ];

            $result = $this->service->updateEvent($breakEnd->id, $data);
            $this->assertEdit($result['status'] === true, "Edit break_end to non-overlapping position", $result);

            if ($result['status']) {
                $this->restoreRecord($breakEnd->id, $breakEnd->time_at->format('H:i'));
            }
        }

        // Try to move into an existing break's range
        echo "\nTest 2: Try to move day_out into break range\n";

        $dayOut = UserTimeClock::where('user_id', $this->userId)
            ->where('date_at', $this->testDate)
            ->where('id', 189) // 18:00
            ->first();

        if ($dayOut) {
            echo "  Try to move day_out from 18:00 to 17:35 (inside 17:31-18:14 break range)\n";

            $data = [
                'shop_id' => $dayOut->shop_id,
                'user_id' => $dayOut->user_id,
                'clock_date' => $dayOut->date_at->format('Y-m-d'),
                'time' => '17:35',
                'type' => $dayOut->type,
                'comment' => 'Overlap test',
                'updated_from' => 'T',
            ];

            $result = $this->service->updateEvent($dayOut->id, $data);
            $this->assertEdit(
                $result['status'] === false,
                "Reject day_out moved into break range",
                $result
            );
        }
    }

    protected function testComplexOverlapScenarios()
    {
        echo "\nComplex Scenario 1: Chain of edits without overlap\n";

        // Get multiple records
        $records = UserTimeClock::where('user_id', $this->userId)
            ->where('date_at', $this->testDate)
            ->where('id', '>=', 194)
            ->where('id', '<=', 197)
            ->orderBy('time_at')
            ->get();

        foreach ($records->take(1) as $record) {
            if ($record->type === 'day_in') {
                echo "  Attempt to move day_in (ID {$record->id}) from {$record->time_at->format('H:i')} to 00:50\n";

                $data = [
                    'shop_id' => $record->shop_id,
                    'user_id' => $record->user_id,
                    'clock_date' => $record->date_at->format('Y-m-d'),
                    'time' => '00:50',
                    'type' => $record->type,
                    'comment' => 'Complex chain test',
                    'updated_from' => 'T',
                ];

                $result = $this->service->updateEvent($record->id, $data);
                $this->assertEdit($result['status'] === true, "Move day_in to valid position", $result);

                if ($result['status']) {
                    $this->restoreRecord($record->id, $record->time_at->format('H:i'));
                }
            }
        }

        echo "\nComplex Scenario 2: Edit break_start and verify it doesn't create overlap with break_end\n";

        $breakStart = UserTimeClock::where('user_id', $this->userId)
            ->where('date_at', $this->testDate)
            ->where('id', 191)
            ->first();

        if ($breakStart) {
            echo "  Current: break_start at 23:45, break_end at 23:46\n";
            echo "  Try to move break_start to 23:44\n";

            $data = [
                'shop_id' => $breakStart->shop_id,
                'user_id' => $breakStart->user_id,
                'clock_date' => $breakStart->date_at->format('Y-m-d'),
                'time' => '23:44',
                'type' => $breakStart->type,
                'comment' => 'Move break_start test',
                'updated_from' => 'T',
            ];

            $result = $this->service->updateEvent($breakStart->id, $data);
            $this->assertEdit($result['status'] === true, "Move break_start before break_end", $result);

            if ($result['status']) {
                $this->restoreRecord($breakStart->id, $breakStart->time_at->format('H:i'));
            }
        }
    }

    protected function restoreRecord(int $id, string $originalTime)
    {
        $record = UserTimeClock::find($id);
        if ($record) {
            $record->update(['time_at' => $originalTime]);
            $record->refresh();
        }
    }

    protected function assertEdit(bool $condition, string $testName, array $result)
    {
        $this->testCount++;

        if ($condition) {
            $this->passCount++;
            echo "✓ PASS: {$testName}\n";
        } else {
            echo "✗ FAIL: {$testName}\n";
            if (isset($result['message'])) {
                echo "  Reason: {$result['message']}\n";
            }
        }
    }

    protected function printSummary()
    {
        echo "\n" . str_repeat("=", 100) . "\n";
        echo "TEST SUMMARY\n";
        echo str_repeat("=", 100) . "\n";
        echo "Total Tests: {$this->testCount}\n";
        echo "Passed: {$this->passCount}\n";
        echo "Failed: " . ($this->testCount - $this->passCount) . "\n";
        echo "Pass Rate: " . number_format(($this->passCount / $this->testCount * 100), 2) . "%\n";
        echo str_repeat("=", 100) . "\n\n";
    }
}

// Run tests
$tester = new EditOverlapTester();
$tester->run();
