<?php
/**
 * JailTrak Probation Officer Dashboard
 */

session_start();
require_once __DIR__ . '/../../config/config.php';

// Check if logged in
if (!isset($_SESSION['officer_id'])) {
    header('Location: login.php');
    exit;
}

$db = new PDO('sqlite:' . __DIR__ . '/../data/jailtrak.db');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$officerId = $_SESSION['officer_id'];
$message = '';
$error = '';

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        if ($_POST['action'] === 'add_watchlist') {
            $inmateName = trim($_POST['inmate_name']);
            $notes = trim($_POST['notes']);
            
            if (!empty($inmateName)) {
                try {
                    $stmt = $db->prepare("
                        INSERT INTO watchlist (officer_id, inmate_name, notes)
                        VALUES (?, ?, ?)
                    ");
                    $stmt->execute([$officerId, $inmateName, $notes]);
                    $message = "Added to watchlist: $inmateName";
                } catch (PDOException $e) {
                    $error = "Error adding to watchlist: " . $e->getMessage();
                }
            }
        } elseif ($_POST['action'] === 'remove_watchlist') {
            $watchlistId = intval($_POST['watchlist_id']);
            $db->prepare("DELETE FROM watchlist WHERE id = ? AND officer_id = ?")
               ->execute([$watchlistId, $officerId]);
            $message = "Removed from watchlist";
        } elseif ($_POST['action'] === 'mark_read') {
            $alertId = intval($_POST['alert_id']);
            $db->prepare("UPDATE probation_alerts SET read_status = 1 WHERE id = ? AND officer_id = ?")
               ->execute([$alertId, $officerId]);
        }
    }
}

// Get officer info
$stmt = $db->prepare("SELECT * FROM probation_officers WHERE id = ?");
$stmt->execute([$officerId]);
$officer = $stmt->fetch(PDO::FETCH_ASSOC);

