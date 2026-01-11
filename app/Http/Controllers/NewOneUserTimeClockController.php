<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreUserTimeClockRequest;
use App\Models\UserTimeClock;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class NewOneUserTimeClockController extends Controller
{
    /**
     * Store a newly created time clock entry.
     */
    public function store(StoreUserTimeClockRequest $request): JsonResponse
    {
        try {
            $validated = $request->validated();

            // Set defaults
            $shiftStart = $validated['shift_start'] ?? '08:00';
            $shiftEnd = $validated['shift_end'] ?? '23:00';
            $bufferTime = $validated['buffer_time'] ?? 3;

            // Parse event date and time
            $eventDate = Carbon::parse($validated['clock_date']);
            $eventTime = $validated['time'];
            $eventDateTime = Carbon::parse($validated['clock_date'] . ' ' . $eventTime);

            // Determine if post-midnight and calculate formated_date_time FIRST
            // This is needed for proper chronological validation
            $isPostMidnight = $this->isPostMidnight($eventTime);
            $formatedDateTime = $isPostMidnight
                ? Carbon::parse($validated['clock_date'])->addDay()->format('Y-m-d') . ' ' . $eventTime
                : $validated['clock_date'] . ' ' . $eventTime;

            // Parse formated_date_time as Carbon instance for comparison
            $formatedDateTime = Carbon::parse($formatedDateTime);

            // Calculate shift window
            $shiftWindow = $this->getShiftWindow($shiftStart, $shiftEnd, $bufferTime);

            // Validate time is within shift window
            $timeValidation = $this->validateTimeWindow($eventTime, $shiftWindow);
            if (!$timeValidation['valid']) {
                return $this->errorResponse(
                    $timeValidation['message'],
                    \App\Enums\ResponseError::ERROR_422
                );
            }

            // Retrieve existing events for this user and date
            $existingEvents = UserTimeClock::forShop($validated['shop_id'])
                ->forUser($validated['user_id'])
                ->forDate($validated['clock_date'])
                ->orderBy('formated_date_time', 'asc')
                ->get();

            // Get last event
            $lastEvent = $existingEvents->last();

            // Validate chronological order using formated_date_time
            $chronologicalValidation = $this->validateChronologicalOrder(
                $formatedDateTime,
                $lastEvent
            );
            if (!$chronologicalValidation['valid']) {
                return $this->errorResponse(
                    $chronologicalValidation['message'],
                    \App\Enums\ResponseError::ERROR_422
                );
            }

            // Validate event sequence
            $sequenceValidation = $this->validateEventSequence(
                $validated['type'],
                $existingEvents
            );
            if (!$sequenceValidation['valid']) {
                return $this->errorResponse(
                    $sequenceValidation['message'],
                    \App\Enums\ResponseError::ERROR_422
                );
            }

            // Prepare data for storage
            $data = [
                'shop_id' => $validated['shop_id'],
                'user_id' => $validated['user_id'],
                'date_at' => $validated['clock_date'], // Always original date
                'time_at' => $eventTime,
                'date_time' => $eventDateTime,
                'formated_date_time' => $formatedDateTime,
                'shift_start' => $shiftStart,
                'shift_end' => $shiftEnd,
                'type' => $validated['type'],
                'comment' => $validated['comment'] ?? null,
                'buffer_time' => $bufferTime,
                'created_from' => $validated['created_from'] ?? null,
            ];

            // Save to database
            $timeClock = UserTimeClock::create($data);

            return $this->successResponse(
                'Attendance saved successfully.',
                $timeClock
            );
        } catch (\Exception $e) {
            Log::error('UserTimeClock store error: ' . $e->getMessage(), [
                'request' => $request->all(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;

            return $this->errorResponse(
                'An error occurred while saving attendance.',
                \App\Enums\ResponseError::ERROR_422
            );
        }
    }

    /**
     * Calculate the allowed shift time window with buffer.
     */
    private function getShiftWindow(string $shiftStart, string $shiftEnd, int $bufferTime): array
    {
        $start = Carbon::parse($shiftStart)->subHours($bufferTime);
        $end = Carbon::parse($shiftEnd)->addHours($bufferTime);

        return [
            'start' => $start->format('H:i'),
            'end' => $end->format('H:i'),
        ];
    }

    /**
     * Validate if event time is within allowed shift window.
     */
    private function validateTimeWindow(string $eventTime, array $shiftWindow): array
    {
        $event = Carbon::parse($eventTime);
        $windowStart = Carbon::parse($shiftWindow['start']);
        $windowEnd = Carbon::parse($shiftWindow['end']);

        // Handle next-day window (e.g., 05:00 - 02:00 next day)
        if ($windowEnd->lessThan($windowStart)) {
            // Window crosses midnight
            $isValid = $event->greaterThanOrEqualTo($windowStart) || $event->lessThanOrEqualTo($windowEnd);
        } else {
            $isValid = $event->between($windowStart, $windowEnd, true);
        }

        if (!$isValid) {
            return [
                'valid' => false,
                'message' => "Event time must be between {$shiftWindow['start']} and {$shiftWindow['end']}.",
            ];
        }

        return ['valid' => true];
    }

    /**
     * Check if time is post-midnight (00:00 - 02:00).
     */
    private function isPostMidnight(string $time): bool
    {
        $timeParsed = Carbon::parse($time);
        $midnight = Carbon::parse('00:00');
        $twoAM = Carbon::parse('02:00');

        return $timeParsed->between($midnight, $twoAM, true);
    }

    /**
     * Validate chronological order - new event must be after last event.
     */
    private function validateChronologicalOrder($formatedDateTime, $lastEvent): array
    {
        if (!$lastEvent) {
            return ['valid' => true];
        }

        $lastEventTime = Carbon::parse($lastEvent->formated_date_time);

        if ($formatedDateTime->lessThanOrEqualTo($lastEventTime)) {
            return [
                'valid' => false,
                'message' => 'Event time must be after the last recorded event at '
                    . $lastEventTime->format('h:i A') . '.',
            ];
        }

        return ['valid' => true];
    }

    /**
     * Validate event sequence based on type and existing events.
     * Supports multiple Day In/Day Out cycles within the same day.
     */
    private function validateEventSequence(string $type, $existingEvents): array
    {
        // Find last day_in and day_out to determine if currently "in shift"
        $lastDayIn = $existingEvents->where('type', 'day_in')->last();
        $lastDayOut = $existingEvents->where('type', 'day_out')->last();

        // Determine if we're currently in a shift (day_in without matching day_out)
        $isInShift = $lastDayIn && (!$lastDayOut || $lastDayOut->id < $lastDayIn->id);

        $lastBreakStart = $existingEvents->where('type', 'break_start')->last();
        $lastBreakEnd = $existingEvents->where('type', 'break_end')->last();

        // Check if there's an unclosed break
        $hasUnclosedBreak = $lastBreakStart && (!$lastBreakEnd || $lastBreakEnd->id < $lastBreakStart->id);

        switch ($type) {
            case 'day_in':
                // Allow multiple day_in events, but not if already in an active shift
                if ($isInShift) {
                    return [
                        'valid' => false,
                        'message' => 'Please record Day Out before starting a new shift.',
                    ];
                }
                if ($hasUnclosedBreak) {
                    return [
                        'valid' => false,
                        'message' => 'Please end the break before starting a new shift.',
                    ];
                }
                break;

            case 'break_start':
                // Break Start requires being in an active shift
                if (!$isInShift) {
                    return [
                        'valid' => false,
                        'message' => 'Day In must be recorded before Break Start.',
                    ];
                }
                if ($hasUnclosedBreak) {
                    return [
                        'valid' => false,
                        'message' => 'Please end the current break before starting a new one.',
                    ];
                }
                break;

            case 'break_end':
                if (!$lastBreakStart) {
                    return [
                        'valid' => false,
                        'message' => 'Break Start must be recorded before Break End.',
                    ];
                }
                if (!$hasUnclosedBreak) {
                    return [
                        'valid' => false,
                        'message' => 'No active break to end.',
                    ];
                }
                break;

            case 'day_out':
                // Day Out requires being in an active shift
                if (!$isInShift) {
                    return [
                        'valid' => false,
                        'message' => 'Day In must be recorded before Day Out.',
                    ];
                }
                if ($hasUnclosedBreak) {
                    return [
                        'valid' => false,
                        'message' => 'Please end the break before recording Day Out.',
                    ];
                }
                break;
        }

        return ['valid' => true];
    }

    /**
     * Format error response.
     */
    private function errorResponse(string $message, int $code): JsonResponse
    {
        return response()->json([
            'status' => false,
            'code' => $code,
            'message' => $message,
        ], $code == \App\Enums\ResponseError::ERROR_404 ? 404 : 422);
    }

    /**
     * Format success response.
     */
    private function successResponse(string $message, $data): JsonResponse
    {
        return response()->json([
            'status' => true,
            'code' => \App\Enums\ResponseError::NO_ERROR,
            'message' => $message,
            'data' => $data,
        ], 200);
    }
}
