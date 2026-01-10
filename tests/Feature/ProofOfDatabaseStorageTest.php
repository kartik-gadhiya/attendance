<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\UserTimeClock;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Demonstration test to prove data IS stored during tests
 * 
 * This test explicitly shows database state at each step
 */
class ProofOfDatabaseStorageTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test that explicitly demonstrates data is stored in database during test execution
     */
    public function test_proof_that_data_is_stored_in_database(): void
    {
        $user = User::factory()->create();

        echo "\n\n=== PROOF OF DATABASE STORAGE ===\n\n";

        // Step 1: Check initial state
        echo "Step 1: Initial database state\n";
        echo "Records in user_time_clock: " . UserTimeClock::count() . "\n";
        echo "Expected: 0\n\n";
        $this->assertEquals(0, UserTimeClock::count());

        // Step 2: Make first API request
        echo "Step 2: Creating day-in entry\n";
        $response1 = $this->postJson('/api/time-clock', [
            'shop_id' => 1,
            'user_id' => $user->id,
            'clock_date' => '2026-01-15',
            'time' => '08:00:00',
            'shift_start' => '08:00:00',
            'shift_end' => '17:00:00',
            'type' => 'day_in',
            'buffer_time' => 180,
        ]);

        $response1->assertStatus(201);

        echo "Records in user_time_clock: " . UserTimeClock::count() . "\n";
        echo "Expected: 1\n";
        echo "Data IS in database! ✓\n\n";
        $this->assertEquals(1, UserTimeClock::count());

        // Step 3: Verify the record details
        echo "Step 3: Verifying record details\n";
        $record = UserTimeClock::first();
        echo "Record ID: " . $record->id . "\n";
        echo "Type: " . $record->type . "\n";
        echo "Time: " . $record->time_at . "\n";
        echo "Data is queryable! ✓\n\n";

        $this->assertEquals('day_in', $record->type);
        $this->assertEquals('08:00:00', $record->time_at instanceof \Carbon\Carbon ? $record->time_at->format('H:i:s') : $record->time_at);

        // Step 4: Add more records
        echo "Step 4: Adding more records\n";
        $this->postJson('/api/time-clock', [
            'shop_id' => 1,
            'user_id' => $user->id,
            'clock_date' => '2026-01-15',
            'time' => '12:00:00',
            'type' => 'break_start',
            'buffer_time' => 180,
        ]);

        $this->postJson('/api/time-clock', [
            'shop_id' => 1,
            'user_id' => $user->id,
            'clock_date' => '2026-01-15',
            'time' => '13:00:00',
            'type' => 'break_end',
            'buffer_time' => 180,
        ]);

        echo "Records in user_time_clock: " . UserTimeClock::count() . "\n";
        echo "Expected: 3\n";
        echo "Multiple records stored! ✓\n\n";
        $this->assertEquals(3, UserTimeClock::count());

        // Step 5: Query specific records
        echo "Step 5: Querying specific records\n";
        $breakRecords = UserTimeClock::where('type', 'LIKE', 'break%')->get();
        echo "Break records found: " . $breakRecords->count() . "\n";
        echo "Expected: 2\n";
        echo "Complex queries work! ✓\n\n";
        $this->assertEquals(2, $breakRecords->count());

        // Step 6: Show all records
        echo "Step 6: All records in database:\n";
        foreach (UserTimeClock::orderBy('time_at')->get() as $r) {
            echo "  - {$r->type} at {$r->time_at}\n";
        }
        echo "\n";

        // Step 7: Demonstrate assertDatabaseHas
        echo "Step 7: Using assertDatabaseHas\n";
        $this->assertDatabaseHas('user_time_clock', [
            'type' => 'day_in',
            'time_at' => '08:00:00',
        ]);
        echo "assertDatabaseHas PASSED - proves data is in DB! ✓\n\n";

        // Step 8: Demonstrate assertDatabaseCount
        echo "Step 8: Using assertDatabaseCount\n";
        $this->assertDatabaseCount('user_time_clock', 3);
        echo "assertDatabaseCount PASSED - proves 3 records exist! ✓\n\n";

        echo "=== CONCLUSION ===\n";
        echo "✓ Data was successfully stored in the database\n";
        echo "✓ All database operations worked normally\n";
        echo "✓ Multiple records were created and queried\n";
        echo "✓ Laravel assertions verified database state\n\n";
        echo "After this test completes, RefreshDatabase will:\n";
        echo "1. Rollback the transaction\n";
        echo "2. Remove all test data\n";
        echo "3. Prepare for the next test\n\n";
        echo "This is CORRECT behavior and ensures test isolation!\n";
        echo "=================================\n\n";
    }

    /**
     * Test to show database state is clean for each test
     */
    public function test_database_is_clean_for_each_test(): void
    {
        echo "\n\n=== CLEAN STATE TEST ===\n\n";

        echo "This test runs AFTER the previous test.\n";
        echo "If RefreshDatabase is working correctly, database should be empty.\n\n";

        $count = UserTimeClock::count();
        echo "Records in user_time_clock: " . $count . "\n";
        echo "Expected: 0 (previous test data was rolled back)\n\n";

        $this->assertEquals(0, $count);

        echo "✓ Database is clean!\n";
        echo "✓ Previous test data was successfully removed!\n";
        echo "✓ This proves RefreshDatabase is working correctly!\n\n";
        echo "=========================\n\n";
    }
}
