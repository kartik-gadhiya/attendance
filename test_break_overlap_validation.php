<?php
/**
 * Test Break Overlap Validation
 * 
 * Tests the fix for preventing overlapping breaks in overnight shifts
 * Based on real SQL data analysis provided by user
 */

use App\Models\UserTimeClock;
use App\Services\UserTimeClockService;
use Illuminate\Support\Facades\DB;

// Use Artisan if running standalone
if (file_exists(__DIR__ . '/artisan')) {
    define('LARAVEL_START', microtime(true));
    require_once __DIR__ . '/vendor/autoload.php';
    $app = require_once __DIR__ . '/bootstrap/app.php';
    $kernel = $app->make('Illuminate\Contracts\Http\Kernel');
    $response = $kernel->handle(
        $request = \Illuminate\Http\Request::capture()
    );
}

// Initialize
$passed = 0;
$failed = 0;

function test($description, $condition) {
    global $passed, $failed;
    if ($condition) {
        echo "✅ PASS: $description\n";
        $passed++;
    } else {
        echo "❌ FAIL: $description\n";
        $failed++;
    }
}

function heading($text) {
    echo "\n" . str_repeat("=", 80) . "\n";
    echo "  " . $text . "\n";
    echo str_repeat("=", 80) . "\n\n";
}

// Clean up before tests
UserTimeClock::truncate();

heading("TEST SUITE: Break Overlap Validation");

// ============================================================================
// TEST 1: Create a simple overnight shift without breaks (baseline)
// ============================================================================
echo "TEST 1: Create overnight shift without breaks\n";
$userId = 5;
$shopId = 1;
$date = '2026-01-11';

$service = new UserTimeClockService();

// Add day_in at 23:00
$dayInResult = $service->dayInAdd([
    'user_id' => $userId,
    'shop_id' => $shopId,
    'clock_date' => $date,
    'time' => '23:00',
    'comment' => 'Test shift start',
]);
test("Day In at 23:00 created", $dayInResult['status'] === true);

// Add day_out at 01:00 (next day)
$dayOutResult = $service->dayOutAdd([
    'user_id' => $userId,
    'shop_id' => $shopId,
    'clock_date' => $date,
    'time' => '01:00',
    'comment' => 'Test shift end',
]);
test("Day Out at 01:00 created", $dayOutResult['status'] === true);

// ============================================================================
// TEST 2: Add first break (23:45 to 00:15)
// ============================================================================
echo "\nTEST 2: Add first break 23:45 to 00:15\n";

// Add break_start at 23:45
$breakStart1Result = $service->breakStartAdd([
    'user_id' => $userId,
    'shop_id' => $shopId,
    'clock_date' => $date,
    'time' => '23:45',
    'comment' => 'First break start',
]);
test("Break start at 23:45 added", $breakStart1Result['status'] === true);

// Add break_end at 00:15
$breakEnd1Result = $service->breakEndAdd([
    'user_id' => $userId,
    'shop_id' => $shopId,
    'clock_date' => $date,
    'time' => '00:15',
    'comment' => 'First break end',
]);
test("Break end at 00:15 added", $breakEnd1Result['status'] === true);

// ============================================================================
// TEST 3: Try to add overlapping break (should fail)
// ============================================================================
echo "\nTEST 3: Try to add overlapping break - should FAIL\n";

// Try to add break_start at 00:05 (falls within first break 23:45-00:15)
$breakStart2OverlapResult = $service->breakStartAdd([
    'user_id' => $userId,
    'shop_id' => $shopId,
    'clock_date' => $date,
    'time' => '00:05',
    'comment' => 'Overlapping break start',
]);
test("Break start at 00:05 BLOCKED (overlaps with 23:45-00:15 break)", 
     $breakStart2OverlapResult['status'] === false && 
     strpos($breakStart2OverlapResult['message'], 'falls within') !== false);

// ============================================================================
// TEST 4: Add non-overlapping second break (00:30 to 00:45)
// ============================================================================
echo "\nTEST 4: Add non-overlapping second break 00:30 to 00:45\n";

// Add break_start at 00:30 (after first break ends at 00:15)
$breakStart2Result = $service->breakStartAdd([
    'user_id' => $userId,
    'shop_id' => $shopId,
    'clock_date' => $date,
    'time' => '00:30',
    'comment' => 'Second break start',
]);
test("Break start at 00:30 added", $breakStart2Result['status'] === true);

// Add break_end at 00:45
$breakEnd2Result = $service->breakEndAdd([
    'user_id' => $userId,
    'shop_id' => $shopId,
    'clock_date' => $date,
    'time' => '00:45',
    'comment' => 'Second break end',
]);
test("Break end at 00:45 added", $breakEnd2Result['status'] === true);

// ============================================================================
// TEST 5: Try to add break that overlaps with both existing breaks
// ============================================================================
echo "\nTEST 5: Try to add break spanning both existing breaks - should FAIL\n";

// Add day_in and day_out fresh for this test
UserTimeClock::truncate();

$service->dayInAdd([
    'user_id' => $userId,
    'shop_id' => $shopId,
    'clock_date' => $date,
    'time' => '23:00',
]);

$service->dayOutAdd([
    'user_id' => $userId,
    'shop_id' => $shopId,
    'clock_date' => $date,
    'time' => '01:00',
]);

// Add first break 23:45-00:15
$service->breakStartAdd([
    'user_id' => $userId,
    'shop_id' => $shopId,
    'clock_date' => $date,
    'time' => '23:45',
]);
$service->breakEndAdd([
    'user_id' => $userId,
    'shop_id' => $shopId,
    'clock_date' => $date,
    'time' => '00:15',
]);

