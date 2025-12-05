<?php
/**
 * PDF Helper - DomPDF Wrapper for Report Generation
 */

use Dompdf\Dompdf;
use Dompdf\Options;

require_once __DIR__ . '/../../vendor/autoload.php';

class PDF {
    private $dompdf;
    private $options;
    
    public function __construct() {
        $this->options = new Options();
        $this->options->set('isHtml5ParserEnabled', true);
        $this->options->set('isRemoteEnabled', true);
        $this->dompdf = new Dompdf($this->options);
    }
    
    /**
     * Generate PDF from HTML
     */
    public function generateFromHTML($html, $filename = 'document.pdf', $output = 'D') {
        $this->dompdf->loadHtml($html);
        $this->dompdf->setPaper('A4', 'portrait');
        $this->dompdf->render();
        
        // Output: D = Download, I = Inline, S = String, F = File
        return $this->dompdf->stream($filename, ['Attachment' => ($output === 'D')]);
    }
    
    /**
     * Generate attendance report PDF
     */
    public static function generateAttendanceReport($companyName, $companyLogo, $reportData, $startDate, $endDate) {
        $pdf = new self();
        
        $html = "
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; font-size: 12px; }
                .header { text-align: center; margin-bottom: 30px; border-bottom: 2px solid #4da6ff; padding-bottom: 10px; }
                .header img { max-height: 60px; }
                .header h1 { color: #4da6ff; margin: 10px 0; }
                .info { margin-bottom: 20px; }
                table { width: 100%; border-collapse: collapse; margin-top: 20px; }
                th { background-color: #4da6ff; color: white; padding: 10px; text-align: left; }
                td { padding: 8px; border-bottom: 1px solid #ddd; }
                tr:nth-child(even) { background-color: #f5f5f5; }
                .overtime { color: #ff9933; font-weight: bold; }
                .footer { margin-top: 30px; text-align: center; font-size: 10px; color: #666; }
            </style>
        </head>
        <body>
            <div class='header'>";
        
        if ($companyLogo) {
            $html .= "<img src='{$companyLogo}' alt='Company Logo'>";
        }
        
        $html .= "
                <h1>{$companyName}</h1>
                <h2>Attendance Report</h2>
            </div>
            
            <div class='info'>
                <p><strong>Report Period:</strong> {$startDate} to {$endDate}</p>
                <p><strong>Generated:</strong> " . date('Y-m-d H:i:s') . "</p>
            </div>
            
            <table>
                <thead>
                    <tr>
                        <th>Employee</th>
                        <th>Date</th>
                        <th>Check In</th>
                        <th>Check Out</th>
                        <th>Regular Hours</th>
                        <th>Overtime Hours</th>
                        <th>Total Hours</th>
                    </tr>
                </thead>
                <tbody>";
        
        $totalRegular = 0;
        $totalOvertime = 0;
        
        foreach ($reportData as $row) {
            $totalHours = $row['regular_hours'] + $row['overtime_hours'];
            $totalRegular += $row['regular_hours'];
            $totalOvertime += $row['overtime_hours'];
            
            $overtimeClass = $row['overtime_hours'] > 0 ? 'overtime' : '';
            
            $html .= "
                    <tr>
                        <td>{$row['employee_name']}</td>
                        <td>{$row['date']}</td>
                        <td>{$row['check_in']}</td>
                        <td>{$row['check_out']}</td>
                        <td>{$row['regular_hours']}</td>
                        <td class='{$overtimeClass}'>{$row['overtime_hours']}</td>
                        <td>" . number_format($totalHours, 2) . "</td>
                    </tr>";
        }
        
        $grandTotal = $totalRegular + $totalOvertime;
        
        $html .= "
                    <tr style='font-weight: bold; background-color: #e6f2ff;'>
                        <td colspan='4'>TOTAL</td>
                        <td>" . number_format($totalRegular, 2) . "</td>
                        <td class='overtime'>" . number_format($totalOvertime, 2) . "</td>
                        <td>" . number_format($grandTotal, 2) . "</td>
                    </tr>
                </tbody>
            </table>
            
            <div class='footer'>
                <p>This is a computer-generated report. No signature is required.</p>
            </div>
        </body>
        </html>";
        
        return $pdf->generateFromHTML($html, "attendance_report_{$startDate}_{$endDate}.pdf");
    }
    
    /**
     * Generate monthly summary report
     */
    public static function generateMonthlyReport($companyName, $companyLogo, $month, $year, $summaryData) {
        $pdf = new self();
        
        $html = "
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; font-size: 12px; }
                .header { text-align: center; margin-bottom: 30px; border-bottom: 2px solid #4da6ff; padding-bottom: 10px; }
                .header img { max-height: 60px; }
                .header h1 { color: #4da6ff; margin: 10px 0; }
                .summary-box { border: 1px solid #ddd; padding: 15px; margin: 10px 0; border-radius: 5px; }
                .summary-box h3 { color: #4da6ff; margin-top: 0; }
                table { width: 100%; border-collapse: collapse; margin-top: 20px; }
                th { background-color: #4da6ff; color: white; padding: 10px; text-align: left; }
                td { padding: 8px; border-bottom: 1px solid #ddd; }
                tr:nth-child(even) { background-color: #f5f5f5; }
                .footer { margin-top: 30px; text-align: center; font-size: 10px; color: #666; }
            </style>
        </head>
        <body>
            <div class='header'>";
        
        if ($companyLogo) {
            $html .= "<img src='{$companyLogo}' alt='Company Logo'>";
        }
        
        $html .= "
                <h1>{$companyName}</h1>
                <h2>Monthly Summary Report - {$month}/{$year}</h2>
            </div>
            
            <div class='summary-box'>
                <h3>Overall Statistics</h3>
                <p><strong>Total Employees:</strong> {$summaryData['total_employees']}</p>
                <p><strong>Total Working Days:</strong> {$summaryData['working_days']}</p>
                <p><strong>Total Regular Hours:</strong> {$summaryData['total_regular_hours']}</p>
                <p><strong>Total Overtime Hours:</strong> {$summaryData['total_overtime_hours']}</p>
                <p><strong>Average Daily Attendance:</strong> {$summaryData['avg_attendance']}%</p>
            </div>
            
            <div class='footer'>
                <p>Generated on " . date('Y-m-d H:i:s') . "</p>
            </div>
        </body>
        </html>";
        
        return $pdf->generateFromHTML($html, "monthly_report_{$month}_{$year}.pdf");
    }
}


