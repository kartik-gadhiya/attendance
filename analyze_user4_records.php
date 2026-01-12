<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\UserTimeClock;

echo "=== Analyzing SQL Records for User 4 on 2026-01-01 ===\n\n";

$events = UserTimeClock::where('user_id', 4)
    ->where('date_at', '2026-01-01')
    ->orderBy('formated_date_time')
    ->get();

echo "Chronological order by formated_date_time:\n";
foreach ($events as $event) {
    echo sprintf(
        "ID %2d: %s %-12s time_at=%s formatted=%s\n",
        $event->id,
        $event->formated_date_time,
        $event->type,
        $event->time_at,
        $event->formated_date_time
    );
}

echo "\n=== ISSUE ANALYSIS ===\n";
echo "Record ID 29 is 'day_out' at time_at=11:00\n";
echo "But chronologically it should be AFTER:\n";
echo "  - ID 28 (day_in at 12:30)\n";
echo "  - ID 30 (break_start at 14:30)\n";
echo "  - ID 31 (break_end at 15:30)\n\n";

echo "The problem: formated_date_time is NOT being calculated correctly!\n";
echo "If original time was 00:30 (next day), formated_date_time should be 2026-01-02 00:30\n";
echo "But it's showing 2026-01-01, which puts it in wrong chronological position.\n";
