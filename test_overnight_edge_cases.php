<?php
/**
 * Test: Comprehensive Edge Cases for Overnight Shifts
 * 
 * This test covers:
 * 1. Break validation with midnight-crossing shifts
 * 2. Invalid break times that should be blocked
 * 3. Edit operations on overnight break times
 * 4. Multiple breaks in the same overnight shift
 */

require_once __DIR__ . '/vendor/autoload.php';

use App\Services\UserTimeClockService;
use App\Models\UserTimeClock;

// Load Laravel
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "\n" . "=".str_repeat("=", 78) . "\n";
echo "Comprehensive Overnight Shift Edge Cases Test\n";
echo "=".str_repeat("=", 78) . "\n\n";

$testsPassed = 0;
$testsFailed = 0;

// Clear any existing test data
UserTimeClock::where('user_id', 6)->delete();

echo "SETUP: Creating overnight shift (22:00 - 04:00 next day)\n";
echo "-".str_repeat("-", 78)."\n";

$service = new UserTimeClockService('en');

// Create Day In at 22:00
$dayInData = [
    'shop_id' => 1,
    'user_id' => 6,
    'clock_date' => '2026-01-12',
    'time' => '22:00',
    'type' => 'day_in',
    'shift_start' => '22:00',
    'shift_end' => '04:00',
    'buffer_time' => 3,
    'created_from' => 'B',
];
$result = $service->dayInAdd($dayInData);
if (!$result['status']) {
    echo "✗ Failed to create day_in: {$result['message']}\n";
    exit(1);
}
echo "✓ Day In created at 22:00 (2026-01-12)\n";

// Create Day Out at 04:00 (next day)
$dayOutData = [
    'shop_id' => 1,
    'user_id' => 6,
    'clock_date' => '2026-01-12',
    'time' => '04:00',
    'type' => 'day_out',
    'shift_start' => '22:00',
    'shift_end' => '04:00',
    'buffer_time' => 3,
    'created_from' => 'B',
];
$result = $service->dayOutAdd($dayOutData);
if (!$result['status']) {
    echo "✗ Failed to create day_out: {$result['message']}\n";
    exit(1);
}
echo "✓ Day Out created at 04:00 (2026-01-13)\n\n";

// Test 1: Add break that doesn't cross midnight
echo "TEST 1: Break within same time period (22:30 - 23:00)\n";
echo "-".str_repeat("-", 78)."\n";

$breakStartData = [
    'shop_id' => 1,
    'user_id' => 6,
    'clock_date' => '2026-01-12',
    'time' => '22:30',
    'type' => 'break_start',
    'shift_start' => '22:00',
    'shift_end' => '04:00',
    'buffer_time' => 3,
    'created_from' => 'B',
];
$result = $service->breakStartAdd($breakStartData);
if ($result['status']) {
    echo "✓ Break Start at 22:30 added\n";
    $testsPassed++;
} else {
    echo "✗ FAILED: {$result['message']}\n";
    $testsFailed++;
}

$breakEndData = [
    'shop_id' => 1,
    'user_id' => 6,
    'clock_date' => '2026-01-12',
    'time' => '23:00',
    'type' => 'break_end',
    'shift_start' => '22:00',
    'shift_end' => '04:00',
    'buffer_time' => 3,
    'created_from' => 'B',
];
$result = $service->breakEndAdd($breakEndData);
if ($result['status']) {
    echo "✓ Break End at 23:00 added\n";
    $testsPassed++;
} else {
    echo "✗ FAILED: {$result['message']}\n";
    $testsFailed++;
}

echo "\n";
echo "TEST 2: Break that crosses midnight (23:30 - 00:30)\n";
echo "-".str_repeat("-", 78)."\n";

$breakStart2Data = [
    'shop_id' => 1,
    'user_id' => 6,
    'clock_date' => '2026-01-12',
    'time' => '23:30',
    'type' => 'break_start',
    'shift_start' => '22:00',
    'shift_end' => '04:00',
    'buffer_time' => 3,
    'created_from' => 'B',
];
$result = $service->breakStartAdd($breakStart2Data);
if ($result['status']) {
    $breakStart2 = $result['data'];
    echo "✓ Break Start at 23:30 added\n";
    $testsPassed++;
} else {
    echo "✗ FAILED: {$result['message']}\n";
    $testsFailed++;
}

$breakEnd2Data = [
    'shop_id' => 1,
    'user_id' => 6,
    'clock_date' => '2026-01-12',
    'time' => '00:30',
    'type' => 'break_end',
    'shift_start' => '22:00',
    'shift_end' => '04:00',
    'buffer_time' => 3,
    'created_from' => 'B',
];
$result = $service->breakEndAdd($breakEnd2Data);
if ($result['status']) {
    $breakEnd2 = $result['data'];
    echo "✓ Break End at 00:30 (next day) added\n";
    echo "  ✓✓✓ CRITICAL: Midnight-crossing break works!\n";
    $testsPassed++;
} else {
    echo "✗✗✗ CRITICAL FAILED: {$result['message']}\n";
    $testsFailed++;
}

