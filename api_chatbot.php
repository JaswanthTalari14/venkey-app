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

if (empty($userMessage)) {
    echo json_encode(['error' => 'Message cannot be empty.']);
    exit;
}

// Natural Conversational & Medical Guidance Engine
function getSmartFallbackReply($msg) {
    $lower = strtolower($msg);
    
    // Greetings & Small Talk
    if (preg_match('/\b(hi|hii|hiii|hello|heyy|hey|greetings|good morning|good afternoon|good evening)\b/i', $lower) || $lower === 'hi' || $lower === 'hii') {
        return "Hello! 👋 How are you feeling today? I am your MedicalAk AI Health Assistant. I can help check your symptoms, answer health questions, or guide you through ordering medicines and booking doctor consultations. How can I help you today?";
    }
    
    if (strpos($lower, 'how are you') !== false || strpos($lower, 'how r u') !== false) {
        return "I'm doing great, thank you for asking! 😊 How are you feeling today? Is there any health concern or service I can help you with?";
    }
    
    if (strpos($lower, 'who are you') !== false || strpos($lower, 'what is your name') !== false || strpos($lower, 'what can you do') !== false) {
        return "I am MedicalAk AI, your personal 24/7 Health Assistant! I'm here to help you assess symptoms, offer health advice, and guide you to our doctors, medicine delivery, and lab test services.";
    }

    // Symptoms Guidance
    if (strpos($lower, 'fever') !== false || strpos($lower, 'temperature') !== false || strpos($lower, 'hot') !== false) {
        return "I understand you're dealing with a fever. Please get plenty of rest and drink lots of fluids. If your temperature stays above 102°F or lasts more than 3 days, please click **Consultations** on the left menu to consult a doctor right away.";
    }
    if (strpos($lower, 'headache') !== false || strpos($lower, 'head pain') !== false || strpos($lower, 'migraine') !== false) {
        return "For a headache, resting in a quiet, dark room and staying hydrated often helps. If your headache is severe, sudden, or accompanied by nausea or vision changes, please book an urgent consultation via the **Consultations** tab.";
    }
    if (strpos($lower, 'stomach') !== false || strpos($lower, 'belly') !== false || strpos($lower, 'nausea') !== false || strpos($lower, 'cramp') !== false || strpos($lower, 'vomit') !== false) {
        return "Stomach discomfort can happen due to indigestion or infection. Try taking small sips of warm water and avoid spicy food. If you experience sharp, persistent pain, please book a doctor visit under **Consultations** immediately.";
    }
    if (strpos($lower, 'cough') !== false || strpos($lower, 'cold') !== false || strpos($lower, 'throat') !== false || strpos($lower, 'flu') !== false || strpos($lower, 'sneez') !== false) {
        return "For a cold or cough, sip warm fluids and gargle with warm salt water. You can also get over-the-counter cough remedies delivered to your home by clicking **Order Medicines**.";
    }

    // Platform Services Navigation
    if (strpos($lower, 'medicine') !== false || strpos($lower, 'pill') !== false || strpos($lower, 'tablet') !== false || strpos($lower, 'pharmacy') !== false || strpos($lower, 'syrup') !== false) {
        return "To order prescribed or over-the-counter medicines delivered directly to your doorstep, click **Order Medicines** on your patient menu.";
    }
    if (strpos($lower, 'doctor') !== false || strpos($lower, 'consult') !== false || strpos($lower, 'appointment') !== false) {
        return "You can schedule online video consultations or door-to-door doctor visits easily by clicking **Consultations** on the left menu.";
    }
    if (strpos($lower, 'lab') !== false || strpos($lower, 'test') !== false || strpos($lower, 'blood') !== false || strpos($lower, 'rmp') !== false) {
        return "To book a home lab test with an RMP, navigate to the **Book Labs (RMP)** tab on your patient dashboard.";
    }
    if (strpos($lower, 'nearby') !== false || strpos($lower, 'clinic') !== false || strpos($lower, 'location') !== false || strpos($lower, '10km') !== false) {
        return "To find offline verified doctors and clinics within a 10km radius, click the **Find Doctors (10km)** tab.";
    }
    if (strpos($lower, 'privacy') !== false || strpos($lower, 'secret') !== false || strpos($lower, 'anonymous') !== false) {
        return "For discreet or confidential health queries, you can submit an image and description privately under **Privacy Consult**.";
    }
    if (strpos($lower, 'thank') !== false || strpos($lower, 'thx') !== false || strpos($lower, 'awesome') !== false) {
        return "You're very welcome! 😊 I am always here to assist you with your health and platform navigation on MedicalAk.";
    }
    
    // Check Patient Database Real-Record Queries (Feature Group 28)
    if (isset($_SESSION['user_id'])) {
        $patient_id = (int)$_SESSION['user_id'];
        global $conn;

        // Order / Tracking query
        if (strpos($lower, 'order') !== false || strpos($lower, 'track') !== false || strpos($lower, 'delivery') !== false) {
            $ord_q = $conn->query("SELECT * FROM orders WHERE patient_id = $patient_id AND (is_deleted IS NULL OR is_deleted = 0) ORDER BY id DESC LIMIT 1");
            if ($ord_q && $ord_q->num_rows > 0) {
                $ord = $ord_q->fetch_assoc();
                $st = ucfirst($ord['status'] ?? 'Pending');
                return "Your latest order (#ORD-" . $ord['id'] . ") placed on " . date('M d, Y', strtotime($ord['created_at'])) . " is currently **" . $st . "**. Total: ₹" . number_format($ord['total_amount'], 2) . ". You can view full tracking details under **Your Orders**.";
            } else {
                return "You have no active orders in your account.";
            }
        }

        // Wallet query
        if (strpos($lower, 'wallet') !== false || strpos($lower, 'balance') !== false) {
            $wal_q = $conn->query("SELECT balance FROM wallets WHERE customer_id = $patient_id LIMIT 1");
            if ($wal_q && $wal_q->num_rows > 0) {
                $wal = $wal_q->fetch_assoc();
                return "Your current MedicalAk Wallet balance is **₹" . number_format($wal['balance'], 2) . "**. You can manage top-ups or transactions under **My Wallet**.";
            } else {
                return "Your wallet balance is currently **₹0.00**. You can top up your wallet under **My Wallet**.";
            }
        }

        // Digital Medical Card query
        if (strpos($lower, 'medical card') !== false || strpos($lower, 'card') !== false || strpos($lower, 'dmc') !== false) {
            $card_q = $conn->query("SELECT * FROM digital_medical_cards WHERE patient_id = $patient_id ORDER BY id DESC LIMIT 1");
            if ($card_q && $card_q->num_rows > 0) {
                $card = $card_q->fetch_assoc();
                $c_num = $card['card_number'] ?: ('DMC-PENDING-' . $card['id']);
                $c_st = ucfirst($card['status']);
                return "Your Digital Medical Card (" . htmlspecialchars($c_num) . ") status is **" . $c_st . "**. " . ($card['status'] === 'active' && !empty($card['valid_until']) ? "Valid until " . date('M d, Y', strtotime($card['valid_until'])) . "." : "Check details under **Digital Medical Card**.");
            } else {
                return "You haven't applied for a Digital Medical Card yet. You can apply for ₹50 under **Digital Medical Card** to unlock healthcare discounts!";
            }
        }

        // Prescription query
        if (strpos($lower, 'prescription') !== false || strpos($lower, 'last rx') !== false) {
            $rx_q = $conn->query("SELECT p.*, u.name as doctor_name FROM prescriptions p JOIN users u ON p.doctor_id = u.id WHERE p.patient_id = $patient_id ORDER BY p.created_at DESC LIMIT 1");
            if ($rx_q && $rx_q->num_rows > 0) {
                $rx = $rx_q->fetch_assoc();
                return "Your latest prescription (Rx #" . $rx['id'] . ") was issued by Dr. " . htmlspecialchars($rx['doctor_name']) . " on " . date('M d, Y', strtotime($rx['consultation_date'])) . ". Note: " . htmlspecialchars($rx['notes'] ?: 'No special notes');
            } else {
                return "I couldn't find any prescription records in your profile.";
            }
        }

        // Appointment query
        if (strpos($lower, 'appointment') !== false || strpos($lower, 'next visit') !== false || strpos($lower, 'doctor visit') !== false) {
            $app_q = $conn->query("SELECT a.*, u.name as doctor_name FROM appointments a JOIN users u ON a.doctor_id = u.id WHERE a.patient_id = $patient_id AND a.status NOT IN ('cancelled', 'completed') ORDER BY a.appointment_date ASC LIMIT 1");
            if ($app_q && $app_q->num_rows > 0) {
                $app = $app_q->fetch_assoc();
                return "Your next appointment is with Dr. " . htmlspecialchars($app['doctor_name']) . " on " . date('M d, Y', strtotime($app['appointment_date'])) . " at " . date('h:i A', strtotime($app['appointment_time'])) . ". Status: " . ucfirst($app['status']) . ".";
            } else {
                return "You currently have no upcoming appointments scheduled.";
            }
        }

        // Report / Document query
        if (strpos($lower, 'report') !== false || strpos($lower, 'lab test') !== false || strpos($lower, 'x-ray') !== false) {
            $doc_q = $conn->query("SELECT * FROM medical_documents WHERE patient_id = $patient_id ORDER BY created_at DESC LIMIT 1");
            if ($doc_q && $doc_q->num_rows > 0) {
                $doc = $doc_q->fetch_assoc();
                return "Your latest uploaded report is \"" . htmlspecialchars($doc['title']) . "\" (" . htmlspecialchars($doc['category']) . ") uploaded on " . date('M d, Y', strtotime($doc['created_at'])) . ". You can view it in your Health Vault under Documents.";
            } else {
                return "I couldn't find any lab or medical report documents in your records.";
            }
        }
    }

    return "I'm right here to assist you! If you have any health symptoms or questions about our platform services (like ordering medicines, booking doctors, tracking orders, or checking your wallet), feel free to ask me.";
}

