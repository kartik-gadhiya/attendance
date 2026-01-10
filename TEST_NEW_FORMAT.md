# Quick Test - New H:i Format

## Test Using curl

```bash
curl -X POST http://localhost:8000/api/time-clock \
  -H "Content-Type: application/json" \
  -d '{
    "shop_id": 1,
    "user_id": 3,
    "clock_date": "2026-01-10",
    "time": "06:00",
    "shift_start": "08:00",
    "shift_end": "23:00",
    "type": "day_in",
    "buffer_time": 3
  }'
```

## Expected Response

```json
{
  "success": true,
  "message": "Time clock entry created successfully.",
  "data": {
    "shop_id": 1,
    "user_id": 3,
    "date_at": "2026-01-10",
    "time_at": "06:00:00",
    "type": "day_in",
    "shift_start": "08:00:00",
    "shift_end": "23:00:00",
    "buffer_time": 180,
    ...
  },
  "code": 201
}
```

## Key Points

✅ **Input**: `"time": "06:00"` (H:i format)  
✅ **Stored**: `"time_at": "06:00:00"` (H:i:s format)  
✅ **Input**: `"buffer_time": 3` (hours)  
✅ **Stored**: `"buffer_time": 180` (minutes)

The system automatically converts the formats internally!