echo "\n";
echo "TEST 3: Early morning break (02:00 - 03:00)\n";
echo "-".str_repeat("-", 78)."\n";

$breakStart3Data = [
    'shop_id' => 1,
    'user_id' => 6,
    'clock_date' => '2026-01-12',
    'time' => '02:00',
    'type' => 'break_start',
    'shift_start' => '22:00',
    'shift_end' => '04:00',
    'buffer_time' => 3,
    'created_from' => 'B',
];
$result = $service->breakStartAdd($breakStart3Data);
if ($result['status']) {
    echo "✓ Break Start at 02:00 added\n";
    $testsPassed++;
} else {
    echo "✗ FAILED: {$result['message']}\n";
    $testsFailed++;
}

$breakEnd3Data = [
    'shop_id' => 1,
    'user_id' => 6,
    'clock_date' => '2026-01-12',
    'time' => '03:00',
    'type' => 'break_end',
    'shift_start' => '22:00',
    'shift_end' => '04:00',
    'buffer_time' => 3,
    'created_from' => 'B',
];
$result = $service->breakEndAdd($breakEnd3Data);
if ($result['status']) {
    echo "✓ Break End at 03:00 added\n";
    $testsPassed++;
} else {
    echo "✗ FAILED: {$result['message']}\n";
    $testsFailed++;
}

echo "\n";
echo "TEST 4: Editing break end time across midnight (00:30 -> 01:00)\n";
echo "-".str_repeat("-", 78)."\n";

if (isset($breakEnd2)) {
    $editData = [
        'time' => '01:00',
        'type' => 'break_end',
        'comment' => 'Extended break',
        'updated_from' => 'B',
    ];
    $result = $service->updateEvent($breakEnd2->id, $editData);
    if ($result['status']) {
        echo "✓ Successfully edited break end from 00:30 to 01:00\n";
        echo "  Updated formated_date_time: {$result['data']->formated_date_time}\n";
        $testsPassed++;
    } else {
        echo "✗ FAILED: {$result['message']}\n";
        $testsFailed++;
    }
}

echo "\n";
echo "TEST 5: Invalid - Break end before break start (22:00 - 21:00 = INVALID)\n";
echo "-".str_repeat("-", 78)."\n";

$invalidData = [
    'shop_id' => 1,
    'user_id' => 6,
    'clock_date' => '2026-01-12',
    'time' => '21:00',
    'type' => 'break_end',
    'shift_start' => '22:00',
    'shift_end' => '04:00',
    'buffer_time' => 3,
    'created_from' => 'B',
];
$result = $service->breakEndAdd($invalidData);
if (!$result['status']) {
    echo "✓ Correctly BLOCKED break end before day_in\n";
    echo "  Error: {$result['message']}\n";
    $testsPassed++;
} else {
    echo "✗ ERROR: Should have blocked break end before day_in\n";
    $testsFailed++;
}

echo "\n";
echo "TEST 6: Invalid - Break start after day_out (04:30 = INVALID)\n";
echo "-".str_repeat("-", 78)."\n";

$invalidBreakData = [
    'shop_id' => 1,
    'user_id' => 6,
    'clock_date' => '2026-01-12',
    'time' => '04:30',
    'type' => 'break_start',
    'shift_start' => '22:00',
    'shift_end' => '04:00',
    'buffer_time' => 3,
    'created_from' => 'B',
];
$result = $service->breakStartAdd($invalidBreakData);
if (!$result['status']) {
    echo "✓ Correctly BLOCKED break start after day_out\n";
    echo "  Error: {$result['message']}\n";
    $testsPassed++;
} else {
    echo "✗ ERROR: Should have blocked break start after day_out\n";
    $testsFailed++;
}

echo "\n";
echo "CLEANUP: Removing all test records\n";
echo "-".str_repeat("-", 78)."\n";

UserTimeClock::where('user_id', 6)->delete();
echo "✓ Test records deleted\n";

echo "\n" . "=".str_repeat("=", 78) . "\n";
echo "FINAL RESULTS\n";
echo "=".str_repeat("=", 78) . "\n";
echo "Tests Passed: $testsPassed\n";
echo "Tests Failed: $testsFailed\n";
echo "Total Tests: " . ($testsPassed + $testsFailed) . "\n\n";

if ($testsFailed === 0) {
    echo "✓ ALL EDGE CASE TESTS PASSED!\n";
    echo "\nBreak validation for overnight shifts is working correctly:\n";
    echo "  • Breaks within same time period ✓\n";
    echo "  • Breaks crossing midnight ✓\n";
    echo "  • Early morning breaks ✓\n";
    echo "  • Editing overnight breaks ✓\n";
    echo "  • Blocking invalid times ✓\n\n";
} else {
    echo "✗ SOME TESTS FAILED\n\n";
    exit(1);
}

echo "=".str_repeat("=", 78) . "\n\n";
