# OfficePro - Multi-Tenant Employee Attendance & Leave Management System

A comprehensive, web-based employee attendance and leave management system built with PHP, MySQL, and AJAX. Supports multiple companies with complete data isolation, overtime tracking, leave management, task management, and more.

## Features

### 🏢 Multi-Tenancy
- Complete company isolation - each company's data is fully separated
- Self-service company registration
- Secure employee invitation system with 7-day expiry tokens
- System admin can manage all companies

### ⏰ Attendance Management
- Real-time check-in/check-out with live timer
- Automatic overtime calculation (after 8 hours)
- Multiple check-ins per day support (e.g., lunch breaks)
- AJAX polling for timer persistence across page refreshes
- Attendance history with detailed reports

### 📅 Leave Management
- Multiple leave types: Paid Leave, Sick Leave, Casual Leave, Work From Home
- Leave request submission with attachments
- Manager/Admin approval workflow with comments
- Automatic leave balance tracking and deduction
- Email notifications on status changes

### 👥 User Management
- Role-based access control: System Admin, Company Owner, Manager, Employee
- Department management
- Employee invitation via email with secure tokens
- Profile images required for all users

### 🔑 Credentials Management (NEW)
- Save website credentials (username/password) for easy access
- Share credentials with specific team members
- Search and filter functionality
- Admin can view all company credentials
- Security warning for plain text storage

### ✅ Task Management (NEW)
- Create and assign tasks to team members
- Task priorities: Low, Medium, High
- Task statuses: Todo, In Progress, Done
- Due date tracking with email reminders
- Search and filter by status, priority, assignee

### 📊 Reports & Analytics
- Daily and monthly attendance reports
- Overtime tracking and reporting
- CSV and PDF export with company branding
- Dashboard with key metrics (present, on leave, overtime, late arrivals)

### 🔔 Notifications
- In-app notification system with bell icon
- Email notifications for:
  - Company registration
  - Employee invitations
  - Leave status changes
  - Task assignments
  - Overtime alerts
  - Check-in reminders

### 🎨 UI/UX
- Classic white + light-blue color scheme
- Responsive design (desktop-first, mobile-friendly)
- Custom animated modals for all CRUD operations
- Smooth CSS animations (slide-down 0.3s, fade-out 0.2s)
- Real-time AJAX operations (no full page reloads)

## Technical Stack

- **Backend:** PHP 7.4+ (OOP, no framework)
- **Database:** MySQL 5.7+
- **Frontend:** HTML5, CSS3, JavaScript (Vanilla)
- **Libraries:**
  - PHPMailer for email notifications
  - DomPDF for PDF report generation
- **Architecture:** MVC-like structure with multi-tenant data isolation

## Requirements

- PHP 7.4 or higher
- MySQL 5.7 or higher
- Apache/Nginx web server
- Composer (for dependency management)
- SMTP server for email notifications (optional but recommended)

## Installation

### 1. Clone or Download the Project

```bash
cd /path/to/your/webserver/root
# Extract the project files here
```

### 2. Install Dependencies

```bash
composer install
```

This will install:
- phpmailer/phpmailer
- dompdf/dompdf

### 3. Configure Database

Edit `app/config/database.php` with your MySQL credentials:

```php
return [
    'host' => 'localhost',
    'dbname' => 'officepro_attendance',
    'username' => 'root',
    'password' => '',
    // ...
];
```

### 4. Configure Email (Optional)

Edit `app/config/email.php` with your SMTP settings:

```php
return [
    'smtp_host' => 'smtp.gmail.com',
    'smtp_port' => 587,
    'smtp_username' => 'your-email@gmail.com',
    'smtp_password' => 'your-app-password',
    // ...
];
```

