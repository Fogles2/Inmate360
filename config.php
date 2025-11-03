<?php
/**
 * JailTrak Configuration File
 * Clayton County Jail Dashboard
 */

// Database Configuration
define('DB_PATH', __DIR__ . '/jail_data.db');

// Check if PDO SQLite driver is available
function checkPDOSQLite() {
    if (!extension_loaded('pdo_sqlite')) {
        echo "ERROR: PDO SQLite driver not found!\n\n";
        echo "Please install the SQLite extension:\n";
        echo "Ubuntu/Debian: sudo apt-get install php-sqlite3\n";
        echo "macOS: brew install php\n";
        echo "Windows: Enable extension=pdo_sqlite in php.ini\n\n";
        
        echo "Available PDO drivers:\n";
        print_r(PDO::getAvailableDrivers());
        echo "\n";
        
        return false;
    }
    return true;
}

// Scraper Configuration - Multiple URLs
define('SCRAPE_URLS', [
    '48_hours' => 'https://weba.claytoncountyga.gov/sjiinqcgi-bin/wsj210r.pgm?days=02&rtype=F',
    '14_days' => 'https://weba.claytoncountyga.gov/sjiinqcgi-bin/wsj210r.pgm?days=14&rtype=F',
    '31_days' => 'https://weba.claytoncountyga.gov/sjiinqcgi-bin/wsj210r.pgm?days=31&rtype=F'
]);

define('SCRAPE_INTERVAL', 3600); // 1 hour in seconds
define('SCRAPE_DURATION', 172800); // 48 hours in seconds
define('USER_AGENT', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36');
define('TIMEOUT', 30);

// Site Configuration
define('SITE_URL', 'http://inmate360.com:8000'); // Change this to your actual domain

// Logging
define('LOG_FILE', __DIR__ . '/scraper.log');

// Error Reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', LOG_FILE);

// Timezone
date_default_timezone_set('America/New_York');
?>