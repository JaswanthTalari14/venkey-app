<?php
require_once 'config.php';

header('Content-Type: application/json');

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['error' => 'Invalid request method.']);
    exit;
}

// Get the raw POST data
$rawData = file_get_contents('php://input');
$data = json_decode($rawData, true);

$userMessage = isset($data['message']) ? trim($data['message']) : '';

if (empty($userMessage)) {
    echo json_encode(['error' => 'Message cannot be empty.']);
    exit;
}

if (empty($gemini_api_key) || $gemini_api_key === 'YOUR_GEMINI_API_KEY_HERE') {
    echo json_encode(['error' => 'API Key not configured. Please add your Gemini API key to config.php.']);
    exit;
}

$apiKey = $gemini_api_key;
$apiUrl = "https://generativelanguage.googleapis.com/v1beta/models/gemini-flash-latest:generateContent?key=" . $apiKey;

$systemInstruction = "You are MedicalAk AI, an advanced, highly knowledgeable, and friendly health assistant for the MedicalAk platform. 
Your purpose is to help users with medical queries and guide them through the platform. 
Platform layout: 
- 'Consultations' to book video/door-to-door doctors 
- 'Order Medicines' to get pharmacy delivered
- 'Find Doctors (10km)' to see nearby offline doctors
- 'Book Labs (RMP)' to schedule a home lab test via an RMP.
- 'Privacy Consult' for discreet or sensitive consultations.

When a user asks a medical question, give clear, scientifically accurate but easy-to-understand advice. If the condition sounds severe, urge them to book a consultation or go to the emergency room immediately. 

CRITICAL INSTRUCTION: You MUST give extremely short and concise answers. NEVER write long paragraphs. Your response must be an absolute maximum of 2 to 3 very brief sentences. Keep it straight to the point. Do not use markdown like **bold** or *italics* as the frontend might not render it perfectly. Be brief, helpful, and empathetic.";

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
        "maxOutputTokens" => 800
    ]
];

$ch = curl_init($apiUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($requestData));
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // For local XAMPP environments often lacking certs

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

if (curl_errno($ch)) {
    echo json_encode(['error' => 'Curl error: ' . curl_error($ch)]);
    curl_close($ch);
    exit;
}

curl_close($ch);

if ($httpCode !== 200) {
    // API returned an error, send back the raw response for debugging
    echo json_encode(['error' => 'API Error: HTTP ' . $httpCode . ' - Details: ' . $response]);
    exit;
}

$responseData = json_decode($response, true);

// Extract the text from Gemini response
if (isset($responseData['candidates'][0]['content']['parts'][0]['text'])) {
    $botReply = $responseData['candidates'][0]['content']['parts'][0]['text'];
    echo json_encode(['reply' => trim($botReply)]);
} else {
    echo json_encode(['error' => 'Failed to parse API response.']);
}
?>
