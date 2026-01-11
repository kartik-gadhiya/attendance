<?php

/**
 * Multiple Shift Segments Test Case - CORRECTED VERSION
 * 
 * Testing break validation across 3 shift segments in a single day
 * Shift Configuration (ADJUSTED):
 * - Shift Start: 05:00 (covers earliest segment start)
 * - Shift End: 23:00 (covers latest same-day segment)
 * - Buffer: 3 hours (allows next-day times 00:00-02:00)
 * 
 * This configuration allows:
 * - Times from 02:00 (buffer start: 05:00 - 3hrs) to 02:00 next day (buffer end: 23:00 + 3hrs)
 * 
 * Shift Segments:
 * 1. Day In 05:00 → Day Out 10:00
 * 2. Day In 12:00 → Day Out 20:00 (8:00 PM)
 * 3. Day In 21:00 (9:00 PM) → Day Out 02:00 (next day)
 */

// Configuration - CORRECTED
$baseUrl = 'http://127.0.0.1:8000/api/time-clock';
$userId = 3;
$shopId = 1;
$date = '2026-01-01';
$shiftStart = '05:00';  // Adjusted to cover earliest segment
$shiftEnd = '23:00';    // Covers all same-day segments
$bufferTime = 3;        // Allows next-day times (00:00-02:00)

// Color output helpers
function colorOutput($text, $color = 'green')
{
    $colors = [
        'green' => "\033[0;32m",
        'red' => "\033[0;31m",
        'yellow' => "\033[1;33m",
        'blue' => "\033[0;34m",
        'cyan' => "\033[0;36m",
        'magenta' => "\033[0;35m",
        'reset' => "\033[0m",
    ];
    return $colors[$color] . $text . $colors['reset'];
}

// Test counter
$testNumber = 0;
$passCount = 0;
$failCount = 0;

/**
 * Send API request
 */
function sendRequest($url, $data)
{
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Accept: application/json',
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return [
        'http_code' => $httpCode,
        'response' => json_decode($response, true),
    ];
}

/**
 * Run a test case
 */
function runTest($testName, $data, $expectedStatus, $shouldPass, &$testNumber, &$passCount, &$failCount, $baseUrl)
{
    global $userId, $shopId, $date, $shiftStart, $shiftEnd, $bufferTime;

    $testNumber++;
    echo "\n" . colorOutput("Test #{$testNumber}: {$testName}", 'cyan') . "\n";
    echo "  Type: {$data['type']} at {$data['time']}\n";

    // Prepare request data
    $requestData = array_merge([
        'user_id' => $userId,
        'shop_id' => $shopId,
        'clock_date' => $date,
        'shift_start' => $shiftStart,
        'shift_end' => $shiftEnd,
        'buffer_time' => $bufferTime,
    ], $data);

    $result = sendRequest($baseUrl, $requestData);
    $statusCode = $result['http_code'];
    $response = $result['response'];

    $actuallyPassed = ($statusCode === $expectedStatus);

    if ($shouldPass) {
        if ($actuallyPassed) {
            echo colorOutput("  ✓ PASS - Request succeeded as expected (Status: {$statusCode})", 'green') . "\n";
            $passCount++;
        } else {
            echo colorOutput("  ✗ FAIL - Expected success but got status {$statusCode}", 'red') . "\n";
            if (isset($response['message'])) {
                echo colorOutput("  Message: {$response['message']}", 'yellow') . "\n";
            }
            $failCount++;
        }
    } else {
        if ($actuallyPassed) {
            echo colorOutput("  ✓ PASS - Request failed as expected (Status: {$statusCode})", 'green') . "\n";
            if (isset($response['message'])) {
                echo colorOutput("  Validation: {$response['message']}", 'yellow') . "\n";
            }
            $passCount++;
        } else {
            echo colorOutput("  ✗ FAIL - Expected failure but got status {$statusCode}", 'red') . "\n";
            if (isset($response['message'])) {
                echo colorOutput("  Message: {$response['message']}", 'yellow') . "\n";
            }
            $failCount++;
        }
    }

    return $actuallyPassed;
}

