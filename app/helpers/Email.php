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
        $this->mail->SMTPSecure = $this->config['smtp_encryption'];
        $this->mail->Port = $this->config['smtp_port'];
        
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
            $this->mail->clearAddresses();
            $this->mail->addAddress($to);
            $this->mail->Subject = $subject;
            $this->mail->Body = $body;
            
            return $this->mail->send();
        } catch (Exception $e) {
            error_log("Email Error: " . $this->mail->ErrorInfo);
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
    public static function sendEmployeeInvitation($email, $token, $companyName, $inviterName) {
        $emailer = new self();
        $appConfig = require __DIR__ . '/../config/app.php';
        
        $registrationLink = $appConfig['app_url'] . '/register.php?token=' . $token;
        
        $subject = "Invitation to join {$companyName}";
        $body = "
            <h2>You've been invited to join {$companyName}!</h2>
            <p>{$inviterName} has invited you to join their team on {$appConfig['app_name']}.</p>
            <p>Click the button below to create your account:</p>
            <p><a href='{$registrationLink}' style='background:#4da6ff;color:white;padding:10px 20px;text-decoration:none;border-radius:5px;display:inline-block;'>Accept Invitation</a></p>
            <p>Or copy this link: {$registrationLink}</p>
            <p>This invitation will expire in 7 days.</p>
            <p>Best regards,<br>{$appConfig['app_name']} Team</p>
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
}


