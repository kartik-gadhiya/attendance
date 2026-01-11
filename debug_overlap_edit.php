<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\UserTimeClock;
use Carbon\Carbon;

echo "Testing overlap scenario:\n\n";

// Get record 19 (break_end at 07:30, previous at 07:15)
$event = UserTimeClock::find(19);
echo "Event ID 19: {$event->type} at {$event->time_at}\n";

// Get all events for same date
$allEvents = UserTimeClock::where('user_id', 2)
    ->where('date_at', $event->date_at)
    ->where('id', '!=', 19)
    ->orderBy('formated_date_time', 'asc')
    ->get();

echo "\nAll other events:\n";
foreach ($allEvents as $e) {
    echo "ID {$e->id}: {$e->type} at {$e->time_at} (formatted: {$e->formated_date_time})\n";
}

// Simulate editing to 07:15
$service = new \App\Services\UserTimeClockService();
$reflection = new \ReflectionClass($service);

// Get shift times
$shiftTimes = ['shift_start' => '08:00:00', 'shift_end' => '23:00:00'];

// Calculate new datetime for 07:15
$dateOnly = Carbon::parse($event->date_at)->format('Y-m-d');
$normalizeMethod = $reflection->getMethod('normalizeDateTime');
$normalizeMethod->setAccessible(true);
$newDateTime = $normalizeMethod->invoke($service, $dateOnly, '07:15:00', '08:00:00', '23:00:00');

echo "\nNew datetime for 07:15: " . $newDateTime['formated_date_time'] . "\n";

$newFormattedDateTime = Carbon::parse($newDateTime['formated_date_time']);

// Find previous and next
$previous = $allEvents->filter(
    fn($e) =>
    Carbon::parse($e->formated_date_time)->lessThan($newFormattedDateTime)
)->last();

$next = $allEvents->filter(
    fn($e) =>
    Carbon::parse($e->formated_date_time)->greaterThan($newFormattedDateTime)
)->first();

echo "\nPrevious event: ";
if ($previous) {
    echo "ID {$previous->id}: {$previous->type} at {$previous->time_at} (formatted: {$previous->formated_date_time})\n";
    $prevTime = Carbon::parse($previous->formated_date_time);
    echo "Comparison: new ({$newFormattedDateTime}) <= previous ({$prevTime}) = " . ($newFormattedDateTime->lessThanOrEqualTo($prevTime) ? "TRUE (SHOULD REJECT)" : "FALSE (SHOULD ACCEPT)") . "\n";
} else {
    echo "None\n";
}

echo "\nNext event: ";
if ($next) {
    echo "ID {$next->id}: {$next->type} at {$next->time_at} (formatted: {$next->formated_date_time})\n";
    $nextTime = Carbon::parse($next->formated_date_time);
    echo "Comparison: new ({$newFormattedDateTime}) >= next ({$nextTime}) = " . ($newFormattedDateTime->greaterThanOrEqualTo($nextTime) ? "TRUE (SHOULD REJECT)" : "FALSE (SHOULD ACCEPT)") . "\n";
} else {
    echo "None\n";
}
