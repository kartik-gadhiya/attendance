<?php

/**
 * Time Clock API - Comprehensive Multi-Shift Test Suite
 * 
 * Tests multi-shift scenarios with next-day handling for User ID 4
 * Run with: php test_time_log.php
 */

$baseUrl = 'http://127.0.0.1:8000/api/time-clock-new';

class Colors
{
    const GREEN = "\033[0;32m";
    const RED = "\033[0;31m";
    const YELLOW = "\033[1;33m";
    const BLUE = "\033[0;34m";
    const CYAN = "\033[0;36m";
    const NC = "\033[0m";
}

function makeRequest($url, $data)
{
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Accept: application/json'
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return [
        'code' => $httpCode,
        'body' => json_decode($response, true)
    ];
}

function printResult($testName, $expectedResult, $response)
{
    $success = ($expectedResult === 'success' && isset($response['body']['success']) && $response['body']['success']) ||
        ($expectedResult === 'fail' && (!isset($response['body']['success']) || !$response['body']['success']));

    if ($success) {
        echo Colors::GREEN . "✅ " . $testName . Colors::NC . "\n";
    } else {
        echo Colors::RED . "❌ " . $testName . Colors::NC . "\n";
    }

    if (isset($response['body']['message'])) {
        $color = ($response['body']['success'] ?? false) ? Colors::CYAN : Colors::YELLOW;
        echo "   " . $color . $response['body']['message'] . Colors::NC . "\n";
    }

    return $success;
}

echo "\n" . Colors::BLUE . str_repeat('=', 70) . Colors::NC . "\n";
echo Colors::BLUE . "TIME CLOCK - MULTI-SHIFT & NEXT-DAY TEST SUITE (User ID 4)" . Colors::NC . "\n";
echo Colors::BLUE . str_repeat('=', 70) . Colors::NC . "\n\n";

$passed = 0;
$failed = 0;

// Test Configuration
$shopId = 1;
$userId = 4;
$testDate = '2026-01-05';
$shiftStart = '06:00';
$shiftEnd = '23:00';
$bufferTime = 3;

