<?php
require_once 'config.php';

header('Content-Type: application/json');

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['error' => 'Invalid request method.']);
    exit;
}

// Get raw POST input
$rawData = file_get_contents('php://input');
$data = json_decode($rawData, true);

$userMessage = isset($data['message']) ? trim($data['message']) : '';
$history = isset($data['history']) && is_array($data['history']) ? $data['history'] : [];

if (empty($userMessage)) {
    echo json_encode(['error' => 'Message cannot be empty.']);
    exit;
}

// Detect Authenticated User Context & Role
$user_id = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;
$user_role = strtolower($_SESSION['role'] ?? 'patient');
$user_name = htmlspecialchars($_SESSION['name'] ?? 'User');

// Helper function to extract past context from chat history
function getContextKeyword($history) {
    if (empty($history)) return '';
    $combined = '';
    foreach (array_reverse($history) as $h) {
        $combined .= ' ' . strtolower($h['text'] ?? '');
    }
    if (strpos($combined, 'medical card') !== false || strpos($combined, 'card') !== false) return 'card';
    if (strpos($combined, 'wallet') !== false || strpos($combined, 'topup') !== false) return 'wallet';
    if (strpos($combined, 'order') !== false || strpos($combined, 'medicine') !== false) return 'order';
    if (strpos($combined, 'appointment') !== false || strpos($combined, 'consult') !== false) return 'appointment';
    if (strpos($combined, 'prescription') !== false || strpos($combined, 'rx') !== false) return 'prescription';
    if (strpos($combined, 'referral') !== false || strpos($combined, 'refer') !== false) return 'referral';
    if (strpos($combined, 'ticket') !== false || strpos($combined, 'support') !== false) return 'support';
    return '';
}

