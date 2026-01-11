<?php

/**
 * Day Out Validation Test
 * 
 * Testing the scenario where Day Out was incorrectly allowed before last Break End
 */

$baseUrl = 'http://127.0.0.1:8000/api/time-clock-new';
$userId = 3;
$shopId = 1;
$date = '2026-01-01';
$shiftStart = '08:00';
$shiftEnd = '23:00';
$bufferTime = 3;

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

function sendEvent($type, $time, $shouldPass = true)
{
    global $baseUrl, $userId, $shopId, $date, $shiftStart, $shiftEnd, $bufferTime;

    $data = [
        'user_id' => $userId,
        'shop_id' => $shopId,
        'clock_date' => $date,
        'time' => $time,
        'type' => $type,
        'shift_start' => $shiftStart,
        'shift_end' => $shiftEnd,
        'buffer_time' => $bufferTime,
    ];

    $result = sendRequest($baseUrl, $data);
    $status = $result['http_code'];
    $message = $result['response']['message'] ?? '';

    $icon = $shouldPass ? '✓' : '✗';
    $expectedStatus = $shouldPass ? 201 : 422;
    $color = ($status === $expectedStatus) ? 'green' : 'red';

    echo colorOutput(sprintf("%-15s at %s: ", ucfirst(str_replace('_', ' ', $type)), $time), 'blue');
    echo colorOutput($icon . " Status $status", $color);

    if ($status !== $expectedStatus) {
        echo colorOutput(" (Expected $expectedStatus)", 'yellow');
    }

    if (!$shouldPass && $status === 422) {
        echo colorOutput(" - " . $message, 'yellow');
    }

    echo "\n";

    return $status === $expectedStatus;
}

echo colorOutput("\n╔════════════════════════════════════════════════════════════════╗\n", 'blue');
echo colorOutput("║          DAY OUT VALIDATION FIX TEST                           ║\n", 'blue');
echo colorOutput("╚════════════════════════════════════════════════════════════════╝\n\n", 'blue');

echo colorOutput("Recreating the bug scenario:\n", 'yellow');
echo "  1. Day In at 20:00 (8:00 PM)\n";
echo "  2. Break Start at 23:56 (11:56 PM)\n";
echo "  3. Break End at 00:16 (12:16 AM next day)\n";
echo "  4. Break Start at 00:30 (12:30 AM)\n";
echo "  5. Break End at 00:45 (12:45 AM)\n";
echo colorOutput("  6. Day Out at 00:44 (12:44 AM) ❌ Should be REJECTED\n", 'red');
echo colorOutput("  7. Day Out at 00:46 (12:46 AM) ✅ Should be ALLOWED\n\n", 'green');

// Execute the test
sendEvent('day_in', '20:00', true);
sendEvent('break_start', '23:56', true);
sendEvent('break_end', '00:16', true);
sendEvent('break_start', '00:30', true);
sendEvent('break_end', '00:45', true);

echo colorOutput("\n--- Critical Test Cases ---\n", 'yellow');

$test1 = sendEvent('day_out', '00:44', false);  // Should FAIL (before last break end)
$test2 = sendEvent('day_out', '00:45', false);  // Should FAIL (equals last break end)

// Clean up the failed attempts if they exist
echo colorOutput("\n", 'blue');

$test3 = sendEvent('day_out', '00:46', true);   // Should PASS (after last break end)

echo colorOutput("\n╔════════════════════════════════════════════════════════════════╗\n", 'blue');
echo colorOutput("║                         RESULT                                 ║\n", 'blue');
echo colorOutput("╚════════════════════════════════════════════════════════════════╝\n", 'blue');

if ($test1 && $test2 && $test3) {
    echo colorOutput("\n🎉 ALL TESTS PASSED! Bug is fixed!\n", 'green');
    echo colorOutput("✅ Day Out before Break End → Correctly rejected\n", 'green');
    echo colorOutput("✅ Day Out equal to Break End → Correctly rejected\n", 'green');
    echo colorOutput("✅ Day Out after Break End → Correctly allowed\n\n", 'green');
} else {
    echo colorOutput("\n⚠️  Some tests failed. Bug may not be fully fixed.\n\n", 'red');
}
