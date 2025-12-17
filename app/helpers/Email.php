<?php
/**
 * Email Helper - PHPMailer Wrapper
 */

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/../../vendor/autoload.php';

class Email {
    private $mail;
    private $config;
    
    public function __construct() {
        $this->config = require __DIR__ . '/../config/email.php';
        $this->mail = new PHPMailer(true);
        
        // SMTP configuration
        $this->mail->isSMTP();
        $this->mail->Host = $this->config['smtp_host'];
        $this->mail->SMTPAuth = true;
        $this->mail->Username = $this->config['smtp_username'];
        $this->mail->Password = $this->config['smtp_password'];
        
        // Handle encryption - port 465 uses SSL, port 587 uses TLS
        $encryption = strtolower($this->config['smtp_encryption']);
        if ($encryption === 'ssl') {
            $this->mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        } elseif ($encryption === 'tls') {
            $this->mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        } else {
            $this->mail->SMTPSecure = '';
        }
        
        $this->mail->Port = $this->config['smtp_port'];
        
        // Add timeout settings to prevent hanging
        $this->mail->Timeout = 10; // 10 seconds timeout
        $this->mail->SMTPKeepAlive = false; // Don't keep connection alive
        
        // Enable verbose debug output (optional - disable in production)
        // $this->mail->SMTPDebug = 2;
        
        // Default from address
        $this->mail->setFrom($this->config['from_email'], $this->config['from_name']);
        $this->mail->addReplyTo($this->config['reply_to']);
        
        $this->mail->isHTML(true);
    }
    
    /**
     * Send email
     */
    public function send($to, $subject, $body) {
        try {
            // Set timeout for the send operation
            set_time_limit(30); // 30 seconds max for email sending
            
            $this->mail->clearAddresses();
            $this->mail->addAddress($to);
            $this->mail->Subject = $subject;
            $this->mail->Body = $body;
            
            $result = $this->mail->send();
            
            if (!$result) {
                error_log("Email Send Failed: " . $this->mail->ErrorInfo);
            }
            
            return $result;
        } catch (Exception $e) {
            error_log("Email Exception: " . $e->getMessage());
            error_log("Email Error Info: " . $this->mail->ErrorInfo);
            return false;
        }
    }
    
    /**
     * Send company welcome email
     */
    public static function sendCompanyWelcome($ownerEmail, $ownerName, $companyName) {
        $email = new self();
        $appConfig = require __DIR__ . '/../config/app.php';
        
        $subject = "Welcome to {$appConfig['app_name']}!";
        $body = "
            <h2>Welcome to {$appConfig['app_name']}, {$ownerName}!</h2>
            <p>Your company <strong>{$companyName}</strong> has been successfully registered.</p>
            <p>You can now:</p>
            <ul>
                <li>Invite employees to join your company</li>
                <li>Configure company settings</li>
                <li>Manage attendance and leave requests</li>
                <li>Generate reports</li>
            </ul>
            <p><a href='{$appConfig['app_url']}/login.php' style='background:#4da6ff;color:white;padding:10px 20px;text-decoration:none;border-radius:5px;display:inline-block;'>Login to Dashboard</a></p>
            <p>Best regards,<br>{$appConfig['app_name']} Team</p>
        ";
        
        return $email->send($ownerEmail, $subject, $body);
    }
    
    /**
     * Send employee invitation
     */
    public static function sendEmployeeInvitation($email, $token, $companyName, $inviterName, $personalMessage = '') {
        $emailer = new self();
        $appConfig = require __DIR__ . '/../config/app.php';
        
        $registrationLink = $appConfig['app_url'] . '/register.php?token=' . $token;
        
        $subject = "Invitation to join {$companyName}";
        
        $personalMessageSection = '';
        if (!empty($personalMessage)) {
            $personalMessageSection = "
                <div style='background:#f0f7ff;border-left:4px solid #667eea;padding:15px;margin:20px 0;border-radius:5px;'>
                    <p style='margin:0;font-style:italic;color:#555;'><strong>Personal Message:</strong><br>{$personalMessage}</p>
                </div>
            ";
        }
        
        $body = "
            <div style='font-family:Arial,sans-serif;max-width:600px;margin:0 auto;padding:20px;'>
                <h2 style='color:#667eea;'>You've been invited to join {$companyName}!</h2>
                <p>Hello,</p>
                <p><strong>{$inviterName}</strong> has invited you to join their team on <strong>{$appConfig['app_name']}</strong>.</p>
                {$personalMessageSection}
                <p>Click the button below to create your account and complete your registration:</p>
                <p style='text-align:center;margin:30px 0;'>
                    <a href='{$registrationLink}' style='background:linear-gradient(135deg, #667eea 0%, #764ba2 100%);color:white;padding:15px 30px;text-decoration:none;border-radius:8px;display:inline-block;font-weight:600;box-shadow:0 4px 15px rgba(102,126,234,0.4);'>Accept Invitation</a>
                </p>
                <p style='color:#666;font-size:14px;'>Or copy and paste this link into your browser:</p>
                <p style='color:#667eea;word-break:break-all;font-size:12px;'>{$registrationLink}</p>
                <hr style='border:none;border-top:1px solid #eee;margin:30px 0;'>
                <p style='color:#999;font-size:12px;'>This invitation will expire in 7 days. If you did not expect this invitation, you can safely ignore this email.</p>
                <p style='margin-top:30px;'>Best regards,<br><strong>{$appConfig['app_name']} Team</strong></p>
            </div>
        ";
        
        return $emailer->send($email, $subject, $body);
    }
    
