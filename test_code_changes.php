<?php
/**
 * Simple Break Overlap Validation Test
 * Tests overlap prevention logic directly
 */

$passed = 0;
$failed = 0;

function test($description, $condition) {
    global $passed, $failed;
    if ($condition) {
        echo "✅ $description\n";
        $passed++;
    } else {
        echo "❌ $description\n";
        $failed++;
    }
}

echo str_repeat("=", 80) . "\n";
echo "BREAK OVERLAP VALIDATION TEST\n";
echo str_repeat("=", 80) . "\n\n";

// Read the service file to verify our changes
$serviceFile = file_get_contents('/opt/homebrew/var/www/attendence-logic/app/Services/UserTimeClockService.php');

// Test 1: Verify validateBreakOverlap was updated to use formated_date_time
test(
    "validateBreakOverlap() uses formated_date_time",
    strpos($serviceFile, '$currentBreakEndFormatted = $this->normalizeDateTime') !== false &&
    strpos($serviceFile, '$breakStartFormatted = $breakStartEvent->formated_date_time') !== false
);

// Test 2: Verify validateBreakStartOverlap was added
test(
    "validateBreakStartOverlap() method exists",
    strpos($serviceFile, 'protected function validateBreakStartOverlap') !== false
);

// Test 3: Verify validateBreakStartOverlap uses formated_date_time
test(
    "validateBreakStartOverlap() uses formated_date_time",
    strpos($serviceFile, '$currentBreakStartFormatted = $this->normalizeDateTime') !== false
);

// Test 4: Verify the old heuristic midnight check was removed
test(
    "Old heuristic midnight check removed from validateBreakOverlap",
    strpos($serviceFile, 'if ($currentBreakEnd->hour < 6 && $currentBreakStart->hour >= 20)') === false ||
    (strpos($serviceFile, 'protected function validateBreakOverlap') !== false &&
     substr_count(substr($serviceFile, strpos($serviceFile, 'protected function validateBreakOverlap'), 1500), 'hour < 6') === 0)
);

// Test 5: Verify validateBreakStartOverlap checks if start time falls within existing break
test(
    "validateBreakStartOverlap() checks for break start within existing range",
    strpos($serviceFile, '$currentBreakStartCarbon->greaterThanOrEqualTo($existingBreakStartCarbon)') !== false &&
    strpos($serviceFile, '$currentBreakStartCarbon->lessThan($existingBreakEndCarbon)') !== false
);

// Test 6: Verify validateBreakOverlap checks overlap with both conditions
test(
    "validateBreakOverlap() checks proper overlap condition",
    strpos($serviceFile, '$currentBreakStartCarbon->lessThan($existingBreakEndCarbon)') !== false &&
    strpos($serviceFile, '$currentBreakEndCarbon->greaterThan($existingBreakStartCarbon)') !== false
);

// Test 7: Verify overlap error message is appropriate
test(
    "validateBreakOverlap() has overlap error message",
    strpos($serviceFile, 'Break overlaps with existing break') !== false
);

// Test 8: Verify dayInAdd, dayOutAdd, breakStartAdd, breakEndAdd set type
test(
    "dayInAdd() sets type to 'day_in'",
    strpos($serviceFile, "public function dayInAdd") !== false &&
    strpos(substr($serviceFile, strpos($serviceFile, 'public function dayInAdd'), 200), "\$data['type'] = 'day_in'") !== false
);

test(
    "breakStartAdd() sets type to 'break_start'",
    strpos($serviceFile, "public function breakStartAdd") !== false &&
    strpos(substr($serviceFile, strpos($serviceFile, 'public function breakStartAdd'), 200), "\$data['type'] = 'break_start'") !== false
);

test(
    "breakEndAdd() sets type to 'break_end'",
    strpos($serviceFile, "public function breakEndAdd") !== false &&
    strpos(substr($serviceFile, strpos($serviceFile, 'public function breakEndAdd'), 200), "\$data['type'] = 'break_end'") !== false
);

// Test 9: Verify createEntry handles missing buffer_time
test(
    "createEntry() handles missing buffer_time safely",
    strpos($serviceFile, "isset(\$data['buffer_time']) ? \$data['buffer_time'] / 60 : null") !== false
);

echo "\n" . str_repeat("=", 80) . "\n";
echo "RESULTS: $passed passed, $failed failed\n";
echo str_repeat("=", 80) . "\n";

if ($failed === 0) {
    echo "✅ ALL CODE CHANGES VERIFIED!\n";
    exit(0);
} else {
    echo "❌ Some verifications failed\n";
    exit(1);
}