echo colorOutput("\n╔════════════════════════════════════════════════════════════════╗", 'blue') . "\n";
echo colorOutput("║     MULTIPLE SHIFT SEGMENTS TEST SUITE (CORRECTED)             ║", 'blue') . "\n";
echo colorOutput("║     Shift: 05:00-23:00 | Buffer: 3hrs | User: 3                ║", 'blue') . "\n";
echo colorOutput("╚════════════════════════════════════════════════════════════════╝\n", 'blue') . "\n";

echo colorOutput("Configuration:", 'magenta') . "\n";
echo colorOutput("  Shift Window: 05:00 - 23:00", 'magenta') . "\n";
echo colorOutput("  Buffer: 3 hours", 'magenta') . "\n";
echo colorOutput("  Valid Times: 02:00 - 02:00 (next day)", 'magenta') . "\n\n";

echo colorOutput("Shift Segments:", 'magenta') . "\n";
echo colorOutput("  Segment 1: 05:00 → 10:00", 'magenta') . "\n";
echo colorOutput("  Segment 2: 12:00 → 20:00", 'magenta') . "\n";
echo colorOutput("  Segment 3: 21:00 → 02:00 (next day)", 'magenta') . "\n\n";

// ============================================
// PHASE 1: Create 3 Shift Segments
// ============================================
echo colorOutput("\n========================================", 'blue') . "\n";
echo colorOutput("PHASE 1: Creating 3 Shift Segments", 'blue') . "\n";
echo colorOutput("========================================\n", 'blue') . "\n";

// Segment 1: 05:00 - 10:00
runTest(
    "Segment 1 - Day In at 05:00",
    ['type' => 'day_in', 'time' => '05:00'],
    201,
    true,
    $testNumber,
    $passCount,
    $failCount,
    $baseUrl
);

runTest(
    "Segment 1 - Day Out at 10:00",
    ['type' => 'day_out', 'time' => '10:00'],
    201,
    true,
    $testNumber,
    $passCount,
    $failCount,
    $baseUrl
);

// Segment 2: 12:00 - 20:00
runTest(
    "Segment 2 - Day In at 12:00",
    ['type' => 'day_in', 'time' => '12:00'],
    201,
    true,
    $testNumber,
    $passCount,
    $failCount,
    $baseUrl
);

runTest(
    "Segment 2 - Day Out at 20:00",
    ['type' => 'day_out', 'time' => '20:00'],
    201,
    true,
    $testNumber,
    $passCount,
    $failCount,
    $baseUrl
);

// Segment 3: 21:00 - 02:00 (next day)
runTest(
    "Segment 3 - Day In at 21:00",
    ['type' => 'day_in', 'time' => '21:00'],
    201,
    true,
    $testNumber,
    $passCount,
    $failCount,
    $baseUrl
);

runTest(
    "Segment 3 - Day Out at 02:00 (next day)",
    ['type' => 'day_out', 'time' => '02:00'],
    201,
    true,
    $testNumber,
    $passCount,
    $failCount,
    $baseUrl
);

// ============================================
// PHASE 2: Valid Breaks Within Segments
// ============================================
echo colorOutput("\n========================================", 'blue') . "\n";
echo colorOutput("PHASE 2: Valid Breaks Within Segments", 'blue') . "\n";
echo colorOutput("========================================\n", 'blue') . "\n";

// Breaks in Segment 1 (05:00 - 10:00)
runTest(
    "✅ Break in Segment 1: Start at 06:00",
    ['type' => 'break_start', 'time' => '06:00'],
    201,
    true,
    $testNumber,
    $passCount,
    $failCount,
    $baseUrl
);

runTest(
    "✅ Break in Segment 1: End at 06:30",
    ['type' => 'break_end', 'time' => '06:30'],
    201,
    true,
    $testNumber,
    $passCount,
    $failCount,
    $baseUrl
);

