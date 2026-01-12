<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Services\UserTimeClockService;
use App\Models\UserTimeClock;

echo "=== Analyzing ID 43 Issue: day_out with Unclosed Break ===\n\n";

// Display the sequence
$events = UserTimeClock::where('user_id', 2)
    ->where('date_at', '2026-01-09')
    ->orderBy('formated_date_time')
    ->get();

echo "Chronological sequence for User 2 on 2026-01-09:\n";
foreach ($events as $event) {
    echo sprintf(
        "ID %2d: %s %-12s\n",
        $event->id,
        $event->formated_date_time,
        $event->type
    );
}

echo "\n=== ISSUE DETECTED ===\n";
echo "ID 39: 23:30 break_start (OPENED)\n";
echo "ID 43: 00:00 day_out ❌ INVALID - break is still open!\n";
echo "ID 40: 00:30 break_end (CLOSED)\n\n";

echo "This violates RULE 4: day_out cannot come after unclosed break_start\n\n";

// Test if the system would allow this now
echo "=== Testing Current Validation ===\n";
echo "Attempting to add day_out at 00:00 with unclosed break at 23:30...\n\n";

$service = new UserTimeClockService();

// Try to create day_out when there's an unclosed break_start
$testData = [
    'shop_id' => 1,
    'user_id' => 2,
    'clock_date' => '2026-01-09',
    'time' => '00:00',
    'type' => 'day_out',
    'shift_start' => '06:00',
    'shift_end' => '22:00',
    'buffer_time' => 180,
    'comment' => 'Test'
];

// Check if there's an unclosed break
$lastBreakStart = UserTimeClock::where('shop_id', 1)
    ->where('user_id', 2)
    ->where('date_at', '2026-01-09')
    ->where('type', 'break_start')
    ->whereNotIn('id', function ($query) {
        $query->select('break_starts.id')
            ->from('user_time_clock as break_starts')
            ->join('user_time_clock as break_ends', function ($join) {
                $join->on('break_starts.user_id', '=', 'break_ends.user_id')
                    ->on('break_starts.date_at', '=', 'break_ends.date_at')
                    ->on('break_starts.shop_id', '=', 'break_ends.shop_id')
                    ->whereColumn('break_ends.formated_date_time', '>', 'break_starts.formated_date_time')
                    ->where('break_ends.type', '=', 'break_end');
            })
            ->where('break_starts.shop_id', 1)
            ->where('break_starts.user_id', 2)
            ->where('break_starts.date_at', '2026-01-09');
    })
    ->orderBy('formated_date_time', 'desc')
    ->first();

if ($lastBreakStart) {
    echo "Found unclosed break_start: ID {$lastBreakStart->id} at {$lastBreakStart->formated_date_time}\n";
    echo "This should BLOCK adding day_out!\n\n";
} else {
    echo "No unclosed break found (unexpected).\n\n";
}

echo "Root Cause Analysis:\n";
echo "- CREATE operation may not be checking for unclosed breaks\n";
echo "- EDIT operation has this validation (RULE 4)\n";
echo "- But CREATE flow might be missing this check\n";