// Smart Knowledge & Feature Explanation Engine
function getSmartKnowledgeReply($msg, $userId, $userRole, $history = []) {
    global $conn;
    $lower = strtolower($msg);
    $prevContext = getContextKeyword($history);

    // 1. GREETINGS & SMALL TALK
    if (preg_match('/\b(hi|hii|hiii|hello|heyy|hey|greetings|good morning|good afternoon|good evening)\b/i', $lower) || $lower === 'hi' || $lower === 'hii') {
        return "Hello " . htmlspecialchars($_SESSION['name'] ?? '') . "! 👋 Welcome to MedicalAk AI.\n\nI am your **MedicalAk Website Guide + Healthcare Platform Assistant**.\n\nI can help you check your symptoms, answer medical queries, guide you step-by-step through every feature available to your role (**" . ucfirst($userRole) . "**), or look up your real-time order, wallet, and appointment statuses!\n\nHow can I assist you today?";
    }

    if (strpos($lower, 'how are you') !== false || strpos($lower, 'how r u') !== false) {
        return "I'm doing great, thank you for asking! 😊 How are you feeling today? Ask me any question about your health or how to use features on MedicalAk!";
    }

    if (strpos($lower, 'who are you') !== false || strpos($lower, 'what can you do') !== false || strpos($lower, 'what is your name') !== false) {
        return "I am **MedicalAk AI**, your 24/7 personal Website Guide and Healthcare Assistant!\n\nHere is what I can do for you:\n- 📖 **Step-by-step guidance** on all MedicalAk features.\n- 🔍 **Live status lookup** for your orders, wallet balance, appointments, prescriptions, and medical card.\n- 🩺 **Health symptom guidance** and specialist doctor recommendations.";
    }

    // 2. OVERVIEW: "WHAT CAN I DO ON MEDICALAK?"
    if (strpos($lower, 'what can i do') !== false || strpos($lower, 'features available') !== false || strpos($lower, 'overview') !== false || strpos($lower, 'what features') !== false) {
        if ($userRole === 'doctor') {
            return "🎯 **As a Doctor on MedicalAk, you have access to:**\n\n- 👨‍⚕️ **Doctor Profile & Availability** ([profile.php](profile.php))\n- 📋 **Consultation Desk Workspace** ([doctor_workspace.php](doctor_workspace.php))\n- 📅 **Appointment Management** ([doctor_appointments.php](doctor_appointments.php))\n- 🔄 **Patient Referrals** ([doctor_referrals.php](doctor_referrals.php))\n- 💊 **Manage Medicines Catalogue** ([doctor_medicines.php](doctor_medicines.php))\n- 📦 **Medicine Orders Management** ([doctor_orders.php](doctor_orders.php))\n- 🕵️ **Anonymous Privacy Queries** ([doctor_queries.php](doctor_queries.php))\n- 📄 **Prescription Vault** ([prescription_vault.php](prescription_vault.php))\n- 💳 **Earnings & Payouts** ([payment_history.php](payment_history.php))\n\nAsk me how to use any of these features for step-by-step instructions!";
        } elseif ($userRole === 'rmp') {
            return "🎯 **As an RMP on MedicalAk, you have access to:**\n\n- 🩺 **RMP Profile & Settings** ([profile.php](profile.php))\n- 🧪 **Diagnostic Tests & Queue** ([rmp_dashboard.php](rmp_dashboard.php))\n- 📤 **Upload Test Results PDF/Image** ([rmp_upload.php](rmp_upload.php))\n- 👨‍⚕️ **Doctor Referrals** ([rmp_referral.php](rmp_referral.php))\n- 📅 **Patient Follow-up Reminders** ([rmp_dashboard.php](rmp_dashboard.php))\n- 💰 **Commission Earnings** ([payment_history.php](payment_history.php))\n\nAsk me how to use any feature for complete step-by-step instructions!";
        } else {
            return "🎯 **As a Patient on MedicalAk, you have access to:**\n\n- 🪪 **Digital Medical Card** ([digital_medical_card.php](digital_medical_card.php))\n- 💊 **Order Medicines & Delivery** ([medicines.php](medicines.php))\n- 📦 **Your Orders & Tracking** ([your_orders.php](your_orders.php))\n- 👨‍⚕️ **Book Consultations** ([book_consult.php](book_consult.php))\n- 📍 **Find Nearby Doctors (10km)** ([nearby_doctors.php](nearby_doctors.php))\n- 🕵️ **Anonymous Privacy Consult** ([privacy_consult.php](privacy_consult.php))\n- 🧪 **Book Lab Tests (RMP)** ([book_tests.php](book_tests.php))\n- 🔐 **Health Vault & Records** ([health_vault.php](health_vault.php))\n- 💰 **My Wallet & Top-Up** ([my_wallet.php](my_wallet.php))\n- 🎁 **Refer & Earn** ([refer_earn.php](refer_earn.php))\n- 💬 **Customer Support & Tickets** ([customer_support.php](customer_support.php))\n- 📱 **PWA Mobile App** ([manifest.json](manifest.json))\n\nAsk me how to use any feature for step-by-step instructions!";
        }
    }

    // 3. PERSONAL DATABASE RECORD QUERIES (Authenticated User Security)
    if ($userId > 0) {
        // Query: "Where is my order?" / "What is my order status?"
        if (preg_match('/\b(my order|track my order|order status|where is my order|latest order)\b/i', $lower)) {
            $ord_q = $conn->query("SELECT * FROM orders WHERE patient_id = $userId AND (is_deleted IS NULL OR is_deleted = 0) ORDER BY id DESC LIMIT 1");
            if ($ord_q && $ord_q->num_rows > 0) {
                $ord = $ord_q->fetch_assoc();
                $st = ucfirst($ord['status'] ?? 'Pending');
                $ordDate = date('M d, Y', strtotime($ord['created_at']));
                $total = number_format($ord['total_amount'], 2);
                return "📦 **Your Latest Order Status**\n\n" .
                       "- **Order ID:** #ORD-" . $ord['id'] . "\n" .
                       "- **Date:** " . $ordDate . "\n" .
                       "- **Status:** **" . $st . "**\n" .
                       "- **Total Amount:** ₹" . $total . "\n\n" .
                       "📍 **Where to track it:** Open [Your Orders](your_orders.php) to view full details and digital receipts.\n\n" .
                       "💡 *Order Workflow:* Pending → Processing → Shipped → Delivered.";
            } else {
                return "You currently have no orders placed in your account. You can browse and order medicines under [Order Medicines](medicines.php).";
            }
        }

        // Query: "My wallet balance" / "Why is topup pending?"
        if (preg_match('/\b(my wallet|wallet balance|topup status|top up status|why is my wallet|wallet pending)\b/i', $lower) || ($prevContext === 'wallet' && strpos($lower, 'pending') !== false)) {
            $wal_q = $conn->query("SELECT balance FROM wallets WHERE customer_id = $userId LIMIT 1");
            $bal = ($wal_q && $wal_q->num_rows > 0) ? number_format($wal_q->fetch_assoc()['balance'], 2) : '0.00';
            
            $top_q = $conn->query("SELECT * FROM wallet_topups WHERE user_id = $userId ORDER BY id DESC LIMIT 1");
            $top_status = "";
            if ($top_q && $top_q->num_rows > 0) {
                $top = $top_q->fetch_assoc();
                $top_status = "\n- **Latest Top-Up Request:** ₹" . number_format($top['amount'], 2) . " (" . ucfirst($top['status']) . ")";
            }

            return "💰 **Your MedicalAk Wallet Overview**\n\n" .
                   "- **Current Wallet Balance:** **₹" . $bal . "**" . $top_status . "\n\n" .
                   "📍 **Manage Wallet:** Open [My Wallet](my_wallet.php) to add funds or view transaction logs.\n\n" .
                   "⚠️ *Why Top-up May Be Pending:* Top-ups made via manual UPI or bank transfer require Admin verification before funds appear in your active balance. Once approved, your wallet updates instantly!";
        }

        // Query: "My Medical Card status" / "Medical card"
        if (preg_match('/\b(my medical card|card status|my card|check my card|medical card status)\b/i', $lower) || ($prevContext === 'card' && (strpos($lower, 'status') !== false || strpos($lower, 'pending') !== false))) {
            $card_q = $conn->query("SELECT * FROM digital_medical_cards WHERE patient_id = $userId ORDER BY id DESC LIMIT 1");
            if ($card_q && $card_q->num_rows > 0) {
                $card = $card_q->fetch_assoc();
                $c_num = $card['card_number'] ?: ('DMC-PENDING-' . $card['id']);
                $c_st = ucfirst($card['status']);
                $valid = (!empty($card['valid_until'])) ? date('M d, Y', strtotime($card['valid_until'])) : 'Under Verification';
                return "🪪 **Your Digital Medical Card Details**\n\n" .
                       "- **Card Number:** " . htmlspecialchars($c_num) . "\n" .
                       "- **Status:** **" . $c_st . "**\n" .
                       "- **Validity:** " . $valid . "\n\n" .
                       "📍 **View Card:** Open [Digital Medical Card](digital_medical_card.php) to view QR code or download your card PDF.\n\n" .
                       "⚠️ *Note:* Newly created cards remain Pending until verified by the MedicalAk Admin team.";
            } else {
                return "You haven't applied for a Digital Medical Card yet!\n\n📍 Open [Digital Medical Card](digital_medical_card.php) to apply and unlock emergency medical QR access.";
            }
        }

        // Query: "My prescription"
        if (preg_match('/\b(my prescription|last rx|my rx|show prescription|my doctor note)\b/i', $lower)) {
            $rx_q = $conn->query("SELECT p.*, u.name as doctor_name FROM prescriptions p JOIN users u ON p.doctor_id = u.id WHERE p.patient_id = $userId ORDER BY p.created_at DESC LIMIT 1");
            if ($rx_q && $rx_q->num_rows > 0) {
                $rx = $rx_q->fetch_assoc();
                return "📄 **Your Latest Issued Prescription**\n\n" .
                       "- **Prescription ID:** Rx #" . $rx['id'] . "\n" .
                       "- **Doctor:** Dr. " . htmlspecialchars($rx['doctor_name']) . "\n" .
                       "- **Date:** " . date('M d, Y', strtotime($rx['consultation_date'])) . "\n" .
                       "- **Notes:** " . htmlspecialchars($rx['notes'] ?: 'No additional notes') . "\n\n" .
                       "📍 **View All Prescriptions:** Open [Health Vault](health_vault.php) or [Prescription Vault](prescription_vault.php).";
            } else {
                return "I couldn't find any prescription records in your profile yet. Once a doctor completes your consultation, your digital prescription will appear automatically.";
            }
        }

        // Query: "My appointments"
        if (preg_match('/\b(my appointment|next visit|upcoming visit|my doctor appointment)\b/i', $lower)) {
            $app_q = $conn->query("SELECT a.*, u.name as doctor_name FROM appointments a JOIN users u ON a.doctor_id = u.id WHERE a.patient_id = $userId AND a.status NOT IN ('cancelled', 'completed') ORDER BY a.appointment_date ASC LIMIT 1");
            if ($app_q && $app_q->num_rows > 0) {
                $app = $app_q->fetch_assoc();
                return "📅 **Your Next Scheduled Appointment**\n\n" .
                       "- **Doctor:** Dr. " . htmlspecialchars($app['doctor_name']) . "\n" .
                       "- **Date:** " . date('M d, Y', strtotime($app['appointment_date'])) . "\n" .
                       "- **Time:** " . date('h:i A', strtotime($app['appointment_time'])) . "\n" .
                       "- **Status:** **" . ucfirst($app['status']) . "**\n\n" .
                       "📍 **Track Live Queue:** Check the Live Digital Queue Tracker on your [Patient Dashboard](patient_dashboard.php) or book new appointments under [Consultations](book_consult.php).";
            } else {
                return "You currently have no upcoming appointments scheduled. You can book an online or offline consultation under [Consultations](book_consult.php).";
            }
        }
    }

    // 4. ROLE RESTRICTION CHECKS
    if ($userRole === 'patient') {
        if (preg_match('/\b(create prescription|issue prescription|write prescription|add medicine stock|manage clinic)\b/i', $lower)) {
            return "⚠️ **Role Notice:** Creating prescriptions and managing pharmacy medicine stocks are Doctor-side capabilities on MedicalAk.\n\nAs a Patient, you can view your issued prescriptions under [Health Vault](health_vault.php) or order prescribed medicines under [Order Medicines](medicines.php).";
        }
        if (preg_match('/\b(upload test result|upload lab report for patient|rmp upload)\b/i', $lower)) {
            return "⚠️ **Role Notice:** Uploading official patient lab test results is an RMP practitioner capability.\n\nAs a Patient, you can upload your personal medical documents under [Health Vault](health_vault.php).";
        }
    }

    // 5. STEP-BY-STEP FEATURE GUIDES (7-Part Structure)

    // FEATURE A: DIGITAL MEDICAL CARD
    if (strpos($lower, 'medical card') !== false || strpos($lower, 'digital medical card') !== false || strpos($lower, 'dmc') !== false || ($prevContext === 'card' && strpos($lower, 'apply') !== false)) {
        return "🎫 **How to Apply & Use Digital Medical Card**\n\n" .
               "🎯 **What is it?**\n" .
               "The Digital Medical Card is a verified health card containing your unique Medical ID, QR code, blood group, emergency contact info, and medical history summary for emergency doctors.\n\n" .
               "📍 **Where to find it**\n" .
               "Log in to your Patient account → Open sidebar menu → Select [Digital Medical Card](digital_medical_card.php).\n\n" .
               "📝 **How to use it step by step**\n" .
               "1. Open [Digital Medical Card](digital_medical_card.php).\n" .
               "2. Review your assigned card details and QR code.\n" .
               "3. Tap **View QR Code** for clinic scan verification.\n" .
               "4. Click **Download Medical Card** to save or print a physical copy for offline emergency use.\n\n" .
               "💡 **Example**\n" .
               "When visiting a doctor or hospital, present your Medical Card QR code. The doctor can scan it to instantly view your verified emergency health profile.\n\n" .
               "✅ **What happens next?**\n" .
               "Newly issued digital medical cards display status as **Pending Approval** until verified by the MedicalAk Admin team. Once approved, status changes to **Active**.\n\n" .
               "⚠️ **Important**\n" .
               "Ensure your profile contact numbers and emergency contact info are updated under [Profile Settings](profile.php).\n\n" .
               "🆘 **If there is a problem**\n" .
               "If your card verification is delayed, contact support via [Customer Support](customer_support.php).";
    }

    // FEATURE B: ORDER MEDICINES
    if (strpos($lower, 'order medicine') !== false || strpos($lower, 'buy medicine') !== false || strpos($lower, 'pharmacy') !== false || strpos($lower, 'cart') !== false) {
        return "💊 **How to Order Medicines on MedicalAk**\n\n" .
               "🎯 **What is it?**\n" .
               "Search, browse, and buy prescribed or over-the-counter medicines delivered directly to your doorstep.\n\n" .
               "📍 **Where to find it**\n" .
               "Log in to your account → Open menu → Select [Order Medicines](medicines.php).\n\n" .
               "📝 **How to use it step by step**\n" .
               "1. Open [Order Medicines](medicines.php).\n" .
               "2. Search for medicines by name or filter by health category.\n" .
               "3. Select required quantity and click **Add to Cart**.\n" .
               "4. Open your **Cart** and review selected items.\n" .
               "5. Enter delivery address and select payment method (Wallet Balance or Online Payment Gateway).\n" .
               "6. Click **Place Order**.\n\n" .
               "💡 **Example**\n" .
               "To order Paracetamol 500mg, search for 'Paracetamol', select quantity 2, add to cart, enter address, select Wallet payment, and confirm checkout.\n\n" .
               "✅ **What happens next?**\n" .
               "Your order status updates to **Pending**, then **Processing**, **Shipped**, and final **Delivered** status. Track live progress under [Your Orders](your_orders.php).\n\n" .
               "⚠️ **Important**\n" .
               "Prescribed drugs require a valid prescription upload attached during checkout or uploaded in [Health Vault](health_vault.php).\n\n" .
               "🆘 **If there is a problem**\n" .
               "For order delivery issues, create a support ticket under [Customer Support](customer_support.php).";
    }

    // FEATURE C: BOOK CONSULTATION
    if (strpos($lower, 'book consult') !== false || strpos($lower, 'book doctor') !== false || strpos($lower, 'doctor appointment') !== false || strpos($lower, 'consultation') !== false) {
        return "👨‍⚕️ **How to Book Doctor Consultations**\n\n" .
               "🎯 **What is it?**\n" .
               "Schedule online video/chat consultations or book door-to-door offline home visit appointments with verified specialist doctors.\n\n" .
               "📍 **Where to find it**\n" .
               "Log in to your account → Open menu → Select [Consultations](book_consult.php).\n\n" .
               "📝 **How to use it step by step**\n" .
               "1. Open [Book Consultation](book_consult.php).\n" .
               "2. Choose consultation type: **Online Consultation** or **Offline Home Visit**.\n" .
               "3. Select specialty (General Physician, Cardiologist, Dermatologist, etc.) and choose a doctor.\n" .
               "4. Select preferred consultation date and available time slot.\n" .
               "5. Enter health symptoms or reason for visit.\n" .
               "6. Select payment method and confirm booking.\n\n" .
               "💡 **Example**\n" .
               "To consult a General Physician for cold/fever, select Online Consultation -> General Physician -> pick 10:00 AM slot -> pay via Wallet -> click Confirm.\n\n" .
               "✅ **What happens next?**\n" .
               "You receive a booking confirmation and queue token. Track live position on the Live Digital Queue Tracker on your [Patient Dashboard](patient_dashboard.php).\n\n" .
               "⚠️ **Important**\n" .
               "Log in 5 minutes before your online appointment or keep registered address accurate for home visits.";
    }

    // FEATURE D: MY WALLET & TOP-UP
    if (strpos($lower, 'wallet') !== false || strpos($lower, 'add money') !== false || strpos($lower, 'topup') !== false || strpos($lower, 'top up') !== false) {
        return "💰 **How to Use My Wallet & Top-Up Balance**\n\n" .
               "🎯 **What is it?**\n" .
               "MedicalAk Wallet is a built-in digital balance used for instant 1-click checkout on consultations, medicines, and lab tests.\n\n" .
               "📍 **Where to find it**\n" .
               "Log in to your account → Open menu → Select [My Wallet](my_wallet.php).\n\n" .
               "📝 **How to use it step by step**\n" .
               "1. Open [My Wallet](my_wallet.php).\n" .
               "2. Check your current wallet balance.\n" .
               "3. Enter amount to add (e.g. ₹500, ₹1000) and click **Top Up Wallet**.\n" .
               "4. Complete payment via UPI, PhonePe, or Payment Gateway.\n" .
               "5. View credit transaction log below.\n\n" .
               "💡 **Example**\n" .
               "To add ₹500 to your wallet, enter 500, choose PhonePe payment gateway, complete payment, and check your updated wallet balance.\n\n" .
               "✅ **What happens next?**\n" .
               "Wallet funds are credited immediately or upon Admin verification for manual transfers.\n\n" .
               "⚠️ **Important**\n" .
               "Refunds for cancelled medicine orders or appointments are credited straight back to your MedicalAk Wallet.";
    }

    // FEATURE E: REFER & EARN
    if (strpos($lower, 'refer') !== false || strpos($lower, 'referral') !== false || strpos($lower, 'earn') !== false || strpos($lower, 'invite') !== false) {
        return "🎁 **How to Use Refer & Earn**\n\n" .
               "🎯 **What is it?**\n" .
               "Invite friends and family to MedicalAk and earn cash rewards credited directly into your MedicalAk Wallet balance.\n\n" .
               "📍 **Where to find it**\n" .
               "Log in to your account → Open menu → Select [Refer & Earn](refer_earn.php).\n\n" .
               "📝 **How to use it step by step**\n" .
               "1. Open [Refer & Earn](refer_earn.php).\n" .
               "2. Copy your unique Referral Code or tap **Share on WhatsApp**.\n" .
               "3. Send your link to friends.\n" .
               "4. When friends register and complete a service, your referral reward is unlocked!\n\n" .
               "✅ **What happens next?**\n" .
               "Referral rewards credit automatically into your MedicalAk Wallet balance.";
    }

    // FEATURE F: PWA INSTALLATION
    if (strpos($lower, 'install') !== false || strpos($lower, 'pwa') !== false || strpos($lower, 'download app') !== false || strpos($lower, 'app') !== false) {
        return "📱 **How to Install MedicalAk Mobile PWA App**\n\n" .
               "🎯 **What is it?**\n" .
               "Install MedicalAk as a lightweight native app on your phone home screen without downloading from Play Store or App Store.\n\n" .
               "📍 **Where to find it**\n" .
               "Tap **Install App** in the sidebar menu or header navigation.\n\n" .
               "📝 **How to install step by step**\n" .
               "1. Tap **Install App** in your sidebar menu.\n" .
               "2. Confirm **Install** on your browser popup prompt.\n" .
               "3. Open MedicalAk directly from your mobile home screen app icon anytime!\n\n" .
               "⚠️ **iOS iPhone Users:** Tap Share icon -> Select **Add to Home Screen**.";
    }

    // FEATURE G: CUSTOMER SUPPORT & TICKETS
    if (strpos($lower, 'support') !== false || strpos($lower, 'ticket') !== false || strpos($lower, 'help') !== false || strpos($lower, 'whatsapp') !== false) {
        return "💬 **How to Use Customer Support & Support Tickets**\n\n" .
               "🎯 **What is it?**\n" .
               "Get 24/7 assistance through live WhatsApp chat or create tracked support tickets for payment, order, or technical help.\n\n" .
               "📍 **Where to find it**\n" .
               "Log in → Open menu → Select [Customer Support](customer_support.php) or [Create Ticket](create_ticket.php).\n\n" .
               "📝 **How to use it step by step**\n" .
               "1. Open [Customer Support](customer_support.php).\n" .
               "2. Tap **Chat on WhatsApp** for instant live assistance.\n" .
               "3. To submit a formal ticket, click [Create Ticket](create_ticket.php).\n" .
               "4. Choose category, enter subject & details, attach screenshots, and submit.\n" .
               "5. Track resolution status under [My Tickets](my_tickets.php).";
    }

    // FEATURE H: UNKNOWN OR NON-EXISTENT FEATURE GUARD
    if (strpos($lower, 'lab reports page') !== false || strpos($lower, 'doctor salary') !== false || strpos($lower, 'apk download') !== false) {
        return "⚠️ **Information Notice:** I cannot confirm that a separate '" . htmlspecialchars($msg) . "' page exists on MedicalAk.\n\nOn MedicalAk:\n- Uploaded lab test reports and medical records are stored in your [Health Vault](health_vault.php).\n- Medicine orders are tracked under [Your Orders](your_orders.php).\n- Platform guidance is available under [How to Use Guideline](guidelines.php).\n\nIf you need additional assistance, please reach out via [Customer Support](customer_support.php).";
    }

    // 6. SYMPTOMS & HEALTH SAFETY GUIDANCE
    if (preg_match('/\b(fever|temperature|headache|cough|cold|stomach|pain|nausea|vomit|diarrhea|throat|dizzy)\b/i', $lower)) {
        return "🩺 **Health & Symptom Guidance**\n\n" .
               "I understand you are experiencing health symptoms. Here is general guidance:\n\n" .
               "- **Stay Hydrated & Rest:** Drink plenty of fluids and get adequate rest.\n" .
               "- **Monitor Symptoms:** If symptoms worsen or persist for more than 48 hours, consult a verified medical professional immediately.\n" .
               "- **Book Consultation:** Click [Book Consultation](book_consult.php) to schedule an online video consultation or door-to-door doctor visit.\n" .
               "- **Find Nearby Doctors:** Click [Find Doctors (10km)](nearby_doctors.php) to locate offline doctors near you.\n\n" .
               "⚠️ *Disclaimer:* This AI assistant provides informational guidance only and does not replace professional medical diagnosis or emergency medical care.";
    }

    // DEFAULT HELPFUL FALLBACK RESPONSE
    return "I am here to assist you! 👋\n\nYou can ask me:\n- 📖 **\"How do I apply for Digital Medical Card?\"**\n- 💊 **\"How do I order medicine?\"**\n- 📦 **\"Where is my order?\"**\n- 💰 **\"What is my wallet balance?\"**\n- 📅 **\"Where is my next appointment?\"**\n- 📱 **\"How do I install the app?\"**\n\nHow can I help you right now?";
}

