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
        } catch (\Exception $e) {
        }
    }
}
