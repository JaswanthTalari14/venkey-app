<?php
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = (int)$_SESSION['user_id'];
$user_role = $_SESSION['role'] ?? 'patient';

// Parameters
$type = isset($_GET['type']) ? trim($_GET['type']) : '';
$id_param = isset($_GET['id']) ? trim($_GET['id']) : '';
$tx_id = isset($_GET['tx_id']) ? trim($_GET['tx_id']) : '';
$card_num = isset($_GET['card_num']) ? trim($_GET['card_num']) : '';

// Auto-detect type if missing based on parameters provided
if (empty($type)) {
    if (!empty($tx_id)) {
        $type = 'wallet';
        $id_param = $tx_id;
    } elseif (!empty($card_num)) {
        $type = 'medical_card';
        $id_param = $card_num;
    } elseif (isset($_GET['order_id'])) {
        $type = 'order';
        $id_param = $_GET['order_id'];
    } else {
        $type = 'order';
    }
}

$receipt_data = null;
$error_msg = '';

if ($type === 'wallet') {
    $search_tx = !empty($id_param) ? $id_param : $tx_id;
    $stmt = $conn->prepare("
        SELECT wt.*, u.name as customer_name, u.email as customer_email, u.phone as customer_phone
        FROM wallet_transactions wt
        JOIN users u ON wt.customer_id = u.id
        WHERE (wt.transaction_id = ? OR CAST(wt.id AS CHAR) = ?) 
          AND (wt.customer_id = ? OR ? = 'admin')
    ");
    $stmt->bind_param("ssis", $search_tx, $search_tx, $user_id, $user_role);
    $stmt->execute();
    $res = $stmt->get_result();

    if ($res && $res->num_rows > 0) {
        $row = $res->fetch_assoc();
        $receipt_data = [
            'receipt_title' => 'Wallet Transaction Receipt',
            'transaction_id' => $row['transaction_id'],
            'payment_id' => $row['payment_id'] ?: $row['transaction_id'],
            'reference_id' => $row['order_id'] ? '#ORD-' . $row['order_id'] : ($row['transaction_id']),
            'date_time' => date('M d, Y — h:i A', strtotime($row['created_at'])),
            'subtotal' => (float)$row['amount'],
            'discount' => 0.00,
            'final_amount' => (float)$row['amount'],
            'payment_method' => 'Wallet (' . ucfirst($row['transaction_type']) . ')',
            'status' => ucfirst($row['status']),
            'is_success' => strtolower($row['status']) === 'completed',
            'customer_name' => $row['customer_name'],
            'customer_email' => $row['customer_email'],
            'customer_phone' => $row['customer_phone'],
            'shipping_address' => '',
            'items' => [
                [
                    'name' => 'Wallet Credit/Debit: ' . str_replace('_', ' ', strtoupper($row['transaction_type'])),
                    'desc' => $row['reason'] ?: 'Balance updated on MedicalAk Digital Wallet',
                    'qty' => 1,
                    'unit_price' => (float)$row['amount'],
                    'total' => (float)$row['amount']
                ]
            ],
            'notes' => 'Wallet Balance After Tx: ₹' . number_format($row['new_balance'], 2)
        ];
    } else {
        // Fallback search in wallet_topups table if pending/rejected top-up
        $stmt_topup = $conn->prepare("
            SELECT wt.*, u.name as customer_name, u.email as customer_email, u.phone as customer_phone
            FROM wallet_topups wt
            JOIN users u ON wt.customer_id = u.id
            WHERE (wt.topup_id = ? OR CAST(wt.id AS CHAR) = ?) 
              AND (wt.customer_id = ? OR ? = 'admin')
        ");
        $stmt_topup->bind_param("ssis", $search_tx, $search_tx, $user_id, $user_role);
        $stmt_topup->execute();
        $res_topup = $stmt_topup->get_result();

        if ($res_topup && $res_topup->num_rows > 0) {
            $row = $res_topup->fetch_assoc();
            $amt = (float)($row['paid_amount'] > 0 ? $row['paid_amount'] : $row['amount']);
            $receipt_data = [
                'receipt_title' => 'Wallet Top-Up Payment Receipt',
                'transaction_id' => $row['topup_id'],
                'payment_id' => $row['payment_id'] ?: ($row['gateway_reference'] ?: $row['topup_id']),
                'reference_id' => 'TopUp-' . $row['topup_id'],
                'date_time' => date('M d, Y — h:i A', strtotime($row['created_at'])),
                'subtotal' => $amt,
                'discount' => 0.00,
                'final_amount' => $amt,
                'payment_method' => $row['payment_method'] ?: 'Online Payment',
                'status' => ucfirst(str_replace('_', ' ', $row['status'])),
                'is_success' => strtolower($row['status']) === 'approved',
                'customer_name' => $row['customer_name'],
                'customer_email' => $row['customer_email'],
                'customer_phone' => $row['customer_phone'],
                'shipping_address' => '',
                'items' => [
                    [
                        'name' => 'Wallet Top-Up Request',
                        'desc' => 'MedicalAk Wallet Top-Up (' . ucfirst($row['status']) . ')',
                        'qty' => 1,
                        'unit_price' => $amt,
                        'total' => $amt
                    ]
                ],
                'notes' => $row['rejection_reason'] ? 'Note: ' . $row['rejection_reason'] : ''
            ];
        } else {
            $error_msg = "Wallet payment record not found or access denied.";
        }
    }
} elseif ($type === 'medical_card') {
    $search_card = !empty($id_param) ? $id_param : $card_num;
    $stmt = $conn->prepare("
        SELECT c.*, u.name as customer_name, u.email as customer_email, u.phone as customer_phone
        FROM digital_medical_cards c
        JOIN users u ON c.patient_id = u.id
        WHERE (c.card_number = ? OR CAST(c.id AS CHAR) = ?)
          AND (c.patient_id = ? OR ? = 'admin')
    ");
    $stmt->bind_param("ssis", $search_card, $search_card, $user_id, $user_role);
    $stmt->execute();
    $res = $stmt->get_result();

    if ($res && $res->num_rows > 0) {
        $row = $res->fetch_assoc();
        $amt = (float)$row['amount_paid'];
        $receipt_data = [
            'receipt_title' => 'Digital Medical Card Membership Receipt',
            'transaction_id' => $row['card_number'] ?: ('DMC-APP-' . $row['id']),
            'payment_id' => $row['gateway_payment_id'] ?: 'DMC-REG-' . $row['id'],
            'reference_id' => $row['card_number'] ?: ('DMC-' . $row['id']),
            'date_time' => date('M d, Y — h:i A', strtotime($row['created_at'])),
            'subtotal' => $amt,
            'discount' => 0.00,
            'final_amount' => $amt,
            'payment_method' => $row['payment_method'] ?: 'Online Payment',
            'status' => ucfirst($row['status']),
            'is_success' => in_array(strtolower($row['status']), ['active', 'paid', 'completed']),
            'customer_name' => $row['customer_name'],
            'customer_email' => $row['customer_email'],
            'customer_phone' => $row['customer_phone'],
            'shipping_address' => '',
            'items' => [
                [
                    'name' => '5-Month Digital Medical Card Subscription',
                    'desc' => 'Card No: ' . ($row['card_number'] ?: 'Pending Approval') . ' | Membership & Healthcare Discounts',
                    'qty' => 1,
                    'unit_price' => $amt,
                    'total' => $amt
                ]
            ],
            'notes' => $row['valid_until'] ? 'Valid From: ' . date('M d, Y', strtotime($row['valid_from'])) . ' To: ' . date('M d, Y', strtotime($row['valid_until'])) : 'Card Status: ' . ucfirst($row['status'])
        ];
    } else {
        $error_msg = "Digital Medical Card receipt record not found or access denied.";
    }
} else {
    // Default: Order Payment Receipt
    $order_id = (int)$id_param;
    $stmt = $conn->prepare("
        SELECT o.*, u.name as customer_name, u.email as customer_email, u.phone as customer_phone
        FROM orders o
        JOIN users u ON o.patient_id = u.id
        WHERE o.id = ? AND (o.patient_id = ? OR ? = 'admin')
    ");
    $stmt->bind_param("iis", $order_id, $user_id, $user_role);
    $stmt->execute();
    $res = $stmt->get_result();

    if ($res && $res->num_rows > 0) {
        $order = $res->fetch_assoc();

        // Fetch Order Items
        $items_stmt = $conn->prepare("
            SELECT oi.*, m.name as medicine_name
            FROM order_items oi
            JOIN medicines m ON oi.medicine_id = m.id
            WHERE oi.order_id = ?
        ");
        $items_stmt->bind_param("i", $order_id);
        $items_stmt->execute();
        $items_res = $items_stmt->get_result();

        $items = [];
        $subtotal = 0.00;
        if ($items_res && $items_res->num_rows > 0) {
            while ($item = $items_res->fetch_assoc()) {
                $line_total = (float)$item['price'] * (int)$item['quantity'];
                $subtotal += $line_total;
                $items[] = [
                    'name' => $item['medicine_name'],
                    'desc' => 'Quantity: ' . $item['quantity'],
                    'qty' => (int)$item['quantity'],
                    'unit_price' => (float)$item['price'],
                    'total' => $line_total
                ];
            }
        } else {
            $subtotal = (float)$order['total_amount'];
            $items[] = [
                'name' => 'Healthcare Order #' . $order_id,
                'desc' => 'Prescription Medicine Delivery',
                'qty' => 1,
                'unit_price' => $subtotal,
                'total' => $subtotal
            ];
        }

        $final_amt = (float)$order['total_amount'];
        $discount_amt = max(0.00, $subtotal - $final_amt);
        $pay_status = $order['payment_status'] ?: ($order['status'] === 'delivered' ? 'Paid' : 'Pending');
        $is_success = in_array(strtolower($pay_status), ['paid', 'completed', 'success']);

        $receipt_data = [
            'receipt_title' => 'Medicine Order Payment Receipt',
            'transaction_id' => $order['gateway_payment_id'] ?: ('ORD-TXN-' . str_pad($order['id'], 6, '0', STR_PAD_LEFT)),
            'payment_id' => $order['gateway_payment_id'] ?: ($order['gateway_order_id'] ?: 'N/A'),
            'reference_id' => '#ORD-' . str_pad($order['id'], 4, '0', STR_PAD_LEFT),
            'date_time' => date('M d, Y — h:i A', strtotime($order['created_at'])),
            'subtotal' => $subtotal,
            'discount' => $discount_amt,
            'final_amount' => $final_amt,
            'payment_method' => $order['payment_method'] ?: 'Cash on Delivery',
            'status' => ucfirst($pay_status),
            'is_success' => $is_success,
            'customer_name' => $order['customer_name'],
            'customer_email' => $order['customer_email'],
            'customer_phone' => $order['customer_phone'],
            'shipping_address' => $order['address'],
            'items' => $items,
            'notes' => 'Delivery Status: ' . ucfirst($order['status'])
        ];
    } else {
        $error_msg = "Order record not found or access denied.";
    }
}

include 'includes/header.php';
?>

<!-- html2pdf.js CDN for reliable PDF downloads -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js" crossorigin="anonymous" referrerpolicy="no-referrer"></script>

<div style="max-width: 750px; margin: 2rem auto; padding: 0 1rem;">
    <?php if (!empty($error_msg)): ?>
        <div class="glass-panel" style="padding: 2.5rem; text-align: center;">
            <i class="fas fa-exclamation-triangle" style="font-size: 3rem; color: #ff4757; margin-bottom: 1rem;"></i>
            <h3 style="color: var(--text-primary); margin-bottom: 0.5rem;">Receipt Not Found</h3>
            <p style="color: var(--text-secondary); margin-bottom: 1.5rem;"><?php echo htmlspecialchars($error_msg); ?></p>
            <a href="index.php" class="btn btn-primary"><i class="fas fa-home"></i> Back to Home</a>
        </div>
    <?php else: ?>
        <!-- Action Toolbar -->
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem; flex-wrap: wrap; gap: 0.8rem;">
            <a href="javascript:history.back()" class="btn btn-outline" style="font-size: 0.85rem;"><i class="fas fa-arrow-left"></i> Back</a>
            <div style="display: flex; gap: 0.5rem; flex-wrap: wrap;">
                <button onclick="shareReceipt()" class="btn btn-outline" style="font-size: 0.85rem;" title="Share Receipt"><i class="fas fa-share-alt"></i> Share</button>
                <button onclick="window.print()" class="btn btn-outline" style="font-size: 0.85rem;" title="Print Receipt"><i class="fas fa-print"></i> Print</button>
                <button id="btnDownloadPdf" onclick="downloadReceiptPDF()" class="btn btn-primary" style="font-size: 0.85rem;"><i class="fas fa-file-pdf"></i> Download PDF</button>
            </div>
        </div>

        <!-- Receipt Card Container -->
        <div id="receiptContainer" class="glass-panel" style="padding: 2.5rem; background: #121826; border-top: 5px solid var(--primary-color); color: #ffffff; border-radius: 16px; box-shadow: 0 15px 35px rgba(0,0,0,0.4);">
            
            <!-- Receipt Header -->
            <div style="display: flex; justify-content: space-between; align-items: flex-start; border-bottom: 2px dashed rgba(255,255,255,0.12); padding-bottom: 1.5rem; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 1rem;">
                <div>
                    <h2 style="margin: 0; color: #50e3c2; font-weight: 800; font-size: 1.8rem; display: flex; align-items: center; gap: 0.5rem;">
                        <i class="fas fa-notes-medical" style="color: var(--primary-color);"></i> MedicalAk
                    </h2>
                    <p style="margin: 0.3rem 0 0; color: #cbd5e1; font-size: 0.88rem;">Smart Healthcare Solutions & Pharmacy</p>
                    <p style="margin: 0.2rem 0 0; color: #94a3b8; font-size: 0.8rem;">Verified Official Payment Receipt</p>
                </div>

                <div style="text-align: right;">
                    <div style="font-size: 0.82rem; color: #cbd5e1; text-transform: uppercase; letter-spacing: 0.5px;">Payment Status</div>
                    <div style="margin-top: 0.3rem;">
                        <?php 
                            $st_lower = strtolower($receipt_data['status']);
                            $badge_bg = 'rgba(255, 171, 0, 0.2)';
                            $badge_color = '#ffab00';
                            if (in_array($st_lower, ['paid', 'completed', 'approved', 'active', 'success'])) {
                                $badge_bg = 'rgba(46, 213, 115, 0.2)';
                                $badge_color = '#2ed573';
                            } elseif (in_array($st_lower, ['failed', 'rejected', 'cancelled'])) {
                                $badge_bg = 'rgba(255, 71, 87, 0.2)';
                                $badge_color = '#ff4757';
                            } elseif (strpos($st_lower, 'refund') !== false) {
                                $badge_bg = 'rgba(112, 161, 255, 0.2)';
                                $badge_color = '#70a1ff';
                            }
                        ?>
                        <span style="display: inline-block; padding: 0.35rem 0.9rem; border-radius: 20px; font-weight: 800; font-size: 0.85rem; background: <?php echo $badge_bg; ?>; color: <?php echo $badge_color; ?>; border: 1px solid <?php echo $badge_color; ?>;">
                            <i class="fas <?php echo $receipt_data['is_success'] ? 'fa-check-circle' : 'fa-info-circle'; ?>"></i> <?php echo htmlspecialchars($receipt_data['status']); ?>
                        </span>
                    </div>
                </div>
            </div>

            <!-- Receipt Meta Info Grid -->
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1.2rem; background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.08); border-radius: 12px; padding: 1.2rem; margin-bottom: 1.5rem;">
                <div>
                    <span style="font-size: 0.78rem; color: #cbd5e1; text-transform: uppercase; display: block; margin-bottom: 0.2rem; font-weight: 600;">Transaction Ref ID</span>
                    <strong style="color: #ffffff; font-family: monospace; font-size: 0.95rem; text-break: break-all;"><?php echo htmlspecialchars($receipt_data['transaction_id']); ?></strong>
                </div>

                <?php if (!empty($receipt_data['payment_id']) && $receipt_data['payment_id'] !== $receipt_data['transaction_id']): ?>
                <div>
                    <span style="font-size: 0.78rem; color: #cbd5e1; text-transform: uppercase; display: block; margin-bottom: 0.2rem; font-weight: 600;">Payment / Gateway ID</span>
                    <strong style="color: #50e3c2; font-family: monospace; font-size: 0.9rem;"><?php echo htmlspecialchars($receipt_data['payment_id']); ?></strong>
                </div>
                <?php endif; ?>

                <div>
                    <span style="font-size: 0.78rem; color: #cbd5e1; text-transform: uppercase; display: block; margin-bottom: 0.2rem; font-weight: 600;">Order / Reference</span>
                    <strong style="color: #ffffff; font-size: 0.95rem;"><?php echo htmlspecialchars($receipt_data['reference_id']); ?></strong>
                </div>

                <div>
                    <span style="font-size: 0.78rem; color: #cbd5e1; text-transform: uppercase; display: block; margin-bottom: 0.2rem; font-weight: 600;">Payment Date & Time</span>
                    <strong style="color: #ffffff; font-size: 0.9rem;"><?php echo htmlspecialchars($receipt_data['date_time']); ?></strong>
                </div>

                <div>
                    <span style="font-size: 0.78rem; color: #cbd5e1; text-transform: uppercase; display: block; margin-bottom: 0.2rem; font-weight: 600;">Payment Method</span>
                    <strong style="color: #ffffff; font-size: 0.9rem; text-transform: uppercase;"><?php echo htmlspecialchars($receipt_data['payment_method']); ?></strong>
                </div>
            </div>

            <!-- Customer Details -->
            <div style="margin-bottom: 1.5rem; padding-bottom: 1rem; border-bottom: 1px solid rgba(255,255,255,0.08); background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.08); border-radius: 12px; padding: 1.2rem;">
                <h4 style="margin: 0 0 0.6rem; color: #50e3c2; font-size: 0.95rem; text-transform: uppercase; letter-spacing: 0.5px; font-weight: 800;"><i class="fas fa-user" style="margin-right: 0.4rem;"></i> Customer Details</h4>
                <div style="font-size: 0.95rem; color: #ffffff; line-height: 1.6;">
                    <div style="margin-bottom: 0.2rem;"><strong style="color: #50e3c2;">Name:</strong> <span style="color: #ffffff; font-weight: 600;"><?php echo htmlspecialchars($receipt_data['customer_name']); ?></span></div>
                    <div style="margin-bottom: 0.2rem;"><strong style="color: #50e3c2;">Contact:</strong> <span style="color: #ffffff; font-weight: 500;"><?php echo htmlspecialchars($receipt_data['customer_phone'] . ' | ' . $receipt_data['customer_email']); ?></span></div>
                    <?php if (!empty($receipt_data['shipping_address'])): ?>
                        <div><strong style="color: #50e3c2;">Address:</strong> <span style="color: #ffffff; font-weight: 500;"><?php echo htmlspecialchars($receipt_data['shipping_address']); ?></span></div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Itemized Table -->
            <div style="margin-bottom: 1.5rem; overflow-x: auto;">
                <table style="width: 100%; border-collapse: collapse; text-align: left; font-size: 0.9rem;">
                    <thead>
                        <tr style="border-bottom: 2px solid rgba(255,255,255,0.15); color: #cbd5e1;">
                            <th style="padding: 0.75rem 0.5rem; color: #50e3c2; font-weight: 700;">Item Description</th>
                            <th style="padding: 0.75rem 0.5rem; text-align: center; color: #50e3c2; font-weight: 700;">Qty</th>
                            <th style="padding: 0.75rem 0.5rem; text-align: right; color: #50e3c2; font-weight: 700;">Unit Price</th>
                            <th style="padding: 0.75rem 0.5rem; text-align: right; color: #50e3c2; font-weight: 700;">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($receipt_data['items'] as $item): ?>
                            <tr style="border-bottom: 1px solid rgba(255,255,255,0.05);">
                                <td style="padding: 0.75rem 0.5rem;">
                                    <strong style="color: #ffffff; display: block; font-size: 0.95rem;"><?php echo htmlspecialchars($item['name']); ?></strong>
                                    <span style="font-size: 0.82rem; color: #94a3b8;"><?php echo htmlspecialchars($item['desc']); ?></span>
                                </td>
                                <td style="padding: 0.75rem 0.5rem; text-align: center; color: #ffffff; font-weight: 600;"><?php echo $item['qty']; ?></td>
                                <td style="padding: 0.75rem 0.5rem; text-align: right; color: #ffffff; font-weight: 600;">₹<?php echo number_format($item['unit_price'], 2); ?></td>
                                <td style="padding: 0.75rem 0.5rem; text-align: right; font-weight: 700; color: #ffffff;">₹<?php echo number_format($item['total'], 2); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Amount Breakdown -->
            <div style="margin-left: auto; max-width: 320px; border-top: 1px solid rgba(255,255,255,0.12); padding-top: 1rem; margin-bottom: 1.5rem;">
                <div style="display: flex; justify-content: space-between; margin-bottom: 0.4rem; font-size: 0.9rem; color: #cbd5e1;">
                    <span>Subtotal:</span>
                    <span style="color: #ffffff; font-weight: 600;">₹<?php echo number_format($receipt_data['subtotal'], 2); ?></span>
                </div>

                <?php if ($receipt_data['discount'] > 0): ?>
                    <div style="display: flex; justify-content: space-between; margin-bottom: 0.4rem; font-size: 0.9rem; color: #2ed573;">
                        <span>Discount / Savings:</span>
                        <span style="font-weight: 600;">-₹<?php echo number_format($receipt_data['discount'], 2); ?></span>
                    </div>
                <?php endif; ?>

                <div style="display: flex; justify-content: space-between; margin-top: 0.8rem; padding-top: 0.8rem; border-top: 2px solid #50e3c2; font-size: 1.25rem; font-weight: 800; color: #50e3c2;">
                    <span>Final Amount Paid:</span>
                    <span>₹<?php echo number_format($receipt_data['final_amount'], 2); ?></span>
                </div>
            </div>

            <?php if (!empty($receipt_data['notes'])): ?>
                <div style="background: rgba(80, 227, 194, 0.08); border-left: 4px solid #50e3c2; padding: 0.8rem 1rem; border-radius: 6px; font-size: 0.88rem; color: #ffffff;">
                    <i class="fas fa-info-circle" style="color: #50e3c2;"></i> <?php echo htmlspecialchars($receipt_data['notes']); ?>
                </div>
            <?php endif; ?>

            <!-- Footer Sign Off -->
            <div style="margin-top: 2rem; border-top: 1px solid rgba(255,255,255,0.08); padding-top: 1rem; text-align: center; font-size: 0.8rem; color: #94a3b8;">
                Thank you for choosing MedicalAk. For any payment inquiries, contact support at support@medicalak.com
            </div>
        </div>
    <?php endif; ?>
</div>

<script>
function downloadReceiptPDF() {
    var element = document.getElementById('receiptContainer');
    var btn = document.getElementById('btnDownloadPdf');

    if (!element || typeof html2pdf === 'undefined') {
        window.print();
        return;
    }

    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Generating PDF...';

    var filename = 'Receipt_<?php echo preg_replace('/[^A-Za-z0-9\-]/', '', $receipt_data['transaction_id'] ?? 'Payment'); ?>.pdf';

    var opt = {
        margin:       [0.2, 0.2, 0.2, 0.2],
        filename:     filename,
        image:        { type: 'jpeg', quality: 0.98 },
        html2canvas:  { scale: 2, useCORS: true, logging: false, backgroundColor: '#121826' },
        jsPDF:        { unit: 'in', format: 'letter', orientation: 'portrait' }
    };

    html2pdf().set(opt).from(element).save().then(function() {
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-file-pdf"></i> Download PDF';
    }).catch(function(err) {
        console.error('PDF Generation error:', err);
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-file-pdf"></i> Download PDF';
        window.print();
    });
}

function shareReceipt() {
    var shareUrl = window.location.href;
    var shareTitle = '<?php echo addslashes($receipt_data['receipt_title'] ?? 'Payment Receipt'); ?>';
    
    if (navigator.share) {
        navigator.share({
            title: shareTitle,
            text: 'Payment receipt from MedicalAk: ' + '<?php echo addslashes($receipt_data['transaction_id'] ?? ''); ?>',
            url: shareUrl
        }).catch(function(e) {
            copyToClipboard(shareUrl);
        });
    } else {
        copyToClipboard(shareUrl);
    }
}

function copyToClipboard(text) {
    if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(text).then(function() {
            alert('Receipt link copied to clipboard!');
        });
    } else {
        var tempInput = document.createElement('input');
        tempInput.value = text;
        document.body.appendChild(tempInput);
        tempInput.select();
        document.execCommand('copy');
        document.body.removeChild(tempInput);
        alert('Receipt link copied to clipboard!');
    }
}
</script>

<?php include 'includes/footer.php'; ?>
