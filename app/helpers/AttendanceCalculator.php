<?php
/**
 * Attendance Hours Calculator Helper
 * Calculates regular and overtime hours based on standard working hours
 */

require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/Tenant.php';

class AttendanceCalculator {
    /**
     * Calculate regular and overtime hours based on standard working hours
     * 
     * @param string $checkInTime Check-in timestamp (Y-m-d H:i:s)
     * @param string $checkOutTime Check-out timestamp (Y-m-d H:i:s)
     * @param int|null $companyId Company ID (if null, uses current company)
     * @return array ['regular_hours' => float, 'overtime_hours' => float, 'total_hours' => float]
     */
    public static function calculateHours($checkInTime, $checkOutTime, $companyId = null) {
        if ($companyId === null) {
            $companyId = Tenant::getCurrentCompanyId();
        }
        
        // Get working hours from company settings (default to 8 hours)
        $workingHours = floatval(Tenant::getCompanySetting('working_hours', '8'));
        
        // Validate working hours
        if ($workingHours < 1 || $workingHours > 24) {
            $workingHours = 8; // Default to 8 hours if invalid
        }
        
        // Parse times
        $checkIn = new DateTime($checkInTime);
        $checkOut = new DateTime($checkOutTime);
        
        // Calculate total hours worked using total seconds for accuracy
        $interval = $checkIn->diff($checkOut);
        $totalSeconds = ($interval->days * 86400) + ($interval->h * 3600) + ($interval->i * 60) + $interval->s;
        $totalHours = $totalSeconds / 3600; // Convert total seconds to decimal hours
        
        // Calculate regular and overtime hours
        // Regular hours = min(total hours, standard working hours)
        // Overtime hours = max(0, total hours - standard working hours)
        $regularHours = min($totalHours, $workingHours);
        $overtimeHours = max(0, $totalHours - $workingHours);
        
        return [
            'regular_hours' => round($regularHours, 4),
            'overtime_hours' => round($overtimeHours, 4),
            'total_hours' => round($totalHours, 4)
        ];
    }
}

