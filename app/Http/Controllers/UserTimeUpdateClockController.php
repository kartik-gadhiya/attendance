<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreUserTimeClockRequest;
use App\Http\Requests\UpdateUserTimeClockRequest;
use App\Models\UserTimeClock;
use App\Services\UserTimeClockService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class UserTimeUpdateClockController extends Controller
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
     * Update an existing time clock entry.
     */
    public function update(UpdateUserTimeClockRequest $request, int $id): JsonResponse
    {
        try {
            // Get validated data
            $validated = $request->validated();

            // Set language if provided in request
            if ($request->has('language')) {
                $this->service->setLanguage($request->input('language'));
            }

            // Call update service
            $result = $this->service->updateEvent($id, $validated);

            // Return response based on result
            if ($result['status']) {
                return response()->json([
                    'success' => true,
                    'code' => $result['code'],
                    'message' => $result['message'],
                    'data' => $result['data'] ?? null,
                ], $result['code'] ?? 200);
            }

            return response()->json([
                'success' => false,
                'code' => $result['code'],
                'message' => $result['message'],
            ], $result['code'] == 404 ? 404 : 422);
        } catch (\Exception $e) {
            Log::error('Failed to update time clock entry', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'id' => $id,
                'request_data' => $request->all(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to update time clock entry',
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
