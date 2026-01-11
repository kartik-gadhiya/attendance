<?php

/**
 * Comprehensive Test Cases for Time-Log System
 * User ID: 3
 * Shop ID: 1
 * Date: 2026-01-01
 * 
 * IMPORTANT: Clear database before running:
 * php artisan tinker --execute="\App\Models\UserTimeClock::where('user_id', 3)->where('date_at', '2026-01-01')->delete();"
 */

// Configuration
$baseUrl = 'http://127.0.0.1:8000/api/time-clock-new';
$userId = 3;
$shopId = 1;
$date = '2026-01-01';
$shiftStart = '08:00';
$shiftEnd = '23:00';
$bufferTime = 3;

// Color output helpers
function colorOutput($text, $color = 'green')
{
    $colors = [
        'green' => "\033[0;32m",
        'red' => "\033[0;31m",
        'yellow' => "\033[1;33m",
        'blue' => "\033[0;34m",
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
    echo "\n" . colorOutput("Test #{$testNumber}: {$testName}", 'blue') . "\n";
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
            $failCount++;
        }
    }

    return $actuallyPassed;
}

echo colorOutput("\n╔════════════════════════════════════════════════════════════════╗", 'blue') . "\n";
echo colorOutput("║     COMPREHENSIVE TIME-LOG SYSTEM TEST SUITE                   ║", 'blue') . "\n";
echo colorOutput("║     User: 3 | Date: 2026-01-01                                 ║", 'blue') . "\n";
echo colorOutput("╚════════════════════════════════════════════════════════════════╝\n", 'blue') . "\n";

// ============================================
// SCENARIO 1: Basic Flow
// ============================================
echo colorOutput("\n========================================", 'blue') . "\n";
echo colorOutput("SCENARIO 1: Basic Flow", 'blue') . "\n";
echo colorOutput("========================================\n", 'blue') . "\n";

runTest(
    "Day In at 09:00",
    ['type' => 'day_in', 'time' => '09:00'],
    201,
    true,
    $testNumber,
    $passCount,
    $failCount,
    $baseUrl
);

runTest(
    "Day Out at 13:00",
    ['type' => 'day_out', 'time' => '13:00'],
    201,
    true,
    $testNumber,
    $passCount,
    $failCount,
    $baseUrl
);

// ============================================
// SCENARIO 2: Multiple Breaks
// ============================================
echo colorOutput("\n========================================", 'blue') . "\n";
echo colorOutput("SCENARIO 2: Second Shift with Multiple Breaks", 'blue') . "\n";
echo colorOutput("========================================\n", 'blue') . "\n";

runTest(
    "Day In at 14:00",
    ['type' => 'day_in', 'time' => '14:00'],
    201,
    true,
    $testNumber,
    $passCount,
    $failCount,
    $baseUrl
);

runTest(
    "Break Start at 15:00",
    ['type' => 'break_start', 'time' => '15:00'],
    201,
    true,
    $testNumber,
    $passCount,
    $failCount,
    $baseUrl
);

runTest(
    "Break End at 15:15",
    ['type' => 'break_end', 'time' => '15:15'],
    201,
    true,
    $testNumber,
    $passCount,
    $failCount,
    $baseUrl
);

runTest(
    "Break Start at 16:00",
    ['type' => 'break_start', 'time' => '16:00'],
    201,
    true,
    $testNumber,
    $passCount,
    $failCount,
    $baseUrl
);

runTest(
    "Break End at 16:20",
    ['type' => 'break_end', 'time' => '16:20'],
    201,
    true,
    $testNumber,
    $passCount,
    $failCount,
    $baseUrl
);

runTest(
    "Day Out at 18:00",
    ['type' => 'day_out', 'time' => '18:00'],
    201,
    true,
    $testNumber,
    $passCount,
    $failCount,
    $baseUrl
);

// ============================================
// SCENARIO 3: Overlap Validation (Should Fail)
// ============================================
echo colorOutput("\n========================================", 'blue') . "\n";
echo colorOutput("SCENARIO 3: Break Overlap Validation Tests", 'blue') . "\n";
echo colorOutput("========================================\n", 'blue') . "\n";

runTest(
    "Day In at 19:00",
    ['type' => 'day_in', 'time' => '19:00'],
    201,
    true,
    $testNumber,
    $passCount,
    $failCount,
    $baseUrl
);

runTest(
    "Break Start at 19:30",
    ['type' => 'break_start', 'time' => '19:30'],
    201,
    true,
    $testNumber,
    $passCount,
    $failCount,
    $baseUrl
);

runTest(
    "Break End at 20:00",
    ['type' => 'break_end', 'time' => '20:00'],
    201,
    true,
    $testNumber,
    $passCount,
    $failCount,
    $baseUrl
);

