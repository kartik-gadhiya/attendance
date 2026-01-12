<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Services\UserTimeClockService;
use Carbon\Carbon;

echo "=== Testing Edge Case: Exact Buffer End Time ===\n\n";

$service = new UserTimeClockService();
$reflection = new \ReflectionClass($service);
$normalizeMethod = $reflection->getMethod('normalizeDateTime');
$normalizeMethod->setAccessible(true);

// Shift: 06:00-23:00, Buffer: 3 hours (180 min)
// Buffer end: 23:00 + 3h = 02:00 next day

echo "Shift: 06:00-23:00, Buffer: 180 minutes\n";
echo "Buffer end (next day): 02:00\n\n";

$testCases = [
    ['time' => '01:58:00', 'expected_day' => 'next', 'description' => '2 minutes before buffer end'],
    ['time' => '01:59:00', 'expected_day' => 'next', 'description' => '1 minute before buffer end'],
    ['time' => '02:00:00', 'expected_day' => 'next', 'description' => 'EXACT buffer end (edge case)'],
    ['time' => '02:01:00', 'expected_day' => 'same', 'description' => '1 minute after buffer end'],
    ['time' => '02:30:00', 'expected_day' => 'same', 'description' => 'Well after buffer end'],
];

foreach ($testCases as $test) {
    $result = $normalizeMethod->invoke($service, '2026-01-01', $test['time'], '06:00:00', '23:00:00', 180);

    $expectedDate = $test['expected_day'] === 'next' ? '2026-01-02' : '2026-01-01';
    $actualDate = substr($result['formated_date_time'], 0, 10);
    $correct = $actualDate === $expectedDate;

    echo sprintf(
        "%s (%s):\n  Expected: %s %s\n  Actual:   %s\n  Status: %s\n\n",
        $test['time'],
        $test['description'],
        $expectedDate,
        $test['time'],
        $result['formated_date_time'],
        $correct ? '✅ PASS' : '❌ FAIL'
    );
}

echo "=== API Test: Edit ID 29 to 02:00 ===\n";
$result = $service->updateEvent(29, ['time' => '02:00', 'comment' => 'Morning shift start kk']);
if ($result['status']) {
    $formatted = $result['data']['formated_date_time'];
    $isNextDay = substr($formatted, 0, 10) === '2026-01-02';
    echo "Result: ✓ ACCEPTED\n";
    echo "formated_date_time: $formatted\n";
    echo "Status: " . ($isNextDay ? "✅ CORRECT (next day)" : "❌ BUG (same day)") . "\n";
} else {
    echo "Result: ✗ REJECTED\n";
    echo "Message: " . $result['message'] . "\n";
}
