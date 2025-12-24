<?php
/**
 * Application Configuration
 */

return [
    'app_name' => 'OfficePro Attendance System',
    'app_url' => 'http://localhost/officepro',
    'base_path' => '/officepro',
    'timezone' => 'Asia/Kolkata', // Kolkata, India timezone
    
    // Attendance settings
    'standard_work_hours' => 8,
    'auto_checkout_time' => '23:59:00',
    'late_arrival_threshold' => '09:15:00',
    
    // Leave settings
    'default_paid_leave' => 20,
    
    // Invitation settings
    'invitation_expiry_days' => 7,
    
    // Pagination
    'items_per_page' => 15,
    
    // File upload
    'max_upload_size' => 2097152, // 2MB in bytes
    'allowed_image_types' => ['jpg', 'jpeg', 'png', 'webp'],
    'allowed_document_types' => ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png'],
    'profile_image_min_width' => 100,
    'profile_image_min_height' => 100,
    
    // Session
    'session_lifetime' => 31536000, // 1 year in seconds (365 days) - prevents auto logout
    
    // Security
    'bcrypt_cost' => 12,
];



