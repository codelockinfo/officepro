<?php
/**
 * Auth Helper - Authentication and Authorization
 */

require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/Tenant.php';

class Auth {
    
    /**
     * Register a new company with owner account
     */
    public static function registerCompany($companyData, $ownerData) {
        $db = Database::getInstance();
        
        try {
            $db->beginTransaction();
            
            // Create company
            $db->execute(
                "INSERT INTO companies (company_name, company_email, phone, address, logo, created_at) 
                VALUES (?, ?, ?, ?, ?, NOW())",
                [
                    $companyData['company_name'],
                    $companyData['company_email'],
                    $companyData['phone'] ?? null,
                    $companyData['address'] ?? null,
                    $companyData['logo'] ?? null
                ]
            );
            
            $companyId = $db->lastInsertId();
            
            // Create owner user
            $hashedPassword = password_hash($ownerData['password'], PASSWORD_BCRYPT);
            $db->execute(
                "INSERT INTO users (company_id, email, password, full_name, profile_image, role, status, created_at) 
                VALUES (?, ?, ?, ?, ?, 'company_owner', 'active', NOW())",
                [
                    $companyId,
                    $ownerData['email'],
                    $hashedPassword,
                    $ownerData['full_name'],
                    $ownerData['profile_image']
                ]
            );
            
            $ownerId = $db->lastInsertId();
            
            // Update company with owner_id
            $db->execute(
                "UPDATE companies SET owner_id = ? WHERE id = ?",
                [$ownerId, $companyId]
            );
            
            // Create default department
            $db->execute(
                "INSERT INTO departments (company_id, name, created_at) VALUES (?, 'General', NOW())",
                [$companyId]
            );
            
            // Create leave balance for current year
            $currentYear = date('Y');
            $appConfig = require __DIR__ . '/../config/app.php';
            $db->execute(
                "INSERT INTO leave_balances (company_id, user_id, year, paid_leave, sick_leave, casual_leave, wfh_days) 
                VALUES (?, ?, ?, ?, ?, ?, ?)",
                [
                    $companyId,
                    $ownerId,
                    $currentYear,
                    $appConfig['default_paid_leave'],
                    $appConfig['default_sick_leave'],
                    $appConfig['default_casual_leave'],
                    $appConfig['default_wfh_days']
                ]
            );
            
            $db->commit();
            
            return [
                'success' => true,
                'company_id' => $companyId,
                'user_id' => $ownerId
            ];
            
        } catch (Exception $e) {
            $db->rollBack();
            error_log("Company Registration Error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Registration failed'];
        }
    }
    
    /**
     * Register employee with invitation token
     */
    public static function register($userData, $invitationToken = null) {
        $db = Database::getInstance();
        
        // Validate invitation token
        if ($invitationToken) {
            $invitation = Invitation::validateToken($invitationToken);
            if (!$invitation) {
                return ['success' => false, 'message' => 'Invalid or expired invitation'];
            }
            
            // Ensure email matches
            if ($invitation['email'] !== $userData['email']) {
                return ['success' => false, 'message' => 'Email does not match invitation'];
            }
            
            $companyId = $invitation['company_id'];
            $role = $invitation['role'];
        } else {
            return ['success' => false, 'message' => 'Invitation token required'];
        }
        
        try {
            $db->beginTransaction();
            
            // Create user
            $hashedPassword = password_hash($userData['password'], PASSWORD_BCRYPT);
            $db->execute(
                "INSERT INTO users (company_id, email, password, full_name, profile_image, role, department_id, status, created_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?, 'active', NOW())",
                [
                    $companyId,
                    $userData['email'],
                    $hashedPassword,
                    $userData['full_name'],
                    $userData['profile_image'],
                    $role,
                    $userData['department_id'] ?? null
                ]
            );
            
            $userId = $db->lastInsertId();
            
            // Create leave balance
            $currentYear = date('Y');
            $appConfig = require __DIR__ . '/../config/app.php';
            $db->execute(
                "INSERT INTO leave_balances (company_id, user_id, year, paid_leave, sick_leave, casual_leave, wfh_days) 
                VALUES (?, ?, ?, ?, ?, ?, ?)",
                [
                    $companyId,
                    $userId,
                    $currentYear,
                    $appConfig['default_paid_leave'],
                    $appConfig['default_sick_leave'],
                    $appConfig['default_casual_leave'],
                    $appConfig['default_wfh_days']
                ]
            );
            
            // Mark invitation as accepted
            Invitation::markAsAccepted($invitationToken, $userId);
            
            $db->commit();
            
            return [
                'success' => true,
                'user_id' => $userId,
                'company_id' => $companyId
            ];
            
        } catch (Exception $e) {
            $db->rollBack();
            error_log("User Registration Error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Registration failed'];
        }
    }
    
    /**
     * Login user
     */
    public static function login($email, $password) {
        $db = Database::getInstance();
        
        $user = $db->fetchOne(
            "SELECT u.*, c.company_name, c.company_email, c.logo as company_logo 
            FROM users u 
            JOIN companies c ON u.company_id = c.id 
            WHERE u.email = ? AND u.status = 'active' 
            LIMIT 1",
            [$email]
        );
        
        if (!$user) {
            return ['success' => false, 'message' => 'Invalid credentials'];
        }
        
        if (!password_verify($password, $user['password'])) {
            return ['success' => false, 'message' => 'Invalid credentials'];
        }
        
        // Create session
        session_regenerate_id(true);
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['email'] = $user['email'];
        $_SESSION['full_name'] = $user['full_name'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['profile_image'] = $user['profile_image'];
        $_SESSION['company_id'] = $user['company_id'];
        $_SESSION['company_name'] = $user['company_name'];
        $_SESSION['company_logo'] = $user['company_logo'];
        $_SESSION['last_activity'] = time();
        
        return ['success' => true, 'user' => $user];
    }
    
    /**
     * Logout user
     */
    public static function logout() {
        session_destroy();
        session_start();
        return ['success' => true];
    }
    
    /**
     * Check if user is logged in
     */
    public static function isLoggedIn() {
        if (!isset($_SESSION['user_id'])) {
            return false;
        }
        
        // Check session timeout
        $appConfig = require __DIR__ . '/../config/app.php';
        if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > $appConfig['session_lifetime'])) {
            self::logout();
            return false;
        }
        
        $_SESSION['last_activity'] = time();
        return true;
    }
    
    /**
     * Get current user data
     */
    public static function getCurrentUser() {
        if (!self::isLoggedIn()) {
            return null;
        }
        
        return [
            'id' => $_SESSION['user_id'],
            'email' => $_SESSION['email'],
            'full_name' => $_SESSION['full_name'],
            'role' => $_SESSION['role'],
            'profile_image' => $_SESSION['profile_image'],
            'company_id' => $_SESSION['company_id']
        ];
    }
    
    /**
     * Check if user has role
     */
    public static function hasRole($roles) {
        if (!self::isLoggedIn()) {
            return false;
        }
        
        $roles = is_array($roles) ? $roles : [$roles];
        return in_array($_SESSION['role'], $roles);
    }
    
    /**
     * Require specific role or throw exception
     */
    public static function requireRole($roles) {
        if (!self::hasRole($roles)) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
            exit;
        }
    }
    
    /**
     * Check if current user is system admin
     */
    public static function isSystemAdmin() {
        return self::hasRole('system_admin');
    }
    
    /**
     * Check if current user is company owner
     */
    public static function isCompanyOwner() {
        return self::hasRole('company_owner');
    }
    
    /**
     * Check if user can manage company
     */
    public static function canManageCompany() {
        return self::hasRole(['system_admin', 'company_owner']);
    }
}


