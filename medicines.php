<?php
require_once 'config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'patient') {
    header("Location: login.php");
    exit;
}

include 'includes/header.php';

$success = '';
$error = '';
$online_order_data = null;

if (isset($_GET['success'])) {
    $success = "Medicine ordered successfully! It will be delivered soon.";
}

// Check and add 'image' column if not exists
$check_col = $conn->query("SHOW COLUMNS FROM medicines LIKE 'image'");
if ($check_col && $check_col->num_rows == 0) {
    $conn->query("ALTER TABLE medicines ADD COLUMN image VARCHAR(255) DEFAULT NULL");
}

// Check and add payment columns in orders table if not exists
$check_pay_col = $conn->query("SHOW COLUMNS FROM orders LIKE 'payment_method'");
if ($check_pay_col && $check_pay_col->num_rows == 0) {
    $conn->query("ALTER TABLE orders ADD COLUMN payment_method VARCHAR(50) DEFAULT 'COD', ADD COLUMN payment_status VARCHAR(50) DEFAULT 'Cash on Delivery', ADD COLUMN gateway_order_id VARCHAR(100) DEFAULT NULL, ADD COLUMN gateway_payment_id VARCHAR(100) DEFAULT NULL");
}

// Seed some sample medicines if empty
$check_meds = $conn->query("SELECT COUNT(*) as count FROM medicines");
$row = $check_meds->fetch_assoc();
if ($row['count'] == 0) {
    $conn->query("INSERT INTO medicines (name, description, price, stock, image) VALUES 
        ('Paracetamol 500mg', 'Fever and mild pain relief.', 15.00, 100, 'images/medicines/paracetamol.png'),
        ('Amoxicillin 250mg', 'Antibiotic for bacterial infections.', 120.00, 50, 'images/medicines/amoxicillin.png'),
        ('Cetirizine 10mg', 'Allergy relief tablets.', 45.00, 200, 'images/medicines/cetirizine.png'),
        ('Vitamin C + Zinc', 'Immunity booster supplement.', 250.00, 80, 'images/medicines/vitaminc.png')");
} else {
    $conn->query("UPDATE medicines SET image = 'images/medicines/paracetamol.png' WHERE name LIKE '%Paracetamol%' AND (image IS NULL OR image = '')");
    $conn->query("UPDATE medicines SET image = 'images/medicines/amoxicillin.png' WHERE name LIKE '%Amoxicillin%' AND (image IS NULL OR image = '')");
    $conn->query("UPDATE medicines SET image = 'images/medicines/cetirizine.png' WHERE name LIKE '%Cetirizine%' AND (image IS NULL OR image = '')");
    $conn->query("UPDATE medicines SET image = 'images/medicines/vitaminc.png' WHERE name LIKE '%Vitamin%' AND (image IS NULL OR image = '')");
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['order'])) {
    $medicine_id = (int)$_POST['medicine_id'];
    $qty = (int)$_POST['quantity'];
    $payment_method = isset($_POST['payment_method']) && $_POST['payment_method'] === 'Online Payment' ? 'Online Payment' : 'COD';
    $patient_id = $_SESSION['user_id'];
    
    // Get price & name
    $med_res = $conn->query("SELECT price, name FROM medicines WHERE id=$medicine_id");
    if ($med_res && $med_res->num_rows > 0) {
        $med = $med_res->fetch_assoc();
        $total = $med['price'] * $qty;
        
        if ($payment_method === 'COD') {
            $stmt = $conn->prepare("INSERT INTO orders (patient_id, total_amount, address, payment_method, payment_status) VALUES (?, ?, 'User Default Address', 'COD', 'Cash on Delivery')");
            $stmt->bind_param("id", $patient_id, $total);
            $stmt->execute();
            $order_id = $stmt->insert_id;
            
            $item_stmt = $conn->prepare("INSERT INTO order_items (order_id, medicine_id, quantity, price) VALUES (?, ?, ?, ?)");
            $item_stmt->bind_param("iiid", $order_id, $medicine_id, $qty, $med['price']);
            $item_stmt->execute();
            
            $success = "Medicine ordered successfully! It will be delivered soon.";
        } else {
            // Online Payment Flow
            $stmt = $conn->prepare("INSERT INTO orders (patient_id, total_amount, address, payment_method, payment_status) VALUES (?, ?, 'User Default Address', 'Online Payment', 'Pending')");
            $stmt->bind_param("id", $patient_id, $total);
            $stmt->execute();
            $order_id = $stmt->insert_id;
            
            $item_stmt = $conn->prepare("INSERT INTO order_items (order_id, medicine_id, quantity, price) VALUES (?, ?, ?, ?)");
            $item_stmt->bind_param("iiid", $order_id, $medicine_id, $qty, $med['price']);
            $item_stmt->execute();

            if (defined('PHONEPE_MERCHANT_ID') && !empty(PHONEPE_MERCHANT_ID)) {
                header("Location: phonepe_pay.php?order_id=" . $order_id);
                exit;
            }
            
            $online_order_data = [
                'order_id' => $order_id,
                'amount_paise' => (int)($total * 100),
                'amount_display' => number_format($total, 2),
                'medicine_name' => $med['name'],
                'patient_id' => $patient_id
            ];
        }
    }
}

