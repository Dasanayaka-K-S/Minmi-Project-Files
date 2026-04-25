<?php
// ============================================================
//  CHATBOT WIDGET — Add this to customer/includes/footer.php
//  Place BEFORE </body> tag
// ============================================================

// Determine base path for chatbot.php
$_doc_root    = rtrim(str_replace('\\','/',$_SERVER['DOCUMENT_ROOT']), '/');
$_script_file = str_replace('\\','/',$_SERVER['SCRIPT_FILENAME']);
$_web_path    = str_replace($_doc_root, '', $_script_file);
$_project_dir = substr($_web_path, 0, strpos($_web_path, '/customer/'));
$chatbot_url  = $_project_dir . '/customer/chatbot.php';
?>

<!-- ══ MINMI CHATBOT WIDGET ══ -->
<div id="chatbot-widget" style="position:fixed;bottom:28px;right:28px;z-index:9999;font-family:'Plus Jakarta Sans',sans-serif">

    <!-- Toggle Button -->
    <button id="chatbot-toggle" onclick="toggleChat()"
        style="width:60px;height:60px;border-radius:50%;border:none;cursor:pointer;
               background:linear-gradient(135deg,#FF4500,#FF9500);
               box-shadow:0 6px 24px rgba(255,69,0,.45);
               display:flex;align-items:center;justify-content:center;
               font-size:1.6rem;transition:all .3s;position:relative;margin-left:auto">
        <span id="chat-icon">💬</span>
        <!-- Notification dot -->
        <span id="chat-dot" style="position:absolute;top:4px;right:4px;width:12px;height:12px;background:#FF3B30;border-radius:50%;border:2px solid #fff;animation:dotPulse 2s infinite"></span>
    </button>

    <!-- Chat Window -->
    <div id="chatbot-window"
        style="display:none;flex-direction:column;
               width:360px;height:520px;
               background:#fff;border-radius:24px;
               box-shadow:0 20px 60px rgba(61,31,10,.18);
               border:1px solid rgba(255,107,43,.15);
               overflow:hidden;margin-bottom:14px;
               animation:chatSlideIn .3s ease;
               position:absolute;bottom:74px;right:0">

        <!-- Header -->
        <div style="background:linear-gradient(135deg,#FF4500,#FF9500);padding:16px 20px;display:flex;align-items:center;gap:12px">
            <div style="width:42px;height:42px;border-radius:50%;background:#fff;display:flex;align-items:center;justify-content:center;flex-shrink:0;overflow:hidden;padding:2px">
                <img src="<?= $chatbot_url ? str_replace('chatbot.php','assets/img/chatbot-icon.jpg',$chatbot_url) : '' ?>"
                     alt="Mimi"
                     style="width:100%;height:100%;object-fit:cover;border-radius:50%"
                     onerror="this.parentElement.innerHTML='🤖'">
            </div>
            <div>
                <div style="font-weight:800;color:#fff;font-size:.92rem">Mimi — Minmi Assistant</div>
                <div style="font-size:.72rem;color:rgba(255,255,255,.8);display:flex;align-items:center;gap:5px">
                    <span style="width:7px;height:7px;background:#4ADE80;border-radius:50%;display:inline-block"></span>
                    Online now
                </div>
            </div>
            <button onclick="toggleChat()" style="margin-left:auto;background:rgba(255,255,255,.2);border:none;color:#fff;width:30px;height:30px;border-radius:50%;cursor:pointer;font-size:1rem;display:flex;align-items:center;justify-content:center">✕</button>
        </div>

        <!-- Messages Area -->
        <div id="chatbot-messages"
            style="flex:1;overflow-y:auto;padding:16px;display:flex;flex-direction:column;gap:12px;background:#FFF8F0">

            <!-- Welcome message -->
            <div class="bot-msg">
                <div class="bot-bubble">
                    👋 Hi! I'm <strong>Mimi</strong>, your Minmi Restaurant assistant!<br><br>
                    I can help you with our menu, prices, ordering and reservations. What can I do for you? 🍛
                </div>
                <div class="msg-time"><?= date('g:i A') ?></div>
            </div>

            <!-- Quick replies -->
            <div id="quick-replies" style="display:flex;flex-wrap:wrap;gap:8px;margin-top:4px">
                <button class="quick-btn" onclick="sendQuick('What is on the menu?')">🍽️ See Menu</button>
                <button class="quick-btn" onclick="sendQuick('What are your opening hours?')">🕐 Hours</button>
                <button class="quick-btn" onclick="sendQuick('How do I book a table?')">📅 Reserve</button>
                <button class="quick-btn" onclick="sendQuick('Any promotions or discounts?')">🎉 Promotions</button>
                <button class="quick-btn" onclick="sendQuick('How do I place an order online?')">📦 Order Online</button>
            </div>
        </div>

        <!-- Typing indicator (hidden by default) -->
        <div id="typing-indicator" style="display:none;padding:8px 16px;background:#FFF8F0">
            <div style="display:inline-flex;align-items:center;gap:6px;background:#fff;border:1px solid #F0E4D4;padding:10px 16px;border-radius:18px 18px 18px 4px">
                <div class="typing-dot"></div>
                <div class="typing-dot" style="animation-delay:.15s"></div>
                <div class="typing-dot" style="animation-delay:.3s"></div>
                <span style="font-size:.75rem;color:#9E6B4A;margin-left:4px">Mimi is typing…</span>
            </div>
        </div>

        <!-- Input Area -->
        <div style="padding:12px 16px;border-top:1px solid #F0E4D4;background:#fff;display:flex;gap:10px;align-items:flex-end">
            <textarea id="chatbot-input"
                placeholder="Ask me anything about Minmi…"
                rows="1"
                onkeydown="handleKey(event)"
                oninput="autoResize(this)"
                style="flex:1;border:2px solid #F0E4D4;border-radius:14px;padding:10px 14px;
                       font-family:'Plus Jakarta Sans',sans-serif;font-size:.84rem;
                       outline:none;resize:none;line-height:1.4;max-height:80px;
                       color:#3D1F0A;background:#FFF8F0;transition:border-color .2s"></textarea>
            <button onclick="sendMessage()"
                style="width:40px;height:40px;border-radius:50%;border:none;cursor:pointer;
                       background:linear-gradient(135deg,#FF4500,#FF9500);
                       color:#fff;font-size:1.1rem;display:flex;align-items:center;
                       justify-content:center;flex-shrink:0;
                       box-shadow:0 3px 10px rgba(255,69,0,.3);
                       transition:all .2s" title="Send">➤</button>
        </div>
    </div>
</div>

<style>
@keyframes chatSlideIn {
    from { opacity:0; transform:translateY(20px) scale(.95); }
    to   { opacity:1; transform:translateY(0) scale(1); }
}
@keyframes dotPulse {
    0%,100% { transform:scale(1); opacity:1; }
    50%      { transform:scale(1.3); opacity:.7; }
}
.bot-bubble {
    background:#fff;border:1px solid #F0E4D4;
    border-radius:18px 18px 18px 4px;
    padding:12px 16px;font-size:.84rem;
    color:#3D1F0A;line-height:1.65;
    box-shadow:0 2px 8px rgba(61,31,10,.06);
    max-width:85%;
}
.user-bubble {
    background:linear-gradient(135deg,#FF4500,#FF9500);
    border-radius:18px 18px 4px 18px;
    padding:12px 16px;font-size:.84rem;
    color:#fff;line-height:1.65;
    max-width:85%;align-self:flex-end;
    box-shadow:0 3px 10px rgba(255,69,0,.25);
}
.msg-time {
    font-size:.65rem;color:#9E6B4A;margin-top:4px;
}
.user-msg { display:flex;flex-direction:column;align-items:flex-end; }
.bot-msg  { display:flex;flex-direction:column;align-items:flex-start; }
.quick-btn {
    background:#fff;border:1.5px solid #F0E4D4;
    border-radius:50px;padding:7px 14px;
    font-size:.76rem;font-weight:700;cursor:pointer;
    color:#6B3A1F;font-family:'Plus Jakarta Sans',sans-serif;
    transition:all .2s;white-space:nowrap;
}
.quick-btn:hover {
    background:linear-gradient(135deg,#FF4500,#FF9500);
    color:#fff;border-color:transparent;
    transform:translateY(-1px);
}
.typing-dot {
    width:7px;height:7px;background:#C4A882;border-radius:50%;
    animation:typingBounce .8s ease-in-out infinite;
}
@keyframes typingBounce {
    0%,80%,100% { transform:translateY(0); }
    40%          { transform:translateY(-6px); }
}
#chatbot-messages::-webkit-scrollbar { width:4px; }
#chatbot-messages::-webkit-scrollbar-track { background:transparent; }
#chatbot-messages::-webkit-scrollbar-thumb { background:#F0E4D4;border-radius:2px; }
#chatbot-input:focus { border-color:#FF6B2B;background:#fff; }
</style>

<script>
const CHATBOT_URL = '<?= $chatbot_url ?>';
let chatOpen = false;

function toggleChat() {
    chatOpen = !chatOpen;
    const win  = document.getElementById('chatbot-window');
    const icon = document.getElementById('chat-icon');
    const dot  = document.getElementById('chat-dot');
    win.style.display  = chatOpen ? 'flex' : 'none';
    icon.textContent   = chatOpen ? '✕' : '💬';
    dot.style.display  = chatOpen ? 'none' : 'block';
    if (chatOpen) {
        win.style.animation = 'chatSlideIn .3s ease';
        setTimeout(() => document.getElementById('chatbot-input').focus(), 300);
        scrollToBottom();
    }
}

function scrollToBottom() {
    const msgs = document.getElementById('chatbot-messages');
    msgs.scrollTop = msgs.scrollHeight;
}

function addBotMessage(text) {
    const msgs = document.getElementById('chatbot-messages');
    const div  = document.createElement('div');
    div.className = 'bot-msg';
    div.innerHTML = `<div class="bot-bubble">${text.replace(/\n/g,'<br>')}</div><div class="msg-time">${getTime()}</div>`;
    msgs.appendChild(div);
    scrollToBottom();
}

function addUserMessage(text) {
    const msgs = document.getElementById('chatbot-messages');
    const div  = document.createElement('div');
    div.className = 'user-msg';
    div.innerHTML = `<div class="user-bubble">${escHtml(text)}</div><div class="msg-time">${getTime()}</div>`;
    msgs.appendChild(div);
    scrollToBottom();
}

function escHtml(t) {
    return t.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
}

function getTime() {
    return new Date().toLocaleTimeString('en-US',{hour:'numeric',minute:'2-digit'});
}

function showTyping(show) {
    document.getElementById('typing-indicator').style.display = show ? 'block' : 'none';
    if (show) scrollToBottom();
}

async function sendMessage() {
    const input = document.getElementById('chatbot-input');
    const msg   = input.value.trim();
    if (!msg) return;

    // Hide quick replies after first message
    const qr = document.getElementById('quick-replies');
    if (qr) qr.style.display = 'none';

    addUserMessage(msg);
    input.value = '';
    input.style.height = 'auto';

    showTyping(true);

    try {
        const res  = await fetch(CHATBOT_URL, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'message=' + encodeURIComponent(msg)
        });
        const data = await res.json();
        showTyping(false);
        addBotMessage(data.reply || "Sorry, I couldn't get a response. Please try again! 😊");
    } catch (err) {
        showTyping(false);
        addBotMessage("Sorry, something went wrong. Please contact us at minmirestaurant@gmail.com 😊");
    }
}

function sendQuick(text) {
    document.getElementById('chatbot-input').value = text;
    sendMessage();
}

function handleKey(e) {
    if (e.key === 'Enter' && !e.shiftKey) {
        e.preventDefault();
        sendMessage();
    }
}

function autoResize(el) {
    el.style.height = 'auto';
    el.style.height = Math.min(el.scrollHeight, 80) + 'px';
}

// Show dot notification after 3 seconds to attract attention
setTimeout(() => {
    if (!chatOpen) {
        const btn = document.getElementById('chatbot-toggle');
        btn.style.transform = 'scale(1.1)';
        setTimeout(() => btn.style.transform = 'scale(1)', 300);
    }
}, 3000);
</script>
<!-- ══ END CHATBOT ══ -->