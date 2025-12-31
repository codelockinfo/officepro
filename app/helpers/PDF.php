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
    
    public function generateFromHTML($html, $filename = 'document.pdf', $output = 'D') {
        $this->dompdf->loadHtml($html);
        $this->dompdf->setPaper('A4', 'portrait');
        $this->dompdf->render();
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
                table { width: 100%; border-collapse: collapse; margin-top: 20px; }
                th { background-color: #4da6ff; color: white; padding: 8px; text-align: left; }
                td { padding: 6px; border-bottom: 1px solid #ddd; }
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

            <p><strong>Report Period:</strong> {$startDate} to {$endDate}</p>
            <p><strong>Generated:</strong> " . date('Y-m-d H:i:s') . "</p>

            <table>
                <thead>
                    <tr>
                        <th>Employee</th>
                        <th>Date</th>
                        <th>Check In</th>
                        <th>Check Out</th>
                        <th>Lunch Time</th>
                        <th>Regular Hours</th>
                        <th>Overtime Hours</th>
                        <th>Total Hours</th>
                    </tr>
                </thead>
                <tbody>";

        // Helpers
        $parseTimeToSeconds = function($time) {
            if (!$time || $time === '00:00:00') return 0;
            [$h,$m,$s] = explode(':', $time);
            return ($h * 3600) + ($m * 60) + $s;
        };

        $formatSeconds = function($seconds) {
            $h = floor($seconds / 3600);
            $m = floor(($seconds % 3600) / 60);
            $s = $seconds % 60;
            return sprintf('%02d:%02d:%02d', $h, $m, $s);
        };

        $totalLunchSeconds   = 0;
        $totalRegularSeconds = 0;
        $totalOvertimeSeconds = 0;

        foreach ($reportData as $row) {
            $lunchSeconds = $parseTimeToSeconds($row['lunch_time'] ?? '00:00:00');
            $regularSeconds = $parseTimeToSeconds($row['regular_hours']);
            $overtimeSeconds = $parseTimeToSeconds($row['overtime_hours']);

            $totalLunchSeconds   += $lunchSeconds;
            $totalRegularSeconds += $regularSeconds;
            $totalOvertimeSeconds += $overtimeSeconds;

            $html .= "
                <tr>
                    <td>{$row['employee_name']}</td>
                    <td>{$row['date']}</td>
                    <td>{$row['check_in']}</td>
                    <td>{$row['check_out']}</td>
                    <td>{$row['lunch_time']}</td>
                    <td>{$row['regular_hours']}</td>
                    <td class='overtime'>{$row['overtime_hours']}</td>
                    <td>{$row['total_hours']}</td>
                </tr>";
        }

        $grandTotalSeconds = $totalRegularSeconds + $totalOvertimeSeconds;

        $html .= "
            <tr style='font-weight:bold;background:#e6f2ff'>
                <td colspan='4'>TOTAL</td>
                <td>{$formatSeconds($totalLunchSeconds)}</td>
                <td>{$formatSeconds($totalRegularSeconds)}</td>
                <td class='overtime'>{$formatSeconds($totalOvertimeSeconds)}</td>
                <td>{$formatSeconds($grandTotalSeconds)}</td>
            </tr>
            </tbody>
            </table>

            <div class='footer'>
                <p>This is a computer-generated report.</p>
            </div>
        </body>
        </html>";

        return $pdf->generateFromHTML(
            $html,
            "attendance_report_{$startDate}_{$endDate}.pdf"
        );
    }
}
