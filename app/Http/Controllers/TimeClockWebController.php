<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreUserTimeClockRequest;
use App\Models\User;
use App\Models\UserTimeClock;
use App\Services\UserTimeClockService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class TimeClockWebController extends Controller
{
    protected UserTimeClockService $service;
    protected string $language;

    public function __construct()
    {
        // Default language, can be overridden from user preferences or request
        $this->language = 'en';
        $this->service = new UserTimeClockService($this->language);
    }

    /**
     * Display the time clock management page.
     */
    public function index(): View
    {
        return view('time-clock.index');
    }

    /**
     * Fetch users for the dropdown.
     */
    public function getUsers(): JsonResponse
    {
        try {
            $users = User::select('id', 'name')
                ->orderBy('name')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $users,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to fetch users', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch users',
            ], 500);
        }
    }

    /**
     * Fetch time clock records with optional filters.
     */
    public function getRecords(Request $request): JsonResponse
    {
        try {
            $query = UserTimeClock::with('user');

            // Filter by user ID if provided
            if ($request->filled('user_id')) {
                $query->where('user_id', $request->user_id);
            }

            // Filter by date if provided
            if ($request->filled('date')) {
                $query->whereDate('date_at', $request->date);
            }

            $timeClocks = $query
                ->orderBy('formated_date_time', 'asc')
                ->paginate(50);

            return response()->json([
                'success' => true,
                'data' => $timeClocks,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to fetch time clock records', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch time clock records',
            ], 500);
        }
    }

    /**
     * Store a newly created time clock entry.
     */
    public function store(StoreUserTimeClockRequest $request): JsonResponse
    {
        try {
            // Get validated data
            $validated = $request->validated();
            $validated['buffer_time'] = 3;
            $validated['shift_start'] = "08:00";
            $validated['shift_end'] = "23:00";
            $validated['created_from'] = "B";

            // Set language if provided in request
            if ($request->has('language')) {
                $this->service->setLanguage($request->input('language'));
            }

            if ($validated['type'] === 'day_in') {
                $result = $this->service->dayInAdd($validated);
            } elseif ($validated['type'] === 'day_out') {
                $result = $this->service->dayOutAdd($validated);
            } elseif ($validated['type'] === 'break_start') {
                $result = $this->service->breakStartAdd($validated);
            } elseif ($validated['type'] === 'break_end') {
                $result = $this->service->breakEndAdd($validated);
            } else {
                $result = [
                    'status' => false,
                    'code' => 422,
                    'message' => 'Invalid entry type',
                ];
            }

            if ($result['status']) {
                return response()->json([
                    'success' => true,
                    'code' => $result['code'],
                    'message' => $result['message'],
                    'data' => $result['data'] ?? null,
                ], $result['code'] ?? 201);
            }

            return response()->json([
                'success' => false,
                'code' => $result['code'],
                'message' => $result['message'],
            ], $result['code'] ?? 422);
        } catch (\Exception $e) {
            Log::error('Failed to create time clock entry', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request_data' => $request->all(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to create time clock entry',
                'error' => config('app.debug') ? $e->getMessage() : 'An error occurred while processing your request',
            ], 500);
        }
    }

    /**
     * Update an existing time clock entry.
     */
    public function update(Request $request, $id): JsonResponse
    {
        try {
            // Validate the request
            $validated = $request->validate([
                'time' => 'required|date_format:H:i',
                'type' => 'required|in:day_in,day_out,break_start,break_end',
                'comment' => 'nullable|string|max:500',
            ]);

            $timeClockEntry = UserTimeClock::findOrFail($id);

            // Build complete data for service validation
            $completeData = [
                'shop_id' => $timeClockEntry->shop_id,
                'user_id' => $timeClockEntry->user_id,
                'clock_date' => $timeClockEntry->date_at,
                'time' => $validated['time'],
                'type' => $validated['type'],
                'shift_start' => $timeClockEntry->shift_start,
                'shift_end' => $timeClockEntry->shift_end,
                'buffer_time' => $timeClockEntry->buffer_time,
                'comment' => $validated['comment'] ?? null,
                'updated_from' => 'B',
            ];

            // Use service to validate and update
            $service = new \App\Services\UserTimeClockService('en');
            $result = $service->updateEvent($id, $completeData);

            if ($result['status']) {
                return response()->json([
                    'success' => true,
                    'message' => $result['message'],
                    'data' => $result['data'],
                ], $result['code'] ?? 200);
            }

            return response()->json([
                'success' => false,
                'message' => $result['message'],
            ], $result['code'] ?? 422);
        } catch (\Exception $e) {
            Log::error('Failed to update time clock entry', [
                'error' => $e->getMessage(),
                'id' => $id,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to update time clock entry',
                'error' => config('app.debug') ? $e->getMessage() : 'An error occurred',
            ], 500);
        }
    }
}
