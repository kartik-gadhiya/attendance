<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreUserTimeClockRequest;
use App\Models\UserTimeClock;
use App\Services\UserTimeClockService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class UserTimeClockController extends Controller
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
     * Store a newly created time clock entry in storage.
     */
    public function store(StoreUserTimeClockRequest $request): JsonResponse
    {
        try {
            // Get validated data
            $validated = $request->validated();
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
            throw $e;

            return response()->json([
                'success' => false,
                'message' => 'Failed to create time clock entry',
                'error' => config('app.debug') ? $e->getMessage() : 'An error occurred while processing your request',
            ], 500);
        }
    }


    /**
     * Display a listing of the time clock entries.
     */
    public function index(): JsonResponse
    {
        try {
            $timeClocks = UserTimeClock::with('user')
                ->orderBy('date_at', 'desc')
                ->orderBy('time_at', 'desc')
                ->paginate(15);

            return response()->json([
                'success' => true,
                'data' => $timeClocks,
            ], 200);
        } catch (\Exception $e) {
            Log::error('Failed to fetch time clock entries', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch time clock entries',
            ], 500);
        }
    }

    /**
     * Display the specified time clock entry.
     */
    public function show(UserTimeClock $userTimeClock): JsonResponse
    {
        try {
            $userTimeClock->load('user');

            return response()->json([
                'success' => true,
                'data' => $userTimeClock,
            ], 200);
        } catch (\Exception $e) {
            Log::error('Failed to fetch time clock entry', [
                'error' => $e->getMessage(),
                'id' => $userTimeClock->id ?? null,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch time clock entry',
            ], 500);
        }
    }
}
