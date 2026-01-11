<?php

/**
 * Time Clock API - Overlapping Events Test Suite
 * 
 * Tests validation of overlapping and conflicting time entries for User ID 4
 * Run with: php test_overlapping.php
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
echo Colors::BLUE . "TIME CLOCK - OVERLAPPING EVENTS TEST SUITE (User ID 4)" . Colors::NC . "\n";
echo Colors::BLUE . str_repeat('=', 70) . Colors::NC . "\n\n";

$passed = 0;
$failed = 0;

$shopId = 1;
$userId = 4;
$testDate = '2026-01-05'; // Different date to avoid conflicts
$shiftStart = '06:00';
$shiftEnd = '23:00';
$bufferTime = 3;

// Clear existing data for this date first
echo Colors::YELLOW . "Clearing existing data for User ID $userId on $testDate...\n" . Colors::NC;
// Note: In production, you'd call an API endpoint or use tinker to clear data

$tests = [
    // === SCENARIO 1: Overlapping Day In/Out ===
    ['name' => 'Setup: Day In at 08:00', 'data' => [
        'shop_id' => $shopId,
        'user_id' => $userId,
        'clock_date' => $testDate,
        'time' => '08:00',
        'type' => 'day_in',
        'shift_start' => $shiftStart,
        'shift_end' => $shiftEnd,
        'buffer_time' => $bufferTime,
        'created_from' => 'A'
    ], 'expected' => 'success'],

    ['name' => 'Overlap: Try Day In at 09:00 without Day Out (should fail)', 'data' => [
        'shop_id' => $shopId,
        'user_id' => $userId,
        'clock_date' => $testDate,
        'time' => '09:00',
        'type' => 'day_in',
        'shift_start' => $shiftStart,
        'shift_end' => $shiftEnd,
        'buffer_time' => $bufferTime,
        'created_from' => 'A'
    ], 'expected' => 'fail'],

    ['name' => 'Valid: Day Out at 12:00', 'data' => [
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

    ['name' => 'Overlap: Try Day In at 10:00 between existing shifts (should fail)', 'data' => [
        'shop_id' => $shopId,
        'user_id' => $userId,
        'clock_date' => $testDate,
        'time' => '10:00',
        'type' => 'day_in',
        'shift_start' => $shiftStart,
        'shift_end' => $shiftEnd,
        'buffer_time' => $bufferTime,
        'created_from' => 'A'
    ], 'expected' => 'fail'],

    // === SCENARIO 2: Overlapping Breaks ===
    ['name' => 'Setup: Day In at 13:00 (new shift)', 'data' => [
        'shop_id' => $shopId,
        'user_id' => $userId,
        'clock_date' => $testDate,
        'time' => '13:00',
        'type' => 'day_in',
        'shift_start' => $shiftStart,
        'shift_end' => $shiftEnd,
        'buffer_time' => $bufferTime,
        'created_from' => 'A'
    ], 'expected' => 'success'],

    ['name' => 'Setup: Break Start at 14:00', 'data' => [
        'shop_id' => $shopId,
        'user_id' => $userId,
        'clock_date' => $testDate,
        'time' => '14:00',
        'type' => 'break_start',
        'shift_start' => $shiftStart,
        'shift_end' => $shiftEnd,
        'buffer_time' => $bufferTime,
        'created_from' => 'A'
    ], 'expected' => 'success'],

    ['name' => 'Overlap: Try another Break Start without ending current (should fail)', 'data' => [
        'shop_id' => $shopId,
        'user_id' => $userId,
        'clock_date' => $testDate,
        'time' => '14:30',
        'type' => 'break_start',
        'shift_start' => $shiftStart,
        'shift_end' => $shiftEnd,
        'buffer_time' => $bufferTime,
        'created_from' => 'A'
    ], 'expected' => 'fail'],

    ['name' => 'Valid: Break End at 14:15', 'data' => [
        'shop_id' => $shopId,
        'user_id' => $userId,
        'clock_date' => $testDate,
        'time' => '14:15',
        'type' => 'break_end',
        'shift_start' => $shiftStart,
        'shift_end' => $shiftEnd,
        'buffer_time' => $bufferTime,
        'created_from' => 'A'
    ], 'expected' => 'success'],

    ['name' => 'Valid: Break End at 15:00', 'data' => [
        'shop_id' => $shopId,
        'user_id' => $userId,
        'clock_date' => $testDate,
        'time' => '15:00',
        'type' => 'break_end',
        'shift_start' => $shiftStart,
        'shift_end' => $shiftEnd,
        'buffer_time' => $bufferTime,
        'created_from' => 'A'
    ], 'expected' => 'success'],

    // === SCENARIO 3: Day Out Before Break End ===
    ['name' => 'Setup: Break Start at 16:00', 'data' => [
        'shop_id' => $shopId,
        'user_id' => $userId,
        'clock_date' => $testDate,
        'time' => '16:00',
        'type' => 'break_start',
        'shift_start' => $shiftStart,
        'shift_end' => $shiftEnd,
        'buffer_time' => $bufferTime,
        'created_from' => 'A'
    ], 'expected' => 'success'],

    ['name' => 'Invalid: Try Day Out with incomplete break (should fail)', 'data' => [
        'shop_id' => $shopId,
        'user_id' => $userId,
        'clock_date' => $testDate,
        'time' => '17:00',
        'type' => 'day_out',
        'shift_start' => $shiftStart,
        'shift_end' => $shiftEnd,
        'buffer_time' => $bufferTime,
        'created_from' => 'A'
    ], 'expected' => 'fail'],

    ['name' => 'Valid: Break End at 16:45', 'data' => [
        'shop_id' => $shopId,
        'user_id' => $userId,
        'clock_date' => $testDate,
        'time' => '16:45',
        'type' => 'break_end',
        'shift_start' => $shiftStart,
        'shift_end' => $shiftEnd,
        'buffer_time' => $bufferTime,
        'created_from' => 'A'
    ], 'expected' => 'success'],

    ['name' => 'Valid: Day Out at 18:00', 'data' => [
        'shop_id' => $shopId,
        'user_id' => $userId,
        'clock_date' => $testDate,
        'time' => '18:00',
        'type' => 'day_out',
        'shift_start' => $shiftStart,
        'shift_end' => $shiftEnd,
        'buffer_time' => $bufferTime,
        'created_from' => 'A'
    ], 'expected' => 'success'],

    // === SCENARIO 4: Time Sequence Constraints ===
    ['name' => 'Setup: Day In at 19:00', 'data' => [
        'shop_id' => $shopId,
        'user_id' => $userId,
        'clock_date' => $testDate,
        'time' => '19:00',
        'type' => 'day_in',
        'shift_start' => $shiftStart,
        'shift_end' => $shiftEnd,
        'buffer_time' => $bufferTime,
        'created_from' => 'A'
    ], 'expected' => 'success'],

    ['name' => 'Valid: Break Start at 20:00', 'data' => [
        'shop_id' => $shopId,
        'user_id' => $userId,
        'clock_date' => $testDate,
        'time' => '20:00',
        'type' => 'break_start',
        'shift_start' => $shiftStart,
        'shift_end' => $shiftEnd,
        'buffer_time' => $bufferTime,
        'created_from' => 'A'
    ], 'expected' => 'success'],

    ['name' => 'Valid: Break End at 20:30', 'data' => [
        'shop_id' => $shopId,
        'user_id' => $userId,
        'clock_date' => $testDate,
        'time' => '20:30',
        'type' => 'break_end',
        'shift_start' => $shiftStart,
        'shift_end' => $shiftEnd,
        'buffer_time' => $bufferTime,
        'created_from' => 'A'
    ], 'expected' => 'success'],

    ['name' => 'Valid: Day Out at 22:00', 'data' => [
        'shop_id' => $shopId,
        'user_id' => $userId,
        'clock_date' => $testDate,
        'time' => '22:00',
        'type' => 'day_out',
        'shift_start' => $shiftStart,
        'shift_end' => $shiftEnd,
        'buffer_time' => $bufferTime,
        'created_from' => 'A'
    ], 'expected' => 'success'],
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
    echo Colors::GREEN . "🎉 All overlapping event validations working perfectly!" . Colors::NC . "\n\n";
} else {
    echo Colors::RED . "⚠️  Some validations failed. Review the output above." . Colors::NC . "\n\n";
}
