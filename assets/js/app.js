/**
 * Core Application JavaScript
 * AJAX helpers, loaders, messages, timer logic
 */

// AJAX request helper
function ajaxRequest(url, method, data, callback, errorCallback) {
    showLoader();
    
    const options = {
        method: method || 'GET',
        headers: {
            'Content-Type': 'application/json'
        }
    };
    
    if (data && (method === 'POST' || method === 'PUT')) {
        if (data instanceof FormData) {
            delete options.headers['Content-Type'];
            options.body = data;
        } else {
            options.body = JSON.stringify(data);
        }
    }
    
    // Set a timeout for the fetch request
    const timeoutId = setTimeout(() => {
        hideLoader();
        console.error('Request timeout after 30 seconds:', url);
        if (errorCallback) {
            errorCallback(new Error('Request timeout'));
        } else {
            showMessage('error', 'Request timed out. Please try again.');
        }
    }, 30000);
    
    fetch(url, options)
        .then(response => {
            clearTimeout(timeoutId);
            // Check if response is ok
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            // Check content type
            const contentType = response.headers.get('content-type');
            if (!contentType || !contentType.includes('application/json')) {
                return response.text().then(text => {
                    throw new Error('Invalid JSON response: ' + text.substring(0, 100));
                });
            }
            return response.json();
        })
        .then(data => {
            hideLoader();
            if (callback) callback(data);
        })
        .catch(error => {
            clearTimeout(timeoutId);
            hideLoader();
            console.error('AJAX Error:', error);
            if (errorCallback) {
                errorCallback(error);
            } else {
                showMessage('error', 'An error occurred. Please try again.');
            }
        });
}

// Show loader
function showLoader() {
    let loader = document.getElementById('global-loader');
    if (!loader) {
        loader = document.createElement('div');
        loader.id = 'global-loader';
        loader.className = 'loading-overlay';
        loader.innerHTML = '<div class="loader"></div>';
        document.body.appendChild(loader);
    }
    loader.style.display = 'flex';
    
    // Safety: Auto-hide after 30 seconds
    clearTimeout(window.loaderTimeout);
    window.loaderTimeout = setTimeout(() => {
        hideLoader();
        console.warn('Loader auto-hidden after timeout');
    }, 30000);
}

// Hide loader
function hideLoader() {
    const loader = document.getElementById('global-loader');
    if (loader) {
        loader.style.display = 'none';
    }
    // Clear any pending timeout
    if (window.loaderTimeout) {
        clearTimeout(window.loaderTimeout);
        window.loaderTimeout = null;
    }
}

// Ensure loader is hidden on page unload
window.addEventListener('beforeunload', function() {
    hideLoader();
});

