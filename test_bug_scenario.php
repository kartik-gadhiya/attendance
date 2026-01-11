<?php

/**
 * Test Bug Scenario - Break Start Before Day In
 * 
 * This reproduces the issue where Break Start at 10:30 was accepted
 * even though Day In was at 11:00 (ID 853 in SQL)
 */

$baseUrl = 'http://127.0.0.1:8000/api/time-clock-new';

function makeRequest($url, $data) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Accept: application/json'
    ]);
    
    $response = curl_exec($ch);
    curl_close($ch);
    
    return json_decode($response, true);
}

echo "\n==== Testing Bug Scenario: Break Start Before Day In ====\n\n";

$common = [
    'shop_id' => 1,
    'user_id' => 4,
    'clock_date' => '2026-01-06',
    'shift_start' => '08:00',
    'shift_end' => '23:00',
    'buffer_time' => 3,
    'created_from' => 'A'
];

// Recreate the exact scenario from user's SQL
echo "1. Day In at 05:00... ";
$r1 = makeRequest($baseUrl, array_merge($common, ['time' => '05:00', 'type' => 'day_in']));
echo ($r1['success'] ? "✅ SUCCESS\n" : "❌ FAILED: " . $r1['message'] . "\n");

echo "2. Break Start at 06:00... ";
$r2 = makeRequest($baseUrl, array_merge($common, ['time' => '06:00', 'type' => 'break_start']));
echo ($r2['success'] ? "✅ SUCCESS\n" : "❌ FAILED: " . $r2['message'] . "\n");

echo "3. Break End at 07:00... ";
$r3 = makeRequest($baseUrl, array_merge($common, ['time' => '07:00', 'type' => 'break_end']));
echo ($r3['success'] ? "✅ SUCCESS\n" : "❌ FAILED: " . $r3['message'] . "\n");

echo "4. Break Start at 08:00... ";
$r4 = makeRequest($baseUrl, array_merge($common, ['time' => '08:00', 'type' => 'break_start']));
echo ($r4['success'] ? "✅ SUCCESS\n" : "❌ FAILED: " . $r4['message'] . "\n");

echo "5. Break End at 09:00... ";
$r5 = makeRequest($baseUrl, array_merge($common, ['time' => '09:00', 'type' => 'break_end']));
echo ($r5['success'] ? "✅ SUCCESS\n" : "❌ FAILED: " . $r5['message'] . "\n");

echo "6. Day Out at 10:00... ";
$r6 = makeRequest($baseUrl, array_merge($common, ['time' => '10:00', 'type' => 'day_out']));
echo ($r6['success'] ? "✅ SUCCESS\n" : "❌ FAILED: " . $r6['message'] . "\n");

echo "7. Day In at 11:00 (NEW SHIFT)... ";
$r7 = makeRequest($baseUrl, array_merge($common, ['time' => '11:00', 'type' => 'day_in']));
echo ($r7['success'] ? "✅ SUCCESS\n" : "❌ FAILED: " . $r7['message'] . "\n");

echo "\n==== THE BUG TEST ====\n";
echo "8. Break Start at 10:30 (SHOULD FAIL - before Day In at 11:00)... ";
$r8 = makeRequest($baseUrl, array_merge($common, ['time' => '10:30', 'type' => 'break_start']));
if (!$r8['success']) {
    echo "✅ CORRECTLY REJECTED: " . $r8['message'] . "\n";
} else {
    echo "❌ BUG STILL EXISTS: Incorrectly accepted!\n";
}

echo "\n==== CORRECT SEQUENCE ====\n";
echo "9. Break Start at 11:30 (SHOULD SUCCEED - after Day In at 11:00)... ";
$r9 = makeRequest($baseUrl, array_merge($common, ['time' => '11:30', 'type' => 'break_start']));
echo ($r9['success'] ? "✅ SUCCESS\n" : "❌ FAILED: " . $r9['message'] . "\n");

echo "10. Break End at 12:00... ";
$r10 = makeRequest($baseUrl, array_merge($common, ['time' => '12:00', 'type' => 'break_end']));
echo ($r10['success'] ? "✅ SUCCESS\n" : "❌ FAILED: " . $r10['message'] . "\n");

echo "11. Day Out at 13:00... ";
$r11 = makeRequest($baseUrl, array_merge($common, ['time' => '13:00', 'type' => 'day_out']));
echo ($r11['success'] ? "✅ SUCCESS\n" : "❌ FAILED: " . $r11['message'] . "\n");

echo "\n";
