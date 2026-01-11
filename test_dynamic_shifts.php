<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Services\UserTimeClockService;
use Carbon\Carbon;

echo "=== Testing Dynamic Shift Support Fix ===\n\n";

$service = new UserTimeClockService();
$reflection = new \ReflectionClass($service);
$normalizeMethod = $reflection->getMethod('normalizeDateTime');
$normalizeMethod->setAccessible(true);

// Test Scenario 1: Shift 5:00 AM - 9:00 PM (Buffer: 2:00 AM - 12:00 AM)
echo "Test 1: Shift 05:00-21:00, Buffer start 02:00\n";
echo "Event at 02:30 (in buffer, same day expected)\n";

$result1 = $normalizeMethod->invoke($service, '2026-01-07', '02:30:00', '05:00:00', '21:00:00', 180);
echo "Date: " . $result1['date_time'] . "\n";
echo "Formatted: " . $result1['formated_date_time'] . "\n";
echo "Status: " . ($result1['formated_date_time'] === '2026-01-07 02:30:00' ? "✅ CORRECT - Same day" : "❌ WRONG - " . $result1['formated_date_time']) . "\n\n";

// Test Scenario 2: Shift 5:00 AM - 9:00 PM, event at 05:00 (shift start)
echo "Test 2: Shift 05:00-21:00\n";
echo "Event at 05:00 (shift start, same day expected)\n";

$result2 = $normalizeMethod->invoke($service, '2026-01-07', '05:00:00', '05:00:00', '21:00:00', 180);
echo "Date: " . $result2['date_time'] . "\n";
echo "Formatted: " . $result2['formated_date_time'] . "\n";
echo "Status: " . ($result2['formated_date_time'] === '2026-01-07 05:00:00' ? "✅ CORRECT - Same day" : "❌ WRONG") . "\n\n";

// Test Scenario 3: Shift 8:00 AM - 11:00 PM, event at 05:00 (in buffer before shift)
echo "Test 3: Shift 08:00-23:00, Buffer start 05:00\n";
echo "Event at 05:30 (in buffer, same day expected)\n";

$result3 = $normalizeMethod->invoke($service, '2026-01-07', '05:30:00', '08:00:00', '23:00:00', 180);
echo "Date: " . $result3['date_time'] . "\n";
echo "Formatted: " . $result3['formated_date_time'] . "\n";
echo "Status: " . ($result3['formated_date_time'] === '2026-01-07 05:30:00' ? "✅ CORRECT - Same day" : "❌ WRONG") . "\n\n";

// Test Scenario 4: Shift 8:00 AM - 11:00 PM, event at 01:00 (next day buffer)
echo "Test 4: Shift 08:00-23:00, Buffer end 02:00\n";
echo "Event at 01:00 (in next-day buffer, next day expected)\n";

$result4 = $normalizeMethod->invoke($service, '2026-01-07', '01:00:00', '08:00:00', '23:00:00', 180);
echo "Date: " . $result4['date_time'] . "\n";
echo "Formatted: " . $result4['formated_date_time'] . "\n";
echo "Status: " . ($result4['formated_date_time'] === '2026-01-08 01:00:00' ? "✅ CORRECT - Next day" : "❌ WRONG") . "\n\n";

// Test Scenario 5: Midnight-crossing shift 20:00 - 04:00
echo "Test 5: Midnight-crossing shift 20:00-04:00\n";
echo "Event at 02:00 (next day portion of shift)\n";

$result5 = $normalizeMethod->invoke($service, '2026-01-07', '02:00:00', '20:00:00', '04:00:00', 180);
echo "Date: " . $result5['date_time'] . "\n";
echo "Formatted: " . $result5['formated_date_time'] . "\n";
echo "Status: " . ($result5['formated_date_time'] === '2026-01-08 02:00:00' ? "✅ CORRECT - Next day" : "❌ WRONG") . "\n\n";

// Test Scenario 6: Very early shift 02:00 - 20:00
echo "Test 6: Very early shift 02:00-20:00\n";
echo "Event at 02:30 (shift start area, same day expected)\n";

$result6 = $normalizeMethod->invoke($service, '2026-01-07', '02:30:00', '02:00:00', '20:00:00', 180);
echo "Date: " . $result6['date_time'] . "\n";
echo "Formatted: " . $result6['formated_date_time'] . "\n";
echo "Status: " . ($result6['formated_date_time'] === '2026-01-07 02:30:00' ? "✅ CORRECT - Same day" : "❌ WRONG") . "\n\n";

// Summary
echo "=== Summary ===\n";
$passed = 0;
$total = 6;

if ($result1['formated_date_time'] === '2026-01-07 02:30:00') $passed++;
if ($result2['formated_date_time'] === '2026-01-07 05:00:00') $passed++;
if ($result3['formated_date_time'] === '2026-01-07 05:30:00') $passed++;
if ($result4['formated_date_time'] === '2026-01-08 01:00:00') $passed++;
if ($result5['formated_date_time'] === '2026-01-08 02:00:00') $passed++;
if ($result6['formated_date_time'] === '2026-01-07 02:30:00') $passed++;

echo "Tests passed: {$passed}/{$total}\n";
echo ($passed === $total ? "✅ ALL TESTS PASSED - Dynamic shifts supported!" : "❌ Some tests failed") . "\n";
