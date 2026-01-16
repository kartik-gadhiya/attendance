<?php

namespace App\Services;

use App\Models\UserTimeClock;
use App\Services\v1\CoreService;
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
        // Set the type
        $data['type'] = 'day_in';

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
        // Set the type
        $data['type'] = 'day_out';

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
        // Set the type
        $data['type'] = 'break_start';

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
        // Set the type
        $data['type'] = 'break_end';

        // Validate the break-end entry
        $validation = $this->validateBreakEnd($data);
        if (!$validation['status']) {
            return $validation;
        }

        return $this->createEntry($data);
    }

    /**
     * Update an existing time clock entry
     */
    public function updateEvent(int $id, array $data): array
    {
        // Get the existing event
        $event = UserTimeClock::find($id);
        if (!$event) {
            return [
                'status' => false,
                'code' => 404,
                'message' => __('Event not found.', locale: $this->language),
            ];
        }

        // Build complete data array using existing record values as defaults
        $completeData = [
            'shop_id' => $event->shop_id,
            'user_id' => $event->user_id,
            'clock_date' => Carbon::parse($event->date_at)->format('Y-m-d'),
            'time' => $data['time'],
            'type' => $data['type'] ?? $event->type,
            'shift_start' => $event->shift_start,
            'shift_end' => $event->shift_end,
            'buffer_time' => $event->buffer_time, // Convert minutes to hours
            'comment' => $data['comment'] ?? $event->comment,
        ];

        // Normalize time format and convert buffer from hours to minutes
        $completeData = $this->normalizeRequestData($completeData);

        // Get shift times (will use values from the record)
        $shiftTimes = $this->getShiftTimes($completeData);

        // Check if time is within buffer window
        if (!$this->isWithinBufferTime($completeData['time'], $shiftTimes['shift_start'], $shiftTimes['shift_end'], $completeData['buffer_time'] ?? 180, $completeData['clock_date'])) {
            return [
                'status' => false,
                'code' => 422,
                'message' => __('Time is outside the allowed buffer window.', locale: $this->language),
            ];
        }

        // Get neighbor events and validate
        $neighbors = $this->getNeighborEvents($event, $completeData, $shiftTimes);

        $validation = $this->validateEventEdit($event, $completeData, $neighbors);
        if (!$validation['status']) {
            return $validation;
        }

        // Perform the update
        return $this->performUpdate($event, $completeData, $shiftTimes);
    }

    /**
     * Get previous and next events for the event being edited
     */
    protected function getNeighborEvents($currentEvent, array $data, array $shiftTimes): array
    {
        // Fetch all events for same date and user, sorted chronologically
        $allEvents = UserTimeClock::forShop($data['shop_id'])
            ->forUser($data['user_id'])
            ->forDate($currentEvent->date_at)
            ->where('id', '!=', $currentEvent->id) // Exclude current event
            ->orderBy('formated_date_time', 'asc')
            ->get();

        // Calculate new formatted_date_time for edited event
        // Extract just the date portion (Y-m-d) from date_at
        $dateOnly = Carbon::parse($currentEvent->date_at)->format('Y-m-d');

        $newDateTime = $this->normalizeDateTime(
            $dateOnly,
            $data['time'],
            $shiftTimes['shift_start'],
            $shiftTimes['shift_end']
        );

        $newFormattedDateTime = Carbon::parse($newDateTime['formated_date_time']);

        // Check if any other event has the exact same time (duplicate/overlap)
        $duplicateTime = $allEvents->first(
            fn($e) =>
            Carbon::parse($e->formated_date_time)->equalTo($newFormattedDateTime)
        );

        if ($duplicateTime) {
            return [
                'previous' => null,
                'next' => null,
                'new_formatted_datetime' => $newFormattedDateTime,
                'duplicate' => $duplicateTime,
            ];
        }

        // Find previous and next events based on new position
        $previous = $allEvents->filter(
            fn($e) =>
            Carbon::parse($e->formated_date_time)->lessThan($newFormattedDateTime)
        )->last();

        $next = $allEvents->filter(
            fn($e) =>
            Carbon::parse($e->formated_date_time)->greaterThan($newFormattedDateTime)
        )->first();

        return [
            'previous' => $previous,
            'next' => $next,
            'new_formatted_datetime' => $newFormattedDateTime,
            'duplicate' => null,
        ];
    }

    /**
     * Find the break_end that is paired with this break_start
     */
    protected function findPairedBreakEnd($breakStartEvent, array $data): ?UserTimeClock
    {
        $allEvents = UserTimeClock::forShop($data['shop_id'])
            ->forUser($data['user_id'])
            ->forDate($breakStartEvent->date_at)
            ->where('id', '!=', $breakStartEvent->id)
            ->orderBy('formated_date_time', 'asc')
            ->get();

        $breakStartTime = Carbon::parse($breakStartEvent->formated_date_time);
        $foundCurrent = false;

        foreach ($allEvents as $e) {
            if ($e->formated_date_time === $breakStartEvent->formated_date_time) {
                $foundCurrent = true;
                continue;
            }
            if ($foundCurrent && $e->type === 'break_end') {
                return $e;
            }
        }

        return null;
    }

    /**
     * Find the break_start that is paired with this break_end
     * Handles midnight-crossing breaks and finds the immediate preceding break_start
     */
    protected function findPairedBreakStart($breakEndEvent, array $data): ?UserTimeClock
    {
        $allEvents = UserTimeClock::forShop($data['shop_id'])
            ->forUser($data['user_id'])
            ->forDate($breakEndEvent->date_at)
            ->where('id', '!=', $breakEndEvent->id)
            ->orderBy('formated_date_time', 'asc')
            ->get();

        $breakEndTime = Carbon::parse($breakEndEvent->formated_date_time);
        $pairedBreakStart = null;

        foreach ($allEvents as $e) {
            // Once we reach the break_end, stop searching
            if ($e->id === $breakEndEvent->id || $e->formated_date_time === $breakEndEvent->formated_date_time) {
                break;
            }

            if ($e->type === 'break_start') {
                // Check if this break_start already has a break_end
                $hasMatchingEnd = $allEvents->first(function ($endEvent) use ($e, $breakEndEvent) {
                    if ($endEvent->type !== 'break_end' || $endEvent->id === $breakEndEvent->id) {
                        return false;
                    }

                    $startTime = Carbon::parse($e->formated_date_time);
                    $endTime = Carbon::parse($endEvent->formated_date_time);

                    // Check if this break_end comes after this break_start
                    return $endTime->greaterThan($startTime);
                });

                // If this break_start doesn't have a paired break_end yet, it's our candidate
                if (!$hasMatchingEnd) {
                    $pairedBreakStart = $e;
                }
            }
        }

        return $pairedBreakStart;
    }

    /**
     * Validate edit against neighbor events
     */
    protected function validateEventEdit($event, array $data, array $neighbors): array
    {
        // Check for duplicate time (exact match with another event)
        if ($neighbors['duplicate']) {
            return [
                'status' => false,
                'code' => 422,
                'message' => sprintf(
                    __('Time conflicts with existing %s at %s', locale: $this->language),
                    $neighbors['duplicate']->type,
                    Carbon::parse($neighbors['duplicate']->time_at)->format('H:i')
                ),
            ];
        }

        // Special handling for break_start: ensure it doesn't move on or after its paired break_end
        if ($event->type === 'break_start') {
            $pairedBreakEnd = $this->findPairedBreakEnd($event, $data);
            if ($pairedBreakEnd) {
                $breakEndTime = Carbon::parse($pairedBreakEnd->formated_date_time);
                if ($neighbors['new_formatted_datetime']->greaterThanOrEqualTo($breakEndTime)) {
                    return [
                        'status' => false,
                        'code' => 422,
                        'message' => sprintf(
                            __('Break start cannot be moved to or after its paired break end at %s', locale: $this->language),
                            $breakEndTime->format('H:i')
                        ),
                    ];
                }
            }
        }

        // Special handling for break_end: ensure it doesn't move on or before its paired break_start
        if ($event->type === 'break_end') {
            $pairedBreakStart = $this->findPairedBreakStart($event, $data);
            if ($pairedBreakStart) {
                $breakStartTime = Carbon::parse($pairedBreakStart->formated_date_time);
                if ($neighbors['new_formatted_datetime']->lessThanOrEqualTo($breakStartTime)) {
                    return [
                        'status' => false,
                        'code' => 422,
                        'message' => sprintf(
                            __('Break end cannot be moved to or before its paired break start at %s', locale: $this->language),
                            $breakStartTime->format('H:i')
                        ),
                    ];
                }
            }
        }

        // Check against previous event
        if ($neighbors['previous']) {
            $prevTime = Carbon::parse($neighbors['previous']->formated_date_time);
            if ($neighbors['new_formatted_datetime']->lessThanOrEqualTo($prevTime)) {
                return [
                    'status' => false,
                    'code' => 422,
                    'message' => sprintf(
                        __('Time must be after previous %s at %s', locale: $this->language),
                        $neighbors['previous']->type,
                        $prevTime->format('H:i')
                    ),
                ];
            }
        }

        // Check against next event
        if ($neighbors['next']) {
            $nextTime = Carbon::parse($neighbors['next']->formated_date_time);
            if ($neighbors['new_formatted_datetime']->greaterThanOrEqualTo($nextTime)) {
                return [
                    'status' => false,
                    'code' => 422,
                    'message' => sprintf(
                        __('Time must be before next %s at %s', locale: $this->language),
                        $neighbors['next']->type,
                        $nextTime->format('H:i')
                    ),
                ];
            }
        }

        // Check event type sequence rules
        $sequenceValidation = $this->validateEventTypeSequence($event, $neighbors);
        if (!$sequenceValidation['status']) {
            return $sequenceValidation;
        }

        // Check day_in vs day_out time relationship
        $dayInOutValidation = $this->validateDayInOutTime($event, $data, $neighbors);
        if (!$dayInOutValidation['status']) {
            return $dayInOutValidation;
        }

        return ['status' => true];
    }

    /**
     * Validate event type sequence rules
     */
    protected function validateEventTypeSequence($event, array $neighbors): array
    {
        $eventType = $event->type;
        $previous = $neighbors['previous'];
        $next = $neighbors['next'];

        // RULE 1: break_end must have break_start as previous event
        if ($eventType === 'break_end') {
            if (!$previous || $previous->type !== 'break_start') {
                return [
                    'status' => false,
                    'code' => 422,
                    'message' => __('break_end must come immediately after break_start.', locale: $this->language),
                ];
            }
        }

        // RULE 2: break_start - if it currently has a break_end as next event in DB,
        // the new position must maintain this (can't move break_start after its break_end)
        if ($eventType === 'break_start') {
            // Get the current next event from database (before edit)
            $currentNextEvent = UserTimeClock::forShop($event->shop_id)
                ->forUser($event->user_id)
                ->forDate($event->date_at)
                ->where('formated_date_time', '>', $event->formated_date_time)
                ->orderBy('formated_date_time', 'asc')
                ->first();

            // If current next event is a break_end, it's this break's pair
            if ($currentNextEvent && $currentNextEvent->type === 'break_end') {
                // After edit, next event must still be this break_end
                if (!$next || $next->id !== $currentNextEvent->id) {
                    return [
                        'status' => false,
                        'code' => 422,
                        'message' => __('break_start cannot be moved after its paired break_end. Edit the break_end first if needed.', locale: $this->language),
                    ];
                }
            }
        }

        // RULE 3: day_in cannot be directly followed by break_end
        if ($eventType === 'day_in') {
            if ($next && $next->type === 'break_end') {
                return [
                    'status' => false,
                    'code' => 422,
                    'message' => __('day_in cannot be directly followed by break_end. A break must start before it ends.', locale: $this->language),
                ];
            }
        }

        // RULE 4: day_out cannot be preceded by break_start (unclosed break)
        if ($eventType === 'day_out') {
            if ($previous && $previous->type === 'break_start') {
                return [
                    'status' => false,
                    'code' => 422,
                    'message' => __('day_out cannot come after an unclosed break_start. The break must be ended first.', locale: $this->language),
                ];
            }
        }

        return ['status' => true];
    }

    /**
     * Validate day_in vs day_out time relationship
     * Ensures that day_out time is always greater than day_in time
     */
    protected function validateDayInOutTime($event, array $data, array $neighbors): array
    {
        $eventType = $event->type;
        // new_formatted_datetime is already a Carbon object
        $eventTime = $neighbors['new_formatted_datetime'];

        // If editing a day_out, verify it's after the day_in
        if ($eventType === 'day_out') {
            // Find the corresponding day_in for this day_out
            $dayInEvent = UserTimeClock::forShop($data['shop_id'])
                ->forUser($data['user_id'])
                ->forDate($event->date_at)
                ->where('type', 'day_in')
                ->where('id', '!=', $event->id)
                ->orderBy('formated_date_time', 'asc')
                ->first();

            if ($dayInEvent) {
                $dayInTime = Carbon::parse($dayInEvent->formated_date_time);

                // day_out must be strictly after day_in
                if ($eventTime->lessThanOrEqualTo($dayInTime)) {
                    return [
                        'status' => false,
                        'code' => 422,
                        'message' => sprintf(
                            __('Day Out time (%s) must be after Day In time (%s)', locale: $this->language),
                            $eventTime->format('H:i'),
                            $dayInTime->format('H:i')
                        ),
                    ];
                }
            }
        }

        // If editing a day_in, verify it's before the day_out
        if ($eventType === 'day_in') {
            // Find the corresponding day_out for this day_in
            $dayOutEvent = UserTimeClock::forShop($data['shop_id'])
                ->forUser($data['user_id'])
                ->forDate($event->date_at)
                ->where('type', 'day_out')
                ->where('id', '!=', $event->id)
                ->orderBy('formated_date_time', 'desc')
                ->first();

            if ($dayOutEvent) {
                $dayOutTime = Carbon::parse($dayOutEvent->formated_date_time);

                // day_in must be strictly before day_out
                if ($eventTime->greaterThanOrEqualTo($dayOutTime)) {
                    return [
                        'status' => false,
                        'code' => 422,
                        'message' => sprintf(
                            __('Day In time (%s) must be before Day Out time (%s)', locale: $this->language),
                            $eventTime->format('H:i'),
                            $dayOutTime->format('H:i')
                        ),
                    ];
                }
            }
        }

        return ['status' => true];
    }

    /**
     * Perform the update operation
     */
    protected function performUpdate($event, array $data, array $shiftTimes): array
    {
        try {
            DB::beginTransaction();

            // Normalize datetime for midnight crossing
            // Extract just the date portion (Y-m-d) from date_at
            $dateOnly = Carbon::parse($event->date_at)->format('Y-m-d');

            $dateTimes = $this->normalizeDateTime(
                $dateOnly,
                $data['time'],
                $shiftTimes['shift_start'],
                $shiftTimes['shift_end']
            );

            // Update the event
            $event->update([
                'time_at' => $data['time'],
                'date_time' => $dateTimes['date_time'],
                'formated_date_time' => $dateTimes['formated_date_time'],
                'comment' => $data['comment'] ?? $event->comment,
                'updated_from' => $data['updated_from'] ?? "B",
            ]);

            DB::commit();

            return [
                'status' => true,
                'code' => 200,
                'message' => __('Event updated successfully.', locale: $this->language),
                'data' => $event->fresh(),
            ];
        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Failed to update time clock event', [
                'error' => $e->getMessage(),
                'event_id' => $event->id,
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'status' => false,
                'code' => 500,
                'message' => __('Failed to update event.', locale: $this->language),
            ];
        }
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

        // Check if within buffer time
        // This check handles all buffer validation including midnight-crossing scenarios
        if (!$this->isWithinBufferTime($data['time'], $shiftTimes['shift_start'], $shiftTimes['shift_end'], $data['buffer_time'] ?? 180, $data['clock_date'])) {
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
            // Use formated_date_time for accurate comparison across midnight
            $shiftTimes = $this->getShiftTimes($data);
            $dayOutFormatted = $this->normalizeDateTime(
                $data['clock_date'], $data['time'], $shiftTimes['shift_start'], $shiftTimes['shift_end']
            )['formated_date_time'];
            
            $breakEndFormatted = $lastBreakEnd->formated_date_time;
            
            $dayOutCarbon = Carbon::parse($dayOutFormatted);
            $breakEndCarbon = Carbon::parse($breakEndFormatted);

            if ($dayOutCarbon->lessThanOrEqualTo($breakEndCarbon)) {
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

        // TIME CHECK: Ensure day-out is after day-in
        $dayInEvent = UserTimeClock::forShop($data['shop_id'])
            ->forUser($data['user_id'])
            ->forDate($data['clock_date'])
            ->where('type', 'day_in')
            ->orderBy('formated_date_time', 'desc')
            ->first();

        if ($dayInEvent) {
            $dayInTime = Carbon::parse($dayInEvent->formated_date_time);
            $dayOutTime = $this->normalizeDateTime(
                $data['clock_date'],
                $data['time'],
                $this->getShiftTimes($data)['shift_start'],
                $this->getShiftTimes($data)['shift_end']
            );
            $dayOutFormattedTime = Carbon::parse($dayOutTime['formated_date_time']);

            if ($dayOutFormattedTime->lessThanOrEqualTo($dayInTime)) {
                return [
                    'status' => false,
                    'code' => 422,
                    'message' => sprintf(
                        __('Day Out time (%s) must be after Day In time (%s)', locale: $this->language),
                        Carbon::parse($data['time'])->format('H:i'),
                        $dayInTime->format('H:i')
                    ),
                ];
            }
        }

        // Get shift times (from request or existing records)
        $shiftTimes = $this->getShiftTimes($data);

        // Check if within buffer time
        if (!$this->isWithinBufferTime($data['time'], $shiftTimes['shift_start'], $shiftTimes['shift_end'], $data['buffer_time'] ?? 180, $data['clock_date'])) {
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
            // Use formated_date_time for proper date/time comparison across midnight
            $shiftTimes = $this->getShiftTimes($data);
            $breakStartFormatted = $this->normalizeDateTime(
                $data['clock_date'], $data['time'], $shiftTimes['shift_start'], $shiftTimes['shift_end']
            )['formated_date_time'];
            
            $previousFormatted = $previousEvent->formated_date_time;
            
            $breakStartCarbon = Carbon::parse($breakStartFormatted);
            $previousCarbon = Carbon::parse($previousFormatted);

            // Break start must be after previous event
            if ($breakStartCarbon->lessThanOrEqualTo($previousCarbon)) {
                return [
                    'status' => false,
                    'code' => 422,
                    'message' => sprintf(
                        __('Break start time (%s) must be after %s at %s', locale: $this->language),
                        $breakStartCarbon->format('H:i'),
                        $previousEvent->type,
                        $previousCarbon->format('H:i')
                    ),
                ];
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
            // Use formated_date_time for proper date/time comparison
            $shiftTimes = $this->getShiftTimes($data);
            $breakStartFormatted = $this->normalizeDateTime(
                $data['clock_date'], $data['time'], $shiftTimes['shift_start'], $shiftTimes['shift_end']
            )['formated_date_time'];
            
            $nextFormatted = $nextEvent->formated_date_time;
            
            $breakStartCarbon = Carbon::parse($breakStartFormatted);
            $nextEventCarbon = Carbon::parse($nextFormatted);

            if ($breakStartCarbon->greaterThanOrEqualTo($nextEventCarbon)) {
                return [
                    'status' => false,
                    'code' => 422,
                    'message' => sprintf(
                        __('Break start time (%s) must be before %s at %s', locale: $this->language),
                        $breakStartCarbon->format('H:i'),
                        $nextEvent->type,
                        $nextEventCarbon->format('H:i')
                    ),
                ];
            }
        }

        // Get shift times (from request or existing records)
        $shiftTimes = $this->getShiftTimes($data);

        // Check if within buffer time
        if (!$this->isWithinBufferTime($data['time'], $shiftTimes['shift_start'], $shiftTimes['shift_end'], $data['buffer_time'] ?? 180, $data['clock_date'])) {
            return [
                'status' => false,
                'code' => 422,
                'message' => __('Break start time is outside the allowed buffer time.', locale: $this->language),
            ];
        }

        // Check for overlap with existing breaks
        $overlapValidation = $this->validateBreakStartOverlap($data);
        if (!$overlapValidation['status']) {
            return $overlapValidation;
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

        // FIX: Get the last OPEN break (not just any previous event)
        // This ensures we match the break_end with the correct break_start
        $breakStartEvent = $this->getLastOpenBreak($data);

        // Rule 1: Must have an open break to end
        if (!$breakStartEvent) {
            return [
                'status' => false,
                'code' => 422,
                'message' => __('Cannot end break: No active break found. Please start a break first.', locale: $this->language),
            ];
        }

        // Rule 2: Break end must be after break start (handle midnight crossing)
        // Use formated_date_time for proper date/time comparison across midnight
        $shiftTimes = $this->getShiftTimes($data);
        $breakEndFormatted = $this->normalizeDateTime(
            $data['clock_date'], $data['time'], $shiftTimes['shift_start'], $shiftTimes['shift_end']
        )['formated_date_time'];
        
        $breakStartFormatted = $breakStartEvent->formated_date_time;
        
        $breakEndCarbon = Carbon::parse($breakEndFormatted);
        $breakStartCarbon = Carbon::parse($breakStartFormatted);

        // Break end must be strictly after break start
        if ($breakEndCarbon->lessThanOrEqualTo($breakStartCarbon)) {
            return [
                'status' => false,
                'code' => 422,
                'message' => sprintf(
                    __('Break end time (%s) must be after break start time (%s).', locale: $this->language),
                    $breakEndCarbon->format('H:i'),
                    $breakStartCarbon->format('H:i')
                ),
            ];
        }

        // TIMELINE CHECK: Get the next event AFTER this time
        $nextEvent = $this->getNextEvent($data);

        if ($nextEvent) {
            // Use formated_date_time for proper date/time comparison
            $nextFormattedTime = $nextEvent->formated_date_time;
            $nextEventCarbon = Carbon::parse($nextFormattedTime);

            if ($breakEndCarbon->greaterThanOrEqualTo($nextEventCarbon)) {
                return [
                    'status' => false,
                    'code' => 422,
                    'message' => sprintf(
                        __('Break end time (%s) must be before %s at %s', locale: $this->language),
                        $breakEndCarbon->format('H:i'),
                        $nextEvent->type,
                        $nextEventCarbon->format('H:i')
                    ),
                ];
            }
        }

        // Check if within buffer time
        if (!$this->isWithinBufferTime($data['time'], $shiftTimes['shift_start'], $shiftTimes['shift_end'], $data['buffer_time'] ?? 180, $data['clock_date'])) {
            return [
                'status' => false,
                'code' => 422,
                'message' => __('Break end time is outside the allowed buffer time.', locale: $this->language),
            ];
        }

        // Check for break overlap
        $overlapValidation = $this->validateBreakOverlap($data);
        if (!$overlapValidation['status']) {
            return $overlapValidation;
        }

        // Note: No need to check hasOpenBreak() here because we already verified
        // that the previous event is a break_start (line 752-759).
        // If getPreviousEvent() returned a break_start, an open break exists.

        return ['status' => true];
    }

    /**
     * Validate that a new break does not overlap with existing breaks.
     */
    protected function validateBreakOverlap(array $data): array
    {
        $events = $this->getTodayEvents($data);

        // Get the current break start (last unclosed break)
        $lastBreakStart = $this->getLastOpenBreak($data);
        if (!$lastBreakStart) {
            return ['status' => true]; // No active break to validate
        }

        // Get current break times using formated_date_time for proper midnight handling
        $shiftTimes = $this->getShiftTimes($data);
        $currentBreakEndFormatted = $this->normalizeDateTime(
            $data['clock_date'], $data['time'], $shiftTimes['shift_start'], $shiftTimes['shift_end']
        )['formated_date_time'];
        
        $currentBreakStartFormatted = $lastBreakStart->formated_date_time;
        
        $currentBreakStartCarbon = Carbon::parse($currentBreakStartFormatted);
        $currentBreakEndCarbon = Carbon::parse($currentBreakEndFormatted);

        // Get all completed breaks
        $completedBreaks = [];
        $tempStart = null;

        foreach ($events as $event) {
            if ($event->type === 'break_start' && $event->id !== $lastBreakStart->id) {
                $tempStart = $event;
            } elseif ($event->type === 'break_end' && $tempStart) {
                $completedBreaks[] = [
                    'start' => $tempStart,
                    'end' => $event,
                    'startFormatted' => $tempStart->formated_date_time,
                    'endFormatted' => $event->formated_date_time,
                ];
                $tempStart = null;
            }
        }

        // Check for overlaps using formated_date_time comparison
        // Two breaks overlap if: (start1 < end2) AND (end1 > start2)
        foreach ($completedBreaks as $break) {
            $existingBreakStartCarbon = Carbon::parse($break['startFormatted']);
            $existingBreakEndCarbon = Carbon::parse($break['endFormatted']);

            // Check if current break overlaps with existing break
            $overlaps = ($currentBreakStartCarbon->lessThan($existingBreakEndCarbon)) &&
                        ($currentBreakEndCarbon->greaterThan($existingBreakStartCarbon));

            if ($overlaps) {
                return [
                    'status' => false,
                    'code' => 422,
                    'message' => sprintf(
                        __('Break overlaps with existing break (%s - %s).', locale: $this->language),
                        $existingBreakStartCarbon->format('H:i'),
                        $existingBreakEndCarbon->format('H:i')
                    ),
                ];
            }
        }

        return ['status' => true];
    }

    /**
     * Validate break-start to prevent overlap with existing breaks
     * This method checks if the break start time would conflict with any completed breaks
     */
    protected function validateBreakStartOverlap(array $data): array
    {
        $events = $this->getTodayEvents($data);

        // Get current break start time using formated_date_time
        $shiftTimes = $this->getShiftTimes($data);
        $currentBreakStartFormatted = $this->normalizeDateTime(
            $data['clock_date'], $data['time'], $shiftTimes['shift_start'], $shiftTimes['shift_end']
        )['formated_date_time'];
        
        $currentBreakStartCarbon = Carbon::parse($currentBreakStartFormatted);

        // Get all completed breaks
        $completedBreaks = [];
        $tempStart = null;

        foreach ($events as $event) {
            if ($event->type === 'break_start') {
                $tempStart = $event;
            } elseif ($event->type === 'break_end' && $tempStart) {
                $completedBreaks[] = [
                    'startFormatted' => $tempStart->formated_date_time,
                    'endFormatted' => $event->formated_date_time,
                ];
                $tempStart = null;
            }
        }

        // Check if the break start time falls within any existing break period
        foreach ($completedBreaks as $break) {
            $existingBreakStartCarbon = Carbon::parse($break['startFormatted']);
            $existingBreakEndCarbon = Carbon::parse($break['endFormatted']);

            // Break start cannot fall within an existing break period
            if ($currentBreakStartCarbon->greaterThanOrEqualTo($existingBreakStartCarbon) &&
                $currentBreakStartCarbon->lessThan($existingBreakEndCarbon)) {
                return [
                    'status' => false,
                    'code' => 422,
                    'message' => sprintf(
                        __('Break start time (%s) falls within an existing break (%s - %s).', locale: $this->language),
                        $currentBreakStartCarbon->format('H:i'),
                        $existingBreakStartCarbon->format('H:i'),
                        $existingBreakEndCarbon->format('H:i')
                    ),
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

    /**
     * Check if event time is within the buffer window (using formated_date_time for accuracy)
     * This ensures accurate validation for shifts that cross midnight
     */
    protected function isWithinBufferTime(string $eventTime, ?string $shiftStart, ?string $shiftEnd, int $bufferMinutes, ?string $clockDate = null): bool
    {
        if (!$shiftStart || !$shiftEnd || !$clockDate) {
            return true; // If no shift times or clock date, allow the entry
        }

        // Use formated_date_time for accurate comparison across midnight
        $eventFormatted = $this->normalizeDateTime($clockDate, $eventTime, $shiftStart, $shiftEnd);
        $eventCarbon = Carbon::parse($eventFormatted['formated_date_time']);

        // Calculate the buffer window
        $shiftStartCarbon = Carbon::createFromFormat('H:i:s', $shiftStart);
        $shiftEndCarbon = Carbon::createFromFormat('H:i:s', $shiftEnd);
        
        // Determine the actual shift start and end dates considering midnight crossing
        $shiftStartDate = $clockDate; // Shift typically starts on the given date
        
        // If shift end is before shift start, it crosses midnight
        $shiftEndDate = $clockDate;
        if ($shiftEndCarbon->lessThan($shiftStartCarbon)) {
            $shiftEndDate = Carbon::createFromFormat('Y-m-d', $clockDate)->addDay()->format('Y-m-d');
        }

        $allowedStartCarbon = Carbon::createFromFormat('Y-m-d H:i:s', "$shiftStartDate {$shiftStart}")
            ->subMinutes($bufferMinutes);
        $allowedEndCarbon = Carbon::createFromFormat('Y-m-d H:i:s', "$shiftEndDate {$shiftEnd}")
            ->addMinutes($bufferMinutes);

        // Event is valid if it's within the allowed buffer window
        return $eventCarbon->greaterThanOrEqualTo($allowedStartCarbon) && 
               $eventCarbon->lessThanOrEqualTo($allowedEndCarbon);
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
     * Uses formated_date_time for accurate midnight-crossing detection
     * 
     * IMPORTANT: For break_end validation, we need to process ALL break events
     * for the day and find which breaks are currently unclosed, because we might
     * be adding a break_end that pairs with a specific break_start anywhere in the day.
     */
    protected function getLastOpenBreak(array $data): ?UserTimeClock
    {
        // getTodayEvents() already returns events ordered by formated_date_time
        $events = $this->getTodayEvents($data);

        // Use a stack to track which breaks are currently OPEN (unclosed)
        // Process ALL break events for the entire day to correctly match them
        $stack = [];

        foreach ($events as $event) {
            // Only process break events
            if ($event->type !== 'break_start' && $event->type !== 'break_end') {
                continue;
            }

            if ($event->type === 'break_start') {
                // Add to stack - this break might be the one we're trying to end
                $stack[] = $event;
            } elseif ($event->type === 'break_end') {
                // Pop from stack - this closes the most recent unclosed break
                if (!empty($stack)) {
                    array_pop($stack);
                }
            }
        }

        // Return the most recent unclosed break (top of stack)
        if (!empty($stack)) {
            return end($stack);
        }

        return null;
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
     * Uses formated_date_time for accurate comparison across midnight
     */
    protected function getPreviousEvent(array $data): ?UserTimeClock
    {
        $events = $this->getTodayEvents($data);
        
        // Get the current datetime using formated_date_time for accurate comparison
        // For getPreviousEvent, we just need to compare event times directly
        $shiftTimes = $this->getShiftTimes($data);
        $currentFormatted = $this->normalizeDateTime(
            $data['clock_date'], $data['time'], $shiftTimes['shift_start'], $shiftTimes['shift_end']
        )['formated_date_time'];
        $currentCarbon = Carbon::parse($currentFormatted);

        // Filter events that occurred before current time using formated_date_time
        $previousEvents = $events->filter(function ($event) use ($currentCarbon) {
            $eventCarbon = Carbon::parse($event->formated_date_time);
            return $eventCarbon->lessThan($currentCarbon);
        })->sortByDesc('formated_date_time');

        return $previousEvents->first(); // Most recent event before current time
    }

    /**
     * Get the next event in the timeline (after current time)
     * Uses formated_date_time for accurate comparison across midnight
     */
    protected function getNextEvent(array $data): ?UserTimeClock
    {
        $events = $this->getTodayEvents($data);

        // Use formated_date_time for accurate comparison
        $shiftTimes = $this->getShiftTimes($data);
        $currentFormatted = $this->normalizeDateTime(
            $data['clock_date'], $data['time'], $shiftTimes['shift_start'], $shiftTimes['shift_end']
        )['formated_date_time'];
        $currentCarbon = Carbon::parse($currentFormatted);

        // Get events after the current time, sorted by formated_date_time ascending
        $nextEvents = $events->filter(function ($event) use ($currentCarbon) {
            $eventTime = Carbon::parse($event->formated_date_time);
            return $eventTime->greaterThan($currentCarbon);
        })->sortBy('formated_date_time');

        return $nextEvents->first(); // First event after current time
    }

    /**
     * Get all blocked time ranges (day-in to day-out pairs)
     */
    protected function getBlockedRanges(array $data): array
    {
        $events = $this->getTodayEvents($data);
        $ranges = [];

        // Get all day-in events sorted by formated_date_time for proper chronological order
        $dayIns = $events->where('type', 'day_in')->sortBy('formated_date_time');

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
                ->sortBy('formated_date_time')
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
            ->orderBy('formated_date_time')
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
                'buffer_time' => isset($data['buffer_time']) ? $data['buffer_time'] / 60 : null,
                'created_from' => $data['created_from'] ?? null,
                'updated_from' => $data['created_from'] ?? null,
            ];

            $timeClock = UserTimeClock::create($entryData);

            DB::commit();

            return [
                'status' => true,
                'code' => 200,
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
            throw $e;

            return [
                'status' => false,
                'code' => 500,
                'message' => __('Failed to create time clock entry.', locale: $this->language),
            ];
        }
    }
}
