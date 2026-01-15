<?php
/**
 * Reproduction test for break_end scenario
 */
require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\UserTimeClock;
use App\Services\UserTimeClockService;

$service = new UserTimeClockService();
$shopId = 1;
$userId = 5;
$testDate = '2026-01-07';

// Clean existing
UserTimeClock::where('user_id', $userId)->where('date_at', $testDate)->delete();

$rows = [
    ['time' => '11:10:00', 'type' => 'day_in', 'comment' => 'sdfsf'],
    ['time' => '12:05:00', 'type' => 'break_start', 'comment' => '1232123'],
    ['time' => '12:10:00', 'type' => 'break_start', 'comment' => 'sdfsdf'],
    ['time' => '17:00:00', 'type' => 'break_end', 'comment' => 'adfsdf'],
    ['time' => '17:15:00', 'type' => 'break_start', 'comment' => 'fghf'],
    ['time' => '18:00:00', 'type' => 'day_out', 'comment' => 'dfgdgerte'],
    ['time' => '23:30:00', 'type' => 'day_in', 'comment' => 'sdfsdf'],
    ['time' => '23:45:00', 'type' => 'break_start', 'comment' => 'sdfsdf'],
    ['time' => '00:05:00', 'type' => 'break_end', 'comment' => 'sdfsdf', 'date_time' => '2026-01-08 00:05:00', 'formated' => '2026-01-08 00:05:00'],
    ['time' => '00:20:00', 'type' => 'day_out', 'comment' => 'dsfsdf', 'date_time' => '2026-01-08 00:20:00', 'formated' => '2026-01-08 00:20:00'],
    ['time' => '00:25:00', 'type' => 'day_in', 'comment' => 'dsfsdf', 'date_time' => '2026-01-08 00:25:00', 'formated' => '2026-01-08 00:25:00'],
    ['time' => '00:30:00', 'type' => 'break_start', 'comment' => 'adsfsdf', 'date_time' => '2026-01-08 00:30:00', 'formated' => '2026-01-08 00:30:00'],
    ['time' => '00:34:00', 'type' => 'break_end', 'comment' => 'asdfd', 'date_time' => '2026-01-08 00:34:00', 'formated' => '2026-01-08 00:34:00'],
    ['time' => '00:35:00', 'type' => 'day_out', 'comment' => 'dfgdfg', 'date_time' => '2026-01-08 00:35:00', 'formated' => '2026-01-08 00:35:00'],
    ['time' => '00:45:00', 'type' => 'day_in', 'comment' => 'dfgdf', 'date_time' => '2026-01-08 00:45:00', 'formated' => '2026-01-08 00:45:00'],
];

foreach ($rows as $r) {
    $date_time = $r['date_time'] ?? ($testDate . ' ' . $r['time']);
    $formated = $r['formated'] ?? ($testDate . ' ' . $r['time']);
    UserTimeClock::create([
        'shop_id' => $shopId,
        'user_id' => $userId,
        'date_at' => $testDate,
        'time_at' => $r['time'],
        'date_time' => $date_time,
        'formated_date_time' => $formated,
        'shift_start' => '09:00:00',
        'shift_end' => '22:00:00',
        'type' => $r['type'],
        'comment' => $r['comment'] ?? null,
        'buffer_time' => 3,
        'created_from' => 'B',
        'updated_from' => 'B',
    ]);
}

echo "Inserted test data.\n";

// Now attempt to add break_end at 12:07
$payload = [
    'shop_id' => $shopId,
    'user_id' => $userId,
    'clock_date' => $testDate,
    'time' => '12:07',
    'type' => 'break_end',
    'comment' => 'Morning shift end',
    'shift_start' => '06:00',
    'shift_end' => '22:00',
    'buffer_time' => 3,
    'created_from' => 'A',
    'updated_from' => 'A',
];

$result = $service->breakEndAdd($payload);

echo "Result:\n";
print_r($result);

return 0;