// Get watchlist
$stmt = $db->prepare("SELECT * FROM watchlist WHERE officer_id = ? ORDER BY created_at DESC");
$stmt->execute([$officerId]);
$watchlist = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get alerts
$stmt = $db->prepare("
    SELECT * FROM probation_alerts 
    WHERE officer_id = ? 
    ORDER BY created_at DESC 
    LIMIT 50
");
$stmt->execute([$officerId]);
$alerts = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get stats
$unreadAlerts = count(array_filter($alerts, fn($a) => $a['read_status'] == 0));
$totalAlerts = count($alerts);
$watchlistCount = count($watchlist);

// Get recent bookings for watchlist inmates
$recentBookings = [];
foreach ($watchlist as $watch) {
    $stmt = $db->prepare("
        SELECT * FROM inmates 
        WHERE name LIKE ? AND in_jail = 1
        ORDER BY booking_date DESC, booking_time DESC
        LIMIT 5
    ");
    $stmt->execute(['%' . $watch['inmate_name'] . '%']);
    $bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($bookings as $booking) {
        $booking['watchlist_notes'] = $watch['notes'];
        $recentBookings[] = $booking;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Officer Dashboard - JailTrak</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #0a0a1a 0%, #1a1a3e 100%);
            color: #e0e0e0;
            padding: 20px;
        }
        .container { max-width: 1400px; margin: 0 auto; }
        header {
            background: linear-gradient(135deg, #1e1e3f 0%, #2a2a4a 100%);
            padding: 30px;
            border-radius: 15px;
            margin-bottom: 30px;
            border: 1px solid rgba(100,149,237,0.3);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        h1 { color: #6495ed; text-shadow: 0 0 20px rgba(100,149,237,0.5); }
        .officer-info { text-align: right; }
        .officer-info .name { color: #00d4ff; font-weight: bold; }
        .officer-info .badge { color: #a0a0a0; font-size: 0.9em; }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
            margin-bottom: 30px;
        }
        .stat-card {
            background: linear-gradient(135deg, #1e1e3f 0%, #2a2a4a 100%);
            padding: 25px;
            border-radius: 15px;
            border: 1px solid rgba(255,255,255,0.1);
            text-align: center;
        }
        .stat-card .icon { font-size: 2.5em; margin-bottom: 10px; }
        .stat-card .label { color: #a0a0a0; margin-bottom: 10px; }
        .stat-card .value { font-size: 2.5em; font-weight: bold; color: #6495ed; }
        .card {
            background: linear-gradient(135deg, #1e1e3f 0%, #2a2a4a 100%);
            padding: 30px;
            border-radius: 15px;
            margin-bottom: 30px;
            border: 1px solid rgba(255,255,255,0.1);
        }
        .card h2 { color: #6495ed; margin-bottom: 20px; }
        .form-group {
            margin-bottom: 15px;
        }
        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #a0a0a0;
            font-weight: 600;
        }
        .form-group input, .form-group textarea {
            width: 100%;
            padding: 12px;
            border: 2px solid rgba(255,255,255,0.2);
            border-radius: 8px;
            background: #16213e;
            color: #e0e0e0;
            font-size: 1em;
        }
        .form-group input:focus, .form-group textarea:focus {
            outline: none;
            border-color: #6495ed;
        }
        .btn {
            padding: 12px 30px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s;
        }
        .btn-primary {
            background: linear-gradient(135deg, #6495ed 0%, #4169e1 100%);
            color: white;
        }
        .btn-primary:hover { transform: translateY(-2px); }
        .btn-danger { background: #ff4444; color: white; padding: 8px 15px; font-size: 0.9em; }
        .btn-small { padding: 6px 12px; font-size: 0.85em; }
        .watchlist-item, .alert-item {
            background: rgba(100,149,237,0.1);
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 15px;
            border-left: 4px solid #6495ed;
        }
        .alert-item.unread {
            border-left-color: #ffaa00;
            background: rgba(255,170,0,0.1);
        }
        .watchlist-item .name { color: #00d4ff; font-weight: bold; font-size: 1.1em; }
        .watchlist-item .notes { color: #a0a0a0; margin: 10px 0; }
        .alert-item .title { color: #ffaa00; font-weight: bold; margin-bottom: 10px; }
        .alert-item .message { color: #e0e0e0; white-space: pre-line; line-height: 1.6; }
        .alert-item .time { color: #a0a0a0; font-size: 0.85em; margin-top: 10px; }
        .success {
            background: rgba(0,255,136,0.2);
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
            border-left: 4px solid #00ff88;
            color: #00ff88;
        }
        .error {
            background: rgba(255,68,68,0.2);
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
            border-left: 4px solid #ff4444;
            color: #ff6868;
        }
        .logout-btn {
            background: #ff4444;
            color: white;
            padding: 10px 20px;
            border-radius: 8px;
            text-decoration: none;
            display: inline-block;
        }
        .grid-2 {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 30px;
        }
        @media (max-width: 768px) {
            .stats-grid { grid-template-columns: 1fr; }
            .grid-2 { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    <div class="container">
        <header>
            <h1>👮 Probation Officer Dashboard</h1>
            <div class="officer-info">
                <div class="name"><?= htmlspecialchars($officer['name']) ?></div>
                <div class="badge">Badge: <?= htmlspecialchars($officer['badge_number']) ?></div>
                <a href="logout.php" class="logout-btn" style="margin-top: 10px;">Logout</a>
            </div>
        </header>
        
        <?php if ($message): ?>
            <div class="success">✓ <?= htmlspecialchars($message) ?></div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="error">❌ <?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        
        <div class="stats-grid">
            <div class="stat-card">
                <div class="icon">🔔</div>
                <div class="label">Unread Alerts</div>
                <div class="value"><?= $unreadAlerts ?></div>
            </div>
            <div class="stat-card">
                <div class="icon">👁️</div>
                <div class="label">Watchlist</div>
                <div class="value"><?= $watchlistCount ?></div>
            </div>
            <div class="stat-card">
                <div class="icon">📊</div>
                <div class="label">Total Alerts</div>
                <div class="value"><?= $totalAlerts ?></div>
            </div>
        </div>
        
        <div class="grid-2">
            <div>
                <div class="card">
                    <h2>➕ Add to Watchlist</h2>
                    <form method="POST">
                        <input type="hidden" name="action" value="add_watchlist">
                        <div class="form-group">
                            <label>Inmate Name *</label>
                            <input type="text" name="inmate_name" required placeholder="John Doe">
                        </div>
                        <div class="form-group">
                            <label>Notes</label>
                            <textarea name="notes" rows="3" placeholder="Probation conditions, case number, etc."></textarea>
                        </div>
                        <button type="submit" class="btn btn-primary">Add to Watchlist</button>
                    </form>
                </div>
                
                <div class="card">
                    <h2>👁️ Your Watchlist (<?= $watchlistCount ?>)</h2>
                    <?php if (empty($watchlist)): ?>
                        <p style="color: #a0a0a0;">No inmates on watchlist. Add one above.</p>
                    <?php else: ?>
                        <?php foreach ($watchlist as $watch): ?>
                            <div class="watchlist-item">
                                <div class="name"><?= htmlspecialchars($watch['inmate_name']) ?></div>
                                <?php if ($watch['notes']): ?>
                                    <div class="notes"><?= htmlspecialchars($watch['notes']) ?></div>
                                <?php endif; ?>
                                <div style="margin-top: 10px;">
                                    <span style="color: #a0a0a0; font-size: 0.85em;">
                                        Added: <?= date('M j, Y', strtotime($watch['created_at'])) ?>
                                    </span>
                                    <form method="POST" style="display: inline; float: right;">
                                        <input type="hidden" name="action" value="remove_watchlist">
                                        <input type="hidden" name="watchlist_id" value="<?= $watch['id'] ?>">
                                        <button type="submit" class="btn btn-danger btn-small" onclick="return confirm('Remove from watchlist?')">Remove</button>
                                    </form>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
            
            <div>
                <div class="card">
                    <h2>🔔 Recent Alerts (<?= $unreadAlerts ?> unread)</h2>
                    <?php if (empty($alerts)): ?>
                        <p style="color: #a0a0a0;">No alerts yet. Add inmates to your watchlist to receive notifications.</p>
                    <?php else: ?>
                        <?php foreach ($alerts as $alert): ?>
                            <div class="alert-item <?= $alert['read_status'] ? '' : 'unread' ?>">
                                <div class="title"><?= htmlspecialchars($alert['alert_title']) ?></div>
                                <div class="message"><?= htmlspecialchars($alert['alert_message']) ?></div>
                                <div class="time">
                                    <?= date('M j, Y g:i A', strtotime($alert['created_at'])) ?>
                                    <?php if (!$alert['read_status']): ?>
                                        <form method="POST" style="display: inline; float: right;">
                                            <input type="hidden" name="action" value="mark_read">
                                            <input type="hidden" name="alert_id" value="<?= $alert['id'] ?>">
                                            <button type="submit" class="btn btn-primary btn-small">Mark Read</button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</body>
</html>