runTest(
    "🔴 INVALID: Break Start at 20:00 (equals last Break End)",
    ['type' => 'break_start', 'time' => '20:00'],
    422,
    false,
    $testNumber,
    $passCount,
    $failCount,
    $baseUrl
);

runTest(
    "🔴 INVALID: Break Start at 19:45 (overlaps existing break)",
    ['type' => 'break_start', 'time' => '19:45'],
    422,
    false,
    $testNumber,
    $passCount,
    $failCount,
    $baseUrl
);

runTest(
    "✅ VALID: Break Start at 20:01 (strictly after last Break End)",
    ['type' => 'break_start', 'time' => '20:01'],
    201,
    true,
    $testNumber,
    $passCount,
    $failCount,
    $baseUrl
);

runTest(
    "Break End at 20:15",
    ['type' => 'break_end', 'time' => '20:15'],
    201,
    true,
    $testNumber,
    $passCount,
    $failCount,
    $baseUrl
);

runTest(
    "Day Out at 21:00",
    ['type' => 'day_out', 'time' => '21:00'],
    201,
    true,
    $testNumber,
    $passCount,
    $failCount,
    $baseUrl
);

// ============================================
// SCENARIO 4: Multiple Event Cycles
// ============================================
echo colorOutput("\n========================================", 'blue') . "\n";
echo colorOutput("SCENARIO 4: Night Shift with Multiple Cycles", 'blue') . "\n";
echo colorOutput("========================================\n", 'blue') . "\n";

runTest(
    "Day In at 22:00",
    ['type' => 'day_in', 'time' => '22:00'],
    201,
    true,
    $testNumber,
    $passCount,
    $failCount,
    $baseUrl
);

runTest(
    "Break Start at 23:00",
    ['type' => 'break_start', 'time' => '23:00'],
    201,
    true,
    $testNumber,
    $passCount,
    $failCount,
    $baseUrl
);

runTest(
    "Break End at 23:15",
    ['type' => 'break_end', 'time' => '23:15'],
    201,
    true,
    $testNumber,
    $passCount,
    $failCount,
    $baseUrl
);

runTest(
    "Break Start at 00:00 (next day)",
    ['type' => 'break_start', 'time' => '00:00'],
    201,
    true,
    $testNumber,
    $passCount,
    $failCount,
    $baseUrl
);

runTest(
    "Break End at 00:30",
    ['type' => 'break_end', 'time' => '00:30'],
    201,
    true,
    $testNumber,
    $passCount,
    $failCount,
    $baseUrl
);

runTest(
    "Day Out at 01:00",
    ['type' => 'day_out', 'time' => '01:00'],
    201,
    true,
    $testNumber,
    $passCount,
    $failCount,
    $baseUrl
);

// ============================================
// SCENARIO 5: Edge Cases
// ============================================
echo colorOutput("\n========================================", 'blue') . "\n";
echo colorOutput("SCENARIO 5: Additional Edge Cases", 'blue') . "\n";
echo colorOutput("========================================\n", 'blue') . "\n";

runTest(
    "🔴 INVALID: Day In at 03:00 (before buffer start 05:00)",
    ['type' => 'day_in', 'time' => '03:00'],
    422,
    false,
    $testNumber,
    $passCount,
    $failCount,
    $baseUrl
);

runTest(
    "✅ VALID: Day In at 05:00 (at buffer start)",
    ['type' => 'day_in', 'time' => '05:00'],
    201,
    true,
    $testNumber,
    $passCount,
    $failCount,
    $baseUrl
);

runTest(
    "Day Out at 06:00",
    ['type' => 'day_out', 'time' => '06:00'],
    201,
    true,
    $testNumber,
    $passCount,
    $failCount,
    $baseUrl
);

// Final Summary
echo colorOutput("\n╔════════════════════════════════════════════════════════════════╗", 'blue') . "\n";
echo colorOutput("║                         TEST SUMMARY                           ║", 'blue') . "\n";
echo colorOutput("╚════════════════════════════════════════════════════════════════╝\n", 'blue') . "\n";

echo "Total Tests: " . $testNumber . "\n";
echo colorOutput("Passed: " . $passCount, 'green') . "\n";
echo colorOutput("Failed: " . $failCount, 'red') . "\n";

if ($failCount === 0) {
    echo "\n" . colorOutput("🎉 ALL TESTS PASSED! The time-log system is working correctly.", 'green') . "\n\n";
} else {
    echo "\n" . colorOutput("⚠️  SOME TESTS FAILED. Please review the failures above.", 'red') . "\n\n";
}

echo colorOutput("\nTo view database records, run:", 'yellow') . "\n";
echo colorOutput("php artisan tinker --execute=\"\\App\\Models\\UserTimeClock::where('user_id', 3)->where('date_at', '2026-01-01')->orderBy('formated_date_time')->get(['type', 'time_at', 'formated_date_time']);\"", 'yellow') . "\n\n";
