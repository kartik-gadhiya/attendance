<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreUserTimeClockRequest;
use App\Models\UserTimeClock;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class NewUserTimeClockController extends Controller
{
    /**
     * Store a newly created time clock entry.
     */
    public function store(StoreUserTimeClockRequest $request): JsonResponse
    {
        try {
            $validated = $request->validated();

            // Extract and prepare data
            $shopId = $validated['shop_id'];
            $userId = $validated['user_id'] ?? null;
            $clockDate = $validated['clock_date'];
            $time = $validated['time'];
            $type = $validated['type'];
            $shiftStart = $validated['shift_start'] ?? '08:00';
            $shiftEnd = $validated['shift_end'] ?? '23:00';
            $bufferTime = $validated['buffer_time'] ?? 3;

            // Calculate buffer times
            $bufferData = $this->calculateBufferTimes($shiftStart, $shiftEnd, $bufferTime);

            // Parse datetime and handle next-day scenarios
            $dateTimeData = $this->parseDateTime($clockDate, $time, $shiftStart, $shiftEnd);

            // Get existing events for this user/shop/date
            $existingEvents = $this->getExistingEvents($shopId, $userId, $clockDate);

            // Validate based on type
            $validation = match ($type) {
                'day_in' => $this->validateDayIn($time, $existingEvents, $bufferData, $dateTimeData),
                'break_start' => $this->validateBreakStart($time, $existingEvents, $bufferData, $dateTimeData),
                'break_end' => $this->validateBreakEnd($time, $existingEvents, $bufferData, $dateTimeData),
                'day_out' => $this->validateDayOut($time, $existingEvents, $bufferData, $dateTimeData),
            };

            // Return error if validation fails
            if (!$validation['valid']) {
                return response()->json([
                    'success' => false,
                    'message' => $validation['message'],
                ], 422);
            }

            // Create the time clock entry
            $timeClock = UserTimeClock::create([
                'shop_id' => $shopId,
                'user_id' => $userId,
                'date_at' => $clockDate,
                'time_at' => $time,
                'date_time' => $dateTimeData['date_time'],
                'formated_date_time' => $dateTimeData['formatted_date_time'],
                'shift_start' => $shiftStart,
                'shift_end' => $shiftEnd,
                'type' => $type,
                'comment' => $validated['comment'] ?? null,
                'buffer_time' => $bufferTime,
                'created_from' => $validated['created_from'] ?? 'A',
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Time clock entry created successfully',
                'data' => $timeClock,
            ], 201);
        } catch (\Exception $e) {
            Log::error('Failed to create time clock entry', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request_data' => $request->all(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to create time clock entry',
                'error' => config('app.debug') ? $e->getMessage() : 'An error occurred',
            ], 500);
        }
    }

    /**
     * Calculate shift start and end times with buffer.
     */
    private function calculateBufferTimes(string $shiftStart, string $shiftEnd, int $bufferTime): array
    {
        $startTime = Carbon::createFromFormat('H:i', $shiftStart);
        $endTime = Carbon::createFromFormat('H:i', $shiftEnd);

        // Calculate buffer start (shift start - buffer hours)
        $bufferStart = $startTime->copy()->subHours($bufferTime);

        // Calculate buffer end (shift end + buffer hours)
        $bufferEnd = $endTime->copy()->addHours($bufferTime);

        return [
            'shift_start' => $shiftStart,
            'shift_end' => $shiftEnd,
            'buffer_start' => $bufferStart->format('H:i'),
            'buffer_end' => $bufferEnd->format('H:i'),
            'buffer_start_obj' => $bufferStart,
            'buffer_end_obj' => $bufferEnd,
        ];
    }

    /**
     * Parse datetime and handle next-day scenarios based on shift rules.
     */
    private function parseDateTime(string $date, string $time, string $shiftStart, string $shiftEnd): array
    {
        $baseDate = Carbon::createFromFormat('Y-m-d', $date);
        $timeObj = Carbon::createFromFormat('H:i', $time);

        // Create datetime with original date
        $dateTime = $baseDate->copy()->setTime($timeObj->hour, $timeObj->minute);

        // Calculate buffer end time (shift end + buffer hours, e.g., 23:00 + 3 = 02:00 next day)
        $shiftEndTime = Carbon::createFromFormat('H:i', $shiftEnd);
        $bufferHours = 3; // Default buffer
        $bufferEndTime = $shiftEndTime->copy()->addHours($bufferHours);

        // Determine if this time falls in next-day window
        // Next-day window is: midnight (00:00) to buffer end time (e.g., 02:00)
        // Everything else (including buffer start like 05:00 to 23:59) is same day

        $isNextDayTime = false;

        // Check if time is in early morning hours (00:00 to buffer end)
        // For example, if buffer end is 02:00, then 00:00-02:00 are next day times
        if ($timeObj->hour >= 0 && $timeObj->hour < $bufferEndTime->hour) {
            // This is a next-day time (e.g., 00:30, 01:00, 01:45)
            $isNextDayTime = true;
        } elseif ($timeObj->hour == $bufferEndTime->hour && $timeObj->minute <= $bufferEndTime->minute) {
            // Times at exactly buffer end hour, up to and including the buffer end minute
            // e.g., if buffer end is 02:00, then 02:00 is next day
            $isNextDayTime = true;
        }

        // Set formatted_date_time based on next-day logic
        if ($isNextDayTime) {
            // Times between 00:00 and buffer end (e.g., 02:00) → next day
            $formattedDateTime = $baseDate->copy()->addDay()->setTime($timeObj->hour, $timeObj->minute);
        } else {
            // Times from buffer start (e.g., 05:00) to 23:59 → same day
            $formattedDateTime = $dateTime->copy();
        }

        return [
            'date_time' => $dateTime,
            'formatted_date_time' => $formattedDateTime,
            'time_obj' => $timeObj,
            'is_next_day' => $isNextDayTime,
        ];
    }

    /**
     * Get existing events for validation.
     */
    private function getExistingEvents(int $shopId, ?int $userId, string $date): array
    {
        $events = UserTimeClock::where('shop_id', $shopId)
            ->where('user_id', $userId)
            ->where('date_at', $date)
            ->orderBy('time_at')
            ->get();

        return [
            'all' => $events,
            'day_in' => $events->where('type', 'day_in')->first(),
            'day_out' => $events->where('type', 'day_out')->first(),
            'break_starts' => $events->where('type', 'break_start'),
            'break_ends' => $events->where('type', 'break_end'),
        ];
    }

    /**
     * Validate Day In entry.
     */
    private function validateDayIn(string $time, array $existingEvents, array $bufferData, array $dateTimeData): array
    {
        $timeObj = $dateTimeData['time_obj'];
        $bufferStartObj = $bufferData['buffer_start_obj'];
        $isNextDay = $dateTimeData['is_next_day'];

        // Check for exact duplicate Day In at same time
        $duplicateDayIn = $existingEvents['all']
            ->where('type', 'day_in')
            ->filter(function ($event) use ($time) {
                $eventTime = is_string($event->time_at) ? $event->time_at : $event->time_at->format('H:i:s');
                return $eventTime === $time . ':00';
            })
            ->first();

        if ($duplicateDayIn) {
            return [
                'valid' => false,
                'message' => 'Day In already exists at this time (' . $time . '). Cannot add duplicate.',
            ];
        }

        // First Day In validation - check buffer time
        if (!$existingEvents['day_in']) {
            // For next-day times (00:00 to buffer end like 02:00), these are VALID
            // For same-day times, must be >= buffer start (e.g., 05:00)

            if (!$isNextDay && $this->isTimeBefore($timeObj, $bufferStartObj)) {
                // Only reject if it's NOT a next-day time AND before buffer start
                return [
                    'valid' => false,
                    'message' => "Cannot clock in before {$bufferData['buffer_start']}",
                ];
            }
            return ['valid' => true];
        }

        // If Day In exists but Day Out doesn't, don't allow another Day In
        if ($existingEvents['day_in'] && !$existingEvents['day_out']) {
            return [
                'valid' => false,
                'message' => 'Please complete Day Out first before adding another Day In',
            ];
        }

        // If both Day In and Day Out exist, new Day In must be after Day Out
        if ($existingEvents['day_in'] && $existingEvents['day_out']) {
            // Parse time_at as time string (H:i:s)
            $timeAtValue = is_string($existingEvents['day_out']->time_at)
                ? $existingEvents['day_out']->time_at
                : $existingEvents['day_out']->time_at->format('H:i:s');
            $dayOutTime = Carbon::createFromFormat('H:i:s', $timeAtValue);

            if ($this->isTimeBeforeOrEqual($timeObj, $dayOutTime)) {
                return [
                    'valid' => false,
                    'message' => 'Cannot add Day In between or before existing Day In and Day Out times. Day In must be after ' . $dayOutTime->format('H:i'),
                ];
            }
        }

        return ['valid' => true];
    }

    /**
     * Validate Break Start entry.
     */
    private function validateBreakStart(string $time, array $existingEvents, array $bufferData, array $dateTimeData): array
    {
        // 1. Check for exact duplicate Break Start at same time
        $duplicateBreakStart = $existingEvents['all']
            ->where('type', 'break_start')
            ->filter(function ($event) use ($time) {
                $eventTime = is_string($event->time_at) ? $event->time_at : $event->time_at->format('H:i:s');
                return $eventTime === $time . ':00';
            })
            ->first();

        if ($duplicateBreakStart) {
            return [
                'valid' => false,
                'message' => 'Break Start already exists at this time (' . $time . '). Cannot add duplicate.',
            ];
        }

        // 2. Must have Day In
        if (!$existingEvents['day_in']) {
            return [
                'valid' => false,
                'message' => 'Please clock in first before starting a break',
            ];
        }

        // 3. Break Start must be after LAST Day In (for current shift)
        // Always get the LAST Day In to support multiple shifts
        $allDayIns = $existingEvents['all']->where('type', 'day_in');
        $lastDayIn = $allDayIns->sortBy('formated_date_time')->last();
        $lastDayInDateTime = Carbon::parse($lastDayIn->formated_date_time);
        $currentBreakStartDateTime = $dateTimeData['formatted_date_time'];

        if ($currentBreakStartDateTime->lte($lastDayInDateTime)) {
            return [
                'valid' => false,
                'message' => 'Break Start must be after Day In time (' . $lastDayInDateTime->format('H:i') . ')',
            ];
        }

        // 4. Check if there's an incomplete break (Break Start without Break End)
        $incompleteBreak = $this->hasIncompleteBreak($existingEvents);
        if ($incompleteBreak) {
            return [
                'valid' => false,
                'message' => 'Please end the current break before starting a new one',
            ];
        }

        return ['valid' => true];
    }

    /**
     * Validate Break End entry.
     */
    private function validateBreakEnd(string $time, array $existingEvents, array $bufferData, array $dateTimeData): array
    {
        $timeObj = $dateTimeData['time_obj'];

        // Check for exact duplicate Break End at same time
        $duplicateBreakEnd = $existingEvents['all']
            ->where('type', 'break_end')
            ->filter(function ($event) use ($time) {
                $eventTime = is_string($event->time_at) ? $event->time_at : $event->time_at->format('H:i:s');
                return $eventTime === $time . ':00';
            })
            ->first();

        if ($duplicateBreakEnd) {
            return [
                'valid' => false,
                'message' => 'Break End already exists at this time (' . $time . '). Cannot add duplicate.',
            ];
        }

        // Must have a Break Start without Break End
        $lastBreakStart = $existingEvents['break_starts']->last();

        if (!$lastBreakStart) {
            return [
                'valid' => false,
                'message' => 'Please start break first',
            ];
        }

        // Check if last break already has an end
        $breakStartTimeValue = is_string($lastBreakStart->time_at)
            ? $lastBreakStart->time_at
            : $lastBreakStart->time_at->format('H:i:s');
        $breakStartTime = Carbon::createFromFormat('H:i:s', $breakStartTimeValue);
        $breakEndForThisStart = $existingEvents['break_ends']->filter(function ($breakEnd) use ($breakStartTime) {
            $breakEndTimeValue = is_string($breakEnd->time_at)
                ? $breakEnd->time_at
                : $breakEnd->time_at->format('H:i:s');
            $breakEndTime = Carbon::createFromFormat('H:i:s', $breakEndTimeValue);
            return $breakEndTime > $breakStartTime;
        })->first();

        if ($breakEndForThisStart) {
            return [
                'valid' => false,
                'message' => 'Break already ended. Please start a new break if needed',
            ];
        }

        // Break End must be after Break Start - use datetime comparison
        $breakStartDateTime = Carbon::parse($lastBreakStart->formated_date_time);
        $currentBreakEndDateTime = $dateTimeData['formatted_date_time'];

        if ($currentBreakEndDateTime->lte($breakStartDateTime)) {
            return [
                'valid' => false,
                'message' => 'Break End must be after Break Start time (' . $breakStartDateTime->format('H:i') . ')',
            ];
        }

        // Check if there's a Day Out for the CURRENT shift (after last Day In)
        // Get the LAST Day In
        $allDayIns = $existingEvents['all']->where('type', 'day_in');
        $lastDayIn = $allDayIns->sortBy('formated_date_time')->last();
        $lastDayInDateTime = Carbon::parse($lastDayIn->formated_date_time);

        $allDayOuts = $existingEvents['all']->where('type', 'day_out');
        $currentShiftDayOut = null;

        foreach ($allDayOuts as $dayOut) {
            $dayOutDateTime = Carbon::parse($dayOut->formated_date_time);
            if ($dayOutDateTime->gt($lastDayInDateTime)) {
                $currentShiftDayOut = $dayOut;
                break;
            }
        }

        // Only check Day Out constraint if there's a Day Out for current shift
        if ($currentShiftDayOut) {
            $currentShiftDayOutDateTime = Carbon::parse($currentShiftDayOut->formated_date_time);

            if ($currentBreakEndDateTime->gt($currentShiftDayOutDateTime)) {
                return [
                    'valid' => false,
                    'message' => 'Break End must be before Day Out time (' . $currentShiftDayOutDateTime->format('H:i') . ')',
                ];
            }
        }
        // If no Day Out, allow break end (main constraint is it must be after break start)


        return ['valid' => true];
    }

    /**
     * Validate Day Out entry.
     */
    private function validateDayOut(string $time, array $existingEvents, array $bufferData, array $dateTimeData): array
    {
        $currentDateTime = $dateTimeData['formatted_date_time'];

        // Must have at least one Day In
        if (!$existingEvents['day_in']) {
            return [
                'valid' => false,
                'message' => 'Please clock in first before clocking out',
            ];
        }

        // Get the LAST Day In by sorting all day_in events by formated_date_time
        $allDayIns = $existingEvents['all']->where('type', 'day_in');
        $lastDayIn = $allDayIns->sortBy('formated_date_time')->last();

        if (!$lastDayIn) {
            return [
                'valid' => false,
                'message' => 'Please clock in first before clocking out',
            ];
        }

        $lastDayInDateTime = Carbon::parse($lastDayIn->formated_date_time);

        // Check if there's already a Day Out AFTER this last Day In
        $allDayOuts = $existingEvents['all']->where('type', 'day_out');
        foreach ($allDayOuts as $dayOut) {
            $dayOutDateTime = Carbon::parse($dayOut->formated_date_time);

            // If this Day Out is after the last Day In, the current shift is already closed
            if ($dayOutDateTime->gt($lastDayInDateTime)) {
                return [
                    'valid' => false,
                    'message' => 'Day Out already exists for this shift at ' . $dayOutDateTime->format('H:i') . '. Cannot add another Day Out.',
                ];
            }
        }

        // Check for incomplete break
        $incompleteBreak = $this->hasIncompleteBreak($existingEvents);
        if ($incompleteBreak) {
            return [
                'valid' => false,
                'message' => 'Please end break first before clocking out',
            ];
        }

        // Day Out must be after Day In - use full datetime comparison with LAST Day In
        // Get the LAST Day In to support multiple shifts
        $allDayIns = $existingEvents['all']->where('type', 'day_in');
        $lastDayIn = $allDayIns->sortBy('formated_date_time')->last();
        $dayInDateTime = Carbon::parse($lastDayIn->formated_date_time);


        if ($currentDateTime->lte($dayInDateTime)) {
            return [
                'valid' => false,
                'message' => 'Day Out must be after Day In time (' . $dayInDateTime->format('H:i') . ')',
            ];
        }

        // If breaks exist, Day Out must be after last Break End - use full datetime comparison
        $lastBreakEnd = $existingEvents['break_ends']->last();
        if ($lastBreakEnd) {
            $breakEndDateTime = Carbon::parse($lastBreakEnd->formated_date_time);


            if ($currentDateTime->lte($breakEndDateTime)) {
                return [
                    'valid' => false,
                    'message' => 'Day Out must be after last Break End time (' . $breakEndDateTime->format('H:i') . ')',
                ];
            }
        }

        return ['valid' => true];
    }

    /**
     * Check if there's an incomplete break (Break Start without Break End).
     */
    private function hasIncompleteBreak(array $existingEvents): bool
    {
        $breakStartCount = $existingEvents['break_starts']->count();
        $breakEndCount = $existingEvents['break_ends']->count();

        return $breakStartCount > $breakEndCount;
    }

    /**
     * Check for break overlap.
     */
    private function checkBreakOverlap($breakStartTime, $breakEndTime, array $existingEvents): array
    {
        // Get all completed break pairs
        $breakStarts = $existingEvents['break_starts'];
        $breakEnds = $existingEvents['break_ends'];

        $completedBreaks = [];
        foreach ($breakStarts as $index => $breakStart) {
            $breakEnd = $breakEnds->get($index);
            if ($breakEnd) {
                $breakStartTimeValue = is_string($breakStart->time_at)
                    ? $breakStart->time_at
                    : $breakStart->time_at->format('H:i:s');
                $breakEndTimeValue = is_string($breakEnd->time_at)
                    ? $breakEnd->time_at
                    : $breakEnd->time_at->format('H:i:s');
                $completedBreaks[] = [
                    'start' => Carbon::createFromFormat('H:i:s', $breakStartTimeValue),
                    'end' => Carbon::createFromFormat('H:i:s', $breakEndTimeValue),
                ];
            }
        }

        // Check if new break overlaps with any existing break
        foreach ($completedBreaks as $existingBreak) {
            // Check if break start falls within an existing break range
            if ($this->isTimeBetween($breakStartTime, $existingBreak['start'], $existingBreak['end'])) {
                return [
                    'valid' => false,
                    'message' => 'Break time overlaps with existing break (' .
                        $existingBreak['start']->format('H:i') . ' - ' .
                        $existingBreak['end']->format('H:i') . ')',
                ];
            }
        }

        return ['valid' => true];
    }

    /**
     * Helper: Check if time1 is before time2.
     */
    private function isTimeBefore(Carbon $time1, Carbon $time2): bool
    {
        return $time1->format('H:i') < $time2->format('H:i');
    }

    /**
     * Helper: Check if time1 is before or equal to time2.
     */
    private function isTimeBeforeOrEqual(Carbon $time1, Carbon $time2): bool
    {
        return $time1->format('H:i') <= $time2->format('H:i');
    }

    /**
     * Helper: Check if time1 is after time2.
     */
    private function isTimeAfter(Carbon $time1, Carbon $time2): bool
    {
        return $time1->format('H:i') > $time2->format('H:i');
    }

    /**
     * Helper: Check if time1 is after or equal to time2.
     */
    private function isTimeAfterOrEqual(Carbon $time1, Carbon $time2): bool
    {
        return $time1->format('H:i') >= $time2->format('H:i');
    }

    /**
     * Helper: Check if time is between start and end.
     */
    private function isTimeBetween(Carbon $time, Carbon $start, Carbon $end): bool
    {
        $timeStr = $time->format('H:i');
        $startStr = $start->format('H:i');
        $endStr = $end->format('H:i');

        return $timeStr > $startStr && $timeStr < $endStr;
    }
}