    /**
     * Send leave status update
     */
    public static function sendLeaveStatusUpdate($employeeEmail, $employeeName, $leaveType, $status, $comments = '') {
        $emailer = new self();
        $appConfig = require __DIR__ . '/../config/app.php';
        
        $statusText = ucfirst($status);
        $subject = "Leave Request {$statusText}";
        
        $body = "
            <h2>Leave Request {$statusText}</h2>
            <p>Hi {$employeeName},</p>
            <p>Your {$leaveType} request has been <strong>{$status}</strong>.</p>
        ";
        
        if ($comments) {
            $body .= "<p><strong>Manager's Comments:</strong> {$comments}</p>";
        }
        
        $body .= "
            <p><a href='{$appConfig['app_url']}/app/views/leaves.php' style='background:#4da6ff;color:white;padding:10px 20px;text-decoration:none;border-radius:5px;display:inline-block;'>View Details</a></p>
            <p>Best regards,<br>{$appConfig['app_name']}</p>
        ";
        
        return $emailer->send($employeeEmail, $subject, $body);
    }
    
    /**
     * Send task assignment notification
     */
    public static function sendTaskAssignment($assigneeEmail, $assigneeName, $taskTitle, $dueDate, $assignedBy) {
        $emailer = new self();
        $appConfig = require __DIR__ . '/../config/app.php';
        
        $subject = "New Task Assigned: {$taskTitle}";
        $body = "
            <h2>New Task Assigned</h2>
            <p>Hi {$assigneeName},</p>
            <p>{$assignedBy} has assigned you a new task:</p>
            <p><strong>{$taskTitle}</strong></p>
            <p><strong>Due Date:</strong> {$dueDate}</p>
            <p><a href='{$appConfig['app_url']}/app/views/employee/tasks.php' style='background:#4da6ff;color:white;padding:10px 20px;text-decoration:none;border-radius:5px;display:inline-block;'>View Task</a></p>
            <p>Best regards,<br>{$appConfig['app_name']}</p>
        ";
        
        return $emailer->send($assigneeEmail, $subject, $body);
    }
    
    /**
     * Send overtime alert
     */
    public static function sendOvertimeAlert($employeeEmail, $employeeName, $overtimeHours, $date) {
        $emailer = new self();
        $appConfig = require __DIR__ . '/../config/app.php';
        
        $subject = "Overtime Alert - {$date}";
        $body = "
            <h2>Overtime Alert</h2>
            <p>Hi {$employeeName},</p>
            <p>You have worked <strong>{$overtimeHours} hours</strong> of overtime on {$date}.</p>
            <p>Please ensure you log out at the end of your shift to accurately track your hours.</p>
            <p>Best regards,<br>{$appConfig['app_name']}</p>
        ";
        
        return $emailer->send($employeeEmail, $subject, $body);
    }
    
    /**
     * Send check-in reminder
     */
    public static function sendCheckInReminder($employeeEmail, $employeeName) {
        $emailer = new self();
        $appConfig = require __DIR__ . '/../config/app.php';
        
        $subject = "Attendance Check-in Reminder";
        $body = "
            <h2>Check-in Reminder</h2>
            <p>Hi {$employeeName},</p>
            <p>We noticed you haven't checked in today. Please remember to check in when you arrive at the office.</p>
            <p><a href='{$appConfig['app_url']}/app/views/dashboard.php' style='background:#4da6ff;color:white;padding:10px 20px;text-decoration:none;border-radius:5px;display:inline-block;'>Check In Now</a></p>
            <p>Best regards,<br>{$appConfig['app_name']}</p>
        ";
        
        return $emailer->send($employeeEmail, $subject, $body);
    }
    
    /**
     * Send password reset code
     */
    public static function sendPasswordResetCode($email, $resetCode, $userName = '') {
        $emailer = new self();
        $appConfig = require __DIR__ . '/../config/app.php';
        
        $subject = "Password Reset Code - {$appConfig['app_name']}";
        $greeting = !empty($userName) ? "Hi {$userName}," : "Hello,";
        
        $body = "
            <div style='font-family:Arial,sans-serif;max-width:600px;margin:0 auto;padding:20px;'>
                <h2 style='color:#667eea;'>Password Reset Request</h2>
                <p>{$greeting}</p>
                <p>You have requested to reset your password for your {$appConfig['app_name']} account.</p>
                <p>Please use the following verification code to reset your password:</p>
                <div style='background:linear-gradient(135deg, #667eea 0%, #764ba2 100%);color:white;padding:20px;border-radius:8px;text-align:center;margin:30px 0;'>
                    <h1 style='margin:0;font-size:36px;letter-spacing:5px;'>{$resetCode}</h1>
                </div>
                <p style='color:#666;font-size:14px;'><strong>This code will expire in 15 minutes.</strong></p>
                <p style='color:#999;font-size:12px;'>If you did not request this password reset, please ignore this email. Your password will remain unchanged.</p>
                <hr style='border:none;border-top:1px solid #eee;margin:30px 0;'>
                <p style='margin-top:30px;'>Best regards,<br><strong>{$appConfig['app_name']} Team</strong></p>
            </div>
        ";
        
        return $emailer->send($email, $subject, $body);
    }
}