$apiKey = isset($gemini_api_key) ? trim($gemini_api_key) : '';

if (!empty($apiKey) && $apiKey !== 'YOUR_GEMINI_API_KEY_HERE') {
    $modelsToTry = [
        'gemini-1.5-flash',
        'gemini-2.0-flash',
        'gemini-2.5-flash'
    ];
    
    $systemInstruction = "You are MedicalAk AI, a warm, empathetic, human-like, and friendly health assistant for the MedicalAk platform. 
Always chat naturally like a caring human assistant. 
Platform layout: 
- 'Consultations' to book video/door-to-door doctors 
- 'Order Medicines' to get pharmacy delivered
- 'Find Doctors (10km)' to see nearby offline doctors
- 'Book Labs (RMP)' to schedule a home lab test via an RMP.
- 'Privacy Consult' for discreet or sensitive consultations.
- 'Digital Medical Card' for medical card discounts.
- 'Your Orders' to track medicine deliveries.
- 'My Wallet' to check or top up wallet balance.

When the user greets you (like 'hi', 'hii', 'hello', 'how are you'), respond warmly and conversationally as a friendly human assistant.
When a user asks medical or health questions, give clear, comforting, easy-to-understand advice.
Keep your responses brief, friendly, helpful, and concise (maximum 2 to 3 sentences).";

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
            "temperature" => 0.7,
            "maxOutputTokens" => 300
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

// Fallback if API fails or key unavailable
$fallbackReply = getSmartFallbackReply($userMessage);
echo json_encode(['reply' => $fallbackReply]);
?>
