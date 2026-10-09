<?php
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = (int)$_SESSION['user_id'];
$user_role = $_SESSION['role'] ?? 'patient';

// Dynamic WhatsApp Support Status
$wa_settings = get_whatsapp_support_settings($conn);
$is_wa_online = is_whatsapp_support_available($conn);
$wa_number = $wa_settings['whatsapp_number'];
$wa_url = build_whatsapp_url($wa_number, "Hello, I need help with my healthcare account.");

// Fetch Customer Tickets Summary
$cnt_open = (int)$conn->query("SELECT COUNT(*) as cnt FROM support_tickets WHERE customer_id = $user_id AND status = 'open'")->fetch_assoc()['cnt'];
$cnt_in_prog = (int)$conn->query("SELECT COUNT(*) as cnt FROM support_tickets WHERE customer_id = $user_id AND status = 'in_progress'")->fetch_assoc()['cnt'];
$cnt_waiting = (int)$conn->query("SELECT COUNT(*) as cnt FROM support_tickets WHERE customer_id = $user_id AND status = 'waiting_customer'")->fetch_assoc()['cnt'];
$cnt_resolved = (int)$conn->query("SELECT COUNT(*) as cnt FROM support_tickets WHERE customer_id = $user_id AND status IN ('resolved', 'closed')")->fetch_assoc()['cnt'];

