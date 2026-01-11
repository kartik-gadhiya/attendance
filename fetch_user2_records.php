<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\UserTimeClock;

echo "User ID 2 Records on 2026-01-07:\n\n";

$records = UserTimeClock::where('user_id', 2)
    ->where('date_at', '2026-01-07')
    ->orderBy('formated_date_time')
    ->get(['id', 'type', 'time_at', 'formated_date_time']);

if ($records->isEmpty()) {
    echo "No records found for User ID 2 on 2026-01-07\n";
} else {
    foreach ($records as $record) {
        echo sprintf(
            "ID: %d, Type: %-12s, Time: %s, Formatted: %s\n",
            $record->id,
            $record->type,
            $record->time_at,
            $record->formated_date_time
        );
    }
    echo "\nTotal records: " . $records->count() . "\n";
}
