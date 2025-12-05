# 🚀 Quick Start Guide - OfficePro

## ✅ Installation Complete!

Your system is installed and ready to use!

---

## 📍 Important URLs

### Public Pages (No Login Required)
- **Landing Page:** `http://localhost/officepro/`
- **Company Registration:** `http://localhost/officepro/company_register.php`
- **Login:** `http://localhost/officepro/login.php`
- **Employee Registration:** `http://localhost/officepro/register.php?token=YOUR_TOKEN`

### Debug Pages (For Testing)
- **Debug Login:** `http://localhost/officepro/debug_login.php`
- **Session Test:** `http://localhost/officepro/test_session.php`

### Employee Pages (After Login)
- **Dashboard:** `http://localhost/officepro/app/views/dashboard.php`
- **Attendance History:** `http://localhost/officepro/app/views/attendance.php`
- **My Leaves:** `http://localhost/officepro/app/views/leaves.php`
- **Calendar:** `http://localhost/officepro/app/views/calendar.php`
- **My Credentials:** `http://localhost/officepro/app/views/employee/credentials.php`
- **My Tasks:** `http://localhost/officepro/app/views/employee/tasks.php`

### Manager Pages
- **Leave Approvals:** `http://localhost/officepro/app/views/leave_approvals.php`
- **Reports Dashboard:** `http://localhost/officepro/app/views/reports/dashboard.php`

### Company Owner Pages
- **Company Settings:** `http://localhost/officepro/app/views/company/settings.php`
- **Employees:** `http://localhost/officepro/app/views/company/employees.php`
- **Departments:** `http://localhost/officepro/app/views/company/departments.php`
- **Invitations:** `http://localhost/officepro/app/views/company/invitations.php`

### System Admin Pages
- **System Dashboard:** `http://localhost/officepro/app/views/system_admin/dashboard.php`
- **Companies:** `http://localhost/officepro/app/views/system_admin/companies.php`
- **All Users:** `http://localhost/officepro/app/views/system_admin/users.php`

---

## 🎯 How to Get Started

### Step 1: Login
Go to: `http://localhost/officepro/login.php`

**System Admin Credentials** (from installation):
- Email: The email you entered during installation
- Password: The password you entered during installation

### Step 2: Register a Company (or use System Admin)
If you want to test as a company:
1. Logout
2. Go to `http://localhost/officepro/company_register.php`
3. Fill in company details and owner information
4. Upload company logo and profile image
5. Submit

### Step 3: Invite Employees
As company owner:
1. Go to "Invitations" in sidebar
2. Click "+ Invite Employee"
3. Enter employee email and select role
4. They'll receive an email with registration link

### Step 4: Test Features

#### Attendance:
1. Go to Dashboard
2. Click "Check In"
3. Watch the live timer
4. After 8 hours, you'll see overtime in orange
5. Click "Check Out"

#### Leave Management:
1. Go to "My Leaves"
2. Click "+ Request Leave"
3. Select type, dates, and reason
4. Submit for approval
5. Managers can approve in "Leave Approvals"

#### Credentials:
1. Go to "My Credentials"
2. Click "+ Add Credential"
3. Save website login info
4. Click "Share" to share with team members

#### Tasks:
1. Go to "My Tasks"
2. Click "+ Create Task"
3. Assign to yourself or team members
4. Track progress

#### Reports:
1. Go to "Reports" (Manager/Owner)
2. Select date range
3. Click "View Report" or "Export CSV/PDF"

---

## 🔧 Troubleshooting

### "Too Many Redirects" Error
**Solution:** Clear browser cookies
- Press `Ctrl + Shift + Delete`
- Select "Cookies"
- Clear and refresh

### Can't See Pages
**Solution:** Make sure you're logged in
- Go to `http://localhost/officepro/login.php`
- Use correct credentials

### Database Errors
**Solution:** Check database connection
- Open: `app/config/database.php`
- Verify credentials match your WAMP setup

### Session Issues
**Solution:** Test session
- Visit: `http://localhost/officepro/test_session.php`
- Check if session is active

---

## ✅ What's Working

- ✅ Multi-tenant company registration
- ✅ Employee invitation system
- ✅ Login/Logout
- ✅ Dashboard with check-in/out
- ✅ Live timer with overtime (8+ hours)
- ✅ Attendance history
- ✅ Leave requests and approvals
- ✅ Leave balance tracking
- ✅ Calendar with events
- ✅ Holiday management
- ✅ Credentials management with sharing
- ✅ Task management
- ✅ Reports with CSV/PDF export
- ✅ Notifications system
- ✅ Company settings
- ✅ Employee management
- ✅ Department management
- ✅ System admin panel

---

## 📞 Need Help?

1. Check `README.md` for detailed documentation
2. Check `PROJECT_STATUS.md` for implementation details
3. Use debug pages to test functionality
4. Check browser console for JavaScript errors
5. Check `logs/error.log` for PHP errors

---

## 🎉 Enjoy Your OfficePro System!

All features are implemented and ready to use. Start by logging in and exploring the dashboard.

**Login URL:** `http://localhost/officepro/login.php`