runTest(
    "✅ Break in Segment 1: Start at 08:00",
    ['type' => 'break_start', 'time' => '08:00'],
    201,
    true,
    $testNumber,
    $passCount,
    $failCount,
    $baseUrl
);

runTest(
    "✅ Break in Segment 1: End at 09:00",
    ['type' => 'break_end', 'time' => '09:00'],
    201,
    true,
    $testNumber,
    $passCount,
    $failCount,
    $baseUrl
);

// Breaks in Segment 2 (12:00 - 20:00)
runTest(
    "✅ Break in Segment 2: Start at 14:00",
    ['type' => 'break_start', 'time' => '14:00'],
    201,
    true,
    $testNumber,
    $passCount,
    $failCount,
    $baseUrl
);

runTest(
    "✅ Break in Segment 2: End at 14:30",
    ['type' => 'break_end', 'time' => '14:30'],
    201,
    true,
    $testNumber,
    $passCount,
    $failCount,
    $baseUrl
);

runTest(
    "✅ Break in Segment 2: Start at 17:00",
    ['type' => 'break_start', 'time' => '17:00'],
    201,
    true,
    $testNumber,
    $passCount,
    $failCount,
    $baseUrl
);

runTest(
    "✅ Break in Segment 2: End at 17:45",
    ['type' => 'break_end', 'time' => '17:45'],
    201,
    true,
    $testNumber,
    $passCount,
    $failCount,
    $baseUrl
);

// Breaks in Segment 3 (21:00 - 02:00 next day)
runTest(
    "✅ Break in Segment 3: Start at 22:00",
    ['type' => 'break_start', 'time' => '22:00'],
    201,
    true,
    $testNumber,
    $passCount,
    $failCount,
    $baseUrl
);

runTest(
    "✅ Break in Segment 3: End at 23:00",
    ['type' => 'break_end', 'time' => '23:00'],
    201,
    true,
    $testNumber,
    $passCount,
    $failCount,
    $baseUrl
);

runTest(
    "✅ Break in Segment 3: Start at 00:00 (midnight)",
    ['type' => 'break_start', 'time' => '00:00'],
    201,
    true,
    $testNumber,
    $passCount,
    $failCount,
    $baseUrl
);

runTest(
    "✅ Break in Segment 3: End at 01:00",
    ['type' => 'break_end', 'time' => '01:00'],
    201,
    true,
    $testNumber,
    $passCount,
    $failCount,
    $baseUrl
);

// ============================================
// PHASE 3: Invalid Breaks in Gaps Between Segments
// ============================================
echo colorOutput("\n========================================", 'blue') . "\n";
echo colorOutput("PHASE 3: Invalid Breaks in Gaps (MUST FAIL)", 'blue') . "\n";
echo colorOutput("========================================\n", 'blue') . "\n";

// Gap 1: Between Segment 1 and 2 (10:00 - 12:00)
runTest(
    "🔴 INVALID: Break Start at 10:30 (GAP between segments)",
    ['type' => 'break_start', 'time' => '10:30'],
    422,
    false,
    $testNumber,
    $passCount,
    $failCount,
    $baseUrl
);

runTest(
    "🔴 INVALID: Break Start at 11:00 (GAP between segments)",
    ['type' => 'break_start', 'time' => '11:00'],
    422,
    false,
    $testNumber,
    $passCount,
    $failCount,
    $baseUrl
);

runTest(
    "🔴 INVALID: Break Start at 11:45 (GAP between segments)",
    ['type' => 'break_start', 'time' => '11:45'],
    422,
    false,
    $testNumber,
    $passCount,
    $failCount,
    $baseUrl
);

// Gap 2: Between Segment 2 and 3 (20:00 - 21:00)
runTest(
    "🔴 INVALID: Break Start at 20:15 (GAP between segments)",
    ['type' => 'break_start', 'time' => '20:15'],
    422,
    false,
    $testNumber,
    $passCount,
    $failCount,
    $baseUrl
);

