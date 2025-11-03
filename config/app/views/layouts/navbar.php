<?php
$currentPage = basename($_SERVER['PHP_SELF']);
?>
<style>
    .navbar {
        background: var(--bg-secondary);
        border: 1px solid var(--border-default);
        border-radius: var(--radius-lg);
        margin-bottom: 24px;
        overflow: hidden;
    }
    
    .navbar-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 16px 24px;
        border-bottom: 1px solid var(--border-default);
    }
    
    .navbar-brand {
        display: flex;
        align-items: center;
        gap: 12px;
        text-decoration: none;
        color: var(--text-primary);
        font-weight: 600;
        font-size: 18px;
    }
    
    .navbar-menu {
        display: flex;
        list-style: none;
        margin: 0;
        padding: 0;
    }
    
    .navbar-menu li {
        flex: 1;
    }
    
    .navbar-menu a {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        padding: 14px 20px;
        color: var(--text-secondary);
        text-decoration: none;
        font-size: 14px;
        font-weight: 500;
        transition: var(--transition-fast);
        border-right: 1px solid var(--border-muted);
    }
    
    .navbar-menu li:last-child a {
        border-right: none;
    }
    
    .navbar-menu a:hover {
        background: var(--bg-hover);
        color: var(--text-primary);
    }
    
    .navbar-menu a.active {
        background: var(--color-primary);
        color: #0d1117;
    }
    
    @media (max-width: 1024px) {
        .navbar-menu {
            flex-direction: column;
        }
        
        .navbar-menu a {
            border-right: none;
            border-bottom: 1px solid var(--border-muted);
            justify-content: flex-start;
        }
    }
</style>

<nav class="navbar">
    <div class="navbar-header">
        <a href="/" class="navbar-brand">
            <span style="font-size: 24px;">🏛️</span>
            <span><?= APP_NAME ?></span>
        </a>
        <span class="badge badge-info">v<?= APP_VERSION ?></span>
    </div>
    
    <ul class="navbar-menu">
        <li>
            <a href="/" class="<?= $currentPage == 'index.php' ? 'active' : '' ?>">
                <span>🏠</span> Dashboard
            </a>
        </li>
        
        <li>
            <a href="/court_dashboard.php" class="<?= $currentPage == 'court_dashboard.php' ? 'active' : '' ?>">
                <span>⚖️</span> Court Cases
            </a>
        </li>
        
        <li>
            <a href="/recidivism_dashboard.php" class="<?= $currentPage == 'recidivism_dashboard.php' ? 'active' : '' ?>">
                <span>🔴</span> Risk Analysis
            </a>
        </li>
        
        <li>
            <a href="/probation/case_manager.php" class="<?= $currentPage == 'case_manager.php' ? 'active' : '' ?>">
                <span>📋</span> Probation
            </a>
        </li>
        
        <li>
            <a href="/app/views/admin/dashboard.php" class="<?= $currentPage == 'dashboard.php' && strpos($_SERVER['REQUEST_URI'], 'admin') !== false ? 'active' : '' ?>">
                <span>⚙️</span> Admin
            </a>
        </li>
        
        <li>
            <a href="/about.php">
                <span>ℹ️</span> About
            </a>
        </li>
    </ul>
</nav>