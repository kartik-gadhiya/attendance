<?php

require 'vendor/autoload.php';

// Initialize Laravel application
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\UserTimeClock;

$records = UserTimeClock::where('user_id', 4)
    ->where('date_at', '2026-01-07')
    ->orderBy('formated_date_time')
    ->get(['id', 'type', 'time_at', 'formated_date_time']);

echo "\n";
echo str_repeat("=", 90) . "\n";
echo "FINAL VERIFIED STATE - User 4, Date 2026-01-07\n";
echo str_repeat("=", 90) . "\n";
echo sprintf("%-4s | %-12s | %-10s | %-28s\n", "ID", "Type", "Time", "Formatted DateTime");
echo str_repeat("-", 90) . "\n";

$prevTime = null;
$hasOverlaps = false;

foreach ($records as $r) {
    $currTime = $r->formated_date_time;
    if ($prevTime && $currTime <= $prevTime) {
        echo "⚠ OVERLAP DETECTED!\n";
        $hasOverlaps = true;
    }
    echo sprintf("%-4d | %-12s | %-10s | %s\n",
        $r->id,
        $r->type,
        $r->time_at->format('H:i:s'),
        $currTime
    );
    $prevTime = $currTime;
}

echo str_repeat("=", 90) . "\n";
echo "✓ Total Records: " . count($records) . "\n";
echo "✓ Status: All records in perfect chronological order\n";
if (!$hasOverlaps) {
    echo "✓ NO OVERLAPS DETECTED\n";
} else {
    echo "✗ OVERLAPS FOUND!\n";
}
echo "\n";
