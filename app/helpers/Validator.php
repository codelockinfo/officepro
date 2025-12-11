<?php
/**
 * Validator Helper - Input Validation and Sanitization
 */

class Validator {
    private $errors = [];
    
    /**
     * Validate email
     */
    public function email($value, $fieldName = 'Email') {
        if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
            $this->errors[$fieldName] = "{$fieldName} is invalid";
            return false;
        }
        return true;
    }
    
    /**
     * Validate required field
     */
    public function required($value, $fieldName) {
        if (empty($value)) {
            $this->errors[$fieldName] = "{$fieldName} is required";
            return false;
        }
        return true;
    }
    
    /**
     * Validate minimum length
     */
    public function minLength($value, $length, $fieldName) {
        if (strlen($value) < $length) {
            $this->errors[$fieldName] = "{$fieldName} must be at least {$length} characters";
            return false;
        }
        return true;
    }
    
    /**
     * Validate maximum length
     */
    public function maxLength($value, $length, $fieldName) {
        if (strlen($value) > $length) {
            $this->errors[$fieldName] = "{$fieldName} must not exceed {$length} characters";
            return false;
        }
        return true;
    }
    
    /**
     * Validate date format
     */
    public function date($value, $fieldName, $format = 'Y-m-d') {
        $d = DateTime::createFromFormat($format, $value);
        if (!$d || $d->format($format) !== $value) {
            $this->errors[$fieldName] = "{$fieldName} is not a valid date";
            return false;
        }
        return true;
    }
    
    /**
     * Validate date range
     */
    public function dateRange($startDate, $endDate, $fieldName = 'Date range') {
        if (strtotime($startDate) > strtotime($endDate)) {
            $this->errors[$fieldName] = "Start date must be before end date";
            return false;
        }
        return true;
    }
    
    /**
     * Validate phone number
     */
    public function phone($value, $fieldName = 'Phone') {
        // Allow empty (for optional fields)
        if (empty($value)) {
            return true;
        }
        
        // Check if it contains only valid characters
        if (!preg_match('/^[\+]?[0-9\s\-\(\)]+$/', $value)) {
            $this->errors[$fieldName] = "{$fieldName} contains invalid characters";
            return false;
        }
        
        // Extract just the digits (excluding country code symbols)
        $digitsOnly = preg_replace('/[^0-9]/', '', $value);
        $digitCount = strlen($digitsOnly);
        
        // For Indian numbers with +91, allow 10-12 digits
        // For international, allow 10-15 digits
        if ($digitCount < 10 || $digitCount > 15) {
            $this->errors[$fieldName] = "{$fieldName} must contain 10-15 digits";
            return false;
        }
        
        return true;
    }
    
    /**
     * Validate URL
     */
    public function url($value, $fieldName = 'URL') {
        if (!filter_var($value, FILTER_VALIDATE_URL)) {
            $this->errors[$fieldName] = "{$fieldName} is invalid";
            return false;
        }
        return true;
    }
    
    /**
     * Validate image upload
     * @param array $file The uploaded file array
     * @param string $fieldName The field name for error messages
     * @param bool $checkDimensions Whether to check minimum dimensions (default: true)
     */
    public function image($file, $fieldName = 'Image', $checkDimensions = true) {
        $appConfig = require __DIR__ . '/../config/app.php';
        
        if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
            $this->errors[$fieldName] = "{$fieldName} upload failed";
            return false;
        }
        
        // Check file size
        if ($file['size'] > $appConfig['max_upload_size']) {
            $this->errors[$fieldName] = "{$fieldName} size must not exceed 2MB";
            return false;
        }
        
        // Check file type
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, $appConfig['allowed_image_types'])) {
            $allowedTypes = implode(', ', array_map('strtoupper', $appConfig['allowed_image_types']));
            $this->errors[$fieldName] = "{$fieldName} must be {$allowedTypes}";
            return false;
        }
        
        // Validate actual image
        $imageInfo = getimagesize($file['tmp_name']);
        if (!$imageInfo) {
            $this->errors[$fieldName] = "{$fieldName} is not a valid image";
            return false;
        }
        
        // Check dimensions for profile images (optional)
        if ($checkDimensions && ($imageInfo[0] < $appConfig['profile_image_min_width'] || $imageInfo[1] < $appConfig['profile_image_min_height'])) {
            $this->errors[$fieldName] = "{$fieldName} must be at least {$appConfig['profile_image_min_width']}x{$appConfig['profile_image_min_height']} pixels";
            return false;
        }
        
        return true;
    }
    
    /**
     * Validate document upload
     */
    public function document($file, $fieldName = 'Document') {
        $appConfig = require __DIR__ . '/../config/app.php';
        
        if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
            $this->errors[$fieldName] = "{$fieldName} upload failed";
            return false;
        }
        
        // Check file size
        if ($file['size'] > $appConfig['max_upload_size']) {
            $this->errors[$fieldName] = "{$fieldName} size must not exceed 2MB";
            return false;
        }
        
        // Check file type
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, $appConfig['allowed_document_types'])) {
            $this->errors[$fieldName] = "{$fieldName} type not allowed";
            return false;
        }
        
        return true;
    }
    
    /**
     * Sanitize string
     */
    public function sanitize($value) {
        return htmlspecialchars(trim($value), ENT_QUOTES, 'UTF-8');
    }
    
    /**
     * Get all errors
     */
    public function getErrors() {
        return $this->errors;
    }
    
    /**
     * Check if there are errors
     */
    public function hasErrors() {
        return count($this->errors) > 0;
    }
    
    /**
     * Clear errors
     */
    public function clearErrors() {
        $this->errors = [];
    }
    
    /**
     * Upload file and return filename
     */
    public static function uploadFile($file, $directory, $prefix = '') {
        if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
            error_log("Upload error: File not uploaded or tmp_name missing");
            return false;
        }
        
        // Create directory if not exists
        if (!file_exists($directory)) {
            if (!mkdir($directory, 0755, true)) {
                error_log("Upload error: Failed to create directory: $directory");
                return false;
            }
        }
        
        // Check if directory is writable
        if (!is_writable($directory)) {
            error_log("Upload error: Directory not writable: $directory");
            return false;
        }
        
        // Generate unique filename
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $filename = $prefix . uniqid() . '_' . time() . '.' . $ext;
        $filepath = $directory . '/' . $filename;
        
        if (move_uploaded_file($file['tmp_name'], $filepath)) {
            error_log("Upload success: File saved to $filepath");
            return $filename;
        }
        
        error_log("Upload error: move_uploaded_file failed. From: {$file['tmp_name']} To: $filepath");
        return false;
    }
}



