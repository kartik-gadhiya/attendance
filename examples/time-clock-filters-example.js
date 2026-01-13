/**
 * EXAMPLE: Using setDefaultFilters Function
 * 
 * This file demonstrates how to use the setDefaultFilters() function
 * to preselect a user and date on the Time Clock page.
 */

// ========================================
// BASIC USAGE
// ========================================

// Example 1: Set both user ID and date
// This will filter records for User ID 4 on January 7, 2026
setDefaultFilters(4, '2026-01-07');

// Example 2: Set only user ID (use current date)
// This will filter records for User ID 4 on today's date
setDefaultFilters(4, null);

// Example 3: Set only date (show all users)
// This will show all users' records on January 10, 2026
setDefaultFilters(null, '2026-01-10');

// Example 4: Reset to defaults
// This will show all users on current selected date
setDefaultFilters(null, null);


// ========================================
// REAL-WORLD SCENARIOS
// ========================================

/**
 * Scenario 1: Testing a specific user's data
 * Use this when debugging issues for a particular user
 */
function testUserRecords() {
    const testUserId = 4; // Michael Johnson
    const testDate = '2026-01-07';
    
    setDefaultFilters(testUserId, testDate);
    console.log(`Testing records for User ${testUserId} on ${testDate}`);
}

/**
 * Scenario 2: View yesterday's attendance
 * Useful for daily review of previous day's records
 */
function viewYesterdayRecords() {
    const yesterday = new Date();
    yesterday.setDate(yesterday.getDate() - 1);
    const dateStr = yesterday.toISOString().split('T')[0];
    
    setDefaultFilters(null, dateStr);
    console.log(`Viewing all records from ${dateStr}`);
}

/**
 * Scenario 3: Weekly user review
 * Check a specific user's activity for the week
 */
function reviewUserWeek(userId, startDate) {
    setDefaultFilters(userId, startDate);
    console.log(`Reviewing User ${userId} starting from ${startDate}`);
}

/**
 * Scenario 4: Preset filter from configuration
 * Use predefined settings from a config object
 */
const config = {
    defaultUserId: 4,
    defaultDate: '2026-01-07'
};

function applyConfigFilters() {
    setDefaultFilters(config.defaultUserId, config.defaultDate);
}


// ========================================
// INTEGRATION EXAMPLES
// ========================================

/**
 * Example 1: Apply filters when page loads (add to DOMContentLoaded)
 */
document.addEventListener("DOMContentLoaded", () => {
    // Your existing initialization...
    
    // Apply static filters after a short delay (to ensure dropdowns are populated)
    setTimeout(() => {
        setDefaultFilters(4, '2026-01-07');
    }, 500);
});

/**
 * Example 2: Read from URL parameters
 */
function applyFiltersFromURL() {
    const urlParams = new URLSearchParams(window.location.search);
    const userId = urlParams.get('user_id');
    const date = urlParams.get('date');
    
    if (userId || date) {
        setDefaultFilters(
            userId ? parseInt(userId) : null,
            date || null
        );
    }
}

// URL examples that would work:
// http://localhost:8000/time-clock?user_id=4&date=2026-01-07
// http://localhost:8000/time-clock?user_id=4
// http://localhost:8000/time-clock?date=2026-01-10


/**
 * Example 3: Create a preset filter button
 */
function createPresetButton() {
    const button = document.createElement('button');
    button.textContent = 'Load User 4 - Jan 7';
    button.className = 'btn btn-secondary';
    button.onclick = () => setDefaultFilters(4, '2026-01-07');
    
    // Add button somewhere on the page
    document.querySelector('.filters-card').appendChild(button);
}


/**
 * Example 4: Cycle through different users
 */
const userIds = [1, 2, 3, 4, 5];
let currentIndex = 0;

function cycleToNextUser() {
    const userId = userIds[currentIndex];
    setDefaultFilters(userId, null);
    
    currentIndex = (currentIndex + 1) % userIds.length;
    console.log(`Switched to User ${userId}`);
}

// Use with a button or interval
// setInterval(cycleToNextUser, 5000); // Switch every 5 seconds


// ========================================
// BROWSER CONSOLE QUICK COMMANDS
// ========================================

/**
 * Open browser console (F12) and paste these commands:
 */

// View Michael Johnson's records on Jan 7, 2026
setDefaultFilters(4, '2026-01-07');

// View all records from Jan 10, 2026
setDefaultFilters(null, '2026-01-10');

// View User 4's records for today
setDefaultFilters(4, null);

// Reset to showing all users
setDefaultFilters(null, null);


// ========================================
// BOOKMARKLET (Save as browser bookmark)
// ========================================

/**
 * Create a bookmark with this URL to quickly apply filters:
 */
// javascript:void(setDefaultFilters(4, '2026-01-07'));


// ========================================
// FUNCTION REFERENCE
// ========================================

/**
 * setDefaultFilters(userId, date)
 * 
 * @param {number|null} userId - User ID to filter by (null = all users)
 * @param {string|null} date - Date in YYYY-MM-DD format (null = keep current)
 * 
 * What it does:
 * 1. Updates the user dropdown to selected userId
 * 2. Updates the date picker to selected date
 * 3. Syncs form inputs with filter values
 * 4. Automatically loads filtered records
 * 5. Logs action to console
 * 
 * Returns: void
 */
