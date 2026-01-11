<?php

class simplecode
{
    public function timeClockUserDataStoreAttendance(array $postData, $timeClockUser)
    {
        $userId = $timeClockUser->id;
        $shift = EmployeeShiftTiming::where('user_id', $userId)->first();

        if (!$shift) {
            return [
                'status' => false,
                'code' => ResponseError::ERROR_404,
                'message' => __('Shift timing not found for this employee.', locale: $this->language),
            ];
        }

        $validateData = $this->validateAddAttendance($postData, $userId, $shift);
        if (!$validateData['status']) {
            return $validateData;
        }


        return [
            'status' => true,
            'code' => ResponseError::NO_ERROR,
            'message' => __('Attendance saved successfully.', locale: $this->language),
            'data' => $validateData['data']
        ];
    }

    private function validateAddAttendance(array $data, int $userId, $shift)
    {
        $bufferHours = 3;
        $shopId = $data['shop_id'];
        $type = $data['type'];

        $timezone = Utility::getTimezoneByShopId($shopId);

        $clockDate = Carbon::parse($data['clock_date'])->startOfDay();
        $punchTime = Carbon::parse(
            $data['clock_date'] . ' ' . $data['time'],
        );

        $lastRecord = TimeClock::where('shop_id', $shopId)
            ->where('user_id', $userId)
            ->where('date_at', $clockDate->toDateString())
            ->orderBy('formated_date_time', 'desc')
            ->first();

        if ($type === 'day_in') {

            if ($lastRecord && $lastRecord->type === 'day_in') {
                return [
                    'status' => false,
                    'code' => ResponseError::ERROR_422,
                    'message' => __('You already punched in. Please punch out first.', locale: $this->language),
                ];
            }

            $shiftStart = Carbon::parse(
                $clockDate->toDateString() . ' ' . $shift->shift_time_start
            );

            $allowedEarlyTime = $shiftStart->copy()->subHours($bufferHours);
            $minAllowedTime   = $allowedEarlyTime;

            $blockedByLastPunchOut = false;

            // If last record is day_out, override minimum allowed time
            if ($lastRecord && $lastRecord->type === 'day_out') {
                $lastDayOutTime = Carbon::parse($lastRecord->formated_date_time);

                if ($lastDayOutTime->greaterThan($minAllowedTime)) {
                    $minAllowedTime = $lastDayOutTime;
                    $blockedByLastPunchOut = true;
                }
            }

            if ($punchTime->lessThan($minAllowedTime)) {

                if ($blockedByLastPunchOut) {
                    return [
                        'status' => false,
                        'code' => ResponseError::ERROR_422,
                        'message' => __('Punch-in time must be after your last punch-out.', locale: $this->language),
                    ];
                }

                return [
                    'status' => false,
                    'code' => ResponseError::ERROR_422,
                    'message' => __('You cannot punch in before :time.', [
                        'time' => $allowedEarlyTime->format('h:i A'),
                    ], locale: $this->language),
                ];
            }
        }


        if ($type === 'day_out') {
            $dayRecords = TimeClock::where('shop_id', $shopId)
                ->where('user_id', $userId)
                ->where('date_at', $clockDate->toDateString())
                ->orderBy('formated_date_time', "ASC")
                // ->orderBy('id', "DESC")
                ->get();

            // dd($dayRecords->toArray());

            $dayIn       = $dayRecords->where('type', 'day_in')->first();
            $dayOut      = $dayRecords->where('type', 'day_out')->first();
            $lastBreakIn = $dayRecords->where('type', 'break_start')->last();
            $lastBreakOut = $dayRecords->where('type', 'break_end')->last();

            if (!$dayIn) {
                return [
                    'status' => false,
                    'code' => ResponseError::ERROR_422,
                    'message' => __('No active punch-in found for today.', locale: $this->language),
                ];
            }

            if ($dayOut) {
                return [
                    'status' => false,
                    'code' => ResponseError::ERROR_422,
                    'message' => __('You have already punched out.', locale: $this->language),
                ];
            }

            if ($lastBreakIn && (!$lastBreakOut || Carbon::parse($lastBreakOut->formated_date_time)->lessThan(Carbon::parse($lastBreakIn->formated_date_time)))) {
                return [
                    'status' => false,
                    'code' => ResponseError::ERROR_422,
                    'message' => __('Please end your break before punching out.', locale: $this->language),
                ];
            }

            $minAllowedTime = $lastBreakOut ? Carbon::parse($lastBreakOut->formated_date_time) : Carbon::parse($dayIn->formated_date_time);

            if ($punchTime->lessThanOrEqualTo($minAllowedTime)) {
                return [
                    'status' => false,
                    'code' => ResponseError::ERROR_422,
                    'message' => __('Punch-out time must be after your last activity.', locale: $this->language),
                ];
            }
        }


        if ($type === 'break_start') {

            $dayRecords = TimeClock::where('shop_id', $shopId)
                ->where('user_id', $userId)
                ->where('date_at', $clockDate->toDateString())
                ->orderBy('formated_date_time', 'DESC')
                ->orderBy('id')
                ->get();

            $dayIn  = $dayRecords->where('type', 'day_in')->first();
            $dayOut = $dayRecords->where('type', 'day_out')->first();

            if (!$dayIn) {
                return [
                    'status' => false,
                    'code' => ResponseError::ERROR_422,
                    'message' => __('You must punch in before starting a break.', locale: $this->language),
                ];
            }

            if ($dayOut) {
                $dayOutTime = Carbon::parse($dayOut->formated_date_time);

                if ($punchTime->greaterThanOrEqualTo($dayOutTime)) {
                    return [
                        'status' => false,
                        'code' => ResponseError::ERROR_422,
                        'message' => __('Break start time must be before punch-out time.', locale: $this->language),
                    ];
                }
            }

            $dayInTime = Carbon::parse($dayIn->formated_date_time);

            if ($punchTime->lessThanOrEqualTo($dayInTime)) {
                return [
                    'status' => false,
                    'code' => ResponseError::ERROR_422,
                    'message' => __('Break start time must be after punch-in time.', locale: $this->language),
                ];
            }

            $shiftEnd = Carbon::parse($clockDate->toDateString() . ' ' . $shift->shift_time_end);

            $maxAllowedTime = $shiftEnd->copy()->addHours($bufferHours);

            if ($punchTime->greaterThan($maxAllowedTime)) {
                return [
                    'status' => false,
                    'code' => ResponseError::ERROR_422,
                    'message' => __('Break start time exceeds allowed shift buffer time.', locale: $this->language),
                ];
            }

            $lastBreakStart = $dayRecords->where('type', 'break_start')->last();
            $lastBreakEnd   = $dayRecords->where('type', 'break_end')->last();

            if ($lastBreakStart && (!$lastBreakEnd || Carbon::parse($lastBreakEnd->formated_date_time)->lessThan(Carbon::parse($lastBreakStart->formated_date_time)))) {
                return [
                    'status' => false,
                    'code' => ResponseError::ERROR_422,
                    'message' => __('You already have an active break.', locale: $this->language),
                ];
            }
        }

        if ($type === 'break_end') {
            $dayRecords = TimeClock::where('shop_id', $shopId)
                ->where('user_id', $userId)
                ->where('date_at', $clockDate->toDateString())
                ->orderBy('formated_date_time', "DESC")
                ->get();

            $lastBreakIn  = $dayRecords->where('type', 'break_start')->last();
            $lastBreakOut = $dayRecords->where('type', 'break_end')->last();
            $lastDayOut   = $dayRecords->where('type', 'day_out')->last();

            if (!$lastBreakIn) {
                return [
                    'status' => false,
                    'code' => ResponseError::ERROR_422,
                    'message' => __('No break started to end.', locale: $this->language),
                ];
            }

            if (
                $lastBreakOut &&
                strtotime($lastBreakOut->formated_date_time) > strtotime($lastBreakIn->formated_date_time)
            ) {
                return [
                    'status' => false,
                    'code' => ResponseError::ERROR_422,
                    'message' => __('Break already ended.', locale: $this->language),
                ];
            }

            if ($punchTime->timestamp <= strtotime($lastBreakIn->formated_date_time)) {
                return [
                    'status' => false,
                    'code' => ResponseError::ERROR_422,
                    'message' => __('Break end time must be after break start time.', locale: $this->language),
                ];
            }

            if ($lastDayOut) {
                if ($punchTime->timestamp >= strtotime($lastDayOut->formated_date_time)) {
                    return [
                        'status' => false,
                        'code' => ResponseError::ERROR_422,
                        'message' => __('Break end time must be before punch-out time.', locale: $this->language),
                    ];
                }
            } else {
                $shiftEnd = Carbon::parse(
                    $clockDate->toDateString() . ' ' . $shift->shift_time_end
                );

                $maxAllowedTime = $shiftEnd->addHours($bufferHours);

                if ($punchTime->timestamp > $maxAllowedTime->timestamp) {
                    return [
                        'status' => false,
                        'code' => ResponseError::ERROR_422,
                        'message' => __('Break end time exceeds allowed shift buffer time.', locale: $this->language),
                    ];
                }
            }
        }



        $saveData = $this->saveAttendanceData($data, $userId, $shift, $bufferHours, $clockDate, $timezone);
        return [
            'status' => true,
            'data' => $saveData
        ];
    }


    private function saveAttendanceData($data, $userId, $shift, $buffer, $clockDate, $timezone)
    {
        $time = Carbon::parse($data['time'])->format('H:i:s');
        return TimeClock::create([
            'shop_id' => $data['shop_id'],
            'user_id' => $userId,
            'date_at' => $clockDate->toDateString(),
            'type' => $data['type'],
            'time_at' => $time,
            'date_time' => Carbon::now($timezone),
            'formated_date_time' => $clockDate->toDateString() . ' ' . $time,
            'shift_start' => $shift->shift_time_start,
            'shift_end' => $shift->shift_time_end,
            'created_from' => "B",
            'buffer_time' => $buffer,
            'comment' => $data['comment'],
        ]);
    }
}
