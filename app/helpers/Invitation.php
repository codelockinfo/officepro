<?php
/**
 * Invitation Helper - Manage Employee Invitations
 */

require_once __DIR__ . '/Database.php';

class Invitation {
    
    /**
     * Generate secure random token
     */
    public static function generateToken() {
        return bin2hex(random_bytes(32)); // 64 characters
    }
    
    /**
     * Create invitation
     */
    public static function createInvitation($companyId, $email, $role, $invitedBy) {
        $db = Database::getInstance();
        $appConfig = require __DIR__ . '/../config/app.php';
        
        $token = self::generateToken();
        $expiryDays = $appConfig['invitation_expiry_days'];
        $expiresAt = date('Y-m-d H:i:s', strtotime("+{$expiryDays} days"));
        
        try {
            // Log before insertion
            error_log("Creating invitation - Company ID: {$companyId}, Email: {$email}, Role: {$role}, Invited By: {$invitedBy}");
            
            $db->beginTransaction();
            
            // Step 1: Create invitation record
            $db->execute(
                "INSERT INTO invitations (company_id, email, token, role, invited_by, expires_at, status, created_at) 
                VALUES (?, ?, ?, ?, ?, ?, 'pending', NOW())",
                [$companyId, $email, $token, $role, $invitedBy, $expiresAt]
            );
            
            $invitationId = $db->lastInsertId();
            
            // Step 2: Check if user already exists (might be from previous invitation)
            $existingUser = $db->fetchOne(
                "SELECT id, email, role, status FROM users WHERE email = ? AND company_id = ?",
                [$email, $companyId]
            );
            
            if ($existingUser) {
                // User exists - check if it's pending
                if ($existingUser['status'] === 'pending') {
                    $userId = $existingUser['id'];
                    error_log("Using existing pending user - ID: {$userId}, Email: {$existingUser['email']}, Role: {$existingUser['role']}");
                } else {
                    throw new Exception("User with this email already exists and is active");
                }
            } else {
                // Create new user record with email and role (status = 'pending')
                // Use temporary values for required fields that will be updated during registration
                $tempPassword = password_hash(uniqid('temp_', true), PASSWORD_BCRYPT); // Temporary password
                $tempName = 'Pending Registration'; // Temporary name
                $tempImage = 'assets/images/default-avatar.png'; // Default image
                
                $db->execute(
                    "INSERT INTO users (company_id, email, password, full_name, profile_image, role, department_id, status, created_at) 
                    VALUES (?, ?, ?, ?, ?, ?, NULL, 'pending', NOW())",
                    [$companyId, $email, $tempPassword, $tempName, $tempImage, $role]
                );
                
                $userId = $db->lastInsertId();
                
                // Verify user was created
                $verifyUser = $db->fetchOne(
                    "SELECT id, email, role, status FROM users WHERE id = ?",
                    [$userId]
                );
                
                if ($verifyUser) {
                    error_log("User created in users table - ID: {$userId}, Email: {$verifyUser['email']}, Role: {$verifyUser['role']}, Status: {$verifyUser['status']}");
                } else {
                    throw new Exception("User creation verification failed");
                }
            }
            
            $db->commit();
            
            // Verify invitation was created
            $verifyInvitation = $db->fetchOne(
                "SELECT * FROM invitations WHERE id = ?",
                [$invitationId]
            );
            
            if ($verifyInvitation) {
                error_log("Invitation created successfully - ID: {$invitationId}, Email: {$verifyInvitation['email']}, Role: {$verifyInvitation['role']}");
            } else {
                error_log("WARNING: Invitation insert succeeded but verification failed!");
            }
            
            return [
                'success' => true,
                'token' => $token,
                'invitation_id' => $invitationId,
                'user_id' => $userId,
                'expires_at' => $expiresAt,
                'email' => $email,
                'role' => $role
            ];
            
        } catch (Exception $e) {
            $db->rollBack();
            error_log("Create Invitation Error: " . $e->getMessage());
            error_log("Create Invitation Error Details: " . $e->getTraceAsString());
            return ['success' => false, 'message' => 'Failed to create invitation: ' . $e->getMessage()];
        }
    }
    
    /**
     * Validate token and return invitation data
     */
    public static function validateToken($token) {
        $db = Database::getInstance();
        
        $invitation = $db->fetchOne(
            "SELECT * FROM invitations 
            WHERE token = ? AND status = 'pending' AND expires_at > NOW() 
            LIMIT 1",
            [$token]
        );
        
        return $invitation ?: false;
    }
    
    /**
     * Mark invitation as accepted
     */
    public static function markAsAccepted($token, $userId) {
        $db = Database::getInstance();
        
        return $db->execute(
            "UPDATE invitations SET status = 'accepted' 
            WHERE token = ?",
            [$token]
        );
    }
    
    /**
     * Resend invitation email
     */
    public static function resendInvitation($invitationId) {
        $db = Database::getInstance();
        $appConfig = require __DIR__ . '/../config/app.php';
        
        // Get invitation
        $invitation = $db->fetchOne(
            "SELECT * FROM invitations WHERE id = ? LIMIT 1",
            [$invitationId]
        );
        
        if (!$invitation) {
            return ['success' => false, 'message' => 'Invitation not found'];
        }
        
        // Generate new token and extend expiry
        $newToken = self::generateToken();
        $expiryDays = $appConfig['invitation_expiry_days'];
        $expiresAt = date('Y-m-d H:i:s', strtotime("+{$expiryDays} days"));
        
        $db->execute(
            "UPDATE invitations SET token = ?, expires_at = ?, status = 'pending' 
            WHERE id = ?",
            [$newToken, $expiresAt, $invitationId]
        );
        
        return [
            'success' => true,
            'token' => $newToken,
            'expires_at' => $expiresAt
        ];
    }
    
    /**
     * Expire old invitations (for cron job)
     */
    public static function expireOldInvitations() {
        $db = Database::getInstance();
        
        return $db->execute(
            "UPDATE invitations SET status = 'expired' 
            WHERE status = 'pending' AND expires_at < NOW()"
        );
    }
    
    /**
     * Cancel invitation
     */
    public static function cancelInvitation($invitationId, $companyId) {
        $db = Database::getInstance();
        
        return $db->execute(
            "UPDATE invitations SET status = 'cancelled' 
            WHERE id = ? AND company_id = ?",
            [$invitationId, $companyId]
        );
    }
    
    /**
     * Get invitations for company
     */
    public static function getCompanyInvitations($companyId, $status = null) {
        $db = Database::getInstance();
        
        $sql = "SELECT i.*, u.full_name as invited_by_name 
                FROM invitations i 
                JOIN users u ON i.invited_by = u.id 
                WHERE i.company_id = ?";
        $params = [$companyId];
        
        if ($status) {
            $sql .= " AND i.status = ?";
            $params[] = $status;
        }
        
        $sql .= " ORDER BY i.created_at DESC";
        
        return $db->fetchAll($sql, $params);
    }
}




