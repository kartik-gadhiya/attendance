# Time Clock Validation System - Test Scenarios

This document provides comprehensive test scenarios to verify the time clock validation system implementation.

## Prerequisites

-   Server running: `php artisan serve`
-   API endpoint: `http://localhost:8000/api/time-clock`
-   Test user ID: 1 (or any valid user ID)
-   Test shop ID: 1

## Test Scenarios

### Test 1: Basic Day-In Entry (First Entry of the Day) ✓

**Purpose**: Verify first entry creates successfully with shift times from request.

```bash
curl -X POST http://localhost:8000/api/time-clock \
  -H "Content-Type: application/json" \
  -d '{
    "shop_id": 1,
    "user_id": 1,
    "clock_date": "2026-01-10",
    "time": "06:00:00",
    "shift_start": "08:00:00",
    "shift_end": "23:00:00",
    "type": "day_in",
    "buffer_time": 180
  }'
```

**Expected Result**:

-   Status: 201
-   Success: true
-   Data contains shift_start and shift_end from request

---

### Test 2: Day-Out Entry (Reuses Shift Times) ✓

**Purpose**: Verify subsequent entries use stored shift times.

```bash
curl -X POST http://localhost:8000/api/time-clock \
  -H "Content-Type: application/json" \
  -d '{
    "shop_id": 1,
    "user_id": 1,
    "clock_date": "2026-01-10",
    "time": "10:00:00",
    "type": "day_out",
    "buffer_time": 180
  }'
```

**Expected Result**:

-   Status: 201
-   Success: true
-   Shift times should match first entry (08:00:00 to 23:00:00)

---

### Test 3: Break Start and End ✓

**Purpose**: Verify break entries work correctly.

**Break Start:**

```bash
curl -X POST http://localhost:8000/api/time-clock \
  -H "Content-Type: application/json" \
  -d '{
    "shop_id": 1,
    "user_id": 1,
    "clock_date": "2026-01-10",
    "time": "12:00:00",
    "type": "break_start",
    "buffer_time": 180
  }'
```

**Expected**: Status 201, Success

**Break End:**

```bash
curl -X POST http://localhost:8000/api/time-clock \
  -H "Content-Type: application/json" \
  -d '{
    "shop_id": 1,
    "user_id": 1,
    "clock_date": "2026-01-10",
    "time": "13:00:00",
    "type": "break_end",
    "buffer_time": 180
  }'
```

**Expected**: Status 201, Success

---

### Test 4: Overlap Detection (Should Fail) ✗

**Purpose**: Verify overlap detection prevents conflicting times.

```bash
curl -X POST http://localhost:8000/api/time-clock \
  -H "Content-Type: application/json" \
  -d '{
    "shop_id": 1,
    "user_id": 1,
    "clock_date": "2026-01-10",
    "time": "12:30:00",
    "type": "day_in",
    "buffer_time": 180
  }'
```

**Expected Result**:

-   Status: 422
-   Success: false
-   Message: "Day-in time overlaps with an existing event."

---

### Test 5: Buffer Time Validation (Should Fail) ✗

**Purpose**: Verify events outside buffer time are rejected.

```bash
curl -X POST http://localhost:8000/api/time-clock \
  -H "Content-Type: application/json" \
  -d '{
    "shop_id": 1,
    "user_id": 1,
    "clock_date": "2026-01-10",
    "time": "02:00:00",
    "shift_start": "08:00:00",
    "shift_end": "23:00:00",
    "type": "day_in",
    "buffer_time": 180
  }'
```

**Expected Result**:

-   Status: 422
-   Success: false
-   Message: "Day-in time is outside the allowed buffer time."

---

### Test 6: Midnight Crossing Shift ✓

**Purpose**: Verify midnight-crossing shifts work correctly.

**Day-In Late Evening:**

```bash
curl -X POST http://localhost:8000/api/time-clock \
  -H "Content-Type: application/json" \
  -d '{
    "shop_id": 1,
    "user_id": 2,
    "clock_date": "2026-01-10",
    "time": "23:00:00",
    "shift_start": "05:00:00",
    "shift_end": "02:00:00",
    "type": "day_in",
    "buffer_time": 180
  }'
```

**Day-Out After Midnight:**

```bash
curl -X POST http://localhost:8000/api/time-clock \
  -H "Content-Type: application/json" \
  -d '{
    "shop_id": 1,
    "user_id": 2,
    "clock_date": "2026-01-10",
    "time": "01:20:00",
    "type": "day_out",
    "buffer_time": 180
  }'
```

**Expected Result**:

-   Status: 201
-   Success: true
-   `date_at`: 2026-01-10
-   `formated_date_time`: 2026-01-11 01:20:00 (next day!)

---

### Test 7: Break End Without Break Start (Should Fail) ✗

**Purpose**: Verify break end validation requires an open break.

```bash
curl -X POST http://localhost:8000/api/time-clock \
  -H "Content-Type: application/json" \
  -d '{
    "shop_id": 1,
    "user_id": 3,
    "clock_date": "2026-01-11",
    "time": "09:00:00",
    "shift_start": "08:00:00",
    "shift_end": "17:00:00",
    "type": "break_end",
    "buffer_time": 180
  }'
```

**Expected Result**:

-   Status: 422
-   Success: false
-   Message: "No active break found to end."

---

### Test 8: Multiple Day-In/Out Cycles ✓

**Purpose**: Verify multiple punch cycles within same shift.

```bash
# First cycle
curl -X POST http://localhost:8000/api/time-clock \
  -H "Content-Type: application/json" \
  -d '{
    "shop_id": 1,
    "user_id": 4,
    "clock_date": "2026-01-11",
    "time": "06:00:00",
    "shift_start": "08:00:00",
    "shift_end": "23:00:00",
    "type": "day_in",
    "buffer_time": 180
  }'

curl -X POST http://localhost:8000/api/time-clock \
  -H "Content-Type: application/json" \
  -d '{
    "shop_id": 1,
    "user_id": 4,
    "clock_date": "2026-01-11",
    "time": "10:00:00",
    "type": "day_out",
    "buffer_time": 180
  }'

# Second cycle
curl -X POST http://localhost:8000/api/time-clock \
  -H "Content-Type: application/json" \
  -d '{
    "shop_id": 1,
    "user_id": 4,
    "clock_date": "2026-01-11",
    "time": "12:00:00",
    "type": "day_in",
    "buffer_time": 180
  }'

curl -X POST http://localhost:8000/api/time-clock \
  -H "Content-Type: application/json" \
  -d '{
    "shop_id": 1,
    "user_id": 4,
    "clock_date": "2026-01-11",
    "time": "22:00:00",
    "type": "day_out",
    "buffer_time": 180
  }'
```

**Expected**: All should succeed with status 201

---

## Quick Test Commands

### View All Entries

```bash
curl -X GET http://localhost:8000/api/time-clock
```

### Clear Test Data (via database)

```bash
# Laravel Tinker
php artisan tinker
> App\Models\UserTimeClock::truncate();
```

## Verification Checklist

-   [ ] First entry stores shift times from request
-   [ ] Subsequent entries use stored shift times
-   [ ] Overlap detection prevents conflicting times
-   [ ] Buffer time validation works correctly (3 hours before/after)
-   [ ] Midnight crossing stores correct dates (date_at vs formated_date_time)
-   [ ] Multiple day-in/out cycles allowed
-   [ ] Break start/end pairs work correctly
-   [ ] Break end without break start is rejected
-   [ ] Proper error messages with status 422
-   [ ] Success responses with status 201