// Add second break 00:30-00:45
$service->breakStartAdd([
    'user_id' => $userId,
    'shop_id' => $shopId,
    'clock_date' => $date,
    'time' => '00:30',
]);
$service->breakEndAdd([
    'user_id' => $userId,
    'shop_id' => $shopId,
    'clock_date' => $date,
    'time' => '00:45',
]);

// Try to add break 23:30-00:50 (spans both breaks)
$breakStart3Result = $service->breakStartAdd([
    'user_id' => $userId,
    'shop_id' => $shopId,
    'clock_date' => $date,
    'time' => '23:30',
]);
test("Break start at 23:30 BLOCKED (would overlap with existing breaks)", 
     $breakStart3Result['status'] === false);

// ============================================================================
// TEST 6: Exact boundary test - breaks cannot touch at exact same time
// ============================================================================
echo "\nTEST 6: Exact boundary test\n";

UserTimeClock::truncate();

$service->dayInAdd([
    'user_id' => $userId,
    'shop_id' => $shopId,
    'clock_date' => $date,
    'time' => '23:00',
]);

$service->dayOutAdd([
    'user_id' => $userId,
    'shop_id' => $shopId,
    'clock_date' => $date,
    'time' => '01:00',
]);

// Add first break 23:45-00:15
$service->breakStartAdd([
    'user_id' => $userId,
    'shop_id' => $shopId,
    'clock_date' => $date,
    'time' => '23:45',
]);
$service->breakEndAdd([
    'user_id' => $userId,
    'shop_id' => $shopId,
    'clock_date' => $date,
    'time' => '00:15',
]);

// Try to add break that starts exactly when previous one ends (00:15)
// This should be allowed (no actual overlap)
$breakStart2AtBoundaryResult = $service->breakStartAdd([
    'user_id' => $userId,
    'shop_id' => $shopId,
    'clock_date' => $date,
    'time' => '00:15',
]);
test("Break start at exact boundary time (00:15) ALLOWED", 
     $breakStart2AtBoundaryResult['status'] === true);

// ============================================================================
// TEST 7: Test with the exact SQL data provided by user
// ============================================================================
echo "\nTEST 7: Test with user's exact SQL data\n";

UserTimeClock::truncate();

// Insert the exact records from user's SQL
DB::statement("INSERT INTO `user_time_clock` 
    (`id`, `shop_id`, `user_id`, `date_at`, `time_at`, `date_time`, `formated_date_time`, 
     `shift_start`, `shift_end`, `type`, `comment`, `buffer_time`, `created_from`, `updated_from`, `created_at`, `updated_at`) 
    VALUES
    (471, 1, 5, '2026-01-11', '23:00:00', '2026-01-11 23:00:00', '2026-01-11 23:00:00', 
     '08:00:00', '23:00:00', 'day_in', 'Morning shift start', 3, 'B', 'B', '2026-01-15 08:19:55', '2026-01-15 08:19:55'),
    (473, 1, 5, '2026-01-11', '23:45:00', '2026-01-11 23:45:00', '2026-01-11 23:45:00', 
     '08:00:00', '23:00:00', 'break_start', 'Morning shift start', 3, 'B', 'B', '2026-01-15 08:20:28', '2026-01-15 08:20:28'),
    (476, 1, 5, '2026-01-11', '00:14:00', '2026-01-11 00:14:00', '2026-01-12 00:14:00', 
     '08:00:00', '23:00:00', 'break_end', 'Morning shift start', 3, 'B', 'B', '2026-01-15 08:21:42', '2026-01-15 08:21:42'),
    (474, 1, 5, '2026-01-11', '00:15:00', '2026-01-11 00:15:00', '2026-01-12 00:15:00', 
     '08:00:00', '23:00:00', 'break_end', 'Morning shift start', 3, 'B', 'B', '2026-01-15 08:20:41', '2026-01-15 08:20:41'),
    (475, 1, 5, '2026-01-11', '00:30:00', '2026-01-11 00:30:00', '2026-01-12 00:30:00', 
     '08:00:00', '23:00:00', 'break_start', 'Morning shift start', 3, 'B', 'B', '2026-01-15 08:21:29', '2026-01-15 08:21:29'),
    (472, 1, 5, '2026-01-11', '01:00:00', '2026-01-11 01:00:00', '2026-01-12 01:00:00', 
     '08:00:00', '23:00:00', 'day_out', 'Morning shift start', 3, 'B', 'B', '2026-01-15 08:20:08', '2026-01-15 08:20:08')");

// Verify the data was inserted
$count = UserTimeClock::count();
test("User's SQL data inserted (6 records)", $count === 6);

// Now try to add a break_end at 00:30 (which should overlap with the break_start at 00:30)
// This is the scenario user encountered
$overlapCheckResult = $service->breakEndAdd([
    'user_id' => $userId,
    'shop_id' => $shopId,
    'clock_date' => $date,
    'time' => '00:30',
]);
test("Attempt to add break_end at 00:30 BLOCKED (overlaps with existing structure)", 
     $overlapCheckResult['status'] === false);

// ============================================================================
// SUMMARY
// ============================================================================
heading("TEST SUMMARY");
echo "Passed: $passed\n";
echo "Failed: $failed\n";
echo "Total:  " . ($passed + $failed) . "\n\n";

if ($failed === 0) {
    echo "🎉 ALL TESTS PASSED! Break overlap validation is working correctly.\n";
    exit(0);
} else {
    echo "⚠️  Some tests failed. Review the output above.\n";
    exit(1);
}
