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
            $db->execute(
                "INSERT INTO invitations (company_id, email, token, role, invited_by, expires_at, status, created_at) 
                VALUES (?, ?, ?, ?, ?, ?, 'pending', NOW())",
                [$companyId, $email, $token, $role, $invitedBy, $expiresAt]
            );
            
            return [
                'success' => true,
                'token' => $token,
                'invitation_id' => $db->lastInsertId(),
                'expires_at' => $expiresAt
            ];
            
        } catch (Exception $e) {
            error_log("Create Invitation Error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Failed to create invitation'];
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
            "UPDATE invitations SET status = 'accepted', updated_at = NOW() 
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
            "UPDATE invitations SET token = ?, expires_at = ?, status = 'pending', updated_at = NOW() 
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




