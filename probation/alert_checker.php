<?php
/**
 * JailTrak Probation Alert Checker
 * Background script to check for probation violations
 * Run via cron: */15 * * * * php /path/to/probation/alert_checker.php
 */

require_once __DIR__ . '/../../config/config.php';

class AlertChecker {
    private $db;
    
    public function __construct() {
        $this->db = new PDO('sqlite:' . __DIR__ . '/../data/jailtrak.db');
        $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }
    
    private function log($message) {
        $timestamp = date('Y-m-d H:i:s');
        echo "[$timestamp] $message\n";
    }
    
    public function checkAlerts() {
        $this->log("Starting alert check...");
        
        // Get all watchlist entries with officer info
        $stmt = $this->db->query("
            SELECT w.*, po.id as officer_id, po.name as officer_name, 
                   po.email as officer_email, po.email_notifications
            FROM watchlist w
            JOIN probation_officers po ON w.officer_id = po.id
        ");
        $watchlist = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $this->log("Found " . count($watchlist) . " watchlist entries");
        
        $alertCount = 0;
        
        foreach ($watchlist as $watch) {
            $inmateName = $watch['inmate_name'];
            
            // Check if inmate is currently in jail
            $stmt = $this->db->prepare("
                SELECT * FROM inmates 
                WHERE name LIKE ? AND in_jail = 1
            ");
            $stmt->execute(["%$inmateName%"]);
            $inmates = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($inmates as $inmate) {
                // Check if we've already alerted for this booking
                $stmt = $this->db->prepare("
                    SELECT id FROM probation_alerts
                    WHERE officer_id = ? AND inmate_id = ?
                    AND created_at >= datetime('now', '-24 hours')
                ");
                $stmt->execute([$watch['officer_id'], $inmate['inmate_id']]);
                
                if (!$stmt->fetch()) {
                    // Create new alert
                    $this->createAlert($watch, $inmate);
                    $alertCount++;
                }
            }
        }
        
        $this->log("Created $alertCount new alerts");
        $this->log("Alert check complete");
    }
    
    private function createAlert($watch, $inmate) {
        // Get charges for the inmate
        $stmt = $this->db->prepare("
            SELECT charge_description FROM charges WHERE inmate_id = ?
        ");
        $stmt->execute([$inmate['inmate_id']]);
        $charges = $stmt->fetchAll(PDO::FETCH_COLUMN);
        $chargesList = implode(', ', $charges);
        
        $alertTitle = "⚠️ Watchlist Alert: {$inmate['name']}";
        $alertMessage = "Your watchlist inmate {$inmate['name']} has been booked.\n\n";
        $alertMessage .= "Booking Date: {$inmate['booking_date']} {$inmate['booking_time']}\n";
        $alertMessage .= "Charges: $chargesList\n";
        $alertMessage .= "Bond Amount: {$inmate['bond_amount']}\n";
        
        // Insert alert
        $stmt = $this->db->prepare("
            INSERT INTO probation_alerts 
            (officer_id, inmate_id, inmate_name, alert_title, alert_message, created_at)
            VALUES (?, ?, ?, ?, ?, CURRENT_TIMESTAMP)
        ");
        $stmt->execute([
            $watch['officer_id'],
            $inmate['inmate_id'],
            $inmate['name'],
            $alertTitle,
            $alertMessage
        ]);
        
        $alertId = $this->db->lastInsertId();
        
        $this->log("Created alert #{$alertId} for officer {$watch['officer_name']}");
        
        // Send email if notifications enabled
        if ($watch['email_notifications']) {
            $this->sendEmail($watch['officer_email'], $watch['officer_name'], $alertTitle, $alertMessage);
            
            // Mark email as sent
            $this->db->prepare("UPDATE probation_alerts SET email_sent = 1 WHERE id = ?")
                    ->execute([$alertId]);
        }
    }
    
    private function sendEmail($to, $name, $subject, $message) {
        $headers = "From: JailTrak Alerts <noreply@jailtrak.com>\r\n";
        $headers .= "Reply-To: noreply@jailtrak.com\r\n";
        $headers .= "X-Mailer: PHP/" . phpversion();
        
        $fullMessage = "Hello Officer $name,\n\n";
        $fullMessage .= $message;
        $fullMessage .= "\n\n---\n";
        $fullMessage .= "Login to view more details: " . SITE_URL . "/probation/dashboard.php\n";
        $fullMessage .= "This is an automated alert from JailTrak.\n";
        
        if (mail($to, $subject, $fullMessage, $headers)) {
            $this->log("Email sent to $to");
        } else {
            $this->log("Failed to send email to $to");
        }
    }
}

// Run the checker
if (php_sapi_name() === 'cli') {
    try {
        $checker = new AlertChecker();
        $checker->checkAlerts();
    } catch (Exception $e) {
        echo "Error: " . $e->getMessage() . "\n";
        exit(1);
    }
} else {
    echo "This script must be run from command line.\n";
}
?>