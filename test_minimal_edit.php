<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Services\UserTimeClockService;

echo "Testing minimal edit payload:\n\n";

$service = new UserTimeClockService();

// Test editing with minimal payload (only time, type, comment)
$result = $service->updateEvent(18, [
    'time' => '07:20',
    'type' => 'break_start',
    'comment' => 'Morning shift start'
]);

echo "Result:\n";
echo json_encode($result, JSON_PRETTY_PRINT) . "\n";

if ($result['status']) {
    echo "\n✓ Edit successful with minimal payload!\n";
    echo "Updated time: " . $result['data']->time_at . "\n";
    echo "Updated type: " . $result['data']->type . "\n";
    echo "Updated comment: " . $result['data']->comment . "\n";
} else {
    echo "\n✗ Edit failed: " . $result['message'] . "\n";
}
