<?php
require_once 'config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'patient') {
    header("Location: login.php");
    exit;
}

include 'includes/header.php';
?>

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
            <li><a href="medicines.php"><i class="fas fa-pills"></i> Order Medicines</a></li>
            <li><a href="nearby_doctors.php"><i class="fas fa-map-marker-alt"></i> Find Doctors (10km)</a></li>
            <li><a href="privacy_consult.php"><i class="fas fa-user-secret"></i> Privacy Consult</a></li>
            <li><a href="book_tests.php"><i class="fas fa-vial"></i> Book Labs (RMP)</a></li>
            <li><a href="payment_history.php"><i class="fas fa-receipt"></i> Payment History</a></li>
            <li><a href="refer_earn.php"><i class="fas fa-gift"></i> Refer & Earn</a></li>
            <li><a href="my_wallet.php"><i class="fas fa-wallet"></i> My Wallet</a></li>
            <li><a href="chatbot.php" class="active"><i class="fas fa-robot"></i> AI Chatbot</a></li>
        </ul>
    </aside>
    
    <main class="dashboard-content">
        <h2>AI Health Assistant</h2>
        <p style="color: var(--text-secondary); margin-bottom: 2rem;">Ask health queries or get help navigating the platform in real-time.</p>
        
        <div class="glass-panel" style="max-width: 650px; width: 100%; display: flex; flex-direction: column; height: 580px; overflow: hidden; background: rgba(18, 18, 18, 0.6);">
            
            <!-- Chat Window -->
            <div id="chat-window" style="flex: 1; padding: 1.5rem; overflow-y: auto; display: flex; flex-direction: column; gap: 1.5rem;">
                
                <!-- Initial Welcome Message -->
                <div class="bot-msg" style="display: flex; gap: 1rem; max-width: 85%;">
                    <div style="width: 40px; height: 40px; border-radius: 50%; background: linear-gradient(45deg, var(--primary-color), var(--secondary-color)); display: flex; align-items: center; justify-content: center; color: #fff; flex-shrink: 0; box-shadow: 0 4px 10px rgba(74, 144, 226, 0.3);">
                        <i class="fas fa-robot"></i>
                    </div>
                    <div style="background: rgba(255,255,255,0.05); padding: 1rem 1.2rem; border-radius: 0 15px 15px 15px; border: 1px solid rgba(255,255,255,0.05);">
                        <p style="font-weight: 600; color: var(--secondary-color); font-size: 0.8rem; margin-bottom: 0.3rem;">MedicalAk AI</p>
                        <p style="font-size: 0.95rem; line-height: 1.5;">Hello <?php echo htmlspecialchars($_SESSION['name']); ?>! I am your 24/7 real-time Health Assistant. I can check your basic symptoms (e.g., 'fever', 'headache', 'stomach') or help you book services. How are you feeling today?</p>
                    </div>
                </div>

            </div>

            <!-- Quick Suggestions Bar -->
            <div style="padding: 0.5rem 1rem; background: rgba(0,0,0,0.2); display: flex; gap: 0.5rem; overflow-x: auto; border-top: 1px solid rgba(255,255,255,0.05);">
                <button type="button" onclick="sendQuickMsg('Hii')" style="background: rgba(74, 144, 226, 0.2); border: 1px solid rgba(74, 144, 226, 0.4); color: #fff; padding: 0.3rem 0.8rem; border-radius: 20px; font-size: 0.8rem; cursor: pointer; white-space: nowrap;">👋 Hii</button>
                <button type="button" onclick="sendQuickMsg('I have a fever')" style="background: rgba(74, 144, 226, 0.15); border: 1px solid rgba(74, 144, 226, 0.3); color: #fff; padding: 0.3rem 0.8rem; border-radius: 20px; font-size: 0.8rem; cursor: pointer; white-space: nowrap;">🤒 Fever</button>
                <button type="button" onclick="sendQuickMsg('I have a headache')" style="background: rgba(74, 144, 226, 0.15); border: 1px solid rgba(74, 144, 226, 0.3); color: #fff; padding: 0.3rem 0.8rem; border-radius: 20px; font-size: 0.8rem; cursor: pointer; white-space: nowrap;">🤕 Headache</button>
                <button type="button" onclick="sendQuickMsg('How to order medicines?')" style="background: rgba(80, 227, 194, 0.15); border: 1px solid rgba(80, 227, 194, 0.3); color: #fff; padding: 0.3rem 0.8rem; border-radius: 20px; font-size: 0.8rem; cursor: pointer; white-space: nowrap;">💊 Order Medicines</button>
                <button type="button" onclick="sendQuickMsg('How to book doctor consultation?')" style="background: rgba(80, 227, 194, 0.15); border: 1px solid rgba(80, 227, 194, 0.3); color: #fff; padding: 0.3rem 0.8rem; border-radius: 20px; font-size: 0.8rem; cursor: pointer; white-space: nowrap;">👨‍⚕️ Book Doctor</button>
            </div>
            
            <!-- Chat Form -->
            <div style="padding: 1rem; border-top: 1px solid var(--glass-border); background: rgba(0,0,0,0.4);">
                <form id="chat-form" style="display: flex; gap: 0.5rem;">
                    <input type="text" id="chat-input" class="form-control" autocomplete="off" placeholder="Type a symptom or a question..." required style="flex: 1; border: none; background: rgba(255,255,255,0.1); border-radius: 25px; padding: 0.8rem 1.5rem;">
                    <button type="submit" class="btn btn-primary" style="border-radius: 50%; width: 50px; height: 50px; padding: 0; display: flex; align-items: center; justify-content: center;"><i class="fas fa-paper-plane"></i></button>
                </form>
            </div>
        </div>
    </main>
