<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$service = new \App\Services\UserTimeClockService();

use Carbon\Carbon;

// Test isWithinBufferTime logic directly
$shiftStart = '08:00:00';
$shiftEnd = '23:00:00';
$bufferMinutes = 180; // 3 hours



$eventCarbon = Carbon::createFromFormat('H:i:s', '04:59:00');
$shiftStartCarbon = Carbon::createFromFormat('H:i:s', $shiftStart);
$shiftEndCarbon = Carbon::createFromFormat('H:i:s', $shiftEnd);

$allowedStart = $shiftStartCarbon->copy()->subMinutes($bufferMinutes);
$allowedEnd = $shiftEndCarbon->copy()->addMinutes($bufferMinutes);

echo "Shift: $shiftStart - $shiftEnd\n";
echo "Buffer: $bufferMinutes minutes\n";
echo "Allowed Start: " . $allowedStart->format('H:i:s') . "\n";
echo "Allowed End: " . $allowedEnd->format('H:i:s') . "\n";
echo "Allowed End Hour: " . $allowedEnd->hour . "\n";
echo "Allowed Start Hour: " . $allowedStart->hour . "\n";
echo "\n";

// Check if buffer window crosses midnight
if ($allowedEnd->hour < $allowedStart->hour) {
    echo "Buffer window crosses midnight!\n";
} else {
    echo "Buffer window does NOT cross midnight\n";
}

echo "\nTesting times:\n";

$testTimes = ['04:59', '05:00', '01:20', '02:00', '02:01'];

foreach ($testTimes as $time) {
    $normalizedData = ['time' => $time, 'buffer_time' => 3];
    if (strlen($normalizedData['time']) === 5) {
        $normalizedData['time'] .= ':00';
    }
    if (isset($normalizedData['buffer_time'])) {
        $normalizedData['buffer_time'] = $normalizedData['buffer_time'] * 60;
    }

    // Call the method via reflection
    $reflection = new \ReflectionClass($service);
    $method = $reflection->getMethod('isWithinBufferTime');
    $method->setAccessible(true);

    $result = $method->invoke($service, $normalizedData['time'], $shiftStart, $shiftEnd, $normalizedData['buffer_time']);

    echo "$time: " . ($result ? "ACCEPTED ✓" : "REJECTED ✗") . "\n";
}