// Show message (toast notification)
function showMessage(type, text, duration = 3000) {
    // Remove existing messages
    const existing = document.querySelectorAll('.toast-message');
    existing.forEach(el => el.remove());
    
    const message = document.createElement('div');
    message.className = `toast-message toast-${type}`;
    message.textContent = text;
    
    // Add styles
    message.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        padding: 15px 20px;
        border-radius: 5px;
        color: white;
        font-weight: 500;
        z-index: 10000;
        animation: slideInRight 0.3s ease;
        box-shadow: 0 4px 10px rgba(0,0,0,0.2);
        max-width: 400px;
    `;
    
    // Set background color based on type
    const colors = {
        success: '#28a745',
        error: '#dc3545',
        warning: '#ffc107',
        info: '#4da6ff'
    };
    message.style.background = colors[type] || colors.info;
    
    document.body.appendChild(message);
    
    // Auto remove
    setTimeout(() => {
        message.style.animation = 'slideOutRight 0.3s ease';
        setTimeout(() => message.remove(), 300);
    }, duration);
}

// Add animations to document
if (!document.getElementById('toast-animations')) {
    const style = document.createElement('style');
    style.id = 'toast-animations';
    style.textContent = `
        @keyframes slideInRight {
            from {
                transform: translateX(100%);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }
        @keyframes slideOutRight {
            from {
                transform: translateX(0);
                opacity: 1;
            }
            to {
                transform: translateX(100%);
                opacity: 0;
            }
        }
    `;
    document.head.appendChild(style);
}

// Timer management for attendance
let timerInterval = null;
let timerStartTime = null;
let timerElapsedSeconds = 0;

function startTimer(checkInTime) {
    // Stop any existing timer
    stopTimer();
    
    // Parse check-in time from server (format: YYYY-MM-DD HH:MM:SS)
    timerStartTime = new Date(checkInTime.replace(' ', 'T')).getTime();
    
    console.log('Timer started. Check-in time:', checkInTime);
    console.log('Timer start timestamp:', timerStartTime);
    console.log('Current time:', Date.now());
    
    // Update immediately
    updateTimerDisplay();
    
    // Update every second
    timerInterval = setInterval(updateTimerDisplay, 1000);
}

function stopTimer() {
    if (timerInterval) {
        clearInterval(timerInterval);
        timerInterval = null;
    }
}

function updateTimerDisplay() {
    const now = Date.now();
    timerElapsedSeconds = Math.floor((now - timerStartTime) / 1000);
    
    // Ensure positive time
    if (timerElapsedSeconds < 0) {
        timerElapsedSeconds = 0;
    }
    
    const hours = Math.floor(timerElapsedSeconds / 3600);
    const minutes = Math.floor((timerElapsedSeconds % 3600) / 60);
    const seconds = timerElapsedSeconds % 60;
    
    const timerDisplay = document.getElementById('timer-display');
    if (timerDisplay) {
        const timeString = `${String(hours).padStart(2, '0')}:${String(minutes).padStart(2, '0')}:${String(seconds).padStart(2, '0')}`;
        timerDisplay.textContent = timeString;
        
        // Add overtime badge if >= 8 hours
        const isOvertime = hours >= 8;
        timerDisplay.className = isOvertime ? 'timer-overtime' : 'timer-regular';
        timerDisplay.style.fontSize = '48px';
        timerDisplay.style.fontWeight = 'bold';
        timerDisplay.style.color = isOvertime ? 'var(--overtime-orange)' : 'var(--primary-blue)';
        
        // Update overtime badge
        const overtimeBadge = document.getElementById('overtime-badge');
        if (overtimeBadge) {
            if (isOvertime) {
                overtimeBadge.style.display = 'inline-block';
                const overtimeHours = hours - 8;
                const overtimeMinutes = minutes;
                overtimeBadge.innerHTML = `<i class="fas fa-clock"></i> Overtime: ${overtimeHours}h ${overtimeMinutes}m`;
            } else {
                overtimeBadge.style.display = 'none';
            }
        }
    }
}

// Background fetch without loader (for polling)
function backgroundFetch(url, callback, errorCallback) {
    fetch(url, {
        method: 'GET',
        headers: {
            'Content-Type': 'application/json'
        }
    })
    .then(response => {
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        return response.json();
    })
    .then(data => {
        if (callback) callback(data);
    })
    .catch(error => {
        console.error('Background fetch error:', error);
        if (errorCallback) {
            errorCallback(error);
        }
    });
}

// Poll attendance status (for timer persistence) - no loader
function pollAttendanceStatus() {
    backgroundFetch('/officepro/app/api/attendance/status.php', (response) => {
        if (response.success && response.data.status === 'in') {
            if (!timerInterval) {
                console.log('Poll: Starting timer with:', response.data.check_in_time);
                startTimer(response.data.check_in_time);
            }
        } else {
            stopTimer();
        }
    }, (error) => {
        console.error('Poll error:', error);
    });
}

// Notification polling - no loader
function fetchNotifications() {
    backgroundFetch('/officepro/app/api/notifications/fetch.php', (response) => {
        if (response.success) {
            updateNotificationBadge(response.data.unread_count);
            displayNotificationList(response.data.notifications);
        }
    });
}

function updateNotificationBadge(count) {
    const badge = document.getElementById('notification-badge');
    if (badge) {
        if (count > 0) {
            badge.textContent = count > 99 ? '99+' : count;
            badge.style.display = 'flex';
        } else {
            badge.style.display = 'none';
        }
    }
}

function displayNotificationList(notifications) {
    const list = document.getElementById('notification-list');
    if (!list) return;
    
    if (notifications.length === 0) {
        list.innerHTML = '<div class="notification-empty">No notifications</div>';
        return;
    }
    
    list.innerHTML = notifications.map(notif => {
        // Escape HTML to prevent XSS
        const message = notif.message ? notif.message.replace(/</g, '&lt;').replace(/>/g, '&gt;') : '';
        const timeAgo = formatTimeAgo(notif.created_at);
        const isUnread = notif.read_status == 0 || notif.read_status === false;
        const link = notif.link || '';
        return `
        <div class="notification-item ${isUnread ? 'unread' : ''}" data-id="${notif.id}" data-link="${link}">
            <div class="notification-message">${message}</div>
            <div class="notification-time">${timeAgo}</div>
        </div>
    `;
    }).join('');
    
    // Add click handlers
    list.querySelectorAll('.notification-item').forEach(item => {
        item.addEventListener('click', function() {
            const notificationId = this.dataset.id;
            const link = this.dataset.link;
            
            // Mark as read
            markNotificationAsRead(notificationId);
            
            // Navigate to link if provided
            if (link) {
                setTimeout(() => {
                    window.location.href = link;
                }, 200);
            }
        });
    });
}

function markNotificationAsRead(notificationId) {
    ajaxRequest('/officepro/app/api/notifications/mark_read.php', 'POST', { id: notificationId }, () => {
        fetchNotifications();
    });
}

function toggleNotificationDropdown() {
    const dropdown = document.getElementById('notification-dropdown');
    const userMenu = document.getElementById('user-menu');
    
    if (!dropdown) return;
    
    // Close user menu if open
    if (userMenu) {
        userMenu.style.display = 'none';
    }
    
    // Toggle notification dropdown
    if (dropdown.style.display === 'none' || !dropdown.style.display) {
        dropdown.style.display = 'block';
        // Fetch latest notifications when opening
        fetchNotifications();
    } else {
        dropdown.style.display = 'none';
    }
}

// Close dropdowns when clicking outside
document.addEventListener('DOMContentLoaded', function() {
    document.addEventListener('click', function(event) {
        const notificationWrapper = document.querySelector('.notification-wrapper');
        const notificationDropdown = document.getElementById('notification-dropdown');
        const userMenu = document.getElementById('user-menu');
        const userProfile = document.querySelector('.user-profile');
        const notificationIcon = document.getElementById('notification-icon');
        
        // Close notification dropdown if clicking outside
        if (notificationDropdown && notificationIcon) {
            if (!notificationWrapper.contains(event.target)) {
                notificationDropdown.style.display = 'none';
            }
        }
        
        // Close user menu if clicking outside
        if (userMenu && userProfile) {
            if (!userProfile.contains(event.target) && !userMenu.contains(event.target)) {
                userMenu.style.display = 'none';
            }
        }
    });
});

function formatTimeAgo(dateString) {
    const date = new Date(dateString);
    const now = new Date();
    const seconds = Math.floor((now - date) / 1000);
    
    if (seconds < 60) return 'Just now';
    if (seconds < 3600) return Math.floor(seconds / 60) + ' minutes ago';
    if (seconds < 86400) return Math.floor(seconds / 3600) + ' hours ago';
    return Math.floor(seconds / 86400) + ' days ago';
}

// Form validation helper
function validateForm(formId) {
    const form = document.getElementById(formId);
    if (!form) return false;
    
    let isValid = true;
    const inputs = form.querySelectorAll('input[required], select[required], textarea[required]');
    
    inputs.forEach(input => {
        if (!input.value.trim()) {
            input.classList.add('error');
            isValid = false;
        } else {
            input.classList.remove('error');
        }
    });
    
    return isValid;
}

// Initialize blob button structure for .btn-primary buttons
function initBlobButtons() {
    const primaryButtons = document.querySelectorAll('.btn-primary');
    
    primaryButtons.forEach(button => {
        // Skip if already initialized
        if (button.querySelector('.blob-btn__inner')) {
            return;
        }
        
        // Wrap the button text in a span to keep it above blobs
        const text = button.textContent.trim();
        button.innerHTML = '';
        
        // Create text span
        const textSpan = document.createElement('span');
        textSpan.textContent = text;
        textSpan.style.position = 'relative';
        textSpan.style.zIndex = '10';
        button.appendChild(textSpan);
        
        // Create blob structure
        const inner = document.createElement('span');
        inner.className = 'blob-btn__inner';
        
        const blobs = document.createElement('span');
        blobs.className = 'blob-btn__blobs';
        
        // Create 4 blob elements
        for (let i = 0; i < 4; i++) {
            const blob = document.createElement('span');
            blob.className = 'blob-btn__blob';
            blobs.appendChild(blob);
        }
        
        inner.appendChild(blobs);
        button.appendChild(inner);
    });
}

// Initialize blob button structure for .btn-secondary buttons
function initBlobButtonsSecondary() {
    const secondaryButtons = document.querySelectorAll('.btn-secondary');
    
    secondaryButtons.forEach(button => {
        // Skip if already initialized
        if (button.querySelector('.blob-btn__inner')) {
            return;
        }
        
        // Wrap the button text in a span to keep it above blobs
        const text = button.textContent.trim();
        button.innerHTML = '';
        
        // Create text span
        const textSpan = document.createElement('span');
        textSpan.textContent = text;
        textSpan.style.position = 'relative';
        textSpan.style.zIndex = '10';
        button.appendChild(textSpan);
        
        // Create blob structure
        const inner = document.createElement('span');
        inner.className = 'blob-btn__inner';
        
        const blobs = document.createElement('span');
        blobs.className = 'blob-btn__blobs';
        
        // Create 4 blob elements
        for (let i = 0; i < 4; i++) {
            const blob = document.createElement('span');
            blob.className = 'blob-btn__blob';
            blobs.appendChild(blob);
        }
        
        inner.appendChild(blobs);
        button.appendChild(inner);
    });
}

// Initialize blob button structure for .btn-successbuttons
function initBlobButtonsSuccess() {
    const successButtons = document.querySelectorAll('.btn-success');
    
    successButtons.forEach(button => {
        // Skip if already initialized
        if (button.querySelector('.blob-btn__inner')) {
            return;
        }
        
        // Wrap the button text in a span to keep it above blobs
        const text = button.textContent.trim();
        button.innerHTML = '';
        
        // Create text span
        const textSpan = document.createElement('span');
        textSpan.textContent = text;
        textSpan.style.position = 'relative';
        textSpan.style.zIndex = '10';
        button.appendChild(textSpan);
        
        // Create blob structure
        const inner = document.createElement('span');
        inner.className = 'blob-btn__inner';
        
        const blobs = document.createElement('span');
        blobs.className = 'blob-btn__blobs';
        
        // Create 4 blob elements
        for (let i = 0; i < 4; i++) {
            const blob = document.createElement('span');
            blob.className = 'blob-btn__blob';
            blobs.appendChild(blob);
        }
        
        inner.appendChild(blobs);
        button.appendChild(inner);
    });
}

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    // Initialize blob buttons
    initBlobButtons();
    initBlobButtonsSecondary();
    initBlobButtonsSuccess();
    // Toggle sidebar on mobile 
    const sidebarToggle = document.getElementById('sidebar-toggle');
    const sidebar = document.querySelector('.sidebar');
    
    if (sidebarToggle && sidebar) {
        sidebarToggle.addEventListener('click', () => {
            sidebar.classList.toggle('active');
        });
    }
    
    // Start notification polling if user is logged in (reduced frequency)
    const notificationIcon = document.getElementById('notification-icon');
    if (notificationIcon) {
        // Initial fetch
        fetchNotifications();
        // Only poll every 2 minutes to reduce load
        setInterval(() => {
            fetchNotifications();
        }, 120000);
    }
    
    // Start attendance status polling if on dashboard and timer exists
    const timerDisplay = document.getElementById('timer-display');
    if (timerDisplay) {
        // Initial poll
        pollAttendanceStatus();
        // Only poll if timer is active and no modal is open
        setInterval(() => {
            // Don't poll if modal is open (prevents interference)
            const hasActiveModal = document.querySelector('.modal-overlay.active');
            if (!hasActiveModal && timerDisplay) {
                pollAttendanceStatus();
            }
        }, 30000); // Poll every 30 seconds only when no modal is open
    }
});



