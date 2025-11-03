<?php
/**
 * JailTrak Probation System Setup Script
 * Initializes probation officer database tables
 */

require_once __DIR__ . '/../../config/config.php';

echo "========================================\n";
echo "JailTrak Probation System Setup\n";
echo "========================================\n\n";

try {
    $db = new PDO('sqlite:' . __DIR__ . '/../data/jailtrak.db');
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "Reading schema...\n";
    $schema = file_get_contents(__DIR__ . '/schema.sql');
    
    echo "Creating probation tables...\n";
    $db->exec($schema);
    
    echo "✓ Probation tables created successfully!\n\n";
    
    // Check if any officers exist
    $count = $db->query("SELECT COUNT(*) FROM probation_officers")->fetchColumn();
    
    if ($count == 0) {
        echo "No officers registered yet.\n";
        echo "Visit probation/register.php to create your first account.\n\n";
    } else {
        echo "Found $count registered officer(s).\n\n";
    }
    
    echo "Setup complete!\n\n";
    echo "========================================\n";
    echo "Next Steps:\n";
    echo "========================================\n";
    echo "1. Register account: /probation/register.php\n";
    echo "2. Login: /probation/login.php\n";
    echo "3. Add inmates to watchlist\n";
    echo "4. Setup cron: */15 * * * * php " . __DIR__ . "/alert_checker.php\n\n";
    
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
?>