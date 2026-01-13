# Using Default Filters in Time Clock

## Overview

The `setDefaultFilters()` function allows you to programmatically set default user and date filters on the Time Clock page. This is useful for:

-   Testing specific user records
-   Deep linking to specific user/date combinations
-   Pre-populating filters based on URL parameters
-   Setting up preset views

## Function Signature

```javascript
setDefaultFilters((userId = null), (date = null));
```

### Parameters

-   **userId** _(number|null)_

    -   The user ID to preselect
    -   Use `null` to select "All Users"
    -   Must match an existing user ID in the dropdown

-   **date** _(string|null)_
    -   The date to preselect in `YYYY-MM-DD` format
    -   Use `null` to keep today's date
    -   Format: `"2026-01-13"`

## Usage Examples

### Example 1: Set User ID 4 and Date 2026-01-07

Open browser console (F12) and run:

```javascript
setDefaultFilters(4, "2026-01-07");
```

**Result:**

-   User dropdown shows "Michael Johnson #4"
-   Date shows "07/01/2026"
-   Records table loads filtered data for User 4 on 2026-01-07
-   Form inputs sync with filter values

### Example 2: Set Only User (Keep Today's Date)

```javascript
setDefaultFilters(4, null);
```

**Result:**

-   User dropdown shows "Michael Johnson #4"
-   Date remains today's date
-   Records filtered for User 4 on today's date

### Example 3: Set Only Date (Show All Users)

```javascript
setDefaultFilters(null, "2026-01-10");
```

**Result:**

-   User dropdown shows "All Users"
-   Date shows "10/01/2026"
-   Records from all users on 2026-01-10

### Example 4: Reset to Defaults

```javascript
setDefaultFilters(null, null);
```

**Result:**

-   User dropdown shows "All Users"
-   Date remains as is
-   Records reload with current filters

## Using with URL Parameters

You can create deep links that automatically apply filters when the page loads.

### Step 1: Update the Blade Template

Add this code after `setupFilterHandlers()` in the initialization:

```javascript
// Initialize
document.addEventListener("DOMContentLoaded", () => {
    setTodayDate();
    setFilterDate();
    loadUsers();

    // Check for URL parameters
    const urlParams = new URLSearchParams(window.location.search);
    const userId = urlParams.get("user_id");
    const date = urlParams.get("date");

    // Apply URL parameters if present
    if (userId || date) {
        // Wait for users to load first
        setTimeout(() => {
            setDefaultFilters(userId, date);
        }, 500);
    } else {
        loadRecords();
    }

    setupFilterHandlers();
});
```

### Step 2: Use Deep Links

Now you can use URLs like:

```
http://localhost:8000/time-clock?user_id=4&date=2026-01-07
http://localhost:8000/time-clock?user_id=4
http://localhost:8000/time-clock?date=2026-01-10
```

## Advanced Usage: Bookmarklets

Create browser bookmarks that apply specific filters.

### Quick Filter for User 4

```javascript
javascript: void setDefaultFilters(4, null);
```

### Quick Filter for Yesterday

```javascript
javascript: void (function () {
    const yesterday = new Date();
    yesterday.setDate(yesterday.getDate() - 1);
    const dateStr = yesterday.toISOString().split("T")[0];
    setDefaultFilters(null, dateStr);
})();
```

## Testing Script

Use this in the browser console to test different scenarios:

```javascript
// Test 1: Specific user and date
console.log("Test 1: User 4, Date 2026-01-07");
setDefaultFilters(4, "2026-01-07");

// Wait 3 seconds then run next test
setTimeout(() => {
    console.log("Test 2: All users, Date 2026-01-10");
    setDefaultFilters(null, "2026-01-10");
}, 3000);

setTimeout(() => {
    console.log("Test 3: User 4, Today");
    setDefaultFilters(4, null);
}, 6000);
```

## Integration with Backend (Blade)

If you want to set defaults from the server side, you can pass values from the controller:

### Controller

```php
public function index(Request $request): View
{
    return view('time-clock.index', [
        'defaultUserId' => $request->query('user_id'),
        'defaultDate' => $request->query('date'),
    ]);
}
```

### Blade Template

```javascript
// In your DOMContentLoaded event
@if(isset($defaultUserId) || isset($defaultDate))
    setTimeout(() => {
        setDefaultFilters(
            {{ $defaultUserId ?? 'null' }},
            '{{ $defaultDate ?? null }}'
        );
    }, 500);
@else
    loadRecords();
@endif
```

## Console Logging

The function logs its activity to the browser console:

```
Filters applied - User ID: 4, Date: 2026-01-07
Filters applied - User ID: All, Date: Today
```

## Browser Console Quick Reference

Open the page, press **F12** to open console, and use these commands:

```javascript
// View currently selected filters
console.log("User:", selectedUserId, "Date:", selectedDate);

// Apply specific filters
setDefaultFilters(4, "2026-01-07");

// Reset filters
setDefaultFilters(null, null);
```

## Notes

-   The function waits for the user dropdown to be populated before applying user filter
-   If an invalid user ID is provided, the dropdown will remain on "All Users"
-   Date must be in `YYYY-MM-DD` format (ISO 8601)
-   The function automatically triggers record loading
-   Form inputs are synced with filter values for creating new records
