<?php
// Bypass security check in config.php for this page
define('IS_SECURITY_GATE_PAGE', true);
require_once 'config.php';

// If already verified, redirect immediately to intended target or index.php
if (!empty($_SESSION['security_gate_verified'])) {
    $redirect = $_SESSION['security_gate_redirect'] ?? 'index.php';
    if (empty($redirect) || strpos($redirect, 'security_gate.php') !== false) {
        $redirect = 'index.php';
    }
    unset($_SESSION['security_gate_redirect']);
    header("Location: " . $redirect);
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <title>Secure Access Required | Medicalak Health</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body, html {
            height: 100%;
            width: 100%;
            font-family: 'Inter', system-ui, -apple-system, sans-serif;
            background: #0f172a;
            color: #f8fafc;
            overflow: hidden;
        }

        .gate-viewport {
            min-height: 100vh;
            min-height: 100dvh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1.5rem;
            background: radial-gradient(circle at top right, rgba(5, 150, 105, 0.15), transparent 45%),
                        radial-gradient(circle at bottom left, rgba(15, 23, 42, 0.9), #0f172a);
            position: relative;
        }

        .gate-card {
            width: 100%;
            max-width: 440px;
            background: rgba(30, 41, 59, 0.85);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.12);
            border-radius: 24px;
            padding: 2.5rem 2rem;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.6), 0 0 30px rgba(5, 150, 105, 0.1);
            text-align: center;
            position: relative;
            animation: cardAppear 0.4s cubic-bezier(0.16, 1, 0.3, 1);
        }

        @keyframes cardAppear {
            from { opacity: 0; transform: scale(0.94) translateY(12px); }
            to { opacity: 1; transform: scale(1) translateY(0); }
        }

        .gate-icon-wrapper {
            width: 72px;
            height: 72px;
            background: linear-gradient(135deg, rgba(5, 150, 105, 0.2) 0%, rgba(16, 185, 129, 0.1) 100%);
            border: 1px solid rgba(5, 150, 105, 0.3);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1.5rem auto;
            color: #34d399;
            font-size: 2rem;
            box-shadow: 0 8px 20px rgba(5, 150, 105, 0.2);
        }

        .gate-title {
            font-size: 1.45rem;
            font-weight: 800;
            color: #ffffff;
            margin-bottom: 0.5rem;
            letter-spacing: -0.3px;
        }

        .gate-subtitle {
            font-size: 0.88rem;
            color: #94a3b8;
            line-height: 1.5;
            margin-bottom: 1.8rem;
        }

        .gate-form {
            display: flex;
            flex-direction: column;
            gap: 1.2rem;
        }

        .input-group {
            position: relative;
            display: flex;
            align-items: center;
        }

        .gate-input {
            width: 100%;
            background: rgba(15, 23, 42, 0.8);
            border: 1px solid rgba(255, 255, 255, 0.15);
            border-radius: 14px;
            padding: 0.9rem 2.8rem 0.9rem 1.1rem;
            font-size: 0.95rem;
            color: #ffffff;
            outline: none;
            transition: all 0.2s ease;
            font-family: inherit;
        }

        .gate-input::placeholder {
            color: #64748b;
        }

        .gate-input:focus {
            border-color: #059669;
            box-shadow: 0 0 0 4px rgba(5, 150, 105, 0.2);
            background: rgba(15, 23, 42, 0.95);
        }

        .toggle-password-btn {
            position: absolute;
            right: 12px;
            background: transparent;
            border: none;
            color: #94a3b8;
            cursor: pointer;
            padding: 0.4rem;
            font-size: 1rem;
            transition: color 0.2s;
        }

        .toggle-password-btn:hover {
            color: #34d399;
        }

        .gate-btn {
            width: 100%;
            background: linear-gradient(135deg, #059669 0%, #047857 100%);
            color: #ffffff;
            border: none;
            border-radius: 14px;
            padding: 0.95rem;
            font-size: 1rem;
            font-weight: 700;
            cursor: pointer;
            box-shadow: 0 4px 15px rgba(5, 150, 105, 0.35);
            transition: all 0.2s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            font-family: inherit;
        }

        .gate-btn:hover:not(:disabled) {
            transform: translateY(-1px);
            box-shadow: 0 6px 20px rgba(5, 150, 105, 0.45);
        }

        .gate-btn:disabled {
            background: #475569;
            box-shadow: none;
            cursor: not-allowed;
            opacity: 0.7;
        }

        .alert-box {
            padding: 0.8rem 1rem;
            border-radius: 12px;
            font-size: 0.84rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            text-align: left;
            animation: alertSlide 0.2s ease-out;
        }

        @keyframes alertSlide {
            from { opacity: 0; transform: translateY(-4px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .alert-error {
            background: rgba(239, 68, 68, 0.15);
            border: 1px solid rgba(239, 68, 68, 0.3);
            color: #f87171;
        }

        .alert-success {
            background: rgba(16, 185, 129, 0.15);
            border: 1px solid rgba(16, 185, 129, 0.3);
            color: #34d399;
        }

        .gate-footer {
            margin-top: 2rem;
            font-size: 0.78rem;
            color: #64748b;
        }

        @media (max-width: 480px) {
            .gate-card {
                padding: 2rem 1.4rem;
                border-radius: 18px;
            }
            .gate-icon-wrapper {
                width: 60px;
                height: 60px;
                font-size: 1.6rem;
            }
            .gate-title {
                font-size: 1.25rem;
            }
        }
    </style>
</head>
<body>

<div class="gate-viewport">
    <div class="gate-card">
        <div class="gate-icon-wrapper">
            <i class="fas fa-user-shield"></i>
        </div>

        <h1 class="gate-title">Secure Access Required</h1>
        <p class="gate-subtitle">Enter the required security key to access the application.</p>

        <form id="securityGateForm" class="gate-form" onsubmit="handleSecurityGateSubmit(event)">
            <div id="alertContainer"></div>

            <div class="input-group">
                <input type="password" id="securityKeyInput" class="gate-input" placeholder="Enter security key" autocomplete="off" autofocus required>
                <button type="button" class="toggle-password-btn" title="Toggle Visibility" onclick="toggleVisibility()">
                    <i id="eyeIcon" class="fas fa-eye"></i>
                </button>
            </div>

            <button type="submit" id="submitBtn" class="gate-btn">
                <span>Continue</span> <i class="fas fa-arrow-right"></i>
            </button>
        </form>

        <div class="gate-footer">
            <i class="fas fa-lock" style="color: #059669;"></i> Protected System &bull; Medicalak Health
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const input = document.getElementById('securityKeyInput');
    if (input) input.focus();
});

function toggleVisibility() {
    const input = document.getElementById('securityKeyInput');
    const icon = document.getElementById('eyeIcon');
    if (input && icon) {
        if (input.type === 'password') {
            input.type = 'text';
            icon.className = 'fas fa-eye-slash';
        } else {
            input.type = 'password';
            icon.className = 'fas fa-eye';
        }
    }
}

function showAlert(message, type = 'error') {
    const container = document.getElementById('alertContainer');
    if (!container) return;
    
    const iconClass = (type === 'success') ? 'fa-check-circle' : 'fa-exclamation-circle';
    const alertClass = (type === 'success') ? 'alert-success' : 'alert-error';
    
    container.innerHTML = `
        <div class="alert-box ${alertClass}">
            <i class="fas ${iconClass}"></i>
            <span>${escapeHtml(message)}</span>
        </div>
    `;
}

function clearAlert() {
    const container = document.getElementById('alertContainer');
    if (container) container.innerHTML = '';
}

function handleSecurityGateSubmit(e) {
    e.preventDefault();
    clearAlert();

    const input = document.getElementById('securityKeyInput');
    const btn = document.getElementById('submitBtn');

    const rawKey = input ? input.value.trim() : '';

    if (!rawKey) {
        showAlert('Security key is required.', 'error');
        if (input) input.focus();
        return;
    }

    // Set Loading State
    input.disabled = true;
    btn.disabled = true;
    btn.innerHTML = `<i class="fas fa-spinner fa-spin"></i> <span>Checking...</span>`;

    const formData = new FormData();
    formData.append('security_key', rawKey);

    fetch('api_security_gate.php', {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            showAlert('✓ Security Verified', 'success');
            btn.style.background = '#10b981';
            btn.innerHTML = `<i class="fas fa-check"></i> <span>Verified</span>`;
            
            setTimeout(() => {
                window.location.href = data.redirect || 'index.php';
            }, 400);
        } else {
            input.disabled = false;
            btn.disabled = false;
            btn.innerHTML = `<span>Continue</span> <i class="fas fa-arrow-right"></i>`;
            showAlert(data.message || 'Incorrect security key. Please try again.', 'error');
            input.focus();
        }
    })
    .catch(err => {
        input.disabled = false;
        btn.disabled = false;
        btn.innerHTML = `<span>Continue</span> <i class="fas fa-arrow-right"></i>`;
        showAlert('Unable to verify security key. Please check your connection and try again.', 'error');
    });
}

function escapeHtml(str) {
    if (!str) return '';
    return str.replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;").replace(/'/g, "&#039;");
}
</script>

</body>
</html>