// Fetch Recent Tickets for this Customer
$recent_tickets = $conn->query("
    SELECT * FROM support_tickets 
    WHERE customer_id = $user_id 
    ORDER BY updated_at DESC LIMIT 5
");

include 'includes/header.php';
?>

<div class="dashboard-layout">
    <aside class="sidebar glass-panel">
        <button class="sidebar-toggle" aria-label="Toggle Menu">
            <span><i class="fas fa-bars" style="margin-right: 0.5rem;"></i> Menu</span>
            <i class="fas fa-chevron-down toggle-icon"></i>
        </button>
        <h3 class="sidebar-title" style="margin-bottom: 2rem;">Patient Menu</h3>
        <ul class="sidebar-menu">
            <li><a href="patient_dashboard.php"><i class="fas fa-columns"></i> Dashboard</a></li>
            <li><a href="customer_support.php" class="active"><i class="fas fa-headset"></i> Customer Support</a></li>
            <li><a href="my_tickets.php"><i class="fas fa-ticket-alt"></i> My Tickets</a></li>
            <li><a href="your_orders.php"><i class="fas fa-shopping-bag"></i> My Orders</a></li>
            <li><a href="my_wallet.php"><i class="fas fa-wallet"></i> My Wallet</a></li>
            <li><a href="digital_medical_card.php"><i class="fas fa-id-card"></i> Medical Card</a></li>
            <li><a href="payment_history.php"><i class="fas fa-receipt"></i> Payment History</a></li>
            <li><a href="profile.php"><i class="fas fa-user-cog"></i> Profile Settings</a></li>
        </ul>
    </aside>

    <main class="dashboard-content">
        <!-- Banner Header -->
        <div class="glass-panel" style="padding: 2rem; margin-bottom: 2rem; background: linear-gradient(135deg, rgba(80, 227, 194, 0.1), rgba(74, 144, 226, 0.15)); border-left: 5px solid var(--primary-color);">
            <h2 style="margin: 0 0 0.5rem 0; color: var(--text-primary); font-size: 1.8rem;"><i class="fas fa-headset" style="color: var(--primary-color);"></i> Customer Support Center</h2>
            <p style="color: var(--text-secondary); margin: 0; font-size: 1rem;">How can we help you today? Search common issues or connect with our support team.</p>
            
            <!-- Quick Search Bar -->
            <div style="margin-top: 1.5rem; position: relative; max-width: 600px;">
                <i class="fas fa-search" style="position: absolute; left: 1rem; top: 50%; transform: translateY(-50%); color: var(--text-secondary);"></i>
                <input type="text" id="supportSearch" class="form-control" placeholder="Search your problem (e.g. wallet, order status, refund)..." onkeyup="filterSupportTopics()" style="padding-left: 2.8rem; font-size: 1rem; border-radius: 25px; background: rgba(0,0,0,0.3);">
            </div>

        </div>

        <!-- Support Channels (WhatsApp & Support Ticket Options) -->
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 1.5rem; margin-bottom: 2rem;">
            
            <!-- Option 1: WhatsApp Support Box -->
            <div class="glass-panel" style="padding: 1.8rem; border-top: 4px solid #25D366; position: relative;">
                <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 1rem;">
                    <div>
                        <h3 style="margin: 0; color: var(--text-primary); display: flex; align-items: center; gap: 0.5rem;">
                            <i class="fab fa-whatsapp" style="color: #25D366; font-size: 1.6rem;"></i> WhatsApp Support
                        </h3>
                        <p style="margin: 0.3rem 0 0 0; color: var(--text-secondary); font-size: 0.88rem;">Chat directly with our official support team</p>
                    </div>

                    <div>
                        <?php if ($is_wa_online): ?>
                            <span style="background: rgba(46, 213, 115, 0.2); color: #2ed573; border: 1px solid #2ed573; padding: 0.25rem 0.75rem; border-radius: 20px; font-size: 0.8rem; font-weight: bold; display: inline-flex; align-items: center; gap: 0.4rem;">
                                <span style="width: 8px; height: 8px; border-radius: 50%; background: #2ed573; box-shadow: 0 0 8px #2ed573;"></span> 🟢 Available Now
                            </span>
                        <?php else: ?>
                            <span style="background: rgba(255, 71, 87, 0.2); color: #ff4757; border: 1px solid #ff4757; padding: 0.25rem 0.75rem; border-radius: 20px; font-size: 0.8rem; font-weight: bold; display: inline-flex; align-items: center; gap: 0.4rem;">
                                🔴 Currently Offline
                            </span>
                        <?php endif; ?>
                    </div>
                </div>

                <p style="color: var(--text-primary); font-size: 0.9rem; line-height: 1.5; margin-bottom: 1.5rem;">
                    <?php echo $is_wa_online ? "Instant responses for urgent queries, order updates, payment issues, and digital card assistance." : htmlspecialchars($wa_settings['offline_message']); ?>
                </p>

                <div style="display: flex; gap: 0.75rem; flex-wrap: wrap;">
                    <?php if ($is_wa_online): ?>
                        <a href="<?php echo htmlspecialchars($wa_url); ?>" target="_blank" class="btn" style="width: 100%; background: #25D366; color: #ffffff; text-align: center; font-weight: bold; padding: 0.75rem; font-size: 0.95rem; border-radius: 10px; display: inline-flex; align-items: center; justify-content: center; gap: 0.5rem; text-decoration: none;">
                            <i class="fab fa-whatsapp" style="font-size: 1.1rem;"></i> Chat on WhatsApp Now
                        </a>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Option 2: Create Support Ticket Box -->
            <div class="glass-panel" style="padding: 1.8rem; border-top: 4px solid var(--primary-color);">
                <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 1rem;">
                    <div>
                        <h3 style="margin: 0; color: var(--text-primary); display: flex; align-items: center; gap: 0.5rem;">
                            <i class="fas fa-ticket-alt" style="color: var(--primary-color); font-size: 1.4rem;"></i> Support Tickets
                        </h3>
                        <p style="margin: 0.3rem 0 0 0; color: var(--text-secondary); font-size: 0.88rem;">Track your issues with a official ticket number</p>
                    </div>
                </div>

                <p style="color: var(--text-primary); font-size: 0.9rem; line-height: 1.5; margin-bottom: 1.5rem;">
                    Submit detailed inquiries, attach screenshots or payment proofs, and track conversation status to resolution.
                </p>

                <div style="display: flex; gap: 0.8rem; flex-wrap: wrap;">
                    <a href="create_ticket.php" class="btn btn-primary" style="flex: 1; text-align: center; font-weight: bold; padding: 0.75rem; font-size: 0.95rem; border-radius: 10px; display: inline-flex; align-items: center; justify-content: center; gap: 0.5rem;">
                        <i class="fas fa-plus-circle"></i> Create Ticket
                    </a>
                    <a href="my_tickets.php" class="btn btn-outline" style="flex: 1; text-align: center; font-weight: bold; padding: 0.75rem; font-size: 0.95rem; border-radius: 10px; display: inline-flex; align-items: center; justify-content: center; gap: 0.5rem;">
                        <i class="fas fa-list"></i> My Tickets (<?php echo $cnt_open + $cnt_in_prog + $cnt_waiting; ?>)
                    </a>
                </div>
            </div>

        </div>

        <!-- Quick Support Categories Grid -->
        <h3 style="margin-bottom: 1rem; color: var(--text-primary); font-size: 1.2rem;"><i class="fas fa-th-large" style="color: var(--secondary-color);"></i> Quick Support Categories</h3>
        <div id="topicsGrid" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 1rem; margin-bottom: 2.5rem;">
            
            <a href="create_ticket.php?category=Payment" class="topic-card glass-panel" style="padding: 1.2rem; text-decoration: none; border-radius: 12px; transition: transform 0.2s;" onmouseover="this.style.transform='translateY(-3px)'" onmouseout="this.style.transform='translateY(0)'">
                <div style="font-size: 1.5rem; margin-bottom: 0.5rem;">💳</div>
                <h4 style="margin: 0; color: var(--text-primary); font-size: 1rem;">Payment Problem</h4>
                <p style="margin: 0.3rem 0 0 0; color: var(--text-secondary); font-size: 0.8rem;">Double charge, failed Tx, UPI issues</p>
            </a>

            <a href="create_ticket.php?category=Order" class="topic-card glass-panel" style="padding: 1.2rem; text-decoration: none; border-radius: 12px; transition: transform 0.2s;" onmouseover="this.style.transform='translateY(-3px)'" onmouseout="this.style.transform='translateY(0)'">
                <div style="font-size: 1.5rem; margin-bottom: 0.5rem;">📦</div>
                <h4 style="margin: 0; color: var(--text-primary); font-size: 1rem;">Order Problem</h4>
                <p style="margin: 0.3rem 0 0 0; color: var(--text-secondary); font-size: 0.8rem;">Delivery delay, missing medicine, packing</p>
            </a>

            <a href="create_ticket.php?category=Wallet" class="topic-card glass-panel" style="padding: 1.2rem; text-decoration: none; border-radius: 12px; transition: transform 0.2s;" onmouseover="this.style.transform='translateY(-3px)'" onmouseout="this.style.transform='translateY(0)'">
                <div style="font-size: 1.5rem; margin-bottom: 0.5rem;">💰</div>
                <h4 style="margin: 0; color: var(--text-primary); font-size: 1rem;">Wallet Problem</h4>
                <p style="margin: 0.3rem 0 0 0; color: var(--text-secondary); font-size: 0.8rem;">Top-up credit, wallet deduction error</p>
            </a>

            <a href="create_ticket.php?category=Medical Card" class="topic-card glass-panel" style="padding: 1.2rem; text-decoration: none; border-radius: 12px; transition: transform 0.2s;" onmouseover="this.style.transform='translateY(-3px)'" onmouseout="this.style.transform='translateY(0)'">
                <div style="font-size: 1.5rem; margin-bottom: 0.5rem;">🪪</div>
                <h4 style="margin: 0; color: var(--text-primary); font-size: 1rem;">Medical Card</h4>
                <p style="margin: 0.3rem 0 0 0; color: var(--text-secondary); font-size: 0.8rem;">Approval status, discount activation</p>
            </a>

            <a href="create_ticket.php?category=Appointment" class="topic-card glass-panel" style="padding: 1.2rem; text-decoration: none; border-radius: 12px; transition: transform 0.2s;" onmouseover="this.style.transform='translateY(-3px)'" onmouseout="this.style.transform='translateY(0)'">
                <div style="font-size: 1.5rem; margin-bottom: 0.5rem;">🩺</div>
                <h4 style="margin: 0; color: var(--text-primary); font-size: 1rem;">Appointment</h4>
                <p style="margin: 0.3rem 0 0 0; color: var(--text-secondary); font-size: 0.8rem;">Reschedule doctor consult, queue token</p>
            </a>

            <a href="create_ticket.php?category=Prescription" class="topic-card glass-panel" style="padding: 1.2rem; text-decoration: none; border-radius: 12px; transition: transform 0.2s;" onmouseover="this.style.transform='translateY(-3px)'" onmouseout="this.style.transform='translateY(0)'">
                <div style="font-size: 1.5rem; margin-bottom: 0.5rem;">💊</div>
                <h4 style="margin: 0; color: var(--text-primary); font-size: 1rem;">Prescription</h4>
                <p style="margin: 0.3rem 0 0 0; color: var(--text-secondary); font-size: 0.8rem;">Upload error, doctor notes missing</p>
            </a>

            <a href="create_ticket.php?category=Account/Login" class="topic-card glass-panel" style="padding: 1.2rem; text-decoration: none; border-radius: 12px; transition: transform 0.2s;" onmouseover="this.style.transform='translateY(-3px)'" onmouseout="this.style.transform='translateY(0)'">
                <div style="font-size: 1.5rem; margin-bottom: 0.5rem;">👤</div>
                <h4 style="margin: 0; color: var(--text-primary); font-size: 1rem;">Account / Login</h4>
                <p style="margin: 0.3rem 0 0 0; color: var(--text-secondary); font-size: 0.8rem;">OTP issue, profile update, password</p>
            </a>

            <a href="create_ticket.php?category=Referral" class="topic-card glass-panel" style="padding: 1.2rem; text-decoration: none; border-radius: 12px; transition: transform 0.2s;" onmouseover="this.style.transform='translateY(-3px)'" onmouseout="this.style.transform='translateY(0)'">
                <div style="font-size: 1.5rem; margin-bottom: 0.5rem;">🎁</div>
                <h4 style="margin: 0; color: var(--text-primary); font-size: 1rem;">Referral Reward</h4>
                <p style="margin: 0.3rem 0 0 0; color: var(--text-secondary); font-size: 0.8rem;">Referral code reward not received</p>
            </a>

            <a href="create_ticket.php?category=Technical Issue" class="topic-card glass-panel" style="padding: 1.2rem; text-decoration: none; border-radius: 12px; transition: transform 0.2s;" onmouseover="this.style.transform='translateY(-3px)'" onmouseout="this.style.transform='translateY(0)'">
                <div style="font-size: 1.5rem; margin-bottom: 0.5rem;">🔧</div>
                <h4 style="margin: 0; color: var(--text-primary); font-size: 1rem;">Technical Problem</h4>
                <p style="margin: 0.3rem 0 0 0; color: var(--text-secondary); font-size: 0.8rem;">App glitch, PWA issue, loading error</p>
            </a>

            <a href="create_ticket.php?category=Other" class="topic-card glass-panel" style="padding: 1.2rem; text-decoration: none; border-radius: 12px; transition: transform 0.2s;" onmouseover="this.style.transform='translateY(-3px)'" onmouseout="this.style.transform='translateY(0)'">
                <div style="font-size: 1.5rem; margin-bottom: 0.5rem;">❓</div>
                <h4 style="margin: 0; color: var(--text-primary); font-size: 1rem;">Other Query</h4>
                <p style="margin: 0.3rem 0 0 0; color: var(--text-secondary); font-size: 0.8rem;">General inquiry or feedback</p>
            </a>

        </div>

        <!-- Recent Customer Tickets Overview -->
        <div class="glass-panel" style="padding: 1.5rem;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem; flex-wrap: wrap; gap: 0.5rem;">
                <h3 style="margin: 0; color: var(--text-primary); font-size: 1.1rem;"><i class="fas fa-ticket-alt" style="color: var(--primary-color);"></i> Recent Support Tickets</h3>
                <a href="my_tickets.php" style="color: var(--primary-color); font-size: 0.85rem; font-weight: bold; text-decoration: none;">View All Tickets →</a>
            </div>

            <?php if ($recent_tickets && $recent_tickets->num_rows > 0): ?>
                <div style="overflow-x: auto;">
                    <table style="width: 100%; border-collapse: collapse; text-align: left; font-size: 0.9rem;">
                        <thead>
                            <tr style="border-bottom: 1px solid var(--glass-border); color: var(--text-secondary);">
                                <th style="padding: 0.75rem;">Ticket Ref</th>
                                <th style="padding: 0.75rem;">Category</th>
                                <th style="padding: 0.75rem;">Subject</th>
                                <th style="padding: 0.75rem;">Status</th>
                                <th style="padding: 0.75rem;">Last Updated</th>
                                <th style="padding: 0.75rem;">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($t = $recent_tickets->fetch_assoc()): ?>
                                <?php
                                    $st = strtolower($t['status']);
                                    $badge_bg = 'rgba(255, 171, 0, 0.2)';
                                    $badge_color = '#ffab00';
                                    $st_label = 'Open';
                                    if ($st === 'in_progress') {
                                        $badge_bg = 'rgba(112, 161, 255, 0.2)';
                                        $badge_color = '#70a1ff';
                                        $st_label = 'In Progress';
                                    } elseif ($st === 'waiting_customer') {
                                        $badge_bg = 'rgba(245, 166, 35, 0.2)';
                                        $badge_color = '#f5a623';
                                        $st_label = 'Waiting for Reply';
                                    } elseif ($st === 'resolved') {
                                        $badge_bg = 'rgba(46, 213, 115, 0.2)';
                                        $badge_color = '#2ed573';
                                        $st_label = 'Resolved';
                                    } elseif ($st === 'closed') {
                                        $badge_bg = 'rgba(255,255,255,0.1)';
                                        $badge_color = 'var(--text-secondary)';
                                        $st_label = 'Closed';
                                    }
                                ?>
                                <tr style="border-bottom: 1px solid rgba(255,255,255,0.05);">
                                    <td style="padding: 0.75rem; font-weight: bold; color: var(--primary-color); font-family: monospace;">
                                        <?php echo htmlspecialchars($t['ticket_number']); ?>
                                    </td>
                                    <td style="padding: 0.75rem; font-weight: 600;">
                                        <?php echo htmlspecialchars($t['category']); ?>
                                    </td>
                                    <td style="padding: 0.75rem; color: var(--text-primary);">
                                        <?php echo htmlspecialchars($t['subject']); ?>
                                    </td>
                                    <td style="padding: 0.75rem;">
                                        <span style="display: inline-block; padding: 0.2rem 0.6rem; border-radius: 12px; font-size: 0.78rem; font-weight: bold; background: <?php echo $badge_bg; ?>; color: <?php echo $badge_color; ?>; border: 1px solid <?php echo $badge_color; ?>;">
                                            <?php echo $st_label; ?>
                                        </span>
                                    </td>
                                    <td style="padding: 0.75rem; color: var(--text-secondary); font-size: 0.82rem;">
                                        <?php echo date('M d, Y h:i A', strtotime($t['updated_at'])); ?>
                                    </td>
                                    <td style="padding: 0.75rem;">
                                        <a href="ticket_view.php?id=<?php echo $t['id']; ?>" class="btn btn-outline" style="font-size: 0.75rem; padding: 0.25rem 0.6rem;">
                                            <i class="fas fa-comments"></i> Chat / View
                                        </a>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <p style="color: var(--text-secondary); font-size: 0.88rem; margin: 0; padding: 1rem 0; text-align: center;">You have not created any support tickets yet.</p>
            <?php endif; ?>
        </div>

    </main>
</div>

<script>
function filterSupportTopics() {
    const input = document.getElementById('supportSearch');
    const filter = input.value.toLowerCase();
    const grid = document.getElementById('topicsGrid');
    const cards = grid.getElementsByClassName('topic-card');

    for (let i = 0; i < cards.length; i++) {
        let text = cards[i].textContent || cards[i].innerText;
        if (text.toLowerCase().indexOf(filter) > -1) {
            cards[i].style.display = "";
        } else {
            cards[i].style.display = "none";
        }
    }
}
</script>

<?php include 'includes/footer.php'; ?>