// Check Gemini API Call Configuration
$apiKey = isset($gemini_api_key) ? trim($gemini_api_key) : '';

if (!empty($apiKey) && $apiKey !== 'YOUR_GEMINI_API_KEY_HERE') {
    $modelsToTry = [
        'gemini-1.5-flash',
        'gemini-2.0-flash',
        'gemini-2.5-flash'
    ];

    $systemInstruction = "You are MedicalAk AI, an expert, warm, and highly structured MedicalAk Website Guide and Healthcare Assistant.
User Role: " . strtoupper($user_role) . "
User Name: " . $user_name . "

CRITICAL RULES:
1. When a user asks how to use ANY MedicalAk feature, ALWAYS provide a COMPLETE, STRUCTURED 7-part response:
   🎯 What is it?
   📍 Where to find it
   📝 How to use it (Step 1, Step 2, Step 3...)
   💡 Example
   ✅ What happens next?
   ⚠️ Important
   🆘 If there is a problem

2. Use exact MedicalAk navigation names and Markdown link buttons:
   - Digital Medical Card: [Digital Medical Card](digital_medical_card.php)
   - Order Medicines: [Order Medicines](medicines.php)
   - Your Orders: [Your Orders](your_orders.php)
   - Book Consultations: [Consultations](book_consult.php)
   - Find Nearby Doctors: [Find Doctors (10km)](nearby_doctors.php)
   - Anonymous Privacy Consult: [Privacy Consult](privacy_consult.php)
   - Book Lab Tests: [Book Labs (RMP)](book_tests.php)
   - Health Vault: [Health Vault](health_vault.php)
   - My Wallet: [My Wallet](my_wallet.php)
   - Refer & Earn: [Refer & Earn](refer_earn.php)
   - Customer Support: [Customer Support](customer_support.php)
   - Guideline: [Guideline](guidelines.php)

3. Strictly respect role permissions. (Patients cannot create doctor prescriptions; Doctors manage workspace; RMPs upload diagnostic lab tests).
4. Never invent fake prices, discounts, features, or invalid pages.
5. If medical symptoms are mentioned, give empathetic health advice and direct them to [Book Consultation](book_consult.php).";

    $requestData = [
        "system_instruction" => [
            "parts" => [
                ["text" => $systemInstruction]
            ]
        ],
        "contents" => [
            [
                "role" => "user",
                "parts" => [
                    ["text" => $userMessage]
                ]
            ]
        ],
        "generationConfig" => [
            "temperature" => 0.6,
            "maxOutputTokens" => 800
        ]
    ];

    foreach ($modelsToTry as $model) {
        $apiUrl = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key=" . $apiKey;

        $ch = curl_init($apiUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($requestData));
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 8);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode === 200 && !empty($response)) {
            $responseData = json_decode($response, true);
            if (isset($responseData['candidates'][0]['content']['parts'][0]['text'])) {
                $botReply = trim($responseData['candidates'][0]['content']['parts'][0]['text']);
                if (!empty($botReply)) {
                    echo json_encode(['reply' => $botReply]);
                    exit;
                }
            }
        }
    }
}

// Fallback to Smart Knowledge Engine if API is unconfigured or unavailable
$reply = getSmartKnowledgeReply($userMessage, $user_id, $user_role, $history);
echo json_encode(['reply' => $reply]);
?>
