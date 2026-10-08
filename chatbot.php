<?php
require_once 'config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'patient') {
    header("Location: login.php");
    exit;
}

$patient_name = htmlspecialchars($_SESSION['name'] ?? 'Patient');
$patient_id = (int)$_SESSION['user_id'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <title>MedicalAk AI Assistant — Full Screen Chat</title>
    
    <!-- DNS Preconnect & Font Optimization -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <!-- FontAwesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" crossorigin="anonymous" referrerpolicy="no-referrer">
    
    <!-- PWA Meta Tags -->
    <link rel="manifest" href="manifest.json">
    <meta name="theme-color" content="#0b0f19">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">

    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            -webkit-tap-highlight-color: transparent;
        }

        :root {
            --bg-dark: #0b0f19;
            --bg-surface: #0f172a;
            --bg-card: rgba(30, 41, 59, 0.7);
            --primary: #2563eb;
            --primary-gradient: linear-gradient(135deg, #2563eb 0%, #06b6d4 100%);
            --accent-cyan: #06b6d4;
            --accent-green: #10b981;
            --text-main: #f8fafc;
            --text-muted: #94a3b8;
            --border-glass: rgba(255, 255, 255, 0.1);
        }

        html, body {
            width: 100%;
            height: 100%;
            height: 100dvh;
            overflow: hidden;
            background-color: var(--bg-dark);
            font-family: 'Inter', system-ui, -apple-system, sans-serif;
            color: var(--text-main);
        }

        .ai-chat-app {
            display: flex;
            flex-direction: column;
            width: 100vw;
            height: 100vh;
            height: 100dvh;
            background: radial-gradient(circle at 50% 0%, #1e293b 0%, #0f172a 60%, #0b0f19 100%);
            position: relative;
            overflow: hidden;
        }

        /* --- Header --- */
        .chat-header {
            height: 68px;
            padding: 0 1.25rem;
            background: rgba(15, 23, 42, 0.85);
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            border-bottom: 1px solid var(--border-glass);
            display: flex;
            align-items: center;
            justify-content: space-between;
            z-index: 100;
            flex-shrink: 0;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.3);
        }

        .header-left {
            display: flex;
            align-items: center;
            gap: 0.85rem;
        }

        .nav-back-btn {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.08);
            border: 1px solid var(--border-glass);
            color: var(--text-main);
            display: flex;
            align-items: center;
            justify-content: center;
            text-decoration: none;
            font-size: 1rem;
            transition: all 0.2s ease;
        }

        .nav-back-btn:hover {
            background: rgba(255, 255, 255, 0.15);
            transform: translateX(-2px);
        }

        .ai-identity {
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .avatar-box {
            position: relative;
            width: 42px;
            height: 42px;
            border-radius: 50%;
            background: var(--primary-gradient);
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            font-size: 1.2rem;
            box-shadow: 0 0 16px rgba(6, 182, 212, 0.4);
            flex-shrink: 0;
        }

        .status-dot {
            position: absolute;
            bottom: 0;
            right: 0;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            background: var(--accent-green);
            border: 2px solid var(--bg-surface);
            box-shadow: 0 0 8px var(--accent-green);
        }

        .identity-text h1 {
            font-size: 1.05rem;
            font-weight: 700;
            color: #fff;
            line-height: 1.2;
            display: flex;
            align-items: center;
            gap: 0.4rem;
        }

        .identity-text .badge-ai {
            font-size: 0.65rem;
            background: rgba(6, 182, 212, 0.2);
            color: var(--accent-cyan);
            border: 1px solid rgba(6, 182, 212, 0.4);
            padding: 0.15rem 0.4rem;
            border-radius: 10px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .identity-text p {
            font-size: 0.78rem;
            color: var(--accent-green);
            display: flex;
            align-items: center;
            gap: 0.35rem;
            margin-top: 2px;
        }

        .header-actions {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .action-btn {
            background: rgba(255, 255, 255, 0.08);
            border: 1px solid var(--border-glass);
            color: var(--text-main);
            padding: 0.45rem 0.85rem;
            border-radius: 20px;
            font-size: 0.82rem;
            font-weight: 500;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 0.4rem;
            transition: all 0.2s ease;
        }

        .action-btn:hover {
            background: rgba(255, 255, 255, 0.15);
            border-color: rgba(255, 255, 255, 0.2);
        }

        /* --- Main Stream --- */
        .chat-main-stream {
            flex: 1;
            overflow-y: auto;
            padding: 1.25rem 1rem;
            scroll-behavior: smooth;
            position: relative;
        }

        .chat-inner-container {
            max-width: 820px;
            width: 100%;
            margin: 0 auto;
            display: flex;
            flex-direction: column;
            gap: 1.25rem;
            min-height: 100%;
            justify-content: flex-end;
        }

        /* --- Welcome Card --- */
        .welcome-card {
            background: rgba(15, 23, 42, 0.75);
            border: 1px solid var(--border-glass);
            border-radius: 20px;
            padding: 1.15rem 1.25rem;
            backdrop-filter: blur(12px);
            box-shadow: 0 12px 40px rgba(0, 0, 0, 0.4);
            margin-bottom: auto;
            animation: fadeIn 0.4s ease-out;
        }

        .welcome-header {
            display: flex;
            align-items: center;
            gap: 0.85rem;
            margin-bottom: 0.85rem;
        }

        .welcome-icon-box {
            width: 44px;
            height: 44px;
            border-radius: 14px;
            background: var(--primary-gradient);
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            font-size: 1.35rem;
            box-shadow: 0 8px 24px rgba(37, 99, 235, 0.4);
            flex-shrink: 0;
        }

        .welcome-title h2 {
            font-size: 1.15rem;
            font-weight: 700;
            color: #fff;
            margin: 0;
        }

        .welcome-title p {
            font-size: 0.82rem;
            color: var(--text-muted);
            margin-top: 0.15rem;
            line-height: 1.35;
        }

        .prompt-chips-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 0.5rem;
            margin-top: 0.75rem;
            max-height: 240px;
            overflow-y: auto;
            padding-right: 4px;
        }

        .prompt-chips-grid::-webkit-scrollbar {
            width: 4px;
        }
        .prompt-chips-grid::-webkit-scrollbar-thumb {
            background: rgba(255, 255, 255, 0.15);
            border-radius: 4px;
        }

        .chip-card {
            background: rgba(30, 41, 59, 0.6);
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 12px;
            padding: 0.55rem 0.7rem;
            color: var(--text-main);
            font-size: 0.8rem;
            font-weight: 500;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.2s ease;
            text-align: left;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .chip-card:hover {
            background: rgba(37, 99, 235, 0.22);
            border-color: rgba(37, 99, 235, 0.5);
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.25);
        }

        .chip-card i {
            font-size: 0.95rem;
            color: var(--accent-cyan);
            flex-shrink: 0;
        }

        /* --- Messages --- */
        .msg-row {
            display: flex;
            gap: 0.85rem;
            max-width: 85%;
            animation: slideUp 0.3s ease-out forwards;
        }

        .msg-row.user-msg {
            align-self: flex-end;
            flex-direction: row-reverse;
        }

        .msg-row.ai-msg {
            align-self: flex-start;
        }

        .msg-avatar {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            font-size: 0.95rem;
            flex-shrink: 0;
            margin-top: 2px;
        }

        .user-msg .msg-avatar {
            background: rgba(255, 255, 255, 0.12);
            border: 1px solid var(--border-glass);
            color: var(--text-muted);
        }

        .ai-msg .msg-avatar {
            background: var(--primary-gradient);
            box-shadow: 0 0 12px rgba(6, 182, 212, 0.3);
        }

        .msg-bubble-container {
            display: flex;
            flex-direction: column;
            gap: 0.35rem;
            max-width: 100%;
        }

        .msg-bubble {
            padding: 0.9rem 1.15rem;
            border-radius: 20px;
            font-size: 0.95rem;
            line-height: 1.55;
            position: relative;
            word-break: break-word;
        }

        .user-msg .msg-bubble {
            background: var(--primary-gradient);
            color: #ffffff;
            border-bottom-right-radius: 4px;
            box-shadow: 0 4px 16px rgba(37, 99, 235, 0.3);
        }

        .ai-msg .msg-bubble {
            background: rgba(30, 41, 59, 0.85);
            color: var(--text-main);
            border: 1px solid var(--border-glass);
            border-bottom-left-radius: 4px;
            backdrop-filter: blur(8px);
            box-shadow: 0 4px 16px rgba(0, 0, 0, 0.25);
        }

        .ai-sender-name {
            font-size: 0.75rem;
            font-weight: 700;
            color: var(--accent-cyan);
            margin-bottom: 0.35rem;
            letter-spacing: 0.3px;
        }

        .msg-footer {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 0.75rem;
            font-size: 0.72rem;
            color: var(--text-muted);
            padding: 0 0.2rem;
        }

        .user-msg .msg-footer {
            justify-content: flex-end;
        }

        .copy-btn {
            background: transparent;
            border: none;
            color: var(--text-muted);
            font-size: 0.72rem;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 0.3rem;
            padding: 2px 6px;
            border-radius: 6px;
            transition: all 0.2s ease;
        }

        .copy-btn:hover {
            color: var(--accent-cyan);
            background: rgba(255, 255, 255, 0.08);
        }

        /* --- Inline Formatting --- */
        .msg-bubble p {
            margin-bottom: 0.5rem;
        }

        .msg-bubble p:last-child {
            margin-bottom: 0;
        }

        .msg-bubble strong {
            color: #60a5fa;
            font-weight: 600;
        }

        .msg-bubble ul, .msg-bubble ol {
            margin: 0.5rem 0 0.5rem 1.25rem;
        }

        .msg-bubble li {
            margin-bottom: 0.25rem;
        }

        .chat-action-link {
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            background: rgba(37, 99, 235, 0.25);
            border: 1px solid rgba(37, 99, 235, 0.5);
            color: #93c5fd;
            padding: 0.3rem 0.75rem;
            border-radius: 14px;
            font-weight: 600;
            font-size: 0.85rem;
            text-decoration: none;
            margin: 0.3rem 0;
            transition: all 0.2s ease;
        }

        .chat-action-link:hover {
            background: var(--primary);
            color: #fff;
        }

        /* --- Typing State --- */
        .typing-bubble {
            display: flex;
            align-items: center;
            gap: 0.4rem;
            padding: 0.85rem 1.25rem;
        }

        .typing-dots {
            display: flex;
            align-items: center;
            gap: 4px;
        }

        .typing-dots span {
            width: 7px;
            height: 7px;
            border-radius: 50%;
            background: var(--accent-cyan);
            animation: pulseDot 1.4s infinite ease-in-out both;
        }

        .typing-dots span:nth-child(1) { animation-delay: -0.32s; }
        .typing-dots span:nth-child(2) { animation-delay: -0.16s; }

        @keyframes pulseDot {
            0%, 80%, 100% { transform: scale(0.4); opacity: 0.4; }
            40% { transform: scale(1); opacity: 1; }
        }

        /* --- Scroll Down Pill --- */
        .scroll-down-btn {
            position: absolute;
            bottom: 85px;
            right: 20px;
            background: rgba(15, 23, 42, 0.9);
            border: 1px solid var(--border-glass);
            color: #fff;
            padding: 0.55rem 1rem;
            border-radius: 25px;
            font-size: 0.82rem;
            font-weight: 600;
            cursor: pointer;
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.4);
            display: flex;
            align-items: center;
            gap: 0.4rem;
            z-index: 90;
            backdrop-filter: blur(8px);
            opacity: 0;
            pointer-events: none;
            transform: translateY(10px);
            transition: all 0.25s ease;
        }

        .scroll-down-btn.show {
            opacity: 1;
            pointer-events: auto;
            transform: translateY(0);
        }

        /* --- Bottom Composer --- */
        .composer-area {
            padding: 0.85rem 1rem 1.1rem 1rem;
            background: rgba(15, 23, 42, 0.95);
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            border-top: 1px solid var(--border-glass);
            z-index: 100;
            flex-shrink: 0;
        }

        .composer-inner {
            max-width: 820px;
            margin: 0 auto;
            display: flex;
            align-items: flex-end;
            gap: 0.65rem;
            background: rgba(30, 41, 59, 0.7);
            border: 1px solid rgba(255, 255, 255, 0.12);
            border-radius: 28px;
            padding: 0.4rem 0.5rem 0.4rem 1.15rem;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
            transition: border-color 0.2s ease, box-shadow 0.2s ease;
        }

        .composer-inner:focus-within {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.25);
        }

        .composer-input {
            flex: 1;
            border: none;
            background: transparent;
            color: #fff;
            font-size: 0.96rem;
            font-family: inherit;
            outline: none;
            padding: 0.6rem 0;
            resize: none;
            max-height: 120px;
            line-height: 1.45;
        }

        .composer-input::placeholder {
            color: var(--text-muted);
        }

        .send-btn {
            width: 44px;
            height: 44px;
            border-radius: 50%;
            background: var(--primary-gradient);
            border: none;
            color: #fff;
            font-size: 1.05rem;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.2s ease;
            box-shadow: 0 4px 14px rgba(37, 99, 235, 0.4);
            flex-shrink: 0;
        }

        .send-btn:hover {
            transform: scale(1.05);
            box-shadow: 0 6px 20px rgba(6, 182, 212, 0.5);
        }

        .send-btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
            transform: none;
        }

        /* --- Animations --- */
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(8px); }
            to { opacity: 1; transform: translateY(0); }
        }

        @keyframes slideUp {
            from { opacity: 0; transform: translateY(12px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* Responsive Breakpoints */
        @media (max-width: 600px) {
            .chat-header {
                height: 62px;
                padding: 0 0.85rem;
            }
            .identity-text h1 {
                font-size: 0.95rem;
            }
            .identity-text p {
                font-size: 0.72rem;
            }
            .action-btn span {
                display: none;
            }
            .action-btn {
                padding: 0.45rem 0.6rem;
                border-radius: 50%;
                width: 36px;
                height: 36px;
                justify-content: center;
            }
            .msg-row {
                max-width: 92%;
            }
            .welcome-card {
                padding: 1rem 0.85rem;
                border-radius: 16px;
            }
            .prompt-chips-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 0.45rem;
                max-height: 210px;
            }
            .chip-card {
                padding: 0.5rem 0.6rem;
                font-size: 0.78rem;
                border-radius: 10px;
            }
        }
    </style>
</head>
<body>

<div class="ai-chat-app">
    
    <!-- Fixed Chat Header -->
    <header class="chat-header">
        <div class="header-left">
            <a href="patient_dashboard.php" class="nav-back-btn" title="Back to Dashboard">
                <i class="fas fa-arrow-left"></i>
            </a>
            <div class="ai-identity">
                <div class="avatar-box">
                    <i class="fas fa-robot"></i>
                    <span class="status-dot"></span>
                </div>
                <div class="identity-text">
                    <h1>MedicalAk AI <span class="badge-ai">24/7 AI</span></h1>
                    <p><i class="fas fa-circle" style="font-size: 0.5rem;"></i> Online ● Healthcare Assistant</p>
                </div>
            </div>
        </div>

        <div class="header-actions">
            <button type="button" class="action-btn" onclick="startNewChat()" title="Start New Chat">
                <i class="fas fa-rotate-left"></i> <span>New Chat</span>
            </button>
            <a href="patient_dashboard.php" class="action-btn" title="Patient Dashboard">
                <i class="fas fa-th-large"></i> <span>Dashboard</span>
            </a>
        </div>
    </header>

    <!-- Scrollable Messages Area -->
    <main class="chat-main-stream" id="chat-main-stream">
        <div class="chat-inner-container" id="chat-inner-container">
            
            <!-- Welcome Screen Card (Shown when conversation starts) -->
            <div class="welcome-card" id="welcome-card">
                <div class="welcome-header">
                    <div class="welcome-icon-box">
                        <i class="fas fa-robot"></i>
                    </div>
                    <div class="welcome-title">
                        <h2>Hello <?php echo $patient_name; ?>! 👋</h2>
                        <p>I am your MedicalAk AI Healthcare Assistant. Ask me symptoms, track orders, or navigate platform tools.</p>
                    </div>
                </div>

                <p style="font-size: 0.85rem; font-weight: 600; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.5px;">Quick Actions & Suggestions:</p>
                
                <div class="prompt-chips-grid">
                    <button type="button" class="chip-card" onclick="sendQuickPrompt('How to order medicines?')">
                        <i class="fas fa-pills"></i>
                        <span>💊 Order Medicines</span>
                    </button>
                    <button type="button" class="chip-card" onclick="sendQuickPrompt('How to book doctor consultation?')">
                        <i class="fas fa-user-md"></i>
                        <span>🩺 Book Doctor Visit</span>
                    </button>
                    <button type="button" class="chip-card" onclick="sendQuickPrompt('Track my order')">
                        <i class="fas fa-box"></i>
                        <span>📦 Track My Order</span>
                    </button>
                    <button type="button" class="chip-card" onclick="sendQuickPrompt('My Digital Medical Card status')">
                        <i class="fas fa-id-card"></i>
                        <span>🪪 View Medical Card</span>
                    </button>
                    <button type="button" class="chip-card" onclick="sendQuickPrompt('Check my wallet balance')">
                        <i class="fas fa-wallet"></i>
                        <span>💰 Check Wallet</span>
                    </button>
                    <button type="button" class="chip-card" onclick="sendQuickPrompt('Show my last prescription')">
                        <i class="fas fa-file-prescription"></i>
                        <span>📋 My Prescriptions</span>
                    </button>
                    <button type="button" class="chip-card" onclick="sendQuickPrompt('Show my next appointment')">
                        <i class="fas fa-calendar-check"></i>
                        <span>📅 My Appointments</span>
                    </button>
                    <button type="button" class="chip-card" onclick="sendQuickPrompt('I have a fever')">
                        <i class="fas fa-thermometer-half"></i>
                        <span>🤒 Fever Guidance</span>
                    </button>
                </div>
            </div>

            <!-- Messages Stream Container -->
            <div id="messages-stream" style="display: flex; flex-direction: column; gap: 1.25rem;"></div>

        </div>
    </main>

    <!-- Floating Scroll-Down Button -->
    <button type="button" class="scroll-down-btn" id="scroll-down-btn" onclick="scrollToBottom(true)">
        <i class="fas fa-arrow-down"></i> New messages
    </button>

    <!-- Fixed Bottom Message Composer -->
    <footer class="composer-area">
        <form id="chat-form" class="composer-inner" onsubmit="handleFormSubmit(event)">
            <textarea id="chat-input" class="composer-input" rows="1" placeholder="Message AI Assistant (e.g. 'I have a headache', 'Track order')..." required></textarea>
            <button type="submit" id="send-btn" class="send-btn" title="Send Message">
                <i class="fas fa-paper-plane"></i>
            </button>
        </form>
    </footer>

</div>

<!-- Real-time AI Assistant Application Logic -->
<script>
    const patientUserId = <?php echo json_encode($patient_id); ?>;
    const patientName = <?php echo json_encode($patient_name); ?>;
    const localStorageKey = `medicalak_ai_history_${patientUserId}`;

    const mainStream = document.getElementById('chat-main-stream');
    const messagesStream = document.getElementById('messages-stream');
    const welcomeCard = document.getElementById('welcome-card');
    const chatInput = document.getElementById('chat-input');
    const sendBtn = document.getElementById('send-btn');
    const scrollDownBtn = document.getElementById('scroll-down-btn');
    const chatForm = document.getElementById('chat-form');

    let chatHistory = [];
    let isRequestPending = false;
    let isUserScrolledUp = false;

    // Load saved conversation history on init
    window.addEventListener('DOMContentLoaded', () => {
        loadChatHistory();
        setupInputEvents();
        setupScrollListener();
        handleMobileViewport();
    });

    // Auto-expand textarea & Enter key submit handling
    function setupInputEvents() {
        chatInput.addEventListener('input', function() {
            this.style.height = 'auto';
            this.style.height = (this.scrollHeight) + 'px';
        });

        chatInput.addEventListener('keydown', function(e) {
            if (e.key === 'Enter' && !e.shiftKey) {
                // Desktop: Enter sends message
                if (window.innerWidth > 768) {
                    e.preventDefault();
                    chatForm.dispatchEvent(new Event('submit', { cancelable: true }));
                }
            }
        });
    }

    // Scroll Position Handling
    function setupScrollListener() {
        mainStream.addEventListener('scroll', () => {
            const threshold = 150;
            const distanceToBottom = mainStream.scrollHeight - mainStream.scrollTop - mainStream.clientHeight;
            isUserScrolledUp = distanceToBottom > threshold;

            if (isUserScrolledUp) {
                scrollDownBtn.classList.add('show');
            } else {
                scrollDownBtn.classList.remove('show');
            }
        });
    }

    function scrollToBottom(force = false) {
        if (!isUserScrolledUp || force) {
            mainStream.scrollTo({
                top: mainStream.scrollHeight,
                behavior: force ? 'smooth' : 'auto'
            });
        }
    }

    // Load saved messages from LocalStorage
    function loadChatHistory() {
        try {
            const saved = localStorage.getItem(localStorageKey);
            if (saved) {
                chatHistory = JSON.parse(saved);
                if (Array.isArray(chatHistory) && chatHistory.length > 0) {
                    welcomeCard.style.display = 'none';
                    messagesStream.innerHTML = '';
                    chatHistory.forEach(msg => {
                        renderMessageDOM(msg.role, msg.text, msg.time, false);
                    });
                    scrollToBottom(true);
                }
            }
        } catch (e) {
            console.error("Failed to load chat history:", e);
        }
    }

    function saveChatHistory() {
        try {
            localStorage.setItem(localStorageKey, JSON.stringify(chatHistory));
        } catch (e) {
            console.error("Failed to save chat history:", e);
        }
    }

    function startNewChat() {
        if (confirm("Start a new conversation with MedicalAk AI?")) {
            chatHistory = [];
            localStorage.removeItem(localStorageKey);
            messagesStream.innerHTML = '';
            welcomeCard.style.display = 'block';
            chatInput.value = '';
            chatInput.style.height = 'auto';
            scrollToBottom(true);
        }
    }

    function sendQuickPrompt(promptText) {
        chatInput.value = promptText;
        chatForm.dispatchEvent(new Event('submit', { cancelable: true }));
    }

    // Submit handler
    async function handleFormSubmit(e) {
        e.preventDefault();
        const userText = chatInput.value.trim();
        if (!userText || isRequestPending) return;

        isRequestPending = true;
        welcomeCard.style.display = 'none';
        
        // Reset input
        chatInput.value = '';
        chatInput.style.height = 'auto';
        sendBtn.disabled = true;
        sendBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';

        const timeStr = getCurrentTime();

        // Render & Save User Message
        renderMessageDOM('user', userText, timeStr, true);
        chatHistory.push({ role: 'user', text: userText, time: timeStr });
        saveChatHistory();

        // Show AI Typing Indicator
        const typingEl = renderTypingIndicatorDOM();
        scrollToBottom(true);

        try {
            const response = await fetch('api_chatbot.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ message: userText })
            });

            const data = await response.json();
            typingEl.remove();

            let aiReplyText = "";
            if (data.reply) {
                aiReplyText = data.reply;
            } else if (data.error) {
                aiReplyText = "⚠️ " + data.error;
            } else {
                aiReplyText = "⚠️ Unable to parse AI response. Please try again.";
            }

            const aiTimeStr = getCurrentTime();
            renderMessageDOM('ai', aiReplyText, aiTimeStr, true);
            chatHistory.push({ role: 'ai', text: aiReplyText, time: aiTimeStr });
            saveChatHistory();

        } catch (error) {
            console.error("AI Request Failed:", error);
            if (typingEl) typingEl.remove();

            const errText = "⚠️ Unable to connect to MedicalAk AI. Please check your network connection and retry.";
            const aiTimeStr = getCurrentTime();
            renderMessageDOM('ai', errText, aiTimeStr, true);
        } finally {
            isRequestPending = false;
            sendBtn.disabled = false;
            sendBtn.innerHTML = '<i class="fas fa-paper-plane"></i>';
            chatInput.focus();
            scrollToBottom(true);
        }
    }

    // Render Message in DOM
    function renderMessageDOM(role, text, time, animate = true) {
        const msgRow = document.createElement('div');
        msgRow.className = `msg-row ${role}-msg`;
        if (!animate) msgRow.style.animation = 'none';

        const isUser = role === 'user';
        const formattedHTML = isUser ? escapeHTML(text) : parseMarkdown(text);

        msgRow.innerHTML = `
            <div class="msg-avatar">
                <i class="fas ${isUser ? 'fa-user' : 'fa-robot'}"></i>
            </div>
            <div class="msg-bubble-container">
                <div class="msg-bubble">
                    ${!isUser ? '<div class="ai-sender-name">MedicalAk AI</div>' : ''}
                    <div>${formattedHTML}</div>
                </div>
                <div class="msg-footer">
                    <span>${time}</span>
                    ${!isUser ? `<button type="button" class="copy-btn" onclick="copyResponse(this, \`${escapeHTML(text).replace(/`/g, '\\`')}\`)"><i class="far fa-copy"></i> Copy</button>` : ''}
                </div>
            </div>
        `;

        messagesStream.appendChild(msgRow);
        scrollToBottom();
    }

    // Render Typing Indicator DOM
    function renderTypingIndicatorDOM() {
        const typingRow = document.createElement('div');
        typingRow.className = 'msg-row ai-msg';
        typingRow.id = 'typing-indicator';

        typingRow.innerHTML = `
            <div class="msg-avatar">
                <i class="fas fa-robot"></i>
            </div>
            <div class="msg-bubble-container">
                <div class="msg-bubble typing-bubble">
                    <div class="typing-dots">
                        <span></span><span></span><span></span>
                    </div>
                    <span style="font-size: 0.85rem; color: var(--text-muted); font-style: italic;">MedicalAk AI is thinking...</span>
                </div>
            </div>
        `;

        messagesStream.appendChild(typingRow);
        return typingRow;
    }

    // Markdown Parser
    function parseMarkdown(str) {
        if (!str) return '';
        let html = str;

        // Escape dangerous tags first
        html = html.replace(/</g, "&lt;").replace(/>/g, "&gt;");

        // Convert linebreaks
        html = html.replace(/\n/g, '<br>');

        // Bold text **text**
        html = html.replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>');

        // Headings ### text
        html = html.replace(/### (.*?)(<br>|$)/g, '<h4 style="color:#fff; margin:0.4rem 0 0.2rem 0; font-size:1rem;">$1</h4>');

        // Convert [Label](url.php) to clickable action buttons
        html = html.replace(/\[([^\]]+)\]\(([^)]+)\)/g, '<a href="$2" class="chat-action-link"><i class="fas fa-external-link-alt"></i> $1</a>');

        // Convert keywords into action links if found in bold
        html = html.replace(/<strong>Consultations<\/strong>/g, '<a href="book_consult.php" class="chat-action-link"><i class="fas fa-calendar-check"></i> Consultations</a>');
        html = html.replace(/<strong>Order Medicines<\/strong>/g, '<a href="medicines.php" class="chat-action-link"><i class="fas fa-pills"></i> Order Medicines</a>');
        html = html.replace(/<strong>Find Doctors \(10km\)<\/strong>/g, '<a href="nearby_doctors.php" class="chat-action-link"><i class="fas fa-map-marker-alt"></i> Find Doctors (10km)</a>');
        html = html.replace(/<strong>Book Labs \(RMP\)<\/strong>/g, '<a href="book_tests.php" class="chat-action-link"><i class="fas fa-vial"></i> Book Labs (RMP)</a>');
        html = html.replace(/<strong>Your Orders<\/strong>/g, '<a href="your_orders.php" class="chat-action-link"><i class="fas fa-boxes"></i> Your Orders</a>');
        html = html.replace(/<strong>My Wallet<\/strong>/g, '<a href="my_wallet.php" class="chat-action-link"><i class="fas fa-wallet"></i> My Wallet</a>');
        html = html.replace(/<strong>Digital Medical Card<\/strong>/g, '<a href="digital_medical_card.php" class="chat-action-link"><i class="fas fa-id-card"></i> Digital Medical Card</a>');

        return html;
    }

    function escapeHTML(str) {
        return str.replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;").replace(/'/g, "&#039;");
    }

    function getCurrentTime() {
        const d = new Date();
        let hours = d.getHours();
        let minutes = d.getMinutes();
        const ampm = hours >= 12 ? 'PM' : 'AM';
        hours = hours % 12;
        hours = hours ? hours : 12;
        minutes = minutes < 10 ? '0' + minutes : minutes;
        return hours + ':' + minutes + ' ' + ampm;
    }

    function copyResponse(btn, text) {
        navigator.clipboard.writeText(text).then(() => {
            const originalHTML = btn.innerHTML;
            btn.innerHTML = '<i class="fas fa-check" style="color: var(--accent-green);"></i> Copied ✓';
            setTimeout(() => {
                btn.innerHTML = originalHTML;
            }, 2000);
        }).catch(err => {
            console.error('Failed to copy text: ', err);
        });
    }

    // Handle Mobile Soft Keyboard Viewport Adaptation
    function handleMobileViewport() {
        if (window.visualViewport) {
            window.visualViewport.addEventListener('resize', () => {
                document.querySelector('.ai-chat-app').style.height = `${window.visualViewport.height}px`;
                scrollToBottom(true);
            });
        }
    }
</script>

</body>
</html>
