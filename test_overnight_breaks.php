<?php
/**
 * Test: Overnight Shift Breaks (Midnight Crossing)
 * 
 * Scenario:
 * - Day In: 23:00 (2026-01-11)
 * - Day Out: 02:00 AM (2026-01-12)
 * - Break Start: 23:30 (2026-01-11) ✓ Should work
 * - Break End: 00:30 AM (2026-01-12) ✓ Should work
 * 
 * This test verifies that breaks crossing midnight are properly validated.
 */

require_once __DIR__ . '/vendor/autoload.php';

use App\Services\UserTimeClockService;
use App\Models\UserTimeClock;
use Illuminate\Foundation\Application;

// Load Laravel
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "\n" . "=".str_repeat("=", 78) . "\n";
echo "Overnight Shift with Midnight-Crossing Breaks Test\n";
echo "=".str_repeat("=", 78) . "\n\n";

$testsPassed = 0;
$testsFailed = 0;

// Clear any existing test data for user 5
UserTimeClock::where('user_id', 5)->where('date_at', '>=', '2026-01-11')->where('date_at', '<=', '2026-01-12')->delete();

echo "TEST 1: Creating Day In at 23:00 on 2026-01-11\n";
echo "-".str_repeat("-", 78)."\n";

$service = new UserTimeClockService('en');

// Step 1: Create Day In at 23:00
$dayInData = [
    'shop_id' => 1,
    'user_id' => 5,
    'clock_date' => '2026-01-11',
    'time' => '23:00',
    'type' => 'day_in',
    'shift_start' => '08:00',
    'shift_end' => '23:00',
    'buffer_time' => 3,
    'created_from' => 'B',
];

$result = $service->dayInAdd($dayInData);
if ($result['status']) {
    $dayInRecord = $result['data'];
    echo "✓ Created Day In at 23:00 (2026-01-11)\n";
    echo "  Record ID: {$dayInRecord->id}, date_at: {$dayInRecord->date_at}\n";
    echo "  formated_date_time: {$dayInRecord->formated_date_time}\n";
    $testsPassed++;
} else {
    echo "✗ FAILED to create Day In: {$result['message']}\n";
    $testsFailed++;
    exit(1);
}

echo "\n";
echo "TEST 2: Creating Day Out at 02:00 AM on 2026-01-12 (next day)\n";
echo "-".str_repeat("-", 78)."\n";

// Step 2: Create Day Out at 02:00 (which is the next day)
// The service should determine the correct date based on the shift
$dayOutData = [
    'shop_id' => 1,
    'user_id' => 5,
    'clock_date' => '2026-01-11',  // Still on Jan 11, but time 02:00 means next day
    'time' => '02:00',
    'type' => 'day_out',
    'shift_start' => '08:00',
    'shift_end' => '23:00',
    'buffer_time' => 3,
    'created_from' => 'B',
];

$result = $service->dayOutAdd($dayOutData);
if ($result['status']) {
    $dayOutRecord = $result['data'];
    echo "✓ Created Day Out at 02:00 (2026-01-12)\n";
    echo "  Record ID: {$dayOutRecord->id}, date_at: {$dayOutRecord->date_at}\n";
    echo "  formated_date_time: {$dayOutRecord->formated_date_time}\n";
    $testsPassed++;
} else {
    echo "✗ FAILED to create Day Out: {$result['message']}\n";
    $testsFailed++;
    exit(1);
}

echo "\n";
echo "TEST 3: Adding Break Start at 23:30 on 2026-01-11\n";
echo "-".str_repeat("-", 78)."\n";

// Step 3: Add break start at 23:30
$breakStartData = [
    'shop_id' => 1,
    'user_id' => 5,
    'clock_date' => '2026-01-11',
    'time' => '23:30',
    'type' => 'break_start',
    'shift_start' => '08:00',
    'shift_end' => '23:00',
    'buffer_time' => 3,
    'created_from' => 'B',
];

$result = $service->breakStartAdd($breakStartData);
if ($result['status']) {
    $breakStartRecord = $result['data'];
    echo "✓ Created Break Start at 23:30 (2026-01-11)\n";
    echo "  Record ID: {$breakStartRecord->id}, date_at: {$breakStartRecord->date_at}\n";
    echo "  formated_date_time: {$breakStartRecord->formated_date_time}\n";
    $testsPassed++;
} else {
    echo "✗ FAILED to create Break Start: {$result['message']}\n";
    echo "  This should succeed! Break start is after day_in and before day_out\n";
    $testsFailed++;
}

echo "\n";
echo "TEST 4: Adding Break End at 00:30 AM on 2026-01-12 (CRITICAL TEST)\n";
echo "-".str_repeat("-", 78)."\n";

// Step 4: Add break end at 00:30 (next day - THIS IS THE CRITICAL TEST)
// This should SUCCEED because 00:30 is between 23:00 (day_in) and 02:00 (day_out)
$breakEndData = [
    'shop_id' => 1,
    'user_id' => 5,
    'clock_date' => '2026-01-11',  // Same as day_in date, but time 00:30 crosses to next day
    'time' => '00:30',
    'type' => 'break_end',
    'shift_start' => '08:00',
    'shift_end' => '23:00',
    'buffer_time' => 3,
    'created_from' => 'B',
];

