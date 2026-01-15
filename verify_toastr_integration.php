<?php
/**
 * Test: Verify Toastr.js Integration in Both Files
 * Checks that both Blade template and static HTML have Toastr properly integrated
 */

echo "\n" . "=".str_repeat("=", 70) . "\n";
echo "Toastr.js Integration Verification Test\n";
echo "=".str_repeat("=", 70) . "\n\n";

$files = [
    '/opt/homebrew/var/www/attendence-logic/resources/views/time-clock/index.blade.php' => 'Blade Template',
    '/opt/homebrew/var/www/attendence-logic/public/time-clock.html' => 'Static HTML File'
];

$totalTests = 0;
$totalPassed = 0;

foreach ($files as $filePath => $fileName) {
    echo "Testing: $fileName\n";
    echo "File: $filePath\n";
    echo str_repeat("-", 70) . "\n";

    if (!file_exists($filePath)) {
        echo "✗ FAIL: File not found\n\n";
        continue;
    }

    $content = file_get_contents($filePath);

    $tests = [
        [
            'name' => 'Toastr CSS CDN included',
            'check' => fn($c) => strpos($c, 'toastr.min.css') !== false,
        ],
        [
            'name' => 'Toastr JS CDN included',
            'check' => fn($c) => strpos($c, 'toastr.min.js') !== false,
        ],
        [
            'name' => 'Toastr initialization is safe (checks if defined)',
            'check' => fn($c) => strpos($c, "if (typeof toastr !== 'undefined')") !== false,
        ],
        [
            'name' => 'Toastr options configured',
            'check' => fn($c) => strpos($c, '"closeButton": true') !== false,
        ],
        [
            'name' => 'showAlert() checks for toastr availability',
            'check' => fn($c) => preg_match('/function showAlert\(.*?\{.*?typeof toastr.*?\}/s', $c),
        ],
        [
            'name' => 'showAlert() has try-catch for error handling',
            'check' => fn($c) => preg_match('/showAlert.*?try\s*\{.*?catch.*?\}/s', $c),
        ],
        [
            'name' => 'showAlert() uses toastr.success()',
            'check' => fn($c) => strpos($c, 'toastr.success(') !== false,
        ],
        [
            'name' => 'showAlert() uses toastr.error()',
            'check' => fn($c) => strpos($c, 'toastr.error(') !== false,
        ],
        [
            'name' => 'No alertContainer in HTML (removed)',
            'check' => fn($c) => strpos($c, '<div id="alertContainer"></div>') === false,
        ],
        [
            'name' => 'Console logging added for debugging',
            'check' => fn($c) => strpos($c, 'console.log("Submitting form data:"') !== false,
        ],
        [
            'name' => 'Server response error logging added',
            'check' => fn($c) => strpos($c, 'console.error("Server response:"') !== false,
        ],
    ];

    $passed = 0;
    $failed = 0;

    foreach ($tests as $test) {
        $totalTests++;
        $result = call_user_func($test['check'], $content);

        if ($result) {
            echo "  ✓ {$test['name']}\n";
            $passed++;
            $totalPassed++;
        } else {
            echo "  ✗ {$test['name']}\n";
            $failed++;
        }
    }

    echo "\n  Results: $passed/{count($tests)} passed\n\n";
}

echo "=".str_repeat("=", 70) . "\n";
echo "Overall Results: $totalPassed/$totalTests tests passed\n";
echo "=".str_repeat("=", 70) . "\n";

if ($totalPassed === $totalTests) {
    echo "\n✓ SUCCESS: All files properly integrated with Toastr.js!\n";
    echo "\nThe following improvements are active:\n";
    echo "  • Toast notifications for all messages\n";
    echo "  • Graceful fallback if CDN fails\n";
    echo "  • Better error diagnostics in console\n";
    echo "  • Unified experience across both routes\n";
    echo "\n";
} else {
    echo "\n✗ Some checks failed. Please review the implementation.\n\n";
}

echo "=".str_repeat("=", 70) . "\n";
?>
