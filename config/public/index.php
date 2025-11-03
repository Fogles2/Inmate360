<?php
/**
 * JailTrak - Main Entry Point (New Structure)
 * Modern dashboard with new dark theme
 */

session_start();
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/invite_gate.php';

checkInviteAccess();

// Initialize database
try {
    $db = new PDO('sqlite:' . DB_PATH);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

// Pagination
$perPage = 30;
$currentPage = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($currentPage - 1) * $perPage;

// Get statistics
$stats = [
    'total' => $db->query("SELECT COUNT(*) FROM inmates")->fetchColumn(),
    'male' => $db->query("SELECT COUNT(*) FROM inmates WHERE sex = 'M'")->fetchColumn(),
    'female' => $db->query("SELECT COUNT(*) FROM inmates WHERE sex = 'F'")->fetchColumn(),
    'in_jail' => $db->query("SELECT COUNT(*) FROM inmates WHERE in_jail = 1")->fetchColumn(),
    'released' => $db->query("SELECT COUNT(*) FROM inmates WHERE in_jail = 0")->fetchColumn(),
    'felonies' => $db->query("SELECT COUNT(DISTINCT inmate_id) FROM charges WHERE charge_type = 'Felony'")->fetchColumn(),
    'misdemeanors' => $db->query("SELECT COUNT(DISTINCT inmate_id) FROM charges WHERE charge_type = 'Misdemeanor'")->fetchColumn(),
    'last_update' => $db->query("SELECT MAX(scrape_time) FROM scrape_logs WHERE status = 'success'")->fetchColumn()
];

// Get filters
$filter = $_GET['filter'] ?? 'all';
$search = $_GET['search'] ?? '';

// Build query
$whereConditions = ['1=1'];
$params = [];

if (!empty($search)) {
    $whereConditions[] = "(i.name LIKE ? OR c.charge_description LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

// Apply filters
switch ($filter) {
    case 'in_jail':
        $whereConditions[] = "i.in_jail = 1";
        break;
    case 'released':
        $whereConditions[] = "i.in_jail = 0";
        break;
    case 'male':
        $whereConditions[] = "i.sex = 'M'";
        break;
    case 'female':
        $whereConditions[] = "i.sex = 'F'";
        break;
    case 'felony':
        // Will filter after grouping
        break;
    case 'misdemeanor':
        // Will filter after grouping
        break;
}

$whereClause = implode(' AND ', $whereConditions);

// Count query
$countQuery = "SELECT COUNT(DISTINCT i.id) FROM inmates i LEFT JOIN charges c ON i.inmate_id = c.inmate_id WHERE $whereClause";
$stmt = $db->prepare($countQuery);
$stmt->execute($params);
$totalInmates = $stmt->fetchColumn();
$totalPages = ceil($totalInmates / $perPage);

// Main query
$query = "
    SELECT 
        i.*,
        GROUP_CONCAT(c.charge_description, '; ') as charges,
        GROUP_CONCAT(c.charge_type) as charge_types
    FROM inmates i
    LEFT JOIN charges c ON i.inmate_id = c.inmate_id
    WHERE $whereClause
    GROUP BY i.id
";

// Apply post-grouping filters
if ($filter === 'felony') {
    $query .= " HAVING charge_types LIKE '%Felony%'";
} elseif ($filter === 'misdemeanor') {
    $query .= " HAVING charge_types LIKE '%Misdemeanor%'";
}

$query .= " ORDER BY i.booking_date DESC, i.booking_time DESC LIMIT ? OFFSET ?";

$params[] = $perPage;
$params[] = $offset;

$stmt = $db->prepare($query);
$stmt->execute($params);
$inmates = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get crime statistics
$crimeStats = $db->query("
    SELECT 
        charge_description,
        COUNT(*) as count,
        charge_type
    FROM charges
    GROUP BY charge_description
    ORDER BY count DESC
    LIMIT 10
")->fetchAll(PDO::FETCH_ASSOC);

$startRecord = $offset + 1;
$endRecord = min($offset + $perPage, $totalInmates);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= APP_NAME ?> - Inmate Dashboard</title>
    <link rel="stylesheet" href="/css/theme-dark.css">
    <style>
        .content-grid {
            display: grid;
            grid-template-columns: 2.5fr 1fr;
            gap: 24px;
            margin-bottom: 24px;
        }
        
        .filters {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
            margin-bottom: 24px;
            padding: 16px;
            background: var(--bg-secondary);
            border: 1px solid var(--border-default);
            border-radius: var(--radius-lg);
        }
        
        .search-box {
            flex: 1;
            min-width: 250px;
        }
        
        .filter-buttons {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }
        
        .charge-type-badge {
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .sidebar-card {
            background: var(--bg-secondary);
            border: 1px solid var(--border-default);
            border-radius: var(--radius-lg);
            padding: 20px;
            margin-bottom: 20px;
        }
        
        .sidebar-title {
            font-size: 14px;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 16px;
            padding-bottom: 12px;
            border-bottom: 1px solid var(--border-default);
        }
        
        .stat-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px;
            margin-bottom: 8px;
            background: var(--bg-tertiary);
            border-radius: var(--radius-sm);
        }
        
        .stat-label {
            font-size: 12px;
            color: var(--text-secondary);
        }
        
        .stat-count {
            font-size: 14px;
            font-weight: 600;
            color: var(--text-primary);
        }
        
        @media (max-width: 1200px) {
            .content-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <?php include __DIR__ . '/../app/views/layouts/navbar.php'; ?>
        
        <header>
            <h1>
                <span>👮</span>
                Inmate Dashboard
            </h1>
            <p class="subtitle">Real-time inmate tracking - Clayton County Jail</p>
            <?php if ($stats['last_update']): ?>
                <p style="color: var(--text-tertiary); font-size: 12px; margin-top: 8px;">
                    Last updated: <?= date('F j, Y g:i A', strtotime($stats['last_update'])) ?>
                </p>
            <?php endif; ?>
        </header>
        
        <!-- Statistics -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon">👥</div>
                <div class="stat-label">Total Inmates</div>
                <div class="stat-value"><?= number_format($stats['total']) ?></div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">🔒</div>
                <div class="stat-label">Currently In Jail</div>
                <div class="stat-value"><?= number_format($stats['in_jail']) ?></div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">✅</div>
                <div class="stat-label">Released</div>
                <div class="stat-value"><?= number_format($stats['released']) ?></div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">👨</div>
                <div class="stat-label">Male</div>
                <div class="stat-value"><?= number_format($stats['male']) ?></div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">👩</div>
                <div class="stat-label">Female</div>
                <div class="stat-value"><?= number_format($stats['female']) ?></div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">⚠️</div>
                <div class="stat-label">Felonies</div>
                <div class="stat-value"><?= number_format($stats['felonies']) ?></div>
            </div>
        </div>
        
        <!-- Filters -->
        <div class="filters">
            <div class="search-box">
                <form method="GET" style="margin: 0;">
                    <input type="text" name="search" placeholder="🔍 Search by name or charge..." 
                           value="<?= htmlspecialchars($search) ?>" style="margin: 0;">
                    <input type="hidden" name="filter" value="<?= htmlspecialchars($filter) ?>">
                </form>
            </div>
            
            <div class="filter-buttons">
                <a href="?filter=all&search=<?= urlencode($search) ?>" 
                   class="btn <?= $filter === 'all' ? 'btn-primary' : 'btn-secondary' ?>">
                    All
                </a>
                <a href="?filter=in_jail&search=<?= urlencode($search) ?>" 
                   class="btn <?= $filter === 'in_jail' ? 'btn-primary' : 'btn-secondary' ?>">
                    In Jail
                </a>
                <a href="?filter=released&search=<?= urlencode($search) ?>" 
                   class="btn <?= $filter === 'released' ? 'btn-primary' : 'btn-secondary' ?>">
                    Released
                </a>
                <a href="?filter=male&search=<?= urlencode($search) ?>" 
                   class="btn <?= $filter === 'male' ? 'btn-primary' : 'btn-secondary' ?>">
                    Male
                </a>
                <a href="?filter=female&search=<?= urlencode($search) ?>" 
                   class="btn <?= $filter === 'female' ? 'btn-primary' : 'btn-secondary' ?>">
                    Female
                </a>
                <a href="?filter=felony&search=<?= urlencode($search) ?>" 
                   class="btn <?= $filter === 'felony' ? 'btn-primary' : 'btn-secondary' ?>">
                    Felonies
                </a>
            </div>
        </div>
        
        <!-- Results Info -->
        <?php if ($totalInmates > 0): ?>
        <div class="card" style="padding: 12px 20px; margin-bottom: 16px;">
            <div style="display: flex; justify-content: space-between; align-items: center;">
                <span style="color: var(--text-secondary); font-size: 13px;">
                    Showing <strong style="color: var(--text-primary);"><?= $startRecord ?>-<?= $endRecord ?></strong> 
                    of <strong style="color: var(--text-primary);"><?= number_format($totalInmates) ?></strong> inmates
                </span>
                <span style="color: var(--text-tertiary); font-size: 12px;">
                    Page <?= $currentPage ?> of <?= $totalPages ?>
                </span>
            </div>
        </div>
        <?php endif; ?>
        
        <div class="content-grid">
            <!-- Main Table -->
            <div>
                <div class="card" style="padding: 0; overflow: hidden;">
                    <?php if (count($inmates) > 0): ?>
                        <table>
                            <thead>
                                <tr>
                                    <th>Inmate ID</th>
                                    <th>Name</th>
                                    <th>Age</th>
                                    <th>Booking Date</th>
                                    <th>Status</th>
                                    <th>Charges</th>
                                    <th>Bond</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($inmates as $inmate): ?>
                                    <tr>
                                        <td>
                                            <a href="/inmate_view.php?id=<?= $inmate['id'] ?>" style="color: var(--text-link); font-weight: 600;">
                                                <?= htmlspecialchars($inmate['inmate_id']) ?>
                                            </a>
                                        </td>
                                        <td>
                                            <strong><?= htmlspecialchars($inmate['name']) ?></strong>
                                        </td>
                                        <td><?= htmlspecialchars($inmate['age']) ?></td>
                                        <td>
                                            <?= date('m/d/Y', strtotime($inmate['booking_date'])) ?>
                                            <?php if ($inmate['booking_time']): ?>
                                                <br><small style="color: var(--text-tertiary);"><?= htmlspecialchars($inmate['booking_time']) ?></small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($inmate['in_jail']): ?>
                                                <span class="badge badge-danger">🔒 In Jail</span>
                                            <?php else: ?>
                                                <span class="badge badge-success">✓ Released</span>
                                            <?php endif; ?>
                                        </td>
                                        <td style="font-size: 12px; max-width: 200px;">
                                            <?php 
                                            if ($inmate['charges']) {
                                                $chargesList = explode('; ', $inmate['charges']);
                                                echo htmlspecialchars(substr($chargesList[0], 0, 50));
                                                if (count($chargesList) > 1) {
                                                    echo '... <span class="badge badge-info">+' . (count($chargesList) - 1) . '</span>';
                                                }
                                            } else {
                                                echo '<span style="color: var(--text-tertiary);">No charges listed</span>';
                                            }
                                            ?>
                                        </td>
                                        <td style="color: var(--color-success); font-weight: 600;">
                                            <?= htmlspecialchars($inmate['bond_amount'] ?: 'N/A') ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <div style="padding: 60px 20px; text-align: center;">
                            <div style="font-size: 48px; margin-bottom: 16px;">🔍</div>
                            <h3 style="color: var(--text-primary); margin-bottom: 8px;">No inmates found</h3>
                            <p style="color: var(--text-secondary);">Try adjusting your search or filter criteria</p>
                        </div>
                    <?php endif; ?>
                </div>
                
                <!-- Pagination -->
                <?php if ($totalPages > 1): ?>
                <div class="pagination">
                    <?php
                    $queryParams = ['filter' => $filter];
                    if ($search) $queryParams['search'] = $search;
                    
                    if ($currentPage > 1): ?>
                        <a href="?<?= http_build_query(array_merge($queryParams, ['page' => 1])) ?>">« First</a>
                        <a href="?<?= http_build_query(array_merge($queryParams, ['page' => $currentPage - 1])) ?>">‹ Prev</a>
                    <?php else: ?>
                        <span class="pagination-disabled">« First</span>
                        <span class="pagination-disabled">‹ Prev</span>
                    <?php endif; ?>
                    
                    <?php
                    $startPage = max(1, $currentPage - 2);
                    $endPage = min($totalPages, $currentPage + 2);
                    
                    for ($i = $startPage; $i <= $endPage; $i++): 
                        if ($i == $currentPage): ?>
                            <span class="current"><?= $i ?></span>
                        <?php else: ?>
                            <a href="?<?= http_build_query(array_merge($queryParams, ['page' => $i])) ?>"><?= $i ?></a>
                        <?php endif;
                    endfor;
                    ?>
                    
                    <?php if ($currentPage < $totalPages): ?>
                        <a href="?<?= http_build_query(array_merge($queryParams, ['page' => $currentPage + 1])) ?>">Next ›</a>
                        <a href="?<?= http_build_query(array_merge($queryParams, ['page' => $totalPages])) ?>">Last »</a>
                    <?php else: ?>
                        <span class="pagination-disabled">Next ›</span>
                        <span class="pagination-disabled">Last »</span>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
            
            <!-- Sidebar -->
            <div>
                <div class="sidebar-card">
                    <div class="sidebar-title">📊 Top Offenses</div>
                    <?php foreach ($crimeStats as $crime): ?>
                        <div class="stat-item">
                            <span class="stat-label"><?= htmlspecialchars(substr($crime['charge_description'], 0, 30)) ?></span>
                            <span class="stat-count"><?= $crime['count'] ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <div class="sidebar-card">
                    <div class="sidebar-title">🔗 Quick Actions</div>
                    <div style="display: grid; gap: 8px;">
                        <a href="/recidivism_dashboard.php" class="btn btn-secondary" style="width: 100%; text-align: center;">
                            🔴 Risk Analysis
                        </a>
                        <a href="/court_dashboard.php" class="btn btn-secondary" style="width: 100%; text-align: center;">
                            ⚖️ Court Cases
                        </a>
                        <a href="/probation/case_manager.php" class="btn btn-secondary" style="width: 100%; text-align: center;">
                            📋 Case Manager
                        </a>
                        <a href="/app/views/admin/dashboard.php" class="btn btn-primary" style="width: 100%; text-align: center;">
                            ⚙️ Admin Dashboard
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>