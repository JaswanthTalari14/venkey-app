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

// Smart Fallback Generator function for reliable medical guidance
function getSmartFallbackReply($msg) {
    $lower = strtolower($msg);
    
    if (strpos($lower, 'fever') !== false || strpos($lower, 'temperature') !== false || strpos($lower, 'hot') !== false) {
        return "I understand you are dealing with a fever. Please get plenty of rest and stay well-hydrated. If your temperature exceeds 102°F or lasts more than 3 days, please click **Consultations** on the left menu to consult a doctor immediately.";
    }
    if (strpos($lower, 'headache') !== false || strpos($lower, 'head pain') !== false || strpos($lower, 'migraine') !== false) {
        return "For a headache, rest in a quiet, dark room and drink water. If your headache is severe, sudden, or accompanied by nausea or vision changes, please book an urgent consultation via the **Consultations** tab.";
    }
    if (strpos($lower, 'stomach') !== false || strpos($lower, 'belly') !== false || strpos($lower, 'nausea') !== false || strpos($lower, 'cramp') !== false) {
        return "Stomach discomfort can be caused by indigestion or acidity. Stay hydrated with small sips of water. If you experience sharp, persistent pain or vomiting, please book a doctor visit in **Consultations** immediately.";
    }
    if (strpos($lower, 'cough') !== false || strpos($lower, 'cold') !== false || strpos($lower, 'throat') !== false || strpos($lower, 'flu') !== false) {
        return "For cough or throat irritation, sip warm fluids and gargle with warm salt water. You can also get over-the-counter syrups delivered home by clicking **Order Medicines**.";
    }
    if (strpos($lower, 'medicine') !== false || strpos($lower, 'pill') !== false || strpos($lower, 'tablet') !== false || strpos($lower, 'pharmacy') !== false) {
        return "To order prescribed or over-the-counter medicines delivered directly to your home, click **Order Medicines** on your patient menu.";
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
    if (preg_match('/\b(hi|hello|hey|greetings)\b/i', $lower)) {
        return "Hello! I am your 24/7 AI Health Assistant. How are you feeling today? Tell me about your symptoms or what service you need help with.";
    }
    if (strpos($lower, 'thank') !== false) {
        return "You are very welcome! I am always here to assist you with your health and platform navigation on MedicalAk.";
    }
    
    return "I am here to help you with symptoms and healthcare services. If you are experiencing mild symptoms, describe them to me. If your condition is urgent, please book a doctor via **Consultations** immediately.";
}

$apiKey = isset($gemini_api_key) ? trim($gemini_api_key) : '';

if (!empty($apiKey) && $apiKey !== 'YOUR_GEMINI_API_KEY_HERE') {
    $modelsToTry = [
        'gemini-1.5-flash',
        'gemini-2.0-flash',
        'gemini-2.5-flash'
    ];
    
    $systemInstruction = "You are MedicalAk AI, an advanced, highly knowledgeable, and friendly health assistant for the MedicalAk platform. 
Platform layout: 
- 'Consultations' to book video/door-to-door doctors 
- 'Order Medicines' to get pharmacy delivered
- 'Find Doctors (10km)' to see nearby offline doctors
- 'Book Labs (RMP)' to schedule a home lab test via an RMP.
- 'Privacy Consult' for discreet or sensitive consultations.

When a user asks a medical question, give clear, easy-to-understand advice. If the condition sounds severe, urge them to book a consultation immediately. 
CRITICAL INSTRUCTION: You MUST give short and concise answers (maximum 2 to 3 sentences). Keep it straight to the point.";

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
