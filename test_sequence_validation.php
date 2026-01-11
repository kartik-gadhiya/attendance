<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Services\UserTimeClockService;
use App\Models\UserTimeClock;

echo "=== Testing Event Sequence Validation Fix ===\n\n";

$service = new UserTimeClockService();

// Test 1: Try to edit break_end (ID 19) to come before its break_start (ID 18)
echo "Test 1: Edit break_end to come before break_start\n";
echo "Current: break_start at 07:25, break_end at 07:15 (WRONG ORDER)\n";
echo "Try: Edit break_end ID 19 from 07:15 to 07:10\n";

$result1 = $service->updateEvent(19, [
    'time' => '07:10',
    'comment' => 'Test edit'
]);

echo "Result: " . ($result1['status'] ? "✓ ACCEPTED" : "✗ REJECTED") . "\n";
echo "Message: " . $result1['message'] . "\n\n";

// Test 2: Try to edit break_start (ID 18) to come after break_end (ID 19)
echo "Test 2: Edit break_start to come after break_end\n";
echo "Try: Edit break_start ID 18 from 07:25 to 07:30\n";

$result2 = $service->updateEvent(18, [
    'time' => '07:30',
    'comment' => 'Test edit'
]);

echo "Result: " . ($result2['status'] ? "✓ ACCEPTED" : "✗ REJECTED") . "\n";
echo "Message: " . $result2['message'] . "\n\n";

// Test 3: Try valid edit - edit break_end to valid time after break_start
echo "Test 3: Valid edit - break_end after break_start\n";
echo "Try: Edit break_end ID 19 from 07:15 to 07:30 (after 07:25)\n";

$result3 = $service->updateEvent(19, [
    'time' => '07:30',
    'comment' => 'Valid edit'
]);

echo "Result: " . ($result3['status'] ? "✓ ACCEPTED" : "✗ REJECTED") . "\n";
echo "Message: " . $result3['message'] . "\n\n";

// Test 4: Now that sequence is correct, verify we can't break it again
echo "Test 4: Try to break sequence again\n";
echo "Current: break_start at 07:25, break_end at 07:30 (CORRECT ORDER)\n";
echo "Try: Edit break_start ID 18 to 07:35 (after break_end)\n";

$result4 = $service->updateEvent(18, [
    'time' => '07:35',
    'comment' => 'Test edit'
]);

echo "Result: " . ($result4['status'] ? "✓ ACCEPTED" : "✗ REJECTED") . "\n";
echo "Message: " . $result4['message'] . "\n\n";

// Summary
echo "=== Summary ===\n";
echo "Test 1 (break_end before break_start): " . ($result1['status'] ? "❌ FAIL - should reject" : "✅ PASS - correctly rejected") . "\n";
echo "Test 2 (break_start after break_end): " . ($result2['status'] ? "❌ FAIL - should reject" : "✅ PASS - correctly rejected") . "\n";
echo "Test 3 (valid sequence): " . ($result3['status'] ? "✅ PASS - correctly accepted" : "❌ FAIL - should accept") . "\n";
echo "Test 4 (break sequence again): " . ($result4['status'] ? "❌ FAIL - should reject" : "✅ PASS - correctly rejected") . "\n";

// Check final state
echo "\n=== Final Record State ===\n";
$record18 = UserTimeClock::find(18);
$record19 = UserTimeClock::find(19);

echo "ID 18 (break_start): " . $record18->time_at . "\n";
echo "ID 19 (break_end): " . $record19->time_at . "\n";

if ($record18->time_at < $record19->time_at) {
    echo "✅ Sequence is CORRECT: break_start < break_end\n";
} else {
    echo "❌ Sequence is WRONG: break_start >= break_end\n";
}
