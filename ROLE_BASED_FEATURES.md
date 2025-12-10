# 👥 Role-Based Features - OfficePro

## User Roles & Access Control

---

## 🔧 System Admin

**Access:** Platform-wide management

### Dashboard Features:
- Platform statistics (total companies, users)
- Recent company registrations
- System health overview

### Available Pages:
- ✅ System Admin Dashboard
- ✅ Manage All Companies (suspend/activate)
- ✅ View All Users (across companies)
- ✅ System Settings
- ✅ Audit Log (platform-wide)

### Sidebar Menu:
- Dashboard
- Companies
- All Users
- System Settings
- Audit Log

**Does NOT see:** Company-specific features, attendance tracking

---

## 🏢 Company Owner

**Access:** Full company management, NO attendance tracking

### Dashboard Features:
- **Company Overview** (not attendance timer!)
  - Total employees count
  - Employees present today
  - Pending leave requests
  - Monthly overtime summary
  - Active employees this month

### Quick Actions:
- ✅ Manage Employees
- ✅ Invite Employees
- ✅ Leave Approvals
- ✅ View Reports
- ✅ View Calendar

### Available Pages:
- ✅ Dashboard (company overview)
- ✅ Calendar
- ✅ Leave Approvals
- ✅ Reports Dashboard
- ✅ **Company Settings** (edit company details)
- ✅ **Employees Management** (view/edit employees)
- ✅ **Departments Management** (create/edit departments)
- ✅ **Invitations** (send/manage invitations)

### Sidebar Menu:
- Dashboard
- Calendar
- Leave Approvals (for team)
- Reports
- **--- Company Management ---**
- Company Settings
- Employees
- Departments
- Invitations

**Does NOT see:**
- ❌ Attendance check-in/out (doesn't need to track own time)
- ❌ My Attendance page
- ❌ My Leaves (request own leave)
- ❌ My Credentials
- ❌ My Tasks (personal)

**Reasoning:** Company owners manage the business, not track their own time.

---

## 👔 Manager

**Access:** Team management + own attendance

### Dashboard Features:
- **Attendance Timer** (can track own time)
  - Check-in/out buttons
  - Live timer with overtime
  - Today's summary (own hours)

### Quick Actions:
- ✅ Request Leave (for self)
- ✅ View My Tasks
- ✅ View Calendar
- ✅ My Credentials

### Available Pages:
- ✅ Dashboard (with timer)
- ✅ **My Attendance** (own history)
- ✅ **My Leaves** (request own leave)
- ✅ **Leave Approvals** (approve team leaves)
- ✅ Calendar
- ✅ My Credentials
- ✅ My Tasks
- ✅ Reports (department reports)
- ✅ Employees (view only)

### Sidebar Menu:
- Dashboard
- Attendance (own)
- My Leaves
- Leave Approvals (for team)
- Calendar
- Reports
- My Credentials
- My Tasks
- Employees (view)

**Can:**
- ✅ Track own attendance
- ✅ Request own leaves
- ✅ Approve team leaves
- ✅ View team reports
- ✅ View employees

**Cannot:**
- ❌ Edit company settings
- ❌ Create departments
- ❌ Invite new employees
- ❌ Change user roles

---

## 👤 Employee

**Access:** Personal features only

### Dashboard Features:
- **Attendance Timer** (track work time)
  - Check-in/out buttons
  - Live timer with overtime
  - Today's summary (own hours)

### Quick Actions:
- ✅ Request Leave
- ✅ View My Tasks
- ✅ View Calendar
- ✅ My Credentials

### Available Pages:
- ✅ Dashboard (with timer)
- ✅ **My Attendance** (own history)
- ✅ **My Leaves** (request and view own)
- ✅ Calendar
- ✅ **My Credentials** (save/share logins)
- ✅ **My Tasks** (assigned tasks)

### Sidebar Menu:
- Dashboard
- Attendance
- My Leaves
- Calendar
- My Credentials
- My Tasks

**Can:**
- ✅ Check-in/out
- ✅ View own attendance
- ✅ Request leaves
- ✅ Save credentials
- ✅ Manage tasks
- ✅ View calendar

**Cannot:**
- ❌ Approve leaves
- ❌ View reports
- ❌ Manage employees
- ❌ Company settings
- ❌ Invite users

---

## 📋 Feature Access Matrix

| Feature | Employee | Manager | Company Owner | System Admin |
|---------|----------|---------|---------------|--------------|
| **Attendance Tracking** | ✅ | ✅ | ❌ | ❌ |
| **Request Leave** | ✅ | ✅ | ❌ | ❌ |
| **Approve Leaves** | ❌ | ✅ | ✅ | ❌ |
| **View Reports** | ❌ | ✅ | ✅ | ❌ |
| **Credentials** | ✅ | ✅ | ❌ | ❌ |
| **Tasks** | ✅ | ✅ | ❌ | ❌ |
| **Calendar** | ✅ | ✅ | ✅ | ❌ |
| **Invite Employees** | ❌ | ❌ | ✅ | ❌ |
| **Manage Employees** | ❌ | View | ✅ Edit | ❌ |
| **Company Settings** | ❌ | ❌ | ✅ | ❌ |
| **Departments** | ❌ | ❌ | ✅ | ❌ |
| **Manage Companies** | ❌ | ❌ | ❌ | ✅ |
| **View All Users** | ❌ | ❌ | ❌ | ✅ |

---

## 🎯 WHY Company Owner Doesn't Track Time:

**Design Rationale:**
- Company owners **manage the business**
- They don't need to clock in/out
- They focus on:
  - Hiring employees
  - Approving leaves
  - Viewing reports
  - Managing settings
  - Business overview

If a company owner also wants to track their time, they can:
1. Create a separate employee account for themselves
2. Or we can add a "Track My Time" toggle in settings (future enhancement)

---

## ✅ Current Implementation:

**Company Owner Dashboard shows:**
- Company overview stats
- Total employees
- Present today
- Pending leaves
- Monthly overtime
- Quick actions for management tasks

**Employee/Manager Dashboard shows:**
- Attendance timer (check-in/out)
- Live timer with overtime
- Today's hours summary
- Personal quick actions

**Perfect separation of concerns!** 🎯