$result = $service->breakEndAdd($breakEndData);
if ($result['status']) {
    $breakEndRecord = $result['data'];
    echo "✓ Created Break End at 00:30 (2026-01-12)\n";
    echo "  Record ID: {$breakEndRecord->id}, date_at: {$breakEndRecord->date_at}\n";
    echo "  formated_date_time: {$breakEndRecord->formated_date_time}\n";
    echo "\n  ✓✓✓ CRITICAL TEST PASSED - Midnight-crossing break end works!\n";
    $testsPassed++;
} else {
    echo "✗✗✗ CRITICAL TEST FAILED - Break End validation error:\n";
    echo "  Error: {$result['message']}\n";
    echo "  Code: {$result['code']}\n";
    echo "\n  This is a BUG! Break end at 00:30 should be allowed.\n";
    echo "  The system incorrectly validates breaks that cross midnight.\n";
    $testsFailed++;
}

echo "\n";
echo "TEST 5: Adding another Break Start at 01:00 AM (after first break ends)\n";
echo "-".str_repeat("-", 78)."\n";

// Step 5: Add another break start at 01:00 (after the first break end)
$breakStart2Data = [
    'shop_id' => 1,
    'user_id' => 5,
    'clock_date' => '2026-01-11',
    'time' => '01:00',
    'type' => 'break_start',
    'shift_start' => '08:00',
    'shift_end' => '23:00',
    'buffer_time' => 3,
    'created_from' => 'B',
];

$result = $service->breakStartAdd($breakStart2Data);
if ($result['status']) {
    $breakStart2Record = $result['data'];
    echo "✓ Created Break Start at 01:00 (2026-01-12)\n";
    echo "  Record ID: {$breakStart2Record->id}, date_at: {$breakStart2Record->date_at}\n";
    echo "  formated_date_time: {$breakStart2Record->formated_date_time}\n";
    $testsPassed++;
} else {
    echo "✗ FAILED to create second Break Start: {$result['message']}\n";
    $testsFailed++;
}

echo "\n";
echo "TEST 6: Adding Break End at 01:30 AM\n";
echo "-".str_repeat("-", 78)."\n";

// Step 6: Add break end at 01:30
$breakEnd2Data = [
    'shop_id' => 1,
    'user_id' => 5,
    'clock_date' => '2026-01-11',
    'time' => '01:30',
    'type' => 'break_end',
    'shift_start' => '08:00',
    'shift_end' => '23:00',
    'buffer_time' => 3,
    'created_from' => 'B',
];

$result = $service->breakEndAdd($breakEnd2Data);
if ($result['status']) {
    $breakEnd2Record = $result['data'];
    echo "✓ Created Break End at 01:30 (2026-01-12)\n";
    echo "  Record ID: {$breakEnd2Record->id}, date_at: {$breakEnd2Record->date_at}\n";
    echo "  formated_date_time: {$breakEnd2Record->formated_date_time}\n";
    $testsPassed++;
} else {
    echo "✗ FAILED to create second Break End: {$result['message']}\n";
    $testsFailed++;
}

echo "\n";
echo "TEST 7: Editing Break End from 00:30 to 00:45 (valid edit)\n";
echo "-".str_repeat("-", 78)."\n";

if (isset($breakEndRecord)) {
    $editData = [
        'time' => '00:45',
        'type' => 'break_end',
        'comment' => 'Edited break end time',
        'updated_from' => 'B',
    ];

    $result = $service->updateEvent($breakEndRecord->id, $editData);
    if ($result['status']) {
        echo "✓ Successfully edited Break End to 00:45\n";
        echo "  Updated record: {$result['data']->formated_date_time}\n";
        $testsPassed++;
    } else {
        echo "✗ FAILED to edit Break End: {$result['message']}\n";
        $testsFailed++;
    }
} else {
    echo "⊘ SKIPPED - Break End record not created\n";
}

echo "\n";
echo "TEST 8: Attempting Invalid Break End at 23:00 (same as day_in - should fail)\n";
echo "-".str_repeat("-", 78)."\n";

if (isset($breakEndRecord)) {
    $invalidData = [
        'time' => '23:00',
        'type' => 'break_end',
        'comment' => 'Invalid time',
        'updated_from' => 'B',
    ];

    $result = $service->updateEvent($breakEndRecord->id, $invalidData);
    if (!$result['status']) {
        echo "✓ Correctly BLOCKED invalid Break End at 23:00\n";
        echo "  Error: {$result['message']}\n";
        $testsPassed++;
    } else {
        echo "✗ ERROR - Should have blocked Break End at 23:00\n";
        $testsFailed++;
    }
} else {
    echo "⊘ SKIPPED - Break End record not created\n";
}

echo "\n";
echo "CLEANUP: Removing test records\n";
echo "-".str_repeat("-", 78)."\n";

UserTimeClock::where('user_id', 5)->where('date_at', '>=', '2026-01-11')->where('date_at', '<=', '2026-01-12')->delete();
echo "✓ Test records deleted\n";

echo "\n" . "=".str_repeat("=", 78) . "\n";
echo "FINAL RESULTS\n";
echo "=".str_repeat("=", 78) . "\n";
echo "Tests Passed: $testsPassed\n";
echo "Tests Failed: $testsFailed\n";
echo "Total Tests: " . ($testsPassed + $testsFailed) . "\n\n";

if ($testsFailed === 0) {
    echo "✓ ALL TESTS PASSED!\n";
    echo "\nThe overnight shift break validation is working correctly:\n";
    echo "  • Breaks can cross midnight ✓\n";
    echo "  • Break end after midnight is allowed ✓\n";
    echo "  • Multiple breaks in overnight shift work ✓\n";
    echo "  • Editing breaks across midnight works ✓\n";
    echo "\n";
} else {
    echo "✗ SOME TESTS FAILED\n";
    echo "Please review the validation logic for overnight shifts.\n";
    echo "\n";
    exit(1);
}

echo "=".str_repeat("=", 78) . "\n\n";
