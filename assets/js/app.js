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
    
    fetch(url, options)
        .then(response => response.json())
        .then(data => {
            hideLoader();
            if (callback) callback(data);
        })
        .catch(error => {
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
}

// Hide loader
function hideLoader() {
    const loader = document.getElementById('global-loader');
    if (loader) {
        loader.style.display = 'none';
    }
}

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
                overtimeBadge.textContent = `⏰ Overtime: ${overtimeHours}h ${overtimeMinutes}m`;
            } else {
                overtimeBadge.style.display = 'none';
            }
        }
    }
}

// Poll attendance status (for timer persistence)
function pollAttendanceStatus() {
    ajaxRequest('/officepro/app/api/attendance/status.php', 'GET', null, (response) => {
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

// Notification polling
function fetchNotifications() {
    ajaxRequest('/officepro/app/api/notifications/fetch.php', 'GET', null, (response) => {
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
    
    list.innerHTML = notifications.map(notif => `
        <div class="notification-item ${notif.read_status ? '' : 'unread'}" data-id="${notif.id}">
            <div class="notification-message">${notif.message}</div>
            <div class="notification-time">${formatTimeAgo(notif.created_at)}</div>
        </div>
    `).join('');
    
    // Add click handlers
    list.querySelectorAll('.notification-item').forEach(item => {
        item.addEventListener('click', function() {
            markNotificationAsRead(this.dataset.id);
        });
    });
}

function markNotificationAsRead(notificationId) {
    ajaxRequest('/officepro/app/api/notifications/mark_read.php', 'POST', { id: notificationId }, () => {
        fetchNotifications();
    });
}

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

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    // Toggle sidebar on mobile
    const sidebarToggle = document.getElementById('sidebar-toggle');
    const sidebar = document.querySelector('.sidebar');
    
    if (sidebarToggle && sidebar) {
        sidebarToggle.addEventListener('click', () => {
            sidebar.classList.toggle('active');
        });
    }
    
    // Start notification polling if user is logged in (reduced frequency)
    if (document.getElementById('notification-icon')) {
        fetchNotifications();
        // Only poll every 2 minutes to reduce load
        setInterval(fetchNotifications, 120000);
    }
    
    // Start attendance status polling if on dashboard and timer exists
    if (document.getElementById('timer-display')) {
        // Only poll if timer is active and no modal is open
        const pollInterval = setInterval(() => {
            // Don't poll if modal is open (prevents interference)
            const hasActiveModal = document.querySelector('.modal-overlay.active');
            if (!hasActiveModal) {
                pollAttendanceStatus();
            }
        }, 30000); // Poll every 30 seconds only when no modal is open
    }
});



