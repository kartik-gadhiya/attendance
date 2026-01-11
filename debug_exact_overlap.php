<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\UserTimeClock;
use Carbon\Carbon;

echo "Testing EXACT TIME overlap:\n\n";

// Get record 18 and its previous event
$event = UserTimeClock::find(18);
echo "Event ID 18: {$event->type} at {$event->time_at->format('H:i:s')} (formatted: {$event->formated_date_time})\n";

$previousEvents = UserTimeClock::where('user_id', 2)
    ->where('date_at', $event->date_at)
    ->where('formated_date_time', '<', $event->formated_date_time)
    ->orderBy('formated_date_time', 'desc')
    ->first();

if ($previousEvents) {
    echo "Previous event: ID {$previousEvents->id}, {$previousEvents->type} at {$previousEvents->time_at->format('H:i:s')} (formatted: {$previousEvents->formated_date_time})\n";

    $previousTime = Carbon::parse($previousEvents->time_at)->format('H:i');
    echo "\nTest: Edit event 18 to same time as previous event: {$previousTime}\n";

    // Simulate the validation
    $service = new \App\Services\UserTimeClockService();
    $result = $service->updateEvent(18, [
        'shop_id' => 1,
        'user_id' => 2,
        'clock_date' => '2026-01-07',
        'time' => $previousTime,
        'type' => 'break_start',
        'shift_start' => '08:00',
        'shift_end' => '23:00',
        'buffer_time' => 3,
    ]);

    echo "\nResult: " . json_encode($result, JSON_PRETTY_PRINT) . "\n";
}
