<?php
/**
 * Application Configuration
 */

return [
    'app_name' => 'OfficePro Attendance System',
    'app_url' => 'http://localhost/officepro',
    'timezone' => 'UTC',
    
    // Attendance settings
    'standard_work_hours' => 8,
    'auto_checkout_time' => '23:59:00',
    'late_arrival_threshold' => '09:15:00',
    
    // Leave settings
    'default_paid_leave' => 20,
    'default_sick_leave' => 10,
    'default_casual_leave' => 5,
    'default_wfh_days' => 12,
    
    // Invitation settings
    'invitation_expiry_days' => 7,
    
    // Pagination
    'items_per_page' => 15,
    
    // File upload
    'max_upload_size' => 2097152, // 2MB in bytes
    'allowed_image_types' => ['jpg', 'jpeg', 'png'],
    'allowed_document_types' => ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png'],
    'profile_image_min_width' => 200,
    'profile_image_min_height' => 200,
    
    // Session
    'session_lifetime' => 1800, // 30 minutes in seconds
    
    // Security
    'bcrypt_cost' => 12,
];