$medicines = $conn->query("SELECT * FROM medicines");

// Fetch patient's medicine orders
$patient_id_for_orders = $_SESSION['user_id'];
$my_orders = $conn->query("
    SELECT o.id, o.created_at, o.status, o.total_amount, o.payment_method, o.payment_status, m.name as medicine_name, oi.quantity 
    FROM orders o
    JOIN order_items oi ON o.id = oi.order_id
    JOIN medicines m ON oi.medicine_id = m.id
    WHERE o.patient_id = $patient_id_for_orders
    ORDER BY o.created_at DESC
");
?>

<script src="https://checkout.razorpay.com/v1/checkout.js"></script>

<div class="dashboard-layout">
    <aside class="sidebar glass-panel">
        <button class="sidebar-toggle" aria-label="Toggle Patient Menu">
            <span><i class="fas fa-bars" style="margin-right: 0.5rem;"></i> Patient Menu</span>
            <i class="fas fa-chevron-down toggle-icon"></i>
        </button>
        <h3 class="sidebar-title" style="margin-bottom: 2rem;">Patient Menu</h3>
        <ul class="sidebar-menu">
            <li><a href="patient_dashboard.php"><i class="fas fa-home"></i> Overview</a></li>
            <li><a href="book_consult.php"><i class="fas fa-calendar-check"></i> Consultations</a></li>
            <li><a href="medicines.php" class="active"><i class="fas fa-pills"></i> Order Medicines</a></li>
            <li><a href="nearby_doctors.php"><i class="fas fa-map-marker-alt"></i> Find Doctors (10km)</a></li>
            <li><a href="privacy_consult.php"><i class="fas fa-user-secret"></i> Privacy Consult</a></li>
            <li><a href="book_tests.php"><i class="fas fa-vial"></i> Book Labs (RMP)</a></li>
            <li><a href="chatbot.php"><i class="fas fa-robot"></i> AI Chatbot</a></li>
        </ul>
    </aside>
    
    <main class="dashboard-content">
        <h2>Medicine Delivery</h2>
        <p style="color: var(--text-secondary); margin-bottom: 2rem;">Order prescribed or over-the-counter medicines delivered directly to your home.</p>
        
        <?php if($success): ?><p style="color: #2ed573; margin-bottom: 1rem; padding: 1rem; background: rgba(46, 213, 115, 0.1); border-radius: 8px;"><?php echo $success; ?></p><?php endif; ?>
        <?php if($error): ?><p style="color: #ff4757; margin-bottom: 1rem; padding: 1rem; background: rgba(255, 71, 87, 0.1); border-radius: 8px;"><?php echo $error; ?></p><?php endif; ?>

        <div class="features-grid" style="margin-top: 1rem;">
            <?php while($med = $medicines->fetch_assoc()): ?>
                <?php 
                    $img_src = 'images/medicines/default.png';
                    if (!empty($med['image']) && file_exists($med['image'])) {
                        $img_src = $med['image'];
                    } else {
                        $name_lower = strtolower($med['name']);
                        if (strpos($name_lower, 'paracetamol') !== false) $img_src = 'images/medicines/paracetamol.png';
                        elseif (strpos($name_lower, 'amoxicillin') !== false) $img_src = 'images/medicines/amoxicillin.png';
                        elseif (strpos($name_lower, 'cetirizine') !== false) $img_src = 'images/medicines/cetirizine.png';
                        elseif (strpos($name_lower, 'vitamin') !== false) $img_src = 'images/medicines/vitaminc.png';
                    }
                ?>
                <div class="feature-card glass-panel" style="padding: 1rem; display: flex; flex-direction: column;">
                    <div style="width: 100%; height: 125px; overflow: hidden; border-radius: 10px; margin-bottom: 0.6rem; background: rgba(0,0,0,0.2);">
                        <img src="<?php echo htmlspecialchars($img_src); ?>" alt="<?php echo htmlspecialchars($med['name']); ?>" style="width: 100%; height: 100%; object-fit: cover; border-radius: 10px; transition: transform 0.3s ease;">
                    </div>
                    <h4 style="color: #fff; margin-bottom: 0.25rem; font-size: 1.05rem;"><?php echo htmlspecialchars($med['name']); ?></h4>
                    <p style="color: var(--text-secondary); font-size: 0.85rem; margin-bottom: 0.5rem; min-height: 32px; line-height: 1.3;"><?php echo htmlspecialchars($med['description']); ?></p>
                    <p style="font-size: 1.3rem; font-weight: bold; color: var(--secondary-color); margin-bottom: 0.5rem;">₹<?php echo $med['price']; ?></p>
                    
                    <form method="POST" action="" style="display: flex; flex-direction: column; gap: 0.5rem; margin-top: auto;">
                        <input type="hidden" name="medicine_id" value="<?php echo $med['id']; ?>">
                        
                        <div style="display: flex; align-items: center; justify-content: space-between; gap: 0.5rem;">
                            <label style="font-size: 0.8rem; color: var(--text-secondary);">Quantity:</label>
                            <input type="number" name="quantity" value="1" min="1" max="10" class="form-control" style="width: 75px; padding: 0.3rem 0.5rem;" required>
                        </div>
                        
                        <div style="background: rgba(255,255,255,0.03); border: 1px solid var(--glass-border); border-radius: 8px; padding: 0.4rem 0.6rem;">
                            <div style="font-size: 0.78rem; color: var(--text-secondary); margin-bottom: 0.25rem;">Payment Method:</div>
                            <div style="display: flex; gap: 0.75rem; align-items: center; flex-wrap: wrap;">
                                <label style="font-size: 0.8rem; color: #fff; cursor: pointer; display: flex; align-items: center; gap: 0.25rem;">
                                    <input type="radio" name="payment_method" value="COD" checked> Cash on Delivery
                                </label>
                                <label style="font-size: 0.8rem; color: #fff; cursor: pointer; display: flex; align-items: center; gap: 0.25rem;">
                                    <input type="radio" name="payment_method" value="Online Payment"> Online Payment
                                </label>
                            </div>
                        </div>

                        <button type="submit" name="order" class="btn btn-primary" style="width: 100%; padding: 0.5rem 1rem;">Order Now</button>
                    </form>
                </div>
            <?php endwhile; ?>
        </div>

        <h3 style="margin-top: 3rem; margin-bottom: 1rem;">Your Medicine Orders</h3>
        <div class="glass-panel" style="overflow-x: auto; padding: 1rem;">
            <table style="width: 100%; text-align: left; border-collapse: collapse;">
                <thead>
                    <tr style="border-bottom: 1px solid var(--glass-border);">
                        <th style="padding: 1rem;">Order ID</th>
                        <th style="padding: 1rem;">Medicine</th>
                        <th style="padding: 1rem;">Qty</th>
                        <th style="padding: 1rem;">Total Amount</th>
                        <th style="padding: 1rem;">Payment Method</th>
                        <th style="padding: 1rem;">Payment Status</th>
                        <th style="padding: 1rem;">Date Ordered</th>
                        <th style="padding: 1rem;">Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($my_orders && $my_orders->num_rows > 0): ?>
                        <?php while($o = $my_orders->fetch_assoc()): ?>
                            <tr style="border-bottom: 1px solid rgba(255,255,255,0.05);">
                                <td style="padding: 1rem;">#<?php echo $o['id']; ?></td>
                                <td style="padding: 1rem; font-weight: bold; color: var(--primary-color);"><?php echo htmlspecialchars($o['medicine_name']); ?></td>
                                <td style="padding: 1rem;"><?php echo $o['quantity']; ?></td>
                                <td style="padding: 1rem; color: var(--secondary-color);">₹<?php echo $o['total_amount']; ?></td>
                                <td style="padding: 1rem; font-size: 0.9rem; color: var(--text-secondary);"><?php echo htmlspecialchars($o['payment_method'] ?? 'COD'); ?></td>
                                <td style="padding: 1rem;">
                                    <?php
                                        $pay_status = $o['payment_status'] ?? 'Cash on Delivery';
                                        $pay_color = '#f5a623';
                                        if ($pay_status === 'Paid') $pay_color = '#2ed573';
                                        if ($pay_status === 'Failed') $pay_color = '#ff4757';
                                        if ($pay_status === 'Cash on Delivery') $pay_color = '#3498db';
                                    ?>
                                    <span style="color: <?php echo $pay_color; ?>; font-weight: bold; font-size: 0.82rem;">
                                        <?php echo htmlspecialchars($pay_status); ?>
                                    </span>
                                    <?php if ($pay_status === 'Pending' && ($o['payment_method'] ?? '') === 'Online Payment'): ?>
                                        <button onclick="retryPayment(<?php echo $o['id']; ?>, <?php echo $o['total_amount']; ?>, '<?php echo htmlspecialchars($o['medicine_name']); ?>')" class="btn btn-primary" style="padding: 0.2rem 0.6rem; font-size: 0.75rem; margin-left: 0.5rem;">Pay Again</button>
                                    <?php endif; ?>
                                </td>
                                <td style="padding: 1rem;"><?php echo date('M d, Y', strtotime($o['created_at'])); ?></td>
                                <td style="padding: 1rem;">
                                    <?php
                                        $status_color = 'var(--text-primary)';
                                        if ($o['status'] == 'pending') $status_color = 'var(--accent)';
                                        if ($o['status'] == 'shipped') $status_color = '#3498db';
                                        if ($o['status'] == 'delivered') $status_color = '#2ed573';
                                        if ($o['status'] == 'cancelled') $status_color = '#ff4757';
                                    ?>
                                    <span style="color: <?php echo $status_color; ?>; font-weight: bold; text-transform: capitalize; background: rgba(255,255,255,0.05); padding: 0.3rem 0.8rem; border-radius: 12px; font-size: 0.8rem;">
                                        <?php echo $o['status']; ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="8" style="padding: 1rem; text-align: center;">You have not ordered any medicines yet.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </main>
</div>

<!-- Payment Modal Dialog for Test/Sandbox Mode & Fallback -->
<div id="paymentGatewayModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.75); backdrop-filter: blur(8px); z-index: 9999; align-items: center; justify-content: center;">
    <div class="glass-panel" style="background: #1a1f2c; border: 1px solid var(--glass-border); width: 90%; max-width: 440px; padding: 2rem; border-radius: 16px; box-shadow: 0 20px 50px rgba(0,0,0,0.5); text-align: center;">
        <div style="display: flex; align-items: center; justify-content: center; gap: 0.5rem; margin-bottom: 1rem;">
            <i class="fas fa-shield-alt" style="color: var(--secondary-color); font-size: 1.8rem;"></i>
            <h3 style="color: #fff; margin: 0;">Online Payment Gateway</h3>
        </div>
        <p style="color: var(--text-secondary); font-size: 0.9rem; margin-bottom: 1rem;" id="modalMedName">Medicine Payment</p>
        <div style="font-size: 2rem; font-weight: bold; color: var(--secondary-color); margin-bottom: 1.5rem;" id="modalAmount">₹0.00</div>
        
        <div style="background: rgba(255,255,255,0.04); border: 1px solid var(--glass-border); border-radius: 10px; padding: 1rem; margin-bottom: 1.5rem; text-align: left;">
            <div style="font-size: 0.85rem; color: var(--text-secondary); margin-bottom: 0.5rem; font-weight: bold;">Select Payment Type:</div>
            <label style="display: flex; align-items: center; gap: 0.5rem; font-size: 0.85rem; color: #fff; margin-bottom: 0.5rem; cursor: pointer;">
                <input type="radio" name="pay_type" value="upi" checked> <i class="fas fa-mobile-alt" style="color: #2ed573;"></i> UPI / GPay / PhonePe / Paytm
            </label>
            <label style="display: flex; align-items: center; gap: 0.5rem; font-size: 0.85rem; color: #fff; margin-bottom: 0.5rem; cursor: pointer;">
                <input type="radio" name="pay_type" value="card"> <i class="fas fa-credit-card" style="color: #3498db;"></i> Credit / Debit Card
            </label>
            <label style="display: flex; align-items: center; gap: 0.5rem; font-size: 0.85rem; color: #fff; cursor: pointer;">
                <input type="radio" name="pay_type" value="netbanking"> <i class="fas fa-university" style="color: #f5a623;"></i> Net Banking
            </label>
        </div>

        <div style="display: flex; gap: 0.75rem;">
            <button id="btnCancelPay" class="btn" style="flex: 1; background: rgba(255,255,255,0.1); color: #fff;">Cancel</button>
            <button id="btnConfirmPay" class="btn btn-primary" style="flex: 1.5;">Complete Payment</button>
        </div>
    </div>
</div>

<?php if ($online_order_data): ?>
<script>
document.addEventListener("DOMContentLoaded", function() {
    triggerRazorpayCheckout(
        <?php echo $online_order_data['order_id']; ?>,
        <?php echo $online_order_data['amount_paise']; ?>,
        "<?php echo htmlspecialchars($online_order_data['medicine_name']); ?>"
    );
});
</script>
<?php endif; ?>

<script>
var activeOrderId = null;

function triggerRazorpayCheckout(orderId, amountPaise, medicineName) {
    activeOrderId = orderId;
    var razorpayKey = "<?php echo RAZORPAY_KEY_ID; ?>";
    
    // If Key ID is default sample placeholder, fallback to sleek built-in Payment Gateway Modal
    if (!razorpayKey || razorpayKey.indexOf('samplekey') !== -1 || razorpayKey === 'rzp_test_samplekeyid') {
        openDemoPaymentModal(orderId, (amountPaise / 100).toFixed(2), medicineName);
        return;
    }

    try {
        var options = {
            "key": razorpayKey,
            "amount": amountPaise,
            "currency": "INR",
            "name": "MedicalAk Medicine Delivery",
            "description": "Payment for " + medicineName,
            "handler": function (response){
                submitPaymentVerification(orderId, response.razorpay_payment_id || ('pay_test_' + Date.now()), response.razorpay_order_id || '', response.razorpay_signature || '');
            },
            "modal": {
                "ondismiss": function() {
                    alert('Payment window closed. You can click "Pay Again" in your order list anytime.');
                }
            },
            "prefill": {
                "name": "Patient User",
                "email": "patient@medicalak.com"
            },
            "theme": {
                "color": "#4a90e2"
            }
        };
        var rzp1 = new Razorpay(options);
        rzp1.on('payment.failed', function (response){
            console.warn('Razorpay checkout failed, opening fallback gateway modal...', response);
            openDemoPaymentModal(orderId, (amountPaise / 100).toFixed(2), medicineName);
        });
        rzp1.open();
    } catch (e) {
        openDemoPaymentModal(orderId, (amountPaise / 100).toFixed(2), medicineName);
    }
}

function openDemoPaymentModal(orderId, amountDisplay, medicineName) {
    activeOrderId = orderId;
    document.getElementById('modalMedName').innerText = "Payment for " + medicineName;
    document.getElementById('modalAmount').innerText = "₹" + amountDisplay;
    document.getElementById('paymentGatewayModal').style.display = 'flex';
}

document.getElementById('btnCancelPay').addEventListener('click', function() {
    document.getElementById('paymentGatewayModal').style.display = 'none';
    alert('Payment cancelled. Your order remains Pending. You can click "Pay Again" anytime.');
});

document.getElementById('btnConfirmPay').addEventListener('click', function() {
    if (!activeOrderId) return;
    document.getElementById('paymentGatewayModal').style.display = 'none';
    
    var simPaymentId = 'pay_online_' + Date.now();
    submitPaymentVerification(activeOrderId, simPaymentId, 'order_online_' + Date.now(), 'simulated_sig_' + Date.now());
});

function submitPaymentVerification(orderId, paymentId, razorpayOrderId, signature) {
    fetch('verify_payment.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({
            order_id: orderId,
            razorpay_payment_id: paymentId,
            razorpay_order_id: razorpayOrderId,
            razorpay_signature: signature
        })
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            window.location.href = 'medicines.php?success=1';
        } else {
            alert('Payment verification error: ' + data.message);
            window.location.href = 'medicines.php';
        }
    })
    .catch(err => {
        alert('Connection error during verification. Please refresh.');
        window.location.href = 'medicines.php';
    });
}

function retryPayment(orderId, totalAmount, medicineName) {
    var amountPaise = Math.round(totalAmount * 100);
    triggerRazorpayCheckout(orderId, amountPaise, medicineName);
}
</script>

<?php include 'includes/footer.php'; ?>
