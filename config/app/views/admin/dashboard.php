<?php
/**
 * JailTrak - Admin Dashboard
 * Central control panel with scraper management
 */

session_start();
require_once __DIR__ . '/../../../config/constants.php';
require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../invite_gate.php';

checkInviteAccess();

$db = new PDO('sqlite:' . DB_PATH);
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Handle scraper actions
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'run_jail_scraper':
            $message = runJailScraper();
            break;
        case 'run_court_scraper':
            $message = runCourtScraper();
            break;
        case 'clear_logs':
            $message = clearLogs();
            break;
        case 'backup_database':
            $message = backupDatabase();
            break;
    }
}

function runJailScraper() {
    $output = [];
    $cmd = 'php ' . SCRAPERS_PATH . '/InmateScraper.php > ' . LOGS_PATH . '/jail_scraper.log 2>&1 &';
    exec($cmd, $output, $returnCode);
    
    if ($returnCode === 0) {
        return '✓ Jail scraper started successfully';
    } else {
        return '✗ Error starting jail scraper';
    }
}

function runCourtScraper() {
    $output = [];
    $cmd = 'php ' . SCRAPERS_PATH . '/CourtScraper.php > ' . LOGS_PATH . '/court_scraper.log 2>&1 &';
    exec($cmd, $output, $returnCode);
    
    if ($returnCode === 0) {
        return '✓ Court scraper started successfully';
    } else {
        return '✗ Error starting court scraper';
    }
}

function clearLogs() {
    $logFiles = glob(LOGS_PATH . '/*.log');
    foreach ($logFiles as $file) {
        file_put_contents($file, '');
    }
    return '✓ Logs cleared successfully';
}

function backupDatabase() {
    $backupFile = STORAGE_PATH . '/backups/jailtrak_' . date('Y-m-d_H-i-s') . '.db';
    if (!is_dir(dirname($backupFile))) {
        mkdir(dirname($backupFile), 0755, true);
    }
    
    if (copy(DB_PATH, $backupFile)) {
        return '✓ Database backed up: ' . basename($backupFile);
    } else {
        return '✗ Error backing up database';
    }
}

// Get system statistics
$stats = [
    'total_inmates' => $db->query("SELECT COUNT(*) FROM inmates")->fetchColumn(),
    'total_cases' => $db->query("SELECT COUNT(*) FROM court_cases")->fetchColumn(),
    'total_charges' => $db->query("SELECT COUNT(*) FROM charges")->fetchColumn(),
    'active_inmates' => $db->query("SELECT COUNT(*) FROM inmates WHERE in_jail = 1")->fetchColumn(),
    'last_jail_scrape' => $db->query("SELECT MAX(scrape_time) FROM scrape_logs WHERE status = 'success'")->fetchColumn(),
    'last_court_scrape' => $db->query("SELECT MAX(scrape_time) FROM court_scrape_logs WHERE status = 'success'")->fetchColumn()
];

