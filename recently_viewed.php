<?php
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = (int)$_SESSION['user_id'];

$recent_q = $conn->query("
    SELECT * FROM recently_viewed
    WHERE user_id = $user_id
    ORDER BY viewed_at DESC
    LIMIT 15
");

include 'includes/header.php';
?>

<div class="dashboard-layout">
    <aside class="sidebar glass-panel">
        <button class="sidebar-toggle" aria-label="Toggle Menu">
            <span><i class="fas fa-bars" style="margin-right: 0.5rem;"></i> Menu</span>
            <i class="fas fa-chevron-down toggle-icon"></i>
        </button>
        <h3 class="sidebar-title" style="margin-bottom: 2rem;">Menu</h3>
        <ul class="sidebar-menu">
            <li><a href="patient_dashboard.php"><i class="fas fa-home"></i> Overview</a></li>
            <li><a href="recently_viewed.php" class="active"><i class="fas fa-history"></i> Recently Viewed</a></li>
            <li><a href="favorites.php"><i class="fas fa-heart"></i> Favorites</a></li>
            <li><a href="health_vault.php"><i class="fas fa-shield-alt"></i> Health Vault</a></li>
        </ul>
    </aside>

    <main class="dashboard-content">
        <h2><i class="fas fa-history" style="color: var(--primary-color);"></i> Recently Viewed Items</h2>
        <p style="color: var(--text-secondary); margin-bottom: 1.5rem;">Quick history of recently viewed doctor profiles, lab reports, prescriptions, and medicines.</p>

        <div class="glass-panel" style="padding: 1.5rem;">
            <div style="display: flex; flex-direction: column; gap: 0.8rem;">
                <?php if ($recent_q && $recent_q->num_rows > 0): ?>
                    <?php while($r = $recent_q->fetch_assoc()): ?>
                        <div style="display: flex; justify-content: space-between; align-items: center; background: rgba(255,255,255,0.03); border: 1px solid var(--glass-border); padding: 1rem; border-radius: 12px; flex-wrap: wrap; gap: 0.5rem;">
                            <div>
                                <h4 style="margin: 0; font-size: 0.95rem; color: var(--text-primary);"><?php echo htmlspecialchars($r['title'] ?: 'Healthcare Item #' . $r['item_id']); ?></h4>
                                <span style="font-size: 0.75rem; color: var(--secondary-color); font-weight: bold; text-transform: uppercase;"><?php echo htmlspecialchars($r['item_type']); ?></span>
                            </div>
                            <div style="display: flex; gap: 1rem; align-items: center;">
                                <span style="font-size: 0.8rem; color: var(--text-secondary);"><?php echo date('M d, h:i A', strtotime($r['viewed_at'])); ?></span>
                                <?php if (!empty($r['url'])): ?>
                                    <a href="<?php echo htmlspecialchars($r['url']); ?>" class="btn btn-outline" style="font-size: 0.75rem; padding: 0.3rem 0.6rem;">Open</a>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <p style="color: var(--text-secondary); text-align: center; padding: 1.5rem;">No recently viewed records found.</p>
                <?php endif; ?>
            </div>
        </div>
    </main>
</div>

<?php include 'includes/footer.php'; ?>
