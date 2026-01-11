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
    'clock_date' => '2026-01-03',
    'shift_start' => '08:00',
    'shift_end' => '23:00',
    'buffer_time' => 3,
];

$service = new \App\Services\UserTimeClockService();

// Day In at 05:00
$result = $service->dayInAdd(array_merge($data, ['time' => '05:00', 'type' => 'day_in']));
echo "Day In at 05:00: " . ($result['status'] ? "✓" : "✗ {$result['message']}") . "\n";

// Break Start at 06:00
$result = $service->breakStartAdd(array_merge($data, ['time' => '06:00', 'type' => 'break_start']));
echo "Break Start at 06:00: " . ($result['status'] ? "✓" : "✗ {$result['message']}") . "\n";

// Break End at 07:00
$result = $service->breakEndAdd(array_merge($data, ['time' => '07:00', 'type' => 'break_end']));
echo "Break End at 07:00: " . ($result['status'] ? "✓" : "✗ {$result['message']}") . "\n";

// Break Start at 06:45 (should succeed - only break END should check overlap)
$result = $service->breakStartAdd(array_merge($data, ['time' => '06:45', 'type' => 'break_start']));
echo "\nBreak Start at 06:45: " . ($result['status'] ? "✓" : "✗ {$result['message']}") . "\n";

if (!$result['status']) {
    echo "\nThis should have succeeded! Break overlap should only be checked on break_end.\n";
}
