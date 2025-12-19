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
                "INSERT INTO leave_balances (company_id, user_id, year, paid_leave) 
                VALUES (?, ?, ?, ?)",
                [
                    $companyId,
                    $ownerId,
                    $currentYear,
                    $appConfig['default_paid_leave']
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
            error_log("Company Registration Stack Trace: " . $e->getTraceAsString());
            
            // Return more detailed error message
            $errorMessage = 'Registration failed';
            if (strpos($e->getMessage(), 'Duplicate entry') !== false) {
                if (strpos($e->getMessage(), 'email') !== false) {
                    $errorMessage = 'Email address already exists. Please use a different email.';
                } elseif (strpos($e->getMessage(), 'company_email') !== false) {
                    $errorMessage = 'Company email already exists. Please use a different company email.';
                } else {
                    $errorMessage = 'A record with this information already exists.';
                }
            } elseif (strpos($e->getMessage(), 'SQLSTATE') !== false) {
                $errorMessage = 'Database error occurred. Please try again or contact support.';
            }
            
            return [
                'success' => false, 
                'message' => $errorMessage,
                'error_details' => $e->getMessage()
            ];
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
                error_log("Registration failed - Invalid invitation token: {$invitationToken}");
                return ['success' => false, 'message' => 'Invalid or expired invitation'];
            }
            
            // Log invitation data
            error_log("Invitation found - Email: {$invitation['email']}, Role: {$invitation['role']}, Company ID: {$invitation['company_id']}");
            
            // Ensure email matches
            if ($invitation['email'] !== $userData['email']) {
                error_log("Email mismatch - Invitation email: {$invitation['email']}, Form email: {$userData['email']}");
                return ['success' => false, 'message' => 'Email does not match invitation'];
            }
            
            $companyId = $invitation['company_id'];
            $role = $invitation['role']; // Use role from invitation, not from form
            
            // Check if user already exists (created during invitation)
            try {
                $existingUser = $db->fetchOne(
                    "SELECT id, email, role, status FROM users WHERE email = ? AND company_id = ?",
                    [$invitation['email'], $companyId]
                );
                
                if ($existingUser) {
                    // User exists - update it instead of creating new
                    if ($existingUser['status'] !== 'pending') {
                        error_log("User already exists and is not pending - ID: {$existingUser['id']}, Status: {$existingUser['status']}");
                        return ['success' => false, 'message' => 'User already registered'];
                    }
                    
                    error_log("Updating existing pending user - ID: {$existingUser['id']}, Email: {$existingUser['email']}, Role: {$existingUser['role']}");
                    $userId = $existingUser['id'];
                } else {
                    error_log("ERROR: User not found for invitation email: {$invitation['email']}, Company ID: {$companyId}");
                    // Try to find any user with this email
                    $anyUser = $db->fetchOne("SELECT * FROM users WHERE email = ?", [$invitation['email']]);
                    if ($anyUser) {
                        error_log("User found but with different company - User Company ID: {$anyUser['company_id']}, Expected: {$companyId}");
                    }
                    return ['success' => false, 'message' => 'User record not found. The invitation may have expired or been cancelled. Please request a new invitation.'];
                }
            } catch (Exception $e) {
                error_log("Error checking existing user: " . $e->getMessage());
                return ['success' => false, 'message' => 'Error checking user: ' . $e->getMessage()];
            }
        } else {
            return ['success' => false, 'message' => 'Invitation token required'];
        }
        
        try {
            $db->beginTransaction();
            
            // Update existing user with registration data
            $hashedPassword = password_hash($userData['password'], PASSWORD_BCRYPT);
            
            // Log the exact values being updated
            error_log("Updating user - ID: {$userId}, Email: {$invitation['email']}, Role: {$role}, Name: {$userData['full_name']}");
            
            // Check if profile image path is valid
            $profileImage = $userData['profile_image'] ?? 'assets/images/default-avatar.png';
            if (empty($profileImage)) {
                $profileImage = 'assets/images/default-avatar.png';
            }
            
            // Verify user exists and is pending before update
            $currentUser = $db->fetchOne("SELECT * FROM users WHERE id = ?", [$userId]);
            if (!$currentUser) {
                throw new Exception("User with ID {$userId} not found in database");
            }
            
            if ($currentUser['status'] !== 'pending') {
                error_log("User status is not pending - Current status: {$currentUser['status']}");
                throw new Exception("User is not in pending status. Current status: {$currentUser['status']}");
            }
            
            if ($currentUser['email'] !== $invitation['email']) {
                error_log("Email mismatch - User email: {$currentUser['email']}, Invitation email: {$invitation['email']}");
                throw new Exception("Email mismatch between user and invitation");
            }
            
            // Log before update
            error_log("Updating user - ID: {$userId}, Email: {$invitation['email']}, Company ID: {$companyId}");
            error_log("Current user data: " . json_encode($currentUser));
            
            $updateResult = $db->execute(
                "UPDATE users SET 
                    password = ?, 
                    full_name = ?, 
                    profile_image = ?, 
                    department_id = ?, 
                    status = 'active'
                WHERE id = ? AND email = ? AND company_id = ? AND status = 'pending'",
                [
                    $hashedPassword,
                    $userData['full_name'],
                    $profileImage,
                    $userData['department_id'] ?? null,
                    $userId,
                    $invitation['email'],
                    $companyId
                ]
            );
            
            // Check if update affected any rows
            if ($updateResult === false || $updateResult === 0) {
                // Get current user state for debugging
                $afterUpdateUser = $db->fetchOne("SELECT * FROM users WHERE id = ?", [$userId]);
                error_log("Update failed or affected 0 rows. User state after update attempt: " . json_encode($afterUpdateUser));
                throw new Exception("User update failed. No rows were updated. Please check database logs for details.");
            }
            
            error_log("Update successful - {$updateResult} row(s) affected");
            
            // Verify user was updated
            $updatedUser = $db->fetchOne("SELECT id, email, role, status FROM users WHERE id = ?", [$userId]);
            if (!$updatedUser) {
                throw new Exception("User update verification failed - user not found");
            }
            
            if ($updatedUser['status'] !== 'active') {
                throw new Exception("User status was not updated to active. Current status: " . $updatedUser['status']);
            }
            
            error_log("User updated successfully - ID: {$userId}, Email: {$updatedUser['email']}, Role: {$updatedUser['role']}, Status: {$updatedUser['status']}");
            
            // Create leave balance (only if it doesn't exist)
            $currentYear = date('Y');
            $appConfig = require __DIR__ . '/../config/app.php';
            
            // Check if leave balance already exists
            $existingBalance = $db->fetchOne(
                "SELECT id FROM leave_balances WHERE company_id = ? AND user_id = ? AND year = ?",
                [$companyId, $userId, $currentYear]
            );
            
            if (!$existingBalance) {
                $db->execute(
                    "INSERT INTO leave_balances (company_id, user_id, year, paid_leave) 
                    VALUES (?, ?, ?, ?)",
                    [
                        $companyId,
                        $userId,
                        $currentYear,
                        $appConfig['default_paid_leave']
                    ]
                );
                error_log("Leave balance created for user ID: {$userId}");
            } else {
                error_log("Leave balance already exists for user ID: {$userId}, skipping creation");
            }
            
            // Mark invitation as accepted
            Invitation::markAsAccepted($invitationToken, $userId);
            
            $db->commit();
            
            return [
                'success' => true,
                'user_id' => $userId,
                'company_id' => $companyId
            ];
            
        } catch (PDOException $e) {
            $db->rollBack();
            $errorMsg = "User Registration PDO Error: " . $e->getMessage();
            $errorMsg .= " | Code: " . $e->getCode();
            error_log($errorMsg);
            error_log("User Registration Error Trace: " . $e->getTraceAsString());
            return ['success' => false, 'message' => 'Registration failed: Database error - ' . $e->getMessage()];
        } catch (Exception $e) {
            $db->rollBack();
            error_log("User Registration Error: " . $e->getMessage());
            error_log("User Registration Error Trace: " . $e->getTraceAsString());
            return ['success' => false, 'message' => 'Registration failed: ' . $e->getMessage()];
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
        $_SESSION['company_logo'] = $user['company_logo'] ?? null;
        $_SESSION['last_activity'] = time();
        
        // Log successful login
        error_log("Login successful - User ID: {$user['id']}, Email: {$user['email']}, Profile: {$user['profile_image']}");
        
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
        // Start session if not started
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        if (!isset($_SESSION['user_id']) || !isset($_SESSION['email'])) {
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
        
        $profileImage = $_SESSION['profile_image'] ?? 'assets/images/default-avatar.png';
        // Ensure profile image is never empty
        if (empty($profileImage) || trim($profileImage) === '') {
            $profileImage = 'assets/images/default-avatar.png';
        }
        
        return [
            'id' => $_SESSION['user_id'] ?? null,
            'email' => $_SESSION['email'] ?? '',
            'full_name' => $_SESSION['full_name'] ?? '',
            'role' => $_SESSION['role'] ?? 'employee',
            'profile_image' => $profileImage,
            'company_id' => $_SESSION['company_id'] ?? null
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
     * Require specific role or throw exception (for API endpoints)
     */
    public static function requireRole($roles) {
        if (!self::hasRole($roles)) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
            exit;
        }
    }
    
    /**
     * Check role for view pages (redirects to error page instead of JSON)
     */
    public static function checkRole($roles, $errorMessage = null) {
        if (!self::hasRole($roles)) {
            $message = $errorMessage ?? 'You do not have permission to access this page.';
            header("Location: /public_html/app/views/error.php?code=403&message=" . urlencode($message));
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



