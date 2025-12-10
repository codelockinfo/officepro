<?php
/**
 * Tenant Helper - Multi-Tenancy Manager
 * Manages company context and ensures data isolation
 */

class Tenant {
    
    /**
     * Get current company ID from session
     */
    public static function getCurrentCompanyId() {
        if (!isset($_SESSION['company_id'])) {
            throw new Exception("No company context in session");
        }
        return (int) $_SESSION['company_id'];
    }
    
    /**
     * Set company context in session
     */
    public static function setCompanyContext($companyId, $companyName = null, $companyLogo = null) {
        $_SESSION['company_id'] = (int) $companyId;
        if ($companyName) {
            $_SESSION['company_name'] = $companyName;
        }
        if ($companyLogo) {
            $_SESSION['company_logo'] = $companyLogo;
        }
    }
    
    /**
     * Validate that a user belongs to a company
     */
    public static function validateCompanyAccess($companyId, $userId) {
        $db = Database::getInstance();
        $user = $db->fetchOne(
            "SELECT id FROM users WHERE id = ? AND company_id = ? LIMIT 1",
            [$userId, $companyId]
        );
        
        return $user !== false;
    }
    
    /**
     * Ensure current user belongs to the company in session
     */
    public static function enforceCompanyAccess($userId) {
        $companyId = self::getCurrentCompanyId();
        
        if (!self::validateCompanyAccess($companyId, $userId)) {
            throw new Exception("Unauthorized: User does not belong to this company");
        }
        
        return true;
    }
    
    /**
     * Check if a resource belongs to the current company
     */
    public static function validateResourceOwnership($table, $resourceId, $companyId = null) {
        if ($companyId === null) {
            $companyId = self::getCurrentCompanyId();
        }
        
        $db = Database::getInstance();
        $resource = $db->fetchOne(
            "SELECT id FROM `{$table}` WHERE id = ? AND company_id = ? LIMIT 1",
            [$resourceId, $companyId]
        );
        
        return $resource !== false;
    }
    
    /**
     * Get company settings
     */
    public static function getCompanySetting($key, $default = null) {
        $companyId = self::getCurrentCompanyId();
        $db = Database::getInstance();
        
        $setting = $db->fetchOne(
            "SELECT setting_value FROM company_settings WHERE company_id = ? AND setting_key = ? LIMIT 1",
            [$companyId, $key]
        );
        
        return $setting ? $setting['setting_value'] : $default;
    }
    
    /**
     * Clear company context from session
     */
    public static function clearCompanyContext() {
        unset($_SESSION['company_id']);
        unset($_SESSION['company_name']);
        unset($_SESSION['company_logo']);
    }
}




