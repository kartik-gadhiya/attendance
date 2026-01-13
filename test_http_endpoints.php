<?php

require 'vendor/autoload.php';

// Initialize Laravel application
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\UserTimeClock;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Carbon\Carbon;

/**
 * Test the actual HTTP endpoints to ensure break_end works correctly
 */

$testDate = now()->format('Y-m-d');
$userId = 12;
$shopId = 1;

// Clean up test data
UserTimeClock::where('user_id', $userId)->where('date_at', $testDate)->delete();

echo "\n" . str_repeat("=", 80) . "\n";
echo "HTTP ENDPOINT TEST: Break End Validation\n";
echo str_repeat("=", 80) . "\n\n";

$baseUrl = 'http://localhost:8000';

// Test 1: Day In
echo "1. Testing Day In at 8:00 AM...\n";
$response1 = Http::post("$baseUrl/api/time-clock", [
    'shop_id' => $shopId,
    'user_id' => $userId,
    'clock_date' => $testDate,
    'time' => '08:00',
    'shift_start' => '08:00',
    'shift_end' => '23:00',
    'type' => 'day_in',
    'buffer_time' => 3,
]);

if ($response1->successful()) {
    echo "✓ Success (201): " . $response1->json('message') . "\n\n";
} else {
    echo "✗ Failed (" . $response1->status() . "): " . json_encode($response1->json()) . "\n\n";
}

// Test 2: Break Start
echo "2. Testing Break Start at 9:00 AM...\n";
$response2 = Http::post("$baseUrl/api/time-clock", [
    'shop_id' => $shopId,
    'user_id' => $userId,
    'clock_date' => $testDate,
    'time' => '09:00',
    'shift_start' => '08:00',
    'shift_end' => '23:00',
    'type' => 'break_start',
    'buffer_time' => 3,
]);

if ($response2->successful()) {
    echo "✓ Success (201): " . $response2->json('message') . "\n\n";
} else {
    echo "✗ Failed (" . $response2->status() . "): " . json_encode($response2->json()) . "\n\n";
}

// Test 3: Break End - CRITICAL TEST
echo "3. Testing Break End at 10:00 AM (CRITICAL TEST)...\n";
$response3 = Http::post("$baseUrl/api/time-clock", [
    'shop_id' => $shopId,
    'user_id' => $userId,
    'clock_date' => $testDate,
    'time' => '10:00',
    'shift_start' => '08:00',
    'shift_end' => '23:00',
    'type' => 'break_end',
    'buffer_time' => 3,
]);

if ($response3->successful()) {
    echo "✓ Success (201): " . $response3->json('message') . "\n";
    echo "✓✓✓ BREAK END VALIDATION WORKS CORRECTLY ✓✓✓\n\n";
} else {
    echo "✗ Failed (" . $response3->status() . "): " . json_encode($response3->json()) . "\n\n";
}

// Test 4: Day Out
echo "4. Testing Day Out at 11:00 PM...\n";
$response4 = Http::post("$baseUrl/api/time-clock", [
    'shop_id' => $shopId,
    'user_id' => $userId,
    'clock_date' => $testDate,
    'time' => '23:00',
    'shift_start' => '08:00',
    'shift_end' => '23:00',
    'type' => 'day_out',
    'buffer_time' => 3,
]);

if ($response4->successful()) {
    echo "✓ Success (201): " . $response4->json('message') . "\n\n";
} else {
    echo "✗ Failed (" . $response4->status() . "): " . json_encode($response4->json()) . "\n\n";
}

// Summary
echo str_repeat("=", 80) . "\n";
echo "HTTP ENDPOINT TEST COMPLETE\n";
echo str_repeat("=", 80) . "\n";

// Display saved records
echo "\nRecords saved to database:\n";
$records = UserTimeClock::where('user_id', $userId)
    ->where('date_at', $testDate)
    ->orderBy('time_at')
    ->get();

foreach ($records as $record) {
    echo sprintf(
        "  %s: %s at %s\n",
        $record->id,
        strtoupper(str_replace('_', ' ', $record->type)),
        $record->time_at->format('H:i')
    );
}

echo "\n";
