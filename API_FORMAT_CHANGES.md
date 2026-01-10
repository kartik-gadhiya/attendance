# API Format Change Summary

## ✅ Changes Applied

### Time Format Simplified

**Before**:

```json
{
    "time": "06:00:00",
    "shift_start": "10:00:00",
    "shift_end": "23:00:00"
}
```

**After** (New Format):

```json
{
    "time": "06:00",
    "shift_start": "10:00",
    "shift_end": "23:00"
}
```

### Buffer Time in Hours

**Before**:

```json
{
    "buffer_time": 180 // minutes
}
```

**After** (New Format):

```json
{
    "buffer_time": 3 // hours
}
```

---

## Example API Request

```json
POST /api/time-clock

{
  "shop_id": 1,
  "user_id": 2,
  "clock_date": "2026-01-01",
  "time": "05:00",           // H:i format (no seconds)
  "shift_start": "08:00",     // H:i format
  "shift_end": "23:00",       // H:i format
  "type": "day_in",
  "buffer_time": 3            // hours (not minutes)
}
```

---

## Files Modified

1. **StoreUserTimeClockRequest.php**

    - Changed validation from `H:i:s` to `H:i`
    - Added min/max validation for buffer_time (1-24 hours)

2. **UserTimeClockService.php**
    - Added `normalizeRequestData()` method
    - Converts H:i → H:i:s internally
    - Converts hours → minutes for buffer_time

---

## Validation Rules

### Time Fields

-   **Format**: `H:i` (e.g., "05:00", "23:00")
-   **Required**: `time`
-   **Optional**: `shift_start`, `shift_end`

### Buffer Time

-   **Type**: Integer (hours)
-   **Range**: 1-24 hours
-   **Optional**: Defaults to 180 minutes (3 hours) if not provided

---

## Backward Compatibility

The system still works internally with:

-   **H:i:s** format (seconds added automatically)
-   **Minutes** for buffer time (converted automatically)

This ensures all existing logic continues to work without changes!