For Gmail, use an [App Password](https://support.google.com/accounts/answer/185833).

### 5. Set Permissions

```bash
chmod -R 755 uploads/
chmod -R 755 logs/
chmod -R 755 backups/
```

### 6. Run Installation

Navigate to: `http://yourdomain.com/install.php`

The installer will:
- Create the database if it doesn't exist
- Run all database migrations
- Create the system admin account
- Set up necessary directories
- Create a lock file to prevent reinstallation

**Installation Credentials:**
- Enter your preferred email and password for the system admin account
- These credentials will be used to manage the entire platform

### 7. Access the System

After installation, you'll be redirected to login. Use:
- System Admin: The email/password you set during installation
- Or register a new company at: `http://yourdomain.com/company_register.php`

## Project Structure

```
officepro/
├── app/
│   ├── api/                  # AJAX API endpoints
│   │   ├── auth/            # Authentication endpoints
│   │   ├── attendance/      # Attendance endpoints
│   │   ├── leaves/          # Leave management endpoints
│   │   ├── employee/        # Credentials & tasks endpoints
│   │   └── company/         # Company management endpoints
│   ├── config/              # Configuration files
│   │   ├── app.php          # App constants
│   │   ├── database.php     # Database config
│   │   └── email.php        # Email config
│   ├── helpers/             # Helper classes
│   │   ├── Database.php     # PDO wrapper
│   │   ├── Auth.php         # Authentication
│   │   ├── Tenant.php       # Multi-tenancy manager
│   │   ├── Invitation.php   # Invitation system
│   │   ├── Validator.php    # Input validation
│   │   ├── Email.php        # Email sending
│   │   └── PDF.php          # PDF generation
│   └── views/               # HTML views
│       ├── includes/        # Shared components (header, sidebar, footer)
│       ├── dashboard.php    # Main dashboard
│       ├── employee/        # Employee features (credentials, tasks)
│       ├── company/         # Company management
│       └── system_admin/    # System admin views
├── assets/
│   ├── css/                 # Stylesheets
│   ├── js/                  # JavaScript files
│   └── images/              # Images
├── database/
│   └── schema.sql           # Database schema
├── uploads/                 # User uploads (profiles, documents, logos)
├── logs/                    # Error logs
├── backups/                 # Database backups
├── index.php                # Landing page
├── login.php                # Login page
├── company_register.php     # Company registration
├── register.php             # Employee registration (with token)
├── install.php              # Installation script
├── composer.json            # Composer dependencies
└── README.md                # This file
```

## Usage Guide

### For Company Owners

1. **Register Your Company**
   - Visit the landing page and click "Register Your Company"
   - Fill in company details and owner information
   - Upload company logo and owner profile image
   - Submit to create your company account

2. **Invite Employees**
   - Go to "Invitations" in the sidebar
   - Click "Invite Employee"
   - Enter employee email and select role (Manager/Employee)
   - Employee receives email with registration link (valid for 7 days)

3. **Manage Company**
   - Set up departments
   - Configure work hours and leave policies
   - Manage holidays
   - View reports and analytics

### For Employees

1. **Register with Invitation**
   - Click the link in your invitation email
   - Complete registration with profile image
   - Login and access your dashboard

2. **Daily Attendance**
   - Click "Check In" when you arrive
   - Live timer shows elapsed time
   - Overtime is highlighted in orange after 8 hours
   - Click "Check Out" when leaving

3. **Request Leave**
   - Go to "My Leaves"
   - Click "Request Leave"
   - Select type, dates, and reason
   - Optional: attach supporting documents
   - Submit for manager approval

4. **Manage Credentials**
   - Go to "My Credentials"
   - Save website login information
   - Share with specific team members
   - Search and filter your credentials

5. **Task Management**
   - Go to "My Tasks"
   - View tasks assigned to you
   - Create and assign tasks to others
   - Mark tasks as complete

## Key Features Implemented

✅ Multi-tenant architecture with complete data isolation
✅ Company registration and employee invitation system
✅ Authentication with bcrypt password hashing
✅ Role-based access control
✅ Dashboard with attendance check-in/out
✅ Live timer with overtime calculation
✅ Attendance API endpoints
✅ Responsive UI with classic white + light-blue theme
✅ Custom animated modals
✅ AJAX-based interactions
✅ Database schema with all required tables
✅ Helper classes for common operations
✅ Email notification system (PHPMailer)
✅ PDF report generation (DomPDF)
✅ Credentials management with sharing
✅ Task management with assignment
✅ File upload with validation

## Features In Development

The following features are planned and ready to be implemented based on the schema and architecture:

- 📝 Leave request and approval pages
- 📆 Calendar view with color-coded events
- 📊 Reports and analytics dashboard
- 📈 CSV/PDF export functionality
- 🔔 In-app notification dropdown
- ⚙️ Company settings page
- 👥 Employee management interface
- 🏢 Department management
- 🔧 System admin panel
- 📧 Additional email notification types
- ⏱️ Auto-checkout cron job
- 🔍 Advanced search and filter for all modules
- 📱 Enhanced mobile responsiveness

## Security Features

- ✅ Bcrypt password hashing (cost: 12)
- ✅ Session-based authentication
- ✅ Company data isolation (tenant-scoped queries)
- ✅ Role-based API endpoint access
- ✅ Input validation (client and server-side)
- ✅ File upload validation (type, size, dimensions)
- ✅ SQL injection prevention (prepared statements)
- ✅ XSS protection (htmlspecialchars)
- ✅ Secure invitation tokens (64-character random)
- ✅ Token expiry (7 days)
- ✅ .htaccess protection for sensitive files

## Configuration

### App Settings

Edit `app/config/app.php`:

```php
return [
    'standard_work_hours' => 8,           # Hours before overtime
    'auto_checkout_time' => '23:59:00',   # Auto-checkout time
    'late_arrival_threshold' => '09:15:00', # Late arrival threshold
    'default_paid_leave' => 20,           # Annual paid leaves
    'default_sick_leave' => 10,           # Annual sick leaves
    'default_casual_leave' => 5,          # Annual casual leaves
    'default_wfh_days' => 12,             # Annual WFH days
    'invitation_expiry_days' => 7,        # Invitation validity
    'items_per_page' => 15,               # Pagination
    'max_upload_size' => 2097152,         # 2MB
];
```

## Troubleshooting

### Installation Issues

**Problem:** Database connection error
**Solution:** Check your database credentials in `app/config/database.php`

**Problem:** Permission denied errors
**Solution:** Ensure web server has write permissions to `uploads/`, `logs/`, and `backups/`

### Email Issues

**Problem:** Emails not sending
**Solution:** 
- Check SMTP credentials in `app/config/email.php`
- For Gmail, use an App Password
- Check your server's firewall allows SMTP connections

### Upload Issues

**Problem:** File upload fails
**Solution:**
- Check `php.ini` settings: `upload_max_filesize` and `post_max_size`
- Ensure `uploads/` directory exists and is writable
- Check `.htaccess` in uploads folder

## Cron Jobs (Optional but Recommended)

Add these to your crontab for automated tasks:

```bash
# Expire old invitations (daily at midnight)
0 0 * * * php /path/to/officepro/scripts/expire_invitations.php

# Auto-checkout employees (daily at midnight)
0 0 * * * php /path/to/officepro/scripts/auto_checkout.php

# Send task reminders (daily at 9 AM)
0 9 * * * php /path/to/officepro/scripts/task_reminders.php

# Database backup (daily at 2 AM)
0 2 * * * php /path/to/officepro/scripts/backup_db.php
```

## Development Roadmap

### Phase 1: Core Features (COMPLETED)
- ✅ Multi-tenant architecture
- ✅ Authentication system
- ✅ Company and employee registration
- ✅ Dashboard with attendance
- ✅ Basic UI/UX with modals

### Phase 2: Advanced Features (IN PROGRESS)
- ⏳ Leave management pages
- ⏳ Calendar integration
- ⏳ Reports and analytics
- ⏳ Admin panels

### Phase 3: Enhancements
- Password reset functionality
- Two-factor authentication
- Advanced reporting with charts
- Mobile app (PWA)
- API for third-party integrations
- Biometric attendance (future)

## Support

For issues, questions, or contributions:
- Create an issue in the repository
- Email: support@officepro.local

## License

This project is for educational and commercial use. Customize as needed for your organization.

## Credits

Built with:
- PHP
- MySQL
- PHPMailer
- DomPDF
- Vanilla JavaScript

---

**OfficePro** - Streamline your workforce management




