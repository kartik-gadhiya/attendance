<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Services\UserTimeClockService;
use App\Models\UserTimeClock;

echo "=== Comprehensive Edit Validation Tests for User 4, Date 2026-01-01 ===\n\n";

$service = new UserTimeClockService();

// First, display current state
echo "Current Records (chronologically):\n";
$events = UserTimeClock::where('user_id', 4)
    ->where('date_at', '2026-01-01')
    ->orderBy('formated_date_time')
    ->get();

foreach ($events as $event) {
    echo sprintf(
        "ID %2d: %s %-12s (time_at: %s)\n",
        $event->id,
        $event->formated_date_time,
        $event->type,
        $event->time_at
    );
}

echo "\n=== ISSUE IDENTIFICATION ===\n";
echo "Looking at the sequence, we can see TWO shifts:\n\n";

echo "SHIFT 1:\n";
echo "  ID 22: 03:50 day_in\n";
echo "  ID 23: 04:50 break_start\n";
echo "  ID 24: 05:50 break_end\n";
echo "  ID 26: 05:55 break_start\n";
echo "  ID 27: 06:30 break_end\n";
echo "  ID 25: 06:50 day_out ✓\n\n";

echo "SHIFT 2 (Problem detected!):\n";
echo "  ID 29: 11:00 day_out ❌ WRONG - day_out comes FIRST!\n";
echo "  ID 28: 12:30 day_in\n";
echo "  ID 30: 14:30 break_start\n";
echo "  ID 31: 15:30 break_end\n";
echo "  Missing: day_out should be AFTER 15:30, not at 11:00!\n\n";

echo "Root Cause: ID 29 was originally 00:30 (next day), which would correctly be\n";
echo "after 15:30. But when edited to 11:00, it appears chronologically BEFORE the\n";
echo "day_in of its own shift!\n\n";

echo "=== TEST CASES ===\n\n";

$testResults = [];

// Test 1: Try to edit day_out (ID 29) to 10:00 (even earlier) - should FAIL
echo "Test 1: Edit day_out (ID 29) from 11:00 to 10:00\n";
echo "Expected: REJECT (day_out cannot come before its day_in at 12:30)\n";
$result1 = $service->updateEvent(29, ['time' => '10:00']);
echo "Actual: " . ($result1['status'] ? "❌ ACCEPTED (BUG!)" : "✓ REJECTED") . "\n";
echo "Message: " . $result1['message'] . "\n";
$testResults[] = ['test' => 'Test 1', 'expected_reject' => true, 'actual_rejected' => !$result1['status']];
echo "\n";

// Test 2: Try to edit day_out (ID 29) to 16:00 (after break_end) - should SUCCEED
echo "Test 2: Edit day_out (ID 29) from 11:00 to 16:00\n";
echo "Expected: ACCEPT (16:00 is after break_end at 15:30)\n";
$result2 = $service->updateEvent(29, ['time' => '16:00']);
echo "Actual: " . ($result2['status'] ? "✓ ACCEPTED" : "❌ REJECTED (BUG!)") . "\n";
echo "Message: " . $result2['message'] . "\n";
$testResults[] = ['test' => 'Test 2', 'expected_reject' => false, 'actual_rejected' => !$result2['status']];
echo "\n";

// Test 3: Try to edit day_in (ID 28) to 13:00 (after its current breaks) - should FAIL
echo "Test 3: Edit day_in (ID 28) from 12:30 to 13:00\n";
echo "Expected: REJECT (day_in cannot come after its breaks)\n";
$result3 = $service->updateEvent(28, ['time' => '13:00']);
echo "Actual: " . ($result3['status'] ? "❌ ACCEPTED (BUG!)" : "✓ REJECTED") . "\n";
echo "Message: " . $result3['message'] . "\n";
$testResults[] = ['test' => 'Test 3', 'expected_reject' => true, 'actual_rejected' => !$result3['status']];
echo "\n";

// Test 4: Try to edit break_end (ID 31) to 14:00 (before break_start) - should FAIL
echo "Test 4: Edit break_end (ID 31) from 15:30 to 14:00\n";
echo "Expected: REJECT (break_end must be after break_start at 14:30)\n";
$result4 = $service->updateEvent(31, ['time' => '14:00']);
echo "Actual: " . ($result4['status'] ? "❌ ACCEPTED (BUG!)" : "✓ REJECTED") . "\n";
echo "Message: " . $result4['message'] . "\n";
$testResults[] = ['test' => 'Test 4', 'expected_reject' => true, 'actual_rejected' => !$result4['status']];
echo "\n";

// Test 5: Try to edit break_start (ID 30) to 16:00 (after break_end) - should FAIL
echo "Test 5: Edit break_start (ID 30) from 14:30 to 16:00\n";
echo "Expected: REJECT (break_start cannot be after its break_end at 15:30)\n";
$result5 = $service->updateEvent(30, ['time' => '16:00']);
echo "Actual: " . ($result5['status'] ? "❌ ACCEPTED (BUG!)" : "✓ REJECTED") . "\n";
echo "Message: " . $result5['message'] . "\n";
$testResults[] = ['test' => 'Test 5', 'expected_reject' => true, 'actual_rejected' => !$result5['status']];
echo "\n";

// Test 6: Valid edit - move day_in (ID 28) to 12:00 (still before breaks) - should SUCCEED
echo "Test 6: Edit day_in (ID 28) from 12:30 to 12:00\n";
echo "Expected: ACCEPT (12:00 is before breaks, chronologically valid)\n";
$result6 = $service->updateEvent(28, ['time' => '12:00']);
echo "Actual: " . ($result6['status'] ? "✓ ACCEPTED" : "❌ REJECTED (BUG!)") . "\n";
echo "Message: " . $result6['message'] . "\n";
$testResults[] = ['test' => 'Test 6', 'expected_reject' => false, 'actual_rejected' => !$result6['status']];
echo "\n";

// Summary
echo "=== TEST SUMMARY ===\n";
$passed = 0;
$failed = 0;

foreach ($testResults as $result) {
    $correct = $result['expected_reject'] === $result['actual_rejected'];
    if ($correct) {
        $passed++;
        echo "✓ {$result['test']}: PASS\n";
    } else {
        $failed++;
        echo "❌ {$result['test']}: FAIL\n";
    }
}

echo "\nTotal: {$passed} passed, {$failed} failed out of " . count($testResults) . " tests\n";

if ($failed > 0) {
    echo "\n🚨 BUGS DETECTED - Edit validation needs fixing!\n";
} else {
    echo "\n✅ All tests passed - Edit validation working correctly!\n";
}

// Display final state
echo "\n=== Final Record State ===\n";
$finalEvents = UserTimeClock::where('user_id', 4)
    ->where('date_at', '2026-01-01')
    ->orderBy('formated_date_time')
    ->get();

foreach ($finalEvents as $event) {
    echo sprintf(
        "ID %2d: %s %-12s\n",
        $event->id,
        $event->formated_date_time,
        $event->type
    );
}
