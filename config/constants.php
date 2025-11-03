<?php
/**
 * JailTrak - Application Constants
 */

// Application Info
define('APP_NAME', 'JailTrak');
define('APP_VERSION', '2.0');
define('APP_URL', 'https://docket.turnpage.io');

// Paths
define('ROOT_PATH', dirname(__DIR__));
define('APP_PATH', ROOT_PATH . '/');
define('CONFIG_PATH', ROOT_PATH . '/');
define('DATA_PATH', ROOT_PATH . '/');
define('LOGS_PATH', ROOT_PATH . '/');
define('STORAGE_PATH', ROOT_PATH . '/');
define('SCRAPERS_PATH', ROOT_PATH . '/');

// Database
define('DB_PATH', DATA_PATH . '/jailtrak.db');

// Scraper URLs
define('JAIL_BASE_URL', 'https://weba.claytoncountyga.gov/sjiserver/htdocs/index.shtml');
define('COURT_BASE_URL', 'https://weba.claytoncountyga.gov/casinqcgi-bin/wci201r.pgm');

// Theme Colors
define('THEME_PRIMARY', '#00d4ff');
define('THEME_SECONDARY', '#ff6b00');
define('THEME_SUCCESS', '#00ff88');
define('THEME_DANGER', '#ff4444');
define('THEME_WARNING', '#ffaa00');

// Admin Settings
define('ADMIN_EMAIL', 'admin@jailtrak.com');
define('SCRAPER_DELAY', 500000); // microseconds (0.5 seconds)
define('SCRAPER_TIMEOUT', 30); // seconds
?>