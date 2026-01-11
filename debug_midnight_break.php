<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\User;
use App\Models\UserTimeClock;

// Clean up first
UserTimeClock::where('shop_id', 1)->delete();

// Create user
$user = User::factory()->create();

// Simulate the scenario
$data = [
    'shop_id' => 1,
    'user_id' => $user->id,
    'clock_date' => '2026-01-01',
    'shift_start' => '08:00',
    'shift_end' => '23:00',
    'buffer_time' => 3,
];

// Create events up to the failing point
$events = [
    ['time' => '08:00', 'type' => 'day_in'],
    ['time' => '09:00', 'type' => 'break_start'],
    ['time' => '10:00', 'type' => 'break_end'],
    ['time' => '12:00', 'type' => 'day_out'],
    ['time' => '13:00', 'type' => 'day_in'],
    ['time' => '14:00', 'type' => 'day_out'],
    ['time' => '15:00', 'type' => 'day_in'],
    ['time' => '16:00', 'type' => 'break_start'],
    ['time' => '17:00', 'type' => 'break_end'],
    ['time' => '20:00', 'type' => 'break_start'],
    ['time' => '21:00', 'type' => 'break_end'],
    ['time' => '23:30', 'type' => 'break_start'],
];

$service = new \App\Services\UserTimeClockService();

foreach ($events as $event) {
    $requestData = array_merge($data, $event);

    $result = match ($event['type']) {
        'day_in' => $service->dayInAdd($requestData),
        'day_out' => $service->dayOutAdd($requestData),
        'break_start' => $service->breakStartAdd($requestData),
        'break_end' => $service->breakEndAdd($requestData),
    };

    if (!$result['status']) {
        echo "Failed at {$event['time']} - {$event['type']}: {$result['message']}\n";
        exit(1);
    }
    echo "✓ {$event['time']} - {$event['type']}\n";
}

// Now try the midnight-crossing break end
echo "\nTesting midnight-crossing break end at 00:30...\n";
$requestData = array_merge($data, ['time' => '00:30', 'type' => 'break_end']);
$result = $service->breakEndAdd($requestData);

if (!$result['status']) {
    echo "FAILED: {$result['message']}\n";
    exit(1);
} else {
    echo "SUCCESS!\n";
}
