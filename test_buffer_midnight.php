<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Services\UserTimeClockService;
use Carbon\Carbon;

echo "=== Testing Buffer Time Validation for Midnight ===\n\n";

$service = new UserTimeClockService();
$reflection = new \ReflectionClass($service);
$bufferMethod = $reflection->getMethod('isWithinBufferTime');
$bufferMethod->setAccessible(true);

// Shift: 06:00-23:00, Buffer: 3 hours
// Expected window: 03:00 - 02:00 (next day)

echo "Shift: 06:00 - 23:00\n";
echo "Buffer: 3 hours (180 minutes)\n";
echo "Expected allowed window: 03:00 - 02:00 (next day)\n\n";

$testTimes = [
    '02:00:00' => 'Should REJECT (before 03:00)',
    '02:59:00' => 'Should REJECT (before 03:00)',
    '03:00:00' => 'Should ACCEPT (buffer start)',
    '05:00:00' => 'Should ACCEPT (in buffer)',
    '06:00:00' => 'Should ACCEPT (shift start)',
    '12:00:00' => 'Should ACCEPT (in shift)',
    '23:00:00' => 'Should ACCEPT (shift end)',
    '23:30:00' => 'Should ACCEPT (in buffer)',
    '00:00:00' => 'Should ACCEPT (in next-day buffer)',
    '01:00:00' => 'Should ACCEPT (in next-day buffer)',
    '02:00:00' => 'Should ACCEPT (buffer end, next day)',
    '02:01:00' => 'Should REJECT (after buffer end)',
];

echo "Testing isWithinBufferTime logic:\n";
foreach ($testTimes as $time => $expected) {
    $result = $bufferMethod->invoke($service, $time, '06:00:00', '23:00:00', 180);
    $status = $result ? '✓ ACCEPT' : '✗ REJECT';
    $correct = (strpos($expected, 'ACCEPT') !== false) === $result;

    echo sprintf(
        "%s: %s | %s %s\n",
        $time,
        $status,
        $expected,
        $correct ? '✓' : '❌ BUG!'
    );
}

echo "\n=== Specific Issue: Editing ID 29 to 00:00 ===\n";
$result = $service->updateEvent(29, ['time' => '00:00', 'comment' => 'night shift start kk']);
echo "Result: " . ($result['status'] ? "✓ ACCEPTED" : "✗ REJECTED") . "\n";
echo "Message: " . $result['message'] . "\n";

if (!$result['status'] && strpos($result['message'], 'outside the allowed buffer') !== false) {
    echo "\n❌ BUG CONFIRMED: 00:00 is being rejected by buffer validation\n";
    echo "But 00:00 (0 minutes) should be <= 120 minutes (02:00), so it should pass!\n";
} else if (!$result['status']) {
    echo "\n⚠️  Rejected for different reason: " . $result['message'] . "\n";
} else {
    echo "\n✅ Fixed: 00:00 correctly accepted\n";
}