runTest(
    "🔴 INVALID: Break Start at 20:30 (GAP between segments)",
    ['type' => 'break_start', 'time' => '20:30'],
    422,
    false,
    $testNumber,
    $passCount,
    $failCount,
    $baseUrl
);

runTest(
    "🔴 INVALID: Break Start at 20:50 (GAP between segments)",
    ['type' => 'break_start', 'time' => '20:50'],
    422,
    false,
    $testNumber,
    $passCount,
    $failCount,
    $baseUrl
);

// ============================================
// PHASE 4: Edge Cases
// ============================================
echo colorOutput("\n========================================", 'blue') . "\n";
echo colorOutput("PHASE 4: Edge Cases & Boundary Testing", 'blue') . "\n";
echo colorOutput("========================================\n", 'blue') . "\n";

// Test exact boundaries
runTest(
    "🔴 INVALID: Break Start at 10:00 (exact Day Out time)",
    ['type' => 'break_start', 'time' => '10:00'],
    422,
    false,
    $testNumber,
    $passCount,
    $failCount,
    $baseUrl
);

runTest(
    "🔴 INVALID: Break Start at 12:00 (exact Day In time)",
    ['type' => 'break_start', 'time' => '12:00'],
    422,
    false,
    $testNumber,
    $passCount,
    $failCount,
    $baseUrl
);

runTest(
    "🔴 INVALID: Break Start at 20:00 (exact Day Out time)",
    ['type' => 'break_start', 'time' => '20:00'],
    422,
    false,
    $testNumber,
    $passCount,
    $failCount,
    $baseUrl
);

runTest(
    "🔴 INVALID: Break Start at 21:00 (exact Day In time)",
    ['type' => 'break_start', 'time' => '21:00'],
    422,
    false,
    $testNumber,
    $passCount,
    $failCount,
    $baseUrl
);

// ============================================
// Final Summary
// ============================================
echo colorOutput("\n╔════════════════════════════════════════════════════════════════╗", 'blue') . "\n";
echo colorOutput("║                         TEST SUMMARY                           ║", 'blue') . "\n";
echo colorOutput("╚════════════════════════════════════════════════════════════════╝\n", 'blue') . "\n";

echo "Total Tests: " . $testNumber . "\n";
echo colorOutput("Passed: " . $passCount, 'green') . "\n";
echo colorOutput("Failed: " . $failCount, 'red') . "\n";

if ($failCount === 0) {
    echo "\n" . colorOutput("🎉 ALL TESTS PASSED!", 'green') . "\n";
    echo colorOutput("✅ Breaks are correctly allowed only within shift segments", 'green') . "\n";
    echo colorOutput("✅ Breaks in gaps between segments are properly blocked", 'green') . "\n";
    echo colorOutput("✅ No time overlap issues detected", 'green') . "\n";
    echo colorOutput("✅ Multiple shift segments handled correctly", 'green') . "\n\n";
} else {
    echo "\n" . colorOutput("⚠️  SOME TESTS FAILED. Reviewing issues...", 'red') . "\n\n";
}

// Display database records
echo colorOutput("╔════════════════════════════════════════════════════════════════╗", 'blue') . "\n";
echo colorOutput("║                    DATABASE VERIFICATION                       ║", 'blue') . "\n";
echo colorOutput("╚════════════════════════════════════════════════════════════════╝\n", 'blue') . "\n";

echo colorOutput("To view all records, run:", 'yellow') . "\n";
echo colorOutput("php artisan tinker --execute=\"echo 'Type | Time | Formatted DateTime\n' . str_repeat('-', 60) . '\n'; \\App\\Models\\UserTimeClock::where('user_id', 3)->where('date_at', '2026-01-01')->orderBy('formated_date_time')->get()->each(function(\\\$r) { echo str_pad(\\\$r->type, 15) . str_pad(\\\$r->time_at, 12) . \\\$r->formated_date_time . '\n'; });\"", 'yellow') . "\n\n";
