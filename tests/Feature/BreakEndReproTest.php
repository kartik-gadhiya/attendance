<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\UserTimeClock;
use App\Services\UserTimeClockService;

class BreakEndReproTest extends TestCase
{
    public function test_break_end_repro()
    {
        $service = new UserTimeClockService();
        $shopId = 1;
        $userId = 5;
        $testDate = '2026-01-07';

        // Clean existing
        UserTimeClock::where('user_id', $userId)->where('date_at', $testDate)->delete();

        $rows = [
            ['time' => '11:10:00', 'type' => 'day_in'],
            ['time' => '12:05:00', 'type' => 'break_start'],
            ['time' => '12:10:00', 'type' => 'break_start'],
            ['time' => '17:00:00', 'type' => 'break_end'],
            ['time' => '17:15:00', 'type' => 'break_start'],
            ['time' => '18:00:00', 'type' => 'day_out'],
            ['time' => '23:30:00', 'type' => 'day_in'],
            ['time' => '23:45:00', 'type' => 'break_start'],
        ];

        foreach ($rows as $r) {
            UserTimeClock::create([
                'shop_id' => $shopId,
                'user_id' => $userId,
                'date_at' => $testDate,
                'time_at' => $r['time'],
                'date_time' => $testDate . ' ' . $r['time'],
                'formated_date_time' => $testDate . ' ' . $r['time'],
                'shift_start' => '09:00:00',
                'shift_end' => '22:00:00',
                'type' => $r['type'],
                'comment' => null,
                'buffer_time' => 3,
                'created_from' => 'B',
                'updated_from' => 'B',
            ]);
        }

        $result = $service->breakEndAdd([
            'shop_id' => $shopId,
            'user_id' => $userId,
            'clock_date' => $testDate,
            'time' => '12:07',
            'type' => 'break_end',
            'buffer_time' => 3,
            'shift_start' => '09:00:00',
            'shift_end' => '22:00:00',
            'created_from' => 'A',
        ]);

        $this->assertTrue((bool) ($result['status'] ?? false), 'Expected breakEndAdd to succeed, got: ' . ($result['message'] ?? 'no message'));
    }
}