// Get recent scrape logs
$recentLogs = $db->query("
    SELECT * FROM scrape_logs 
    ORDER BY scrape_time DESC 
    LIMIT 10
")->fetchAll(PDO::FETCH_ASSOC);

// Check disk usage
$dbSize = filesize(DB_PATH);
$diskFree = disk_free_space(DATA_PATH);
$diskTotal = disk_total_space(DATA_PATH);
$diskUsedPercent = (($diskTotal - $diskFree) / $diskTotal) * 100;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - <?= APP_NAME ?></title>
    <link rel="stylesheet" href="/css/theme-dark.css">
    <style>
        .admin-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 24px;
        }
        
        .scraper-controls {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 16px;
        }
        
        .scraper-card {
            background: var(--bg-tertiary);
            border: 2px solid var(--border-default);
            border-radius: var(--radius-lg);
            padding: 24px;
            text-align: center;
            transition: var(--transition-base);
        }
        
        .scraper-card:hover {
            border-color: var(--color-primary);
            transform: translateY(-4px);
            box-shadow: var(--shadow-glow);
        }
        
        .scraper-icon {
            font-size: 48px;
            margin-bottom: 16px;
        }
        
        .scraper-title {
            font-size: 18px;
            font-weight: 600;
            margin-bottom: 8px;
            color: var(--text-primary);
        }
        
        .scraper-status {
            font-size: 12px;
            color: var(--text-secondary);
            margin-bottom: 16px;
        }
        
        .run-scraper-btn {
            width: 100%;
            padding: 12px;
            font-size: 14px;
            font-weight: 600;
        }
        
        .log-viewer {
            background: var(--bg-accent);
            border: 1px solid var(--border-default);
            border-radius: var(--radius-md);
            padding: 16px;
            max-height: 300px;
            overflow-y: auto;
            font-family: 'Consolas', 'Monaco', monospace;
            font-size: 12px;
            line-height: 1.6;
        }
        
        .log-entry {
            margin-bottom: 8px;
            color: var(--text-secondary);
        }
        
        .log-entry .timestamp {
            color: var(--color-info);
        }
        
        .log-entry.success {
            color: var(--color-success);
        }
        
        .log-entry.error {
            color: var(--color-danger);
        }
        
        .alert {
            padding: 16px;
            border-radius: var(--radius-md);
            margin-bottom: 24px;
            border-left: 4px solid;
        }
        
        .alert-success {
            background: rgba(63, 185, 80, 0.1);
            border-color: var(--color-success);
            color: var(--color-success);
        }
        
        .alert-error {
            background: rgba(248, 81, 73, 0.1);
            border-color: var(--color-danger);
            color: var(--color-danger);
        }
        
        .system-info {
            display: grid;
            gap: 12px;
        }
        
        .info-row {
            display: flex;
            justify-content: space-between;
            padding: 12px;
            background: var(--bg-tertiary);
            border-radius: var(--radius-sm);
        }
        
        .info-label {
            color: var(--text-secondary);
            font-size: 12px;
        }
        
        .info-value {
            color: var(--text-primary);
            font-weight: 600;
        }
        
        .progress-bar {
            width: 100%;
            height: 8px;
            background: var(--bg-tertiary);
            border-radius: 4px;
            overflow: hidden;
            margin-top: 8px;
        }
        
        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, var(--color-success), var(--color-primary));
            transition: width 0.3s ease;
        }
        
        @media (max-width: 1024px) {
            .admin-grid {
                grid-template-columns: 1fr;
            }
            
            .scraper-controls {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <?php include __DIR__ . '/../layouts/navbar.php'; ?>
        
        <header>
            <h1>
                <span>⚙️</span>
                Admin Dashboard
            </h1>
            <p class="subtitle">System management and scraper controls</p>
        </header>
        
        <?php if ($message): ?>
            <div class="alert <?= strpos($message, '✓') !== false ? 'alert-success' : 'alert-error' ?>">
                <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>
        
        <!-- System Stats -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon">👥</div>
                <div class="stat-label">Total Inmates</div>
                <div class="stat-value"><?= number_format($stats['total_inmates']) ?></div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">⚖️</div>
                <div class="stat-label">Court Cases</div>
                <div class="stat-value"><?= number_format($stats['total_cases']) ?></div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">📋</div>
                <div class="stat-label">Total Charges</div>
                <div class="stat-value"><?= number_format($stats['total_charges']) ?></div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">🔒</div>
                <div class="stat-label">Currently Incarcerated</div>
                <div class="stat-value"><?= number_format($stats['active_inmates']) ?></div>
            </div>
        </div>
        
        <div class="admin-grid">
            <!-- Left Column -->
            <div>
                <!-- Scraper Controls -->
                <div class="card">
                    <div class="card-header">
                        <h2 class="card-title">🤖 Scraper Controls</h2>
                    </div>
                    
                    <div class="scraper-controls">
                        <!-- Jail Scraper -->
                        <div class="scraper-card">
                            <div class="scraper-icon">👮</div>
                            <div class="scraper-title">Jail Scraper</div>
                            <div class="scraper-status">
                                Last run: <?= $stats['last_jail_scrape'] ? date('M j, g:i A', strtotime($stats['last_jail_scrape'])) : 'Never' ?>
                            </div>
                            <form method="POST">
                                <input type="hidden" name="action" value="run_jail_scraper">
                                <button type="submit" class="btn btn-primary run-scraper-btn">
                                    ▶️ Run Jail Scraper
                                </button>
                            </form>
                        </div>
                        
                        <!-- Court Scraper -->
                        <div class="scraper-card">
                            <div class="scraper-icon">⚖️</div>
                            <div class="scraper-title">Court Scraper</div>
                            <div class="scraper-status">
                                Last run: <?= $stats['last_court_scrape'] ? date('M j, g:i A', strtotime($stats['last_court_scrape'])) : 'Never' ?>
                            </div>
                            <form method="POST">
                                <input type="hidden" name="action" value="run_court_scraper">
                                <button type="submit" class="btn btn-primary run-scraper-btn">
                                    ▶️ Run Court Scraper
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
                
                <!-- Recent Scrape Logs -->
                <div class="card">
                    <div class="card-header">
                        <h2 class="card-title">📋 Recent Scrape Logs</h2>
                        <form method="POST" style="margin: 0;">
                            <input type="hidden" name="action" value="clear_logs">
                            <button type="submit" class="btn btn-secondary">Clear Logs</button>
                        </form>
                    </div>
                    
                    <div class="log-viewer">
                        <?php foreach ($recentLogs as $log): ?>
                            <div class="log-entry <?= $log['status'] ?>">
                                <span class="timestamp">[<?= date('Y-m-d H:i:s', strtotime($log['scrape_time'])) ?>]</span>
                                <?= htmlspecialchars($log['message']) ?>
                                (<?= $log['inmates_found'] ?> inmates)
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                
                <!-- System Actions -->
                <div class="card">
                    <div class="card-header">
                        <h2 class="card-title">🛠️ System Actions</h2>
                    </div>
                    
                    <div style="display: grid; gap: 12px;">
                        <form method="POST">
                            <input type="hidden" name="action" value="backup_database">
                            <button type="submit" class="btn btn-success" style="width: 100%;">
                                💾 Backup Database
                            </button>
                        </form>
                        
                        <a href="/scrapers/view_logs.php" class="btn btn-secondary" style="width: 100%; text-align: center;">
                            📄 View Full Logs
                        </a>
                        
                        <a href="/admin/phpinfo.php" class="btn btn-secondary" style="width: 100%; text-align: center;">
                            ℹ️ PHP Info
                        </a>
                        
                        <a href="/admin/database_manager.php" class="btn btn-secondary" style="width: 100%; text-align: center;">
                            🗄️ Database Manager
                        </a>
                    </div>
                </div>
            </div>
            
            <!-- Right Column -->
            <div>
                <!-- System Information -->
                <div class="card">
                    <div class="card-header">
                        <h2 class="card-title">💻 System Information</h2>
                    </div>
                    
                    <div class="system-info">
                        <div class="info-row">
                            <span class="info-label">App Version</span>
                            <span class="info-value"><?= APP_VERSION ?></span>
                        </div>
                        
                        <div class="info-row">
                            <span class="info-label">PHP Version</span>
                            <span class="info-value"><?= phpversion() ?></span>
                        </div>
                        
                        <div class="info-row">
                            <span class="info-label">Database Size</span>
                            <span class="info-value"><?= round($dbSize / 1024 / 1024, 2) ?> MB</span>
                        </div>
                        
                        <div class="info-row">
                            <span class="info-label">Server