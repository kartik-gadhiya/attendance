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
        // Get shift times (from request or existing records)
        $shiftTimes = $this->getShiftTimes($data);

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

        // Check for overlaps with existing events
        $overlap = $this->checkOverlap($data, 'break_start');
        if ($overlap) {
            return [
                'status' => false,
                'code' => 422,
                'message' => __('Break start time overlaps with an existing event.', locale: $this->language),
            ];
        }

        return ['status' => true];
    }

    /**
     * Validate break-end entry
     */
    protected function validateBreakEnd(array $data): array
    {
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

        // Check for overlaps with existing events
        $overlap = $this->checkOverlap($data, 'break_end');
        if ($overlap) {
            return [
                'status' => false,
                'code' => 422,
                'message' => __('Break end time overlaps with an existing event.', locale: $this->language),
            ];
        }

        return ['status' => true];
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
     * Check if event time is within buffer time range
     */
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

                    // Check if event falls within break range (exclusive of endpoints for break_end type)
                    if ($currentType === 'break_end') {
                        // For break_end, it should match the break_start time or be after it
                        if ($eventTime->greaterThan($eventStart) && $eventTime->lessThan($breakEndTime)) {
                            return true;
                        }
                    } else {
                        // For other types, check if they fall within the break
                        if ($eventTime->greaterThan($eventStart) && $eventTime->lessThan($breakEndTime)) {
                            return true;
                        }
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

    /**
     * Normalize datetime to handle midnight crossing
     */
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
