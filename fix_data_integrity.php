<?php

require 'vendor/autoload.php';

// Initialize Laravel application
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\UserTimeClock;

// Fix data integrity issues
$record188 = UserTimeClock::find(188);
$record200 = UserTimeClock::find(200);

echo "Fixing data integrity issues...\n\n";

if ($record188) {
    echo "Before fix - ID 188:\n";
    echo "  time_at: " . $record188->time_at->format('H:i:s') . "\n";
    echo "  formated_date_time: " . $record188->formated_date_time . "\n";
    
    $record188->update(['formated_date_time' => '2026-01-07 17:31:00']);
    $record188->refresh();
    
    echo "After fix - ID 188:\n";
    echo "  time_at: " . $record188->time_at->format('H:i:s') . "\n";
    echo "  formated_date_time: " . $record188->formated_date_time . "\n\n";
}

if ($record200) {
    echo "Before fix - ID 200:\n";
    echo "  time_at: " . $record200->time_at->format('H:i:s') . "\n";
    echo "  formated_date_time: " . $record200->formated_date_time . "\n";
    
    $record200->update(['formated_date_time' => '2026-01-07 18:14:00']);
    $record200->refresh();
    
    echo "After fix - ID 200:\n";
    echo "  time_at: " . $record200->time_at->format('H:i:s') . "\n";
    echo "  formated_date_time: " . $record200->formated_date_time . "\n\n";
}

echo "✓ Data integrity fixed!\n";
