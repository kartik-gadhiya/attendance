<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\UserTimeClock;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Diagnostic test - Insert user's EXACT SQL data and try operations
 */
class DiagnosticUserDataTest extends TestCase
{
    protected ?User $user = null;
    protected int $userId = 2;
    protected int $shopId = 1;
    protected string $testDate = '2026-01-05';

    protected function setUp(): void
    {
        parent::setUp();

        // Clean
        UserTimeClock::where('user_id', $this->userId)->delete();

        // Ensure user exists
        $this->user = User::find($this->userId);
        if (!$this->user) {
            $this->user = User::factory()->create(['id' => $this->userId]);
        }
    }

    /**
     * Insert user's EXACT SQL data and try adding a third break
     */
    public function test_with_users_exact_sql_data(): void
    {
        echo "\n[Diagnostic] Inserting user's EXACT SQL data\n";

        // Insert user's exact records
        DB::table('user_time_clock')->insert([
            ['shop_id' => 1, 'user_id' => 2, 'date_at' => '2026-01-05', 'time_at' => '05:00:00', 'date_time' => '2026-01-05 05:00:00', 'formated_date_time' => '2026-01-05 05:00:00', 'shift_start' => '08:00:00', 'shift_end' => '23:00:00', 'type' => 'day_in', 'comment' => 'test', 'buffer_time' => 180, 'created_from' => 'A', 'updated_from' => 'A', 'created_at' => now(), 'updated_at' => now()],
            ['shop_id' => 1, 'user_id' => 2, 'date_at' => '2026-01-05', 'time_at' => '07:00:00', 'date_time' => '2026-01-05 07:00:00', 'formated_date_time' => '2026-01-05 07:00:00', 'shift_start' => '08:00:00', 'shift_end' => '23:00:00', 'type' => 'break_start', 'comment' => 'test', 'buffer_time' => 180, 'created_from' => 'A', 'updated_from' => 'A', 'created_at' => now(), 'updated_at' => now()],
            ['shop_id' => 1, 'user_id' => 2, 'date_at' => '2026-01-05', 'time_at' => '08:00:00', 'date_time' => '2026-01-05 08:00:00', 'formated_date_time' => '2026-01-05 08:00:00', 'shift_start' => '08:00:00', 'shift_end' => '23:00:00', 'type' => 'break_end', 'comment' => 'test', 'buffer_time' => 180, 'created_from' => 'A', 'updated_from' => 'A', 'created_at' => now(), 'updated_at' => now()],
            ['shop_id' => 1, 'user_id' => 2, 'date_at' => '2026-01-05', 'time_at' => '08:00:00', 'date_time' => '2026-01-05 08:00:00', 'formated_date_time' => '2026-01-05 08:00:00', 'shift_start' => '08:00:00', 'shift_end' => '23:00:00', 'type' => 'day_out', 'comment' => 'test', 'buffer_time' => 180, 'created_from' => 'A', 'updated_from' => 'A', 'created_at' => now(), 'updated_at' => now()],
            ['shop_id' => 1, 'user_id' => 2, 'date_at' => '2026-01-05', 'time_at' => '08:01:00', 'date_time' => '2026-01-05 08:01:00', 'formated_date_time' => '2026-01-05 08:01:00', 'shift_start' => '08:00:00', 'shift_end' => '23:00:00', 'type' => 'day_in', 'comment' => 'test', 'buffer_time' => 180, 'created_from' => 'A', 'updated_from' => 'A', 'created_at' => now(), 'updated_at' => now()],
            ['shop_id' => 1, 'user_id' => 2, 'date_at' => '2026-01-05', 'time_at' => '08:30:00', 'date_time' => '2026-01-05 08:30:00', 'formated_date_time' => '2026-01-05 08:30:00', 'shift_start' => '08:00:00', 'shift_end' => '23:00:00', 'type' => 'break_start', 'comment' => 'test', 'buffer_time' => 180, 'created_from' => 'A', 'updated_from' => 'A', 'created_at' => now(), 'updated_at' => now()],
            ['shop_id' => 1, 'user_id' => 2, 'date_at' => '2026-01-05', 'time_at' => '09:00:00', 'date_time' => '2026-01-05 09:00:00', 'formated_date_time' => '2026-01-05 09:00:00', 'shift_start' => '08:00:00', 'shift_end' => '23:00:00', 'type' => 'break_end', 'comment' => 'test', 'buffer_time' => 180, 'created_from' => 'A', 'updated_from' => 'A', 'created_at' => now(), 'updated_at' => now()],
            ['shop_id' => 1, 'user_id' => 2, 'date_at' => '2026-01-05', 'time_at' => '12:00:00', 'date_time' => '2026-01-05 12:00:00', 'formated_date_time' => '2026-01-05 12:00:00', 'shift_start' => '08:00:00', 'shift_end' => '23:00:00', 'type' => 'day_out', 'comment' => 'test', 'buffer_time' => 180, 'created_from' => 'A', 'updated_from' => 'A', 'created_at' => now(), 'updated_at' => now()],
        ]);

        echo "✓ Inserted 8 records from user's SQL\n";

        // Show current state
        $events = UserTimeClock::where('user_id', 2)
            ->where('date_at', '2026-01-05')
            ->orderBy('time_at')
            ->get(['time_at', 'type']);

        echo "\nCurrent database state:\n";
        foreach ($events as $event) {
            echo "  {$event->time_at} → {$event->type}\n";
        }

        // Count breaks
        $breakStarts = $events->where('type', 'break_start')->count();
        $breakEnds = $events->where('type', 'break_end')->count();
        echo "\nBreak counts: {$breakStarts} starts, {$breakEnds} ends\n";

        // Now try to add ANOTHER break with both formats
        echo "\n--- Testing with NEW format (H:i) ---\n";
        $response1 = $this->postJson('/api/time-clock', [
            'shop_id' => 1,
            'user_id' => 2,
            'clock_date' => '2026-01-05',
            'time' => '10:00',  // H:i format
            'type' => 'break_start',
            'buffer_time' => 3,  // hours
        ]);

        if ($response1->status() !== 201) {
            echo "✗ Failed with H:i format\n";
            echo "Response: " . json_encode($response1->json()) . "\n";
        } else {
            echo "✓ Success with H:i format\n";
        }

        // Clean and try again with old format
        UserTimeClock::where('time_at', '10:00:00')->delete();

        echo "\n--- Testing with OLD format (H:i:s) ---\n";
        $response2 = $this->postJson('/api/time-clock', [
            'shop_id' => 1,
            'user_id' => 2,
            'clock_date' => '2026-01-05',
            'time' => '10:00:00',  // H:i:s format
            'type' => 'break_start',
            'buffer_time' => 180,  // minutes
        ]);

        if ($response2->status() !== 201) {
            echo "✗ Failed with H:i:s format\n";
            echo "Response: " . json_encode($response2->json()) . "\n";
        } else {
            echo "✓ Success with H:i:s format\n";
        }

        // The test should pass with new format
        $response1->assertStatus(201);
    }
}