$tests = [
    // === SHIFT 1: Morning Shift ===
    ['name' => 'Shift 1: Day In at 05:00 AM', 'data' => [
        'shop_id' => $shopId,
        'user_id' => $userId,
        'clock_date' => $testDate,
        'time' => '05:00',
        'type' => 'day_in',
        'shift_start' => $shiftStart,
        'shift_end' => $shiftEnd,
        'buffer_time' => $bufferTime,
        'created_from' => 'A'
    ], 'expected' => 'success'],

    ['name' => 'Shift 1: Break Start at 05:30 AM', 'data' => [
        'shop_id' => $shopId,
        'user_id' => $userId,
        'clock_date' => $testDate,
        'time' => '05:30',
        'type' => 'break_start',
        'shift_start' => $shiftStart,
        'shift_end' => $shiftEnd,
        'buffer_time' => $bufferTime,
        'created_from' => 'A'
    ], 'expected' => 'success'],

    ['name' => 'Shift 1: Break End at 06:00 AM', 'data' => [
        'shop_id' => $shopId,
        'user_id' => $userId,
        'clock_date' => $testDate,
        'time' => '06:00',
        'type' => 'break_end',
        'shift_start' => $shiftStart,
        'shift_end' => $shiftEnd,
        'buffer_time' => $bufferTime,
        'created_from' => 'A'
    ], 'expected' => 'success'],

    ['name' => 'Shift 1: Break Start at 10:00 AM', 'data' => [
        'shop_id' => $shopId,
        'user_id' => $userId,
        'clock_date' => $testDate,
        'time' => '10:00',
        'type' => 'break_start',
        'shift_start' => $shiftStart,
        'shift_end' => $shiftEnd,
        'buffer_time' => $bufferTime,
        'created_from' => 'A'
    ], 'expected' => 'success'],

    ['name' => 'Shift 1: Break End at 11:00 AM', 'data' => [
        'shop_id' => $shopId,
        'user_id' => $userId,
        'clock_date' => $testDate,
        'time' => '11:00',
        'type' => 'break_end',
        'shift_start' => $shiftStart,
        'shift_end' => $shiftEnd,
        'buffer_time' => $bufferTime,
        'created_from' => 'A'
    ], 'expected' => 'success'],

    ['name' => 'Shift 1: Day Out at 12:00 PM', 'data' => [
        'shop_id' => $shopId,
        'user_id' => $userId,
        'clock_date' => $testDate,
        'time' => '12:00',
        'type' => 'day_out',
        'shift_start' => $shiftStart,
        'shift_end' => $shiftEnd,
        'buffer_time' => $bufferTime,
        'created_from' => 'A'
    ], 'expected' => 'success'],

    // === SHIFT 2: Evening Shift with Next-Day Events ===
    ['name' => 'Shift 2: Day In at 05:00 PM', 'data' => [
        'shop_id' => $shopId,
        'user_id' => $userId,
        'clock_date' => $testDate,
        'time' => '17:00',
        'type' => 'day_in',
        'shift_start' => $shiftStart,
        'shift_end' => $shiftEnd,
        'buffer_time' => $bufferTime,
        'created_from' => 'A'
    ], 'expected' => 'success'],

    ['name' => 'Shift 2: Break Start at 06:00 PM', 'data' => [
        'shop_id' => $shopId,
        'user_id' => $userId,
        'clock_date' => $testDate,
        'time' => '18:00',
        'type' => 'break_start',
        'shift_start' => $shiftStart,
        'shift_end' => $shiftEnd,
        'buffer_time' => $bufferTime,
        'created_from' => 'A'
    ], 'expected' => 'success'],

    ['name' => 'Shift 2: Break End at 07:00 PM', 'data' => [
        'shop_id' => $shopId,
        'user_id' => $userId,
        'clock_date' => $testDate,
        'time' => '19:00',
        'type' => 'break_end',
        'shift_start' => $shiftStart,
        'shift_end' => $shiftEnd,
        'buffer_time' => $bufferTime,
        'created_from' => 'A'
    ], 'expected' => 'success'],

    ['name' => 'Shift 2: Break Start at 11:30 PM', 'data' => [
        'shop_id' => $shopId,
        'user_id' => $userId,
        'clock_date' => $testDate,
        'time' => '23:30',
        'type' => 'break_start',
        'shift_start' => $shiftStart,
        'shift_end' => $shiftEnd,
        'buffer_time' => $bufferTime,
        'created_from' => 'A'
    ], 'expected' => 'success'],

    ['name' => 'Shift 2: Day Out at 02:00 AM (NEXT DAY)', 'data' => [
        'shop_id' => $shopId,
        'user_id' => $userId,
        'clock_date' => $testDate,
        'time' => '02:00',
        'type' => 'day_out',
        'shift_start' => $shiftStart,
        'shift_end' => $shiftEnd,
        'buffer_time' => $bufferTime,
        'created_from' => 'A'
    ], 'expected' => 'success'],

    ['name' => 'Shift 2: Break End at 12:30 AM (NEXT DAY)', 'data' => [
        'shop_id' => $shopId,
        'user_id' => $userId,
        'clock_date' => $testDate,
        'time' => '00:30',
        'type' => 'break_end',
        'shift_start' => $shiftStart,
        'shift_end' => $shiftEnd,
        'buffer_time' => $bufferTime,
        'created_from' => 'A'
    ], 'expected' => 'success'],

    // === DUPLICATE PREVENTION TESTS ===
    ['name' => 'Duplicate: Try Day Out again (should fail)', 'data' => [
        'shop_id' => $shopId,
        'user_id' => $userId,
        'clock_date' => $testDate,
        'time' => '02:00',
        'type' => 'day_out',
        'shift_start' => $shiftStart,
        'shift_end' => $shiftEnd,
        'buffer_time' => $bufferTime,
        'created_from' => 'A'
    ], 'expected' => 'fail'],
];

// Run tests
foreach ($tests as $test) {
    $response = makeRequest($baseUrl, $test['data']);
    $success = printResult($test['name'], $test['expected'], $response);

    if ($success) {
        $passed++;
    } else {
        $failed++;
    }

    usleep(200000); // 200ms delay
}

// Summary
echo "\n" . Colors::BLUE . str_repeat('=', 70) . Colors::NC . "\n";
echo Colors::BLUE . "TEST SUMMARY" . Colors::NC . "\n";
echo Colors::BLUE . str_repeat('=', 70) . Colors::NC . "\n";
echo "Total Tests: " . count($tests) . "\n";
echo Colors::GREEN . "Passed: " . $passed . Colors::NC . "\n";
echo Colors::RED . "Failed: " . $failed . Colors::NC . "\n\n";

if ($failed === 0) {
    echo Colors::GREEN . "🎉 All tests passed! Multi-shift with next-day handling works perfectly!" . Colors::NC . "\n\n";
} else {
    echo Colors::RED . "⚠️  Some tests failed. Please review the output above." . Colors::NC . "\n\n";
}