</div>

<!-- Real-time Chatbot Script -->
<script>
    const form = document.getElementById('chat-form');
    const input = document.getElementById('chat-input');
    const chatWindow = document.getElementById('chat-window');

    // Bot Response Logic Dictionary
    const responses = {
        'fever': "I understand you have a fever. Ensure you drink plenty of fluids. If it exceeds 102°F or lasts more than 3 days, please click 'Consultations' to book a Doctor immediately.",
        'headache': "For a mild headache, resting in a quiet, dark room and staying hydrated often helps. If your headache is severe, accompanied by nausea or vision changes, please book an urgent consultation.",
        'stomach': "Stomach pain can have many causes. Avoid spicy foods and stay hydrated. If the pain is sharp, persistent, or accompanied by vomiting, I strongly suggest booking an offline Doctor visit.",
        'medicine': "To get prescribed pills delivered directly to your door, please navigate to the 'Order Medicines' tab on the left menu.",
        'test': "You can easily schedule a home visit from an RMP for diagnostic lab tests by clicking the 'Book Labs' tab.",
        'book': "You can schedule a video or door-to-door consultation by navigating to the 'Consultations' tab.",
        'hi': "Hi there! How can I help you regarding your health today?",
        'hello': "Hello! I am ready to assist you. Tell me about your symptoms or what service you are looking for.",
        'thank': "You're very welcome! Always here to help you stay healthy.",
        'default': "I can help with checking basic symptoms or finding platform tools. Could you provide a bit more detail about your health issue?"
    };

    function appendUserMessage(text) {
        const msgDiv = document.createElement('div');
        msgDiv.className = 'user-msg';
        msgDiv.style.cssText = 'display: flex; gap: 1rem; max-width: 85%; align-self: flex-end; flex-direction: row-reverse;';
        msgDiv.innerHTML = `
            <div style="width: 40px; height: 40px; border-radius: 50%; background: rgba(255,255,255,0.1); display: flex; align-items: center; justify-content: center; color: var(--text-secondary); flex-shrink: 0; border: 1px solid rgba(255,255,255,0.2);">
                <i class="fas fa-user"></i>
            </div>
            <div style="background: rgba(74, 144, 226, 0.2); padding: 1rem 1.2rem; border-radius: 15px 0 15px 15px; border: 1px solid rgba(74, 144, 226, 0.3);">
                <p style="font-size: 0.95rem; line-height: 1.5; color: #fff;">${text.replace(/</g, "&lt;").replace(/>/g, "&gt;")}</p>
            </div>
        `;
        chatWindow.appendChild(msgDiv);
        chatWindow.scrollTop = chatWindow.scrollHeight;
    }

    function appendTypingIndicator() {
        const typingDiv = document.createElement('div');
        typingDiv.id = 'typing-indicator';
        typingDiv.className = 'bot-msg';
        typingDiv.style.cssText = 'display: flex; gap: 1rem; max-width: 85%;';
        typingDiv.innerHTML = `
            <div style="width: 40px; height: 40px; border-radius: 50%; background: linear-gradient(45deg, var(--primary-color), var(--secondary-color)); display: flex; align-items: center; justify-content: center; color: #fff; flex-shrink: 0; box-shadow: 0 4px 10px rgba(74, 144, 226, 0.3);">
                <i class="fas fa-robot"></i>
            </div>
            <div style="background: rgba(255,255,255,0.05); padding: 1rem 1.2rem; border-radius: 0 15px 15px 15px; border: 1px solid rgba(255,255,255,0.05); display: flex; align-items: center; gap: 5px;">
                <span style="font-size: 0.8rem; color: var(--text-secondary); font-style: italic;">AI is typing...</span>
            </div>
        `;
        chatWindow.appendChild(typingDiv);
        chatWindow.scrollTop = chatWindow.scrollHeight;
        return typingDiv;
    }

    function appendBotMessage(text, typingIndicator) {
        typingIndicator.remove(); // Remove "typing..."
        const msgDiv = document.createElement('div');
        msgDiv.className = 'bot-msg';
        msgDiv.style.cssText = 'display: flex; gap: 1rem; max-width: 85%;';
        msgDiv.innerHTML = `
            <div style="width: 40px; height: 40px; border-radius: 50%; background: linear-gradient(45deg, var(--primary-color), var(--secondary-color)); display: flex; align-items: center; justify-content: center; color: #fff; flex-shrink: 0; box-shadow: 0 4px 10px rgba(74, 144, 226, 0.3);">
                <i class="fas fa-robot"></i>
            </div>
            <div style="background: rgba(255,255,255,0.05); padding: 1rem 1.2rem; border-radius: 0 15px 15px 15px; border: 1px solid rgba(255,255,255,0.05);">
                <p style="font-weight: 600; color: var(--secondary-color); font-size: 0.8rem; margin-bottom: 0.3rem;">MedicalAk AI</p>
                <p style="font-size: 0.95rem; line-height: 1.5;">${text}</p>
            </div>
        `;
        chatWindow.appendChild(msgDiv);
        chatWindow.scrollTop = chatWindow.scrollHeight;
    }

    form.addEventListener('submit', async function(e) {
        e.preventDefault();
        const userText = input.value.trim();
        if (!userText) return;

        // 1. Show user message
        appendUserMessage(userText);
        input.value = '';

        // 2. Show typing indicator
        const typingIndicator = appendTypingIndicator();

        // 3. Make fetch request to our PHP API
        try {
            const response = await fetch('api_chatbot.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({ message: userText })
            });

            const data = await response.json();

            if (data.reply) {
                // Ensure newlines are converted to <br> for plain text rendering
                let formattedReply = data.reply.replace(/\n/g, '<br>');
                // Bold formatting
                formattedReply = formattedReply.replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>');
                appendBotMessage(formattedReply, typingIndicator);
            } else if (data.error) {
                appendBotMessage("⚠️ System Error: " + data.error, typingIndicator);
            } else {
                appendBotMessage("⚠️ Unexpected response from the server.", typingIndicator);
            }

        } catch (error) {
            console.error(error);
            appendBotMessage("⚠️ Failed to connect to the AI service. Please check your internet connection.", typingIndicator);
        }
    });

    function sendQuickMsg(text) {
        input.value = text;
        form.dispatchEvent(new Event('submit', { cancelable: true }));
    }
</script>

<?php include 'includes/footer.php'; ?>
