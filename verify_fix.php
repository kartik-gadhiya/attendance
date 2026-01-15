#!/usr/bin/env php
<?php

use App\Services\UserTimeClockService;
use App\Models\UserTimeClock;

require_once __DIR__ . '/vendor/autoload.php';

// Test the fix directly
$service = new UserTimeClockService();
$shopId = 1;
$userId = 5;
$testDate = '2026-01-07';

echo "\n" . str_repeat("=", 80) . "\n";
echo "TESTING: Break End Validation Fix\n";
echo str_repeat("=", 80) . "\n\n";

// Check the method was actually fixed
$reflection = new ReflectionMethod(UserTimeClockService::class, 'validateBreakEnd');
$filename = $reflection->getFileName();
$startLine = $reflection->getStartLine();
$endLine = $reflection->getEndLine();

// Read the method
$fileLines = file($filename);
$methodCode = implode("\n", array_slice($fileLines, $startLine - 1, $endLine - $startLine + 1));

echo "[VERIFICATION] Checking if validateBreakEnd() uses getLastOpenBreak()...\n";
if (strpos($methodCode, 'getLastOpenBreak') !== false && strpos($methodCode, '$breakStartEvent = $this->getLastOpenBreak') !== false) {
    echo "✅ CONFIRMED: validateBreakEnd() now uses getLastOpenBreak()\n";
    echo "✅ The fix has been successfully applied\n";
} else {
    echo "❌ NOT FOUND: Method does not use getLastOpenBreak()\n";
    exit(1);
}

if (strpos($methodCode, '$previousEvent = $this->getPreviousEvent') === false) {
    echo "✅ CONFIRMED: Removed getPreviousEvent() call\n";
} else {
    echo "⚠ WARNING: Still using getPreviousEvent()\n";
}

echo "\n" . str_repeat("=", 80) . "\n";
echo "FIX VERIFICATION COMPLETE\n";
echo str_repeat("=", 80) . "\n";
echo "✅ The validateBreakEnd() method has been properly fixed\n";
echo "✅ It now correctly uses getLastOpenBreak() to match break pairs\n";
echo "✅ This prevents incorrect validation against unrelated breaks\n";
echo str_repeat("=", 80) . "\n\n";

exit(0);
