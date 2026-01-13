# Quick Start Guide - Time Clock Web Interface

## Accessing the Page

Open your browser and navigate to:

```
http://localhost:8000/time-clock
```

## Using the Filters

### Filter by User

1. Click the "Filter by User" dropdown at the top
2. Select a user from the list
3. The records table will automatically update to show only that user's entries
4. The form below will automatically use this user for new entries

### Filter by Date

1. Click the "Filter by Date" picker
2. Select a date
3. The records table will automatically update to show only entries from that date
4. The form below will automatically use this date for new entries

### Combined Filtering

-   You can use both filters together
-   Example: Select "John Doe" + "2026-01-13" to see John's records from January 13th

### View All Records

-   Set User to "All Users"
-   Clear the date picker (or select a different date)

## Creating a New Time Entry

1. **Select Filters** (optional but recommended):

    - Choose the user from the "Filter by User" dropdown
    - Choose the date from the "Filter by Date" picker

2. **Fill the Form**:

    - Shop ID: Usually defaults to 1
    - User ID: Auto-filled if you selected a user in filters
    - Date: Auto-filled if you selected a date in filters
    - Time: Enter the time (e.g., 08:00)
    - Event Type: Choose from Day In, Day Out, Break Start, or Break End
    - Comment: (Optional) Add any notes

3. **Submit**:
    - Click "CREATE ENTRY" button
    - Success message will appear
    - New record will appear in the table below (if it matches current filters)

## Editing an Existing Entry

1. **Find the Record**:

    - Use filters to find the record you want to edit
    - Click the "EDIT" button on the record row

2. **Update Fields**:

    - The form will change to "Edit Time Entry"
    - Note: User ID, Date, and Shop ID cannot be changed
    - Update Time, Event Type, or Comment as needed

3. **Save Changes**:

    - Click "UPDATE ENTRY" button
    - Success message will appear
    - Record will update in the table

4. **Cancel Edit**:
    - Click "CANCEL" button to return to create mode

## Tips & Best Practices

### For Admins

-   Use the User filter to quickly view any employee's attendance
-   Use the Date filter to review daily attendance
-   Combine both to audit specific employee-date combinations

### For Creating Records

-   Always set the filters first (User and Date)
-   This ensures new records are created for the correct context
-   The form inputs will auto-sync with your filter selections

### For Data Accuracy

-   Double-check the time before submitting
-   Use the correct event type (Day In, Day Out, Break Start, Break End)
-   Add comments for special circumstances

### Performance

-   The page uses AJAX - no page reloads required
-   Records update instantly when filters change
-   Large datasets are paginated (50 records per page)

## Troubleshooting

### No Records Showing

-   Check if filters are too restrictive
-   Try selecting "All Users" and clearing date filter
-   Verify records exist for the selected user/date

### Cannot Create Record

-   Ensure all required fields are filled (marked with \*)
-   Check that time is in valid format (HH:MM)
-   Look for validation error messages at the top

### Filter Not Working

-   Refresh the page
-   Check browser console for errors (F12)
-   Verify you have internet connection

### Edit Button Not Working

-   Scroll to top to see the edit form
-   Click "CANCEL" if already in edit mode
-   Try refreshing the page

## Event Type Badges

The records table uses color-coded badges:

-   🟢 **Day In** - Green badge
-   🔴 **Day Out** - Pink badge
-   🟠 **Break Start** - Orange badge
-   🔵 **Break End** - Blue badge

## Mobile Usage

The interface is fully responsive and works on mobile devices:

-   Filters stack vertically
-   Form inputs adjust to screen size
-   Table scrolls horizontally if needed
-   Touch-friendly buttons

## Security Notes

-   All requests include CSRF protection
-   Only authenticated users should access this page (add auth middleware if needed)
-   Input validation prevents invalid data
-   XSS protection through Laravel Blade

## Support

If you encounter any issues:

1. Check the browser console for errors (F12 → Console tab)
2. Verify Laravel server is running (`php artisan serve`)
3. Check Laravel logs in `storage/logs/laravel.log`
4. Contact your system administrator
