# Edit Overlap Validation - Issues Identified

## Test Results Summary
- Total Tests: 8
- Passed: 5 (62.50%)
- Failed: 3 (37.50%)

## Critical Issues Found

### Issue 1: Break_start Cannot Move Between Its Own Pair
**Test**: Scenario 1, Attempt 1
**Details**:
- Record ID: 185 (break_start)
- Current time: 12:05
- Paired break_end: 12:07 (ID 199)
- Attempted new time: 12:08 (which is AFTER its break_end)
- **Expected**: Should reject (break_start can't be after break_end)
- **Actual**: Correctly rejected, but error message indicates the logic is overly strict

**Root Cause**: The `validateEventTypeSequence()` RULE 2 check at lines 439-453:
```php
if ($eventType === 'break_start') {
    $currentNextEvent = UserTimeClock::...->where('formated_date_time', '>', $event->formated_date_time)...->first();
    
    if ($currentNextEvent && $currentNextEvent->type === 'break_end') {
        if (!$next || $next->id !== $currentNextEvent->id) {
            return [... 'break_start cannot be moved after its paired break_end'];
        }
    }
}
```

**Problem**: When a break_start is edited to a time between its current position and its paired break_end, the `findPreviousEvent()` for the NEW position may find a different event as "next", causing the validation to fail even though the move is valid.

**Example**:
- Original: day_in at 12:00, break_start at 12:05, break_end at 12:07, break_start at 12:10
- Edit break_start (12:05) to 12:08
- After edit, neighbors become: previous=day_in(12:00), next=break_start(12:10)
- The logic checks: "Is next event (12:10) the same as current's paired break_end (12:07)?"
- Answer: No! So it rejects the move.

---

### Issue 2: Break_end Paired Break Detection Using Wrong References
**Test**: Scenario 4, Test 1
**Details**:
- Record ID: 196 (break_end)
- Current time: 00:34
- Paired break_start: 00:30 (ID 195)
- Attempted new time: 12:09
- **Expected**: Should allow (12:09 is after its paired break_start at 00:30, and it's early morning time so next day)
- **Actual**: Rejects with "Break end cannot be moved to or before its paired break start at 00:30"

**Root Cause**: The `findPairedBreakStart()` method is finding the WRONG paired break_start.

Looking at records:
```
ID 195 | break_start | 00:30 | 2026-01-08 00:30
ID 196 | break_end   | 00:34 | 2026-01-08 00:34
ID 188 | break_start | 17:31 | 2026-01-07 17:15  ← WRONG! This is earlier in formated_date_time
ID 200 | break_end   | 18:14 | 2026-01-07 17:30
```

**The Problem**:
- ID 188 has formated_date_time of 2026-01-07 17:15 (this is likely a data issue or storage issue)
- ID 196 has formated_date_time of 2026-01-08 00:34
- When finding paired break_start, the logic looks for events BEFORE 196 in formated_date_time order
- It might find ID 188 (17:15) instead of ID 195 (00:30) because of the date/time mismatch

**Note**: There's a DATA INTEGRITY ISSUE! Look at the formated_date_time values:
- ID 188 break_start at 17:31 stored as formated_date_time 2026-01-07 17:15 (WRONG TIME!)
- ID 200 break_end at 18:14 stored as formated_date_time 2026-01-07 17:30 (WRONG TIME!)

---

### Issue 3: Day_out Not Checked for Overlap with Breaks During Edit
**Test**: Scenario 4, Test 2
**Details**:
- Record ID: 189 (day_out)
- Current time: 18:00
- Attempted new time: 17:35 (which falls inside the break 17:31-18:14)
- **Expected**: Should reject (day_out overlaps with break)
- **Actual**: Successfully updated! This is a critical bug.

**Root Cause**: The `validateEventEdit()` method does NOT check for overlaps with break ranges. It only checks:
1. Duplicate time (same second)
2. Paired event constraints (break_start/break_end pairs)
3. Sequence rules

But it DOES NOT check if an event falls WITHIN a break range.

**The issue**: When editing a day_in, day_out, or break_start to a new time, we need to ensure:
1. It doesn't overlap with any complete break range (break_start...break_end)
2. It doesn't fall within the boundaries of other events

Currently, this overlap check is missing from the edit validation.

---

## Data Integrity Issue Discovered

The following records have mismatched time_at vs formated_date_time:

```
ID 188 | break_start | time_at: 17:31 | formated_date_time: 2026-01-07 17:15:00 (MISMATCH!)
ID 200 | break_end   | time_at: 18:14 | formated_date_time: 2026-01-07 17:30:00 (MISMATCH!)
```

These should be:
- ID 188: formated_date_time should be 2026-01-07 17:31:00
- ID 200: formated_date_time should be 2026-01-07 18:14:00

This is causing the pairing logic to fail because it relies on formated_date_time being accurate.

---

## Summary of Fixes Needed

1. **Fix the break_start edit validation** (RULE 2):
   - Instead of checking if the next event is the paired break_end, verify that:
     - The new position is still BEFORE the paired break_end
     - Don't reject just because another event moved into the "next" position

2. **Fix the break_end pairing logic**:
   - Ensure findPairedBreakStart() finds the correct paired break_start
   - Handle cases where break times might be on different calendar days

3. **Add overlap checking for edit operations**:
   - Check if the new position overlaps with any break ranges
   - Check if day_in/day_out fall within break boundaries

4. **Fix data integrity**:
   - Correct the formated_date_time values for IDs 188 and 200
   - Ensure formated_date_time always matches the date portion of date_at and time_at

---

## Affected Records

### Data Integrity Issues:
- ID 188 (break_start): time_at=17:31, formated_date_time=2026-01-07 17:15:00
- ID 200 (break_end): time_at=18:14, formated_date_time=2026-01-07 17:30:00

### Validation Logic Issues:
- All break_start edits between current position and paired break_end
- All break_end edits (due to wrong pairing)
- All day_in/day_out edits moving into break ranges

---

## Validation Test Results

**Passing Tests** (5):
✓ Reject break_start after its break_end
✓ Reject day_in overlapping with break
✓ Reject duplicate timestamp
✓ Move day_in to valid position
✓ Move break_start before break_end

**Failing Tests** (3):
✗ Move break_start to position between current and paired break_end
✗ Move break_end to non-overlapping position
✗ Reject day_out moved into break range

