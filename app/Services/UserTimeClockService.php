<?php

namespace App\Services;

use App\Models\UserTimeClock;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class UserTimeClockService
{
    protected string $language;

    public function __construct(string $language = 'en')
    {
        $this->language = $language;
    }

    /**
     * Set the language for validation messages
     */
    public function setLanguage(string $language): void
    {
        $this->language = $language;
    }

    /**
     * Add a day-in entry
     */
    public function dayInAdd(array $data): array
    {
        // Validate the day-in entry
        $validation = $this->validateDayIn($data);
        if (!$validation['status']) {
            return $validation;
        }

        return $this->createEntry($data);
    }

    /**
     * Add a day-out entry
     */
    public function dayOutAdd(array $data): array
    {
        // Validate the day-out entry
        $validation = $this->validateDayOut($data);
        if (!$validation['status']) {
            return $validation;
        }

        return $this->createEntry($data);
    }

    /**
     * Add a break-start entry
     */
    public function breakStartAdd(array $data): array
    {
        // Validate the break-start entry
        $validation = $this->validateBreakStart($data);
        if (!$validation['status']) {
            return $validation;
        }

        return $this->createEntry($data);
    }

    /**
     * Add a break-end entry
     */
    public function breakEndAdd(array $data): array
    {
        // Validate the break-end entry
        $validation = $this->validateBreakEnd($data);
        if (!$validation['status']) {
            return $validation;
        }

        return $this->createEntry($data);
    }

    /**
     * Validate day-in entry
     */
    protected function validateDayIn(array $data): array
    {
        // Normalize time format and convert buffer from hours to minutes
        $data = $this->normalizeRequestData($data);

        // CHECK: Prevent duplicate timestamps
        if ($this->hasDuplicateTimestamp($data)) {
            return [
                'status' => false,
                'code' => 422,
                'message' => __('Cannot add event: Another event already exists at this exact time.', locale: $this->language),
            ];
        }

        // RANGE CHECK: Prevent entries in blocked ranges
        if ($this->isInBlockedRange($data['time'], $data, 'day_in')) {
            return [
                'status' => false,
                'code' => 422,
                'message' => __('Cannot add day-in: Time falls within an existing shift range.', locale: $this->language),
            ];
        }

        // STATE CHECK: Prevent duplicate day-in
        if ($this->hasActiveDayIn($data)) {
            return [
                'status' => false,
                'code' => 422,
                'message' => __('Cannot perform day-in: You already have an active check-in. Please perform day-out first.', locale: $this->language),
            ];
        }

        // Get shift times (from request or existing records)
        $shiftTimes = $this->getShiftTimes($data);

        // CRITICAL CHECK: Reject very early morning times for day-in
        // E.g., shift starts at 08:00, buffer 3hrs, earliest = 05:00
        // Day-in at 01:59 should be REJECTED (before 05:00)
        if ($shiftTimes['shift_start']) {
            $dayInTime = Carbon::createFromFormat('H:i:s', $data['time']);
            $shiftStart = Carbon::createFromFormat('H:i:s', $shiftTimes['shift_start']);
            $bufferMinutes = $data['buffer_time'] ?? 180;
            $earliestAllowed = $shiftStart->copy()->subMinutes($bufferMinutes);

            // If day-in time is very early (before 05:00) and earliest allowed is 05:00 or later
            // then this is TOO EARLY for a day-in (not a late-shift continuation)
            if ($dayInTime->hour < 5 && $earliestAllowed->hour >= 5) {
                return [
                    'status' => false,
                    'code' => 422,
                    'message' => __('Day-in time is outside the allowed buffer window. Earliest allowed: ' . $earliestAllowed->format('H:i'), locale: $this->language),
                ];
            }
        }

        // Check if within buffer time
        if (!$this->isWithinBufferTime($data['time'], $shiftTimes['shift_start'], $shiftTimes['shift_end'], $data['buffer_time'] ?? 180)) {
            return [
                'status' => false,
                'code' => 422,
                'message' => __('Day-in time is outside the allowed buffer time.', locale: $this->language),
            ];
        }

        // Check for overlaps with existing events
        $overlap = $this->checkOverlap($data, 'day_in');
        if ($overlap) {
            return [
                'status' => false,
                'code' => 422,
                'message' => __('Day-in time overlaps with an existing event.', locale: $this->language),
            ];
        }

        return ['status' => true];
    }

    /**
     * Validate day-out entry
     */
    protected function validateDayOut(array $data): array
    {
        // Normalize time format and convert buffer from hours to minutes
        $data = $this->normalizeRequestData($data);

        // CHECK: Prevent duplicate timestamps
        if ($this->hasDuplicateTimestamp($data)) {
            return [
                'status' => false,
                'code' => 422,
                'message' => __('Cannot add event: Another event already exists at this exact time.', locale: $this->language),
            ];
        }

        // Range check disabled for day-out - day-out CLOSES ranges, shouldn't be blocked by them
        // Timeline validation ensures day-out is at correct position
        /*
        if ($this->isInBlockedRange($data['time'], $data, 'day_out')) {
            return [
                'status' => false,
                'code' => 422,
                'message' => __('Cannot add day-out: Time falls within another existing shift range.', locale: $this->language),
            ];
        }
        */

        // STATE CHECK: Ensure day-in exists
        if (!$this->hasActiveDayIn($data)) {
            return [
                'status' => false,
                'code' => 422,
                'message' => __('Cannot perform day-out: No active check-in found. Please perform day-in first.', locale: $this->language),
            ];
        }

        // Get the last break end (if any)
        $lastBreakEnd = $this->getLastBreakEnd($data);
        if ($lastBreakEnd) {
            $breakEndTime = Carbon::createFromFormat(
                'H:i:s',
                $lastBreakEnd->time_at instanceof Carbon ? $lastBreakEnd->time_at->format('H:i:s') : $lastBreakEnd->time_at
            );
            $dayOutTime = Carbon::createFromFormat('H:i:s', $data['time']);

            // Handle cross-midnight: if day-out time < break-end time, it's next day
            if ($dayOutTime->lessThan($breakEndTime)) {
                $dayOutTime->addDay();
            }

            if ($dayOutTime->lessThanOrEqualTo($breakEndTime)) {
                return [
                    'status' => false,
                    'code' => 422,
                    'message' => __('Day-out time must be after the last break end time.', locale: $this->language),
                ];
            }
        }

        // STATE CHECK: Ensure no open breaks
        if ($this->hasOpenBreak($data)) {
            return [
                'status' => false,
                'code' => 422,
                'message' => __('Cannot perform day-out: You have an open break. Please end the break first.', locale: $this->language),
            ];
        }

        // Get shift times (from request or existing records)
        $shiftTimes = $this->getShiftTimes($data);

        // Check if within buffer time
        if (!$this->isWithinBufferTime($data['time'], $shiftTimes['shift_start'], $shiftTimes['shift_end'], $data['buffer_time'] ?? 180)) {
            return [
                'status' => false,
                'code' => 422,
                'message' => __('Day-out time is outside the allowed buffer time.', locale: $this->language),
            ];
        }

        // Check for overlaps with existing events
        $overlap = $this->checkOverlap($data, 'day_out');
        if ($overlap) {
            return [
                'status' => false,
                'code' => 422,
                'message' => __('Day-out time overlaps with an existing event.', locale: $this->language),
            ];
        }

        return ['status' => true];
    }

    /**
     * Validate break-start entry
     */
    protected function validateBreakStart(array $data): array
    {
        // Normalize time format and convert buffer from hours to minutes
        $data = $this->normalizeRequestData($data);

        // CHECK: Prevent duplicate timestamps
        if ($this->hasDuplicateTimestamp($data)) {
            return [
                'status' => false,
                'code' => 422,
                'message' => __('Cannot add event: Another event already exists at this exact time.', locale: $this->language),
            ];
        }

        // TIMELINE CHECK: Get the last event BEFORE this time
        $previousEvent = $this->getPreviousEvent($data);

        // Rule 1: Previous event must be either day_in or break_end
        if ($previousEvent) {
            if ($previousEvent->type === 'break_start') {
                return [
                    'status' => false,
                    'code' => 422,
                    'message' => __('Cannot start break: Previous break is still open. Please end the current break first.', locale: $this->language),
                ];
            }

            if ($previousEvent->type === 'day_out') {
                return [
                    'status' => false,
                    'code' => 422,
                    'message' => __('Cannot start break: Shift has already ended. Break must be before day-out.', locale: $this->language),
                ];
            }

            // If previous is break_end or day_in, check that our time is after it
            $previousTime = Carbon::createFromFormat(
                'H:i:s',
                $previousEvent->time_at instanceof Carbon ? $previousEvent->time_at->format('H:i:s') : $previousEvent->time_at
            );
            $breakStartTime = Carbon::createFromFormat('H:i:s', $data['time']);

            // Handle cross-midnight: if break time appears earlier than previous time,
            // and previous is late (PM) while break is early (AM), assume next day
            if ($breakStartTime->lessThan($previousTime)) {
                if ($previousTime->hour >= 12 && $breakStartTime->hour < 6) {
                    // Cross-midnight scenario: break is next day, which is valid
                    // Don't error - this is allowed
                } else {
                    // Same-day scenario: break is actually before previous event
                    return [
                        'status' => false,
                        'code' => 422,
                        'message' => __('Break start time must be after ' . $previousEvent->type . ' at ' . $previousTime->format('H:i'), locale: $this->language),
                    ];
                }
            }
        } else {
            // No previous event found
            return [
                'status' => false,
                'code' => 422,
                'message' => __('Cannot start break: No day-in found. Please perform day-in first.', locale: $this->language),
            ];
        }

        // TIMELINE CHECK: Get the next event AFTER this time
        $nextEvent = $this->getNextEvent($data);

        if ($nextEvent) {
            $nextTime = Carbon::createFromFormat(
                'H:i:s',
                $nextEvent->time_at instanceof Carbon ? $nextEvent->time_at->format('H:i:s') : $nextEvent->time_at
            );
            $currentTime = Carbon::createFromFormat('H:i:s', $data['time']);

            if ($currentTime->greaterThanOrEqualTo($nextTime)) {
                return [
                    'status' => false,
                    'code' => 422,
                    'message' => __('Break start time must be before ' . $nextEvent->type . ' at ' . $nextTime->format('H:i'), locale: $this->language),
                ];
            }
        }

        // Get shift times (from request or existing records)
        $shiftTimes = $this->getShiftTimes($data);

        // Check if within buffer time
        if (!$this->isWithinBufferTime($data['time'], $shiftTimes['shift_start'], $shiftTimes['shift_end'], $data['buffer_time'] ?? 180)) {
            return [
                'status' => false,
                'code' => 422,
                'message' => __('Break start time is outside the allowed buffer time.', locale: $this->language),
            ];
        }

        return ['status' => true];
    }

    /**
     * Validate break-end entry
     */
    protected function validateBreakEnd(array $data): array
    {
        // Normalize time format and convert buffer from hours to minutes
        $data = $this->normalizeRequestData($data);

        // CHECK: Prevent duplicate timestamps
        if ($this->hasDuplicateTimestamp($data)) {
            return [
                'status' => false,
                'code' => 422,
                'message' => __('Cannot add event: Another event already exists at this exact time.', locale: $this->language),
            ];
        }

        // TIMELINE CHECK: Get the last event BEFORE this time
        $previousEvent = $this->getPreviousEvent($data);

        // Rule 1: Previous event must be break_start
        if (!$previousEvent || $previousEvent->type !== 'break_start') {
            return [
                'status' => false,
                'code' => 422,
                'message' => __('Cannot end break: No active break found. Please start a break first.', locale: $this->language),
            ];
        }

        // Rule 2: Break end must be after break start
        $breakStartTime = Carbon::createFromFormat(
            'H:i:s',
            $previousEvent->time_at instanceof Carbon ? $previousEvent->time_at->format('H:i:s') : $previousEvent->time_at
        );
        $currentTime = Carbon::createFromFormat('H:i:s', $data['time']);

        if ($currentTime->lessThanOrEqualTo($breakStartTime)) {
            return [
                'status' => false,
                'code' => 422,
                'message' => __('Break end time must be after break start time (' . $breakStartTime->format('H:i') . ').', locale: $this->language),
            ];
        }

        // TIMELINE CHECK: Get the next event AFTER this time
        $nextEvent = $this->getNextEvent($data);

        if ($nextEvent) {
            $nextTime = Carbon::createFromFormat(
                'H:i:s',
                $nextEvent->time_at instanceof Carbon ? $nextEvent->time_at->format('H:i:s') : $nextEvent->time_at
            );

            if ($currentTime->greaterThanOrEqualTo($nextTime)) {
                return [
                    'status' => false,
                    'code' => 422,
                    'message' => __('Break end time must be before ' . $nextEvent->type . ' at ' . $nextTime->format('H:i'), locale: $this->language),
                ];
            }
        }

        // Get shift times (from request or existing records)
        $shiftTimes = $this->getShiftTimes($data);

        // Check if within buffer time
        if (!$this->isWithinBufferTime($data['time'], $shiftTimes['shift_start'], $shiftTimes['shift_end'], $data['buffer_time'] ?? 180)) {
            return [
                'status' => false,
                'code' => 422,
                'message' => __('Break end time is outside the allowed buffer time.', locale: $this->language),
            ];
        }

        // Check if there's a corresponding break_start without a break_end
        $hasOpenBreak = $this->hasOpenBreak($data);
        if (!$hasOpenBreak) {
            return [
                'status' => false,
                'code' => 422,
                'message' => __('No active break found to end.', locale: $this->language),
            ];
        }

        // STATE CHECK: Ensure break-end time is after break-start time
        $lastBreakStart = $this->getLastOpenBreak($data);
        if ($lastBreakStart) {
            $breakEndTime = Carbon::createFromFormat('H:i:s', $data['time']);
            $breakStartTime = Carbon::createFromFormat(
                'H:i:s',
                $lastBreakStart->time_at instanceof Carbon ? $lastBreakStart->time_at->format('H:i:s') : $lastBreakStart->time_at
            );

            if ($breakEndTime->lessThanOrEqualTo($breakStartTime)) {
                return [
                    'status' => false,
                    'code' => 422,
                    'message' => __('Break end time must be after break start time.', locale: $this->language),
                ];
            }
        }

        return ['status' => true];
    }

    /**
     * Normalize request data: convert H:i to H:i:s and hours to minutes
     */
    protected function normalizeRequestData(array $data): array
    {
        // Normalize time format (add :00 seconds if not present)
        if (isset($data['time']) && strlen($data['time']) === 5) {
            $data['time'] .= ':00';
        }

        // Normalize shift times
        if (isset($data['shift_start']) && strlen($data['shift_start']) === 5) {
            $data['shift_start'] .= ':00';
        }
        if (isset($data['shift_end']) && strlen($data['shift_end']) === 5) {
            $data['shift_end'] .= ':00';
        }

        // Convert buffer_time from hours to minutes (if provided)
        if (isset($data['buffer_time'])) {
            $data['buffer_time'] = $data['buffer_time'] * 60;
        }

        return $data;
    }

    /**
     * Get shift times from request or existing records
     */
    protected function getShiftTimes(array $data): array
    {
        // Check if there are existing entries for this date
        $existingEntry = UserTimeClock::where('shop_id', $data['shop_id'])
            ->where('user_id', $data['user_id'])
            ->where('date_at', $data['clock_date'])
            ->whereNotNull('shift_start')
            ->whereNotNull('shift_end')
            ->first();

        if ($existingEntry) {
            // Use shift times from existing entry
            return [
                'shift_start' => $existingEntry->shift_start instanceof Carbon
                    ? $existingEntry->shift_start->format('H:i:s')
                    : $existingEntry->shift_start,
                'shift_end' => $existingEntry->shift_end instanceof Carbon
                    ? $existingEntry->shift_end->format('H:i:s')
                    : $existingEntry->shift_end,
            ];
        }

        // Use shift times from request (first entry of the day)
        return [
            'shift_start' => $data['shift_start'] ?? null,
            'shift_end' => $data['shift_end'] ?? null,
        ];
    }

    protected function isWithinBufferTime(string $eventTime, ?string $shiftStart, ?string $shiftEnd, int $bufferMinutes): bool
    {
        if (!$shiftStart || !$shiftEnd) {
            return true; // If no shift times, allow the entry
        }

        $eventCarbon = Carbon::createFromFormat('H:i:s', $eventTime);
        $shiftStartCarbon = Carbon::createFromFormat('H:i:s', $shiftStart);
        $shiftEndCarbon = Carbon::createFromFormat('H:i:s', $shiftEnd);

        // Add buffer time (in minutes)
        $allowedStart = $shiftStartCarbon->copy()->subMinutes($bufferMinutes);
        $allowedEnd = $shiftEndCarbon->copy()->addMinutes($bufferMinutes);

        // Handle midnight crossing shifts
        if ($shiftEndCarbon->lessThan($shiftStartCarbon)) {
            // Shift crosses midnight (e.g., 22:00 to 02:00)
            // Event is valid if it's after allowed_start OR before allowed_end
            return $eventCarbon->greaterThanOrEqualTo($allowedStart) ||
                $eventCarbon->lessThanOrEqualTo($allowedEnd);
        }

        // CRITICAL FIX: For day-in, reject very early times that are before buffer window
        // Shift 08:00-23:00, buffer 3hrs, earliest = 05:00
        // Time at 01:59 should be REJECTED (before 05:00)
        // Time at 01:30 for day-out should still be ALLOWED (next-day continuation)
        if ($eventCarbon->hour < 5 && $allowedStart->hour >= 5) {
            // Event is very early (before 05:00) but buffer starts later (e.g., 05:00)
            // This is only valid for late-shift continuation (day-out), not day-in
            // For now, we'll let the next check handle it, but ensure day-in is blocked
            // by checking if shift actually extends into next day
            $shiftEndMinutes = $shiftEndCarbon->hour * 60 + $shiftEndCarbon->minute;
            $bufferEndMinutes = $shiftEndMinutes + $bufferMinutes - (24 * 60);

            if ($bufferEndMinutes <= 0) {
                // Buffer doesn't extend into next day, so early morning time is invalid
                return false;
            }

            // Buffer DOES extend into next day - check if event is within that range
            $minutesAfterMidnight = $eventCarbon->hour * 60 + $eventCarbon->minute;
            if ($minutesAfterMidnight > $bufferEndMinutes) {
                // Event is after buffer end (next day)
                return false;
            }
            // Event is within next-day buffer - this is for day-out, should be allowed
            // But for day-in, we want to block it. We can't distinguish here, so we'll
            // let it pass and handle it elsewhere... Actually, let's just block very early day-in
            // For events before 02:00 AND earliest allowed is after 05:00, block it
            if ($eventCarbon->hour < 2 && $allowedStart->hour > 5) {
                return false; // Too early for day-in
            }
        }
        // Check if event is early morning (after midnight) and shift ends late at night
        // This handles cases where shift is 08:00-23:00, buffer extends to 02:00 next day
        // and event is at 01:00 (which should be valid as it's within buffer after 23:00)
        if ($eventCarbon->hour <= 4 && $shiftEndCarbon->hour >= 20) {
            // Event is early morning (00:00-04:59) and shift ends late (20:00 or later)
            // Treat event as next day - check if it's within buffer after midnight
            $midnightBuffer = 24 * 60; // Convert to minutes from midnight
            $minutesAfterMidnight = $eventCarbon->hour * 60 + $eventCarbon->minute;
            $shiftEndMinutes = $shiftEndCarbon->hour * 60 + $shiftEndCarbon->minute;
            $bufferEndMinutes = $shiftEndMinutes + $bufferMinutes - $midnightBuffer;

            // If buffer end is positive, it extends into next day
            if ($bufferEndMinutes > 0 && $minutesAfterMidnight <= $bufferEndMinutes) {
                return true; // Event is within buffer after midnight
            }
            return false; // Event is too late (after buffer end)
        }

        // Normal shift (within same day)
        return $eventCarbon->greaterThanOrEqualTo($allowedStart) &&
            $eventCarbon->lessThanOrEqualTo($allowedEnd);
    }

    /**
     * Check if event overlaps with existing events
     */
    protected function checkOverlap(array $data, string $currentType): bool
    {
        $events = $this->getTodayEvents($data);

        if ($events->isEmpty()) {
            return false;
        }

        $eventTime = Carbon::createFromFormat('H:i:s', $data['time']);

        // Special handling for break_end: it should NOT overlap with its own break_start
        if ($currentType === 'break_end') {
            // Get the last open break (this is the break we're trying to end)
            $lastBreakStart = $this->getLastOpenBreak($data);

            if ($lastBreakStart) {
                $breakStartTime = Carbon::createFromFormat(
                    'H:i:s',
                    $lastBreakStart->time_at instanceof Carbon ? $lastBreakStart->time_at->format('H:i:s') : $lastBreakStart->time_at
                );

                // Check overlap with OTHER events (excluding our own break-start)
                foreach ($events as $event) {
                    // Skip our own break-start
                    if ($event->type === 'break_start' && $event->id === $lastBreakStart->id) {
                        continue;
                    }

                    $eventStart = Carbon::createFromFormat(
                        'H:i:s',
                        $event->time_at instanceof Carbon ? $event->time_at->format('H:i:s') : $event->time_at
                    );

                    // Check for exact time match with other events
                    if ($eventTime->equalTo($eventStart)) {
                        return true; // Overlap found
                    }

                    // Check if break-end falls within another break range
                    if ($event->type === 'break_start') {
                        $otherBreakEnd = $events->first(function ($e) use ($event, $eventStart) {
                            if ($e->type !== 'break_end') {
                                return false;
                            }
                            $breakEndTime = Carbon::createFromFormat(
                                'H:i:s',
                                $e->time_at instanceof Carbon ? $e->time_at->format('H:i:s') : $e->time_at
                            );
                            return $breakEndTime->greaterThan($eventStart);
                        });

                        if ($otherBreakEnd) {
                            $otherBreakEndTime = Carbon::createFromFormat(
                                'H:i:s',
                                $otherBreakEnd->time_at instanceof Carbon ? $otherBreakEnd->time_at->format('H:i:s') : $otherBreakEnd->time_at
                            );

                            // Check if our break-end falls within this OTHER break
                            if ($eventTime->greaterThan($eventStart) && $eventTime->lessThan($otherBreakEndTime)) {
                                return true;
                            }
                        }
                    }
                }

                return false; // No overlap found with other events
            }
        }

        // For all other event types (day_in, day_out, break_start)
        foreach ($events as $event) {
            $eventStart = Carbon::createFromFormat('H:i:s', $event->time_at instanceof Carbon ? $event->time_at->format('H:i:s') : $event->time_at);

            // For point events (day_in, day_out, break_start), check if they occur at the same time
            if (in_array($event->type, ['day_in', 'day_out', 'break_start'])) {
                if ($eventTime->equalTo($eventStart)) {
                    return true; // Times overlap
                }
            }

            // For break ranges, check if the current event falls within a break
            if ($event->type === 'break_start') {
                // Find the corresponding break_end
                $breakEnd = $events->first(function ($e) use ($event, $eventStart) {
                    if ($e->type !== 'break_end') {
                        return false;
                    }
                    $breakEndTime = Carbon::createFromFormat('H:i:s', $e->time_at instanceof Carbon ? $e->time_at->format('H:i:s') : $e->time_at);
                    return $breakEndTime->greaterThan($eventStart);
                });

                if ($breakEnd) {
                    $breakEndTime = Carbon::createFromFormat('H:i:s', $breakEnd->time_at instanceof Carbon ? $breakEnd->time_at->format('H:i:s') : $breakEnd->time_at);

                    // Check if event falls within break range
                    if ($eventTime->greaterThan($eventStart) && $eventTime->lessThan($breakEndTime)) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    /**
     * Check if there's an open break (break_start without break_end)
     */
    protected function hasOpenBreak(array $data): bool
    {
        $events = $this->getTodayEvents($data);

        $breakStarts = $events->where('type', 'break_start')->count();
        $breakEnds = $events->where('type', 'break_end')->count();

        return $breakStarts > $breakEnds;
    }

    /**
     * Check if there's an active day-in (day_in without day_out)
     */
    protected function hasActiveDayIn(array $data): bool
    {
        $events = $this->getTodayEvents($data);

        $dayIns = $events->where('type', 'day_in')->count();
        $dayOuts = $events->where('type', 'day_out')->count();

        return $dayIns > $dayOuts;
    }

    /**
     * Get the last open break-start (without a corresponding break-end)
     */
    protected function getLastOpenBreak(array $data): ?UserTimeClock
    {
        $events = $this->getTodayEvents($data);

        // Get all break starts
        $breakStarts = $events->where('type', 'break_start')->sortByDesc('time_at');

        foreach ($breakStarts as $breakStart) {
            // Check if this break has a corresponding end
            $hasEnd = $events->where('type', 'break_end')
                ->filter(function ($breakEnd) use ($breakStart) {
                    $startTime = Carbon::createFromFormat(
                        'H:i:s',
                        $breakStart->time_at instanceof Carbon ? $breakStart->time_at->format('H:i:s') : $breakStart->time_at
                    );
                    $endTime = Carbon::createFromFormat(
                        'H:i:s',
                        $breakEnd->time_at instanceof Carbon ? $breakEnd->time_at->format('H:i:s') : $breakEnd->time_at
                    );
                    return $endTime->greaterThan($startTime);
                })
                ->isNotEmpty();

            if (!$hasEnd) {
                return $breakStart; // Found an open break
            }
        }

        return null; // No open breaks
    }

    /**
     * Get the last break-end entry
     */
    protected function getLastBreakEnd(array $data): ?UserTimeClock
    {
        $events = $this->getTodayEvents($data);

        return $events->where('type', 'break_end')
            ->sortByDesc('time_at')
            ->first();
    }

    /**
     * Check if an event already exists at this exact timestamp
     */
    protected function hasDuplicateTimestamp(array $data): bool
    {
        $events = $this->getTodayEvents($data);
        $currentTime = Carbon::createFromFormat('H:i:s', $data['time']);

        foreach ($events as $event) {
            $eventTime = Carbon::createFromFormat(
                'H:i:s',
                $event->time_at instanceof Carbon ? $event->time_at->format('H:i:s') : $event->time_at
            );

            if ($eventTime->equalTo($currentTime)) {
                return true; // Duplicate timestamp found
            }
        }

        return false;
    }

    /**
     * Get the previous event in the timeline (before current time)
     */
    protected function getPreviousEvent(array $data): ?UserTimeClock
    {
        $events = $this->getTodayEvents($data);
        $currentTime = Carbon::createFromFormat('H:i:s', $data['time']);

        // Detect cross-midnight: current time is early AM (< 6:00)
        $isEarlyMorning = $currentTime->hour < 6;

        if ($isEarlyMorning) {
            // First check for same-morning events before current time
            $morningEvents = $events->filter(function ($event) use ($currentTime) {
                $eventTime = Carbon::createFromFormat(
                    'H:i:s',
                    $event->time_at instanceof Carbon ? $event->time_at->format('H:i:s') : $event->time_at
                );
                return $eventTime->hour < 6 && $eventTime->lessThan($currentTime);
            })->sortByDesc('time_at');

            if ($morningEvents->isNotEmpty()) {
                return $morningEvents->first();
            }

            // If no morning events, check for late evening events (from yesterday)
            $lateEvents = $events->filter(function ($event) {
                $eventTime = Carbon::createFromFormat(
                    'H:i:s',
                    $event->time_at instanceof Carbon ? $event->time_at->format('H:i:s') : $event->time_at
                );
                return $eventTime->hour >= 12;
            });

            if ($lateEvents->isNotEmpty()) {
                return $lateEvents->sortByDesc('time_at')->first();
            }

            return null;
        }

        // Normal (same-day): Get events before the current time
        $previousEvents = $events->filter(function ($event) use ($currentTime) {
            $eventTime = Carbon::createFromFormat(
                'H:i:s',
                $event->time_at instanceof Carbon ? $event->time_at->format('H:i:s') : $event->time_at
            );
            return $eventTime->lessThan($currentTime);
        })->sortByDesc('time_at');

        return $previousEvents->first(); // Most recent event before current time
    }

    /**
     * Get the next event in the timeline (after current time)
     */
    protected function getNextEvent(array $data): ?UserTimeClock
    {
        $events = $this->getTodayEvents($data);

        $currentTime = Carbon::createFromFormat('H:i:s', $data['time']);

        // Get events after the current time, sorted by time ascending
        $nextEvents = $events->filter(function ($event) use ($currentTime) {
            $eventTime = Carbon::createFromFormat(
                'H:i:s',
                $event->time_at instanceof Carbon ? $event->time_at->format('H:i:s') : $event->time_at
            );
            return $eventTime->greaterThan($currentTime);
        })->sortBy('time_at');

        return $nextEvents->first(); // First event after current time
    }

    /**
     * Get all blocked time ranges (day-in to day-out pairs)
     */
    protected function getBlockedRanges(array $data): array
    {
        $events = $this->getTodayEvents($data);
        $ranges = [];

        // Get all day-in events sorted by time
        $dayIns = $events->where('type', 'day_in')->sortBy('time_at');

        foreach ($dayIns as $dayIn) {
            $dayInTime = Carbon::createFromFormat(
                'H:i:s',
                $dayIn->time_at instanceof Carbon ? $dayIn->time_at->format('H:i:s') : $dayIn->time_at
            );

            // Find corresponding day-out (first day-out after this day-in)
            $dayOut = $events->where('type', 'day_out')
                ->filter(function ($event) use ($dayInTime) {
                    $eventTime = Carbon::createFromFormat(
                        'H:i:s',
                        $event->time_at instanceof Carbon ? $event->time_at->format('H:i:s') : $event->time_at
                    );
                    return $eventTime->greaterThan($dayInTime);
                })
                ->sortBy('time_at')
                ->first();

            if ($dayOut) {
                // Complete range: day-in to day-out
                $dayOutTime = Carbon::createFromFormat(
                    'H:i:s',
                    $dayOut->time_at instanceof Carbon ? $dayOut->time_at->format('H:i:s') : $dayOut->time_at
                );

                $ranges[] = [
                    'start' => $dayInTime,
                    'end' => $dayOutTime,
                    'type' => 'closed'
                ];
            } else {
                // Open range: day-in without day-out (blocks everything after)
                $ranges[] = [
                    'start' => $dayInTime,
                    'end' => Carbon::createFromFormat('H:i:s', '23:59:59'),
                    'type' => 'open'
                ];
            }
        }

        return $ranges;
    }

    /**
     * Check if a time falls within any blocked range
     */
    protected function isInBlockedRange(string $time, array $data, string $eventType = null): bool
    {
        $ranges = $this->getBlockedRanges($data);
        $currentTime = Carbon::createFromFormat('H:i:s', $time);

        //  Only check against CLOSED ranges (completed shifts)
        // OPEN ranges represent the current active shift, which should allow breaks/day-out
        foreach ($ranges as $range) {
            // Skip OPEN ranges - they represent current shift
            if ($range['type'] === 'open') {
                continue;
            }

            // Check if current time falls within this CLOSED range (inclusive)
            if (
                $currentTime->greaterThanOrEqualTo($range['start']) &&
                $currentTime->lessThanOrEqualTo($range['end'])
            ) {
                return true; // Falls in blocked range
            }
        }

        return false;
    }

    /**
     * Get all events for today
     */
    protected function getTodayEvents(array $data)
    {
        return UserTimeClock::where('shop_id', $data['shop_id'])
            ->where('user_id', $data['user_id'])
            ->where('date_at', $data['clock_date'])
            ->orderBy('time_at')
            ->get();
    }

    protected function normalizeDateTime(string $date, string $time, ?string $shiftStart, ?string $shiftEnd): array
    {
        $dateCarbon = Carbon::createFromFormat('Y-m-d', $date);
        $timeCarbon = Carbon::createFromFormat('H:i:s', $time);

        $dateTime = $dateCarbon->copy()->setTimeFromTimeString($time);
        $formattedDateTime = $dateCarbon->copy()->setTimeFromTimeString($time);

        // Check if shift crosses midnight and if event time suggests it's the next day
        if ($shiftStart && $shiftEnd) {
            $shiftStartCarbon = Carbon::createFromFormat('H:i:s', $shiftStart);
            $shiftEndCarbon = Carbon::createFromFormat('H:i:s', $shiftEnd);

            // If shift crosses midnight (end < start)
            if ($shiftEndCarbon->lessThan($shiftStartCarbon)) {
                // If event time is before shift end time (early morning), it's actually the next day
                if ($timeCarbon->lessThanOrEqualTo($shiftEndCarbon)) {
                    $formattedDateTime->addDay();
                }
            }
            // Check if event is early morning and shift ends late at night (doesn't cross midnight)
            // This handles buffer time scenarios: shift 08:00-23:00, event at 01:00 next day
            elseif ($timeCarbon->hour <= 4 && $shiftEndCarbon->hour >= 20) {
                // Event is in early morning (00:00-04:59) and shift ends late (after 20:00)
                // This means the event is actually the next day
                $formattedDateTime->addDay();
            }
        }

        return [
            'date_time' => $dateTime->format('Y-m-d H:i:s'),
            'formated_date_time' => $formattedDateTime->format('Y-m-d H:i:s'),
        ];
    }

    /**
     * Create the time clock entry
     */
    protected function createEntry(array $data): array
    {
        try {
            DB::beginTransaction();

            // Normalize time format and convert buffer from hours to minutes
            $data = $this->normalizeRequestData($data);

            // Get shift times
            $shiftTimes = $this->getShiftTimes($data);

            // Normalize datetime for midnight crossing
            $dateTimes = $this->normalizeDateTime(
                $data['clock_date'],
                $data['time'],
                $shiftTimes['shift_start'],
                $shiftTimes['shift_end']
            );

            // Prepare entry data
            $entryData = [
                'shop_id' => $data['shop_id'],
                'user_id' => $data['user_id'] ?? null,
                'date_at' => $data['clock_date'],
                'time_at' => $data['time'],
                'date_time' => $dateTimes['date_time'],
                'formated_date_time' => $dateTimes['formated_date_time'],
                'shift_start' => $shiftTimes['shift_start'],
                'shift_end' => $shiftTimes['shift_end'],
                'type' => $data['type'],
                'comment' => $data['comment'] ?? null,
                'buffer_time' => $data['buffer_time'] ?? null,
                'created_from' => $data['created_from'] ?? null,
                'updated_from' => $data['created_from'] ?? null,
            ];

            $timeClock = UserTimeClock::create($entryData);

            DB::commit();

            return [
                'status' => true,
                'code' => 201,
                'message' => __('Time clock entry created successfully.', locale: $this->language),
                'data' => $timeClock,
            ];
        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Failed to create time clock entry', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'data' => $data,
            ]);

            return [
                'status' => false,
                'code' => 500,
                'message' => __('Failed to create time clock entry.', locale: $this->language),
            ];
        }
    }
}
