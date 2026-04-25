<?php
// ============================================================
//  CHATBOT API — Minmi Restaurent Customer Website
//  Place in: customer/chatbot.php
//  Rule-based chatbot — No API key needed, 100% free!
// ============================================================
require_once __DIR__ . '/includes/db.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['reply' => 'Invalid request.']);
    exit;
}

$msg = strtolower(trim($_POST['message'] ?? ''));
if (!$msg) {
    echo json_encode(['reply' => 'Please type a message.']);
    exit;
}

// ── Fetch live data from DB ──
$menu_items = $pdo->query("SELECT name, category, price FROM menu_items WHERE status IN ('Available','Seasonal') ORDER BY category, name")->fetchAll();

try {
    $today  = date('Y-m-d');
    $promos = $pdo->prepare("SELECT title, discount_percent, description, end_date FROM promotions WHERE status='Active' AND start_date<=? AND end_date>=?");
    $promos->execute([$today, $today]);
    $promos = $promos->fetchAll();
} catch (Exception $e) {
    $promos = [];
}

// ── Helper: build menu text ──
function buildMenuText($items, $category = null) {
    $text = '';
    $grouped = [];
    foreach ($items as $item) {
        $grouped[$item['category']][] = $item;
    }
    if ($category) {
        foreach ($grouped as $cat => $cat_items) {
            if (stripos($cat, $category) !== false) {
                foreach ($cat_items as $i) {
                    $text .= "🍽️ <strong>{$i['name']}</strong> — Rs. " . number_format($i['price'], 0) . "<br>";
                }
            }
        }
    } else {
        foreach ($grouped as $cat => $cat_items) {
            $text .= "<br><strong>📂 {$cat}</strong><br>";
            foreach ($cat_items as $i) {
                $text .= "• {$i['name']} — Rs. " . number_format($i['price'], 0) . "<br>";
            }
        }
    }
    return $text ?: 'No items found.';
}

// ── Match keywords ──
function contains($msg, $keywords) {
    foreach ($keywords as $kw) {
        if (strpos($msg, $kw) !== false) return true;
    }
    return false;
}

$reply = '';

// ── GREETING ──
if (contains($msg, ['hello', 'hi', 'hey', 'good morning', 'good evening', 'good afternoon', 'ayubowan', 'helo', 'hai'])) {
    $greetings = [
        "👋 Hello! Welcome to Minmi Restaurant! I'm Mimi, your dining assistant. How can I help you today? 🍛",
        "🔥 Hey there! I'm Mimi from Minmi Restaurant! Ask me about our menu, reservations or anything else! 😊",
        "👋 Hi! Great to see you at Minmi Restaurant! I can help with menu, orders, reservations and more! 🍽️"
    ];
    $reply = $greetings[array_rand($greetings)];
}

// ── MENU — General ──
elseif (contains($msg, ['menu', 'food', 'what do you serve', 'what you have', 'show menu', 'full menu', 'all items', 'what to eat'])) {
    $menu_text = buildMenuText($menu_items);
    $reply = "🍽️ Here's our full menu:<br>{$menu_text}<br>Would you like details about a specific category? 😊";
}

// ── MENU — Categories ──
elseif (contains($msg, ['rice', 'rice and curry'])) {
    $t = buildMenuText($menu_items, 'Rice');
    $reply = "🍚 Our Rice dishes:<br>{$t}";
}
elseif (contains($msg, ['curry', 'curries'])) {
    $t = buildMenuText($menu_items, 'Curry');
    $reply = "🍛 Our Curry dishes:<br>{$t}";
}
elseif (contains($msg, ['kottu', 'kotto'])) {
    $t = buildMenuText($menu_items, 'Kottu');
    $reply = "🥘 Our Kottu dishes:<br>{$t}";
}
elseif (contains($msg, ['hopper', 'appa', 'appam'])) {
    $t = buildMenuText($menu_items, 'Hopper');
    $reply = "🫓 Our Hoppers:<br>{$t}";
}
elseif (contains($msg, ['drink', 'beverage', 'juice', 'water', 'coffee', 'tea'])) {
    $t = buildMenuText($menu_items, 'Drink');
    $reply = "🥤 Our Drinks:<br>{$t}";
}
elseif (contains($msg, ['dessert', 'sweet', 'pudding', 'cake'])) {
    $t = buildMenuText($menu_items, 'Dessert');
    $reply = "🍮 Our Desserts:<br>{$t}";
}
elseif (contains($msg, ['seafood', 'fish', 'prawn', 'crab', 'shrimp'])) {
    $t = buildMenuText($menu_items, 'Seafood');
    $reply = "🦐 Our Seafood dishes:<br>{$t}";
}
elseif (contains($msg, ['chicken'])) {
    $t = buildMenuText($menu_items, 'Chicken');
    $reply = "🍗 Our Chicken dishes:<br>{$t}";
}
elseif (contains($msg, ['vegetarian', 'vegan', 'veggie', 'no meat'])) {
    $t = buildMenuText($menu_items, 'Vegetarian');
    $reply = "🥗 Our Vegetarian options:<br>{$t}";
}

// ── PRICE ──
elseif (contains($msg, ['price', 'cost', 'how much', 'expensive', 'cheap', 'affordable', 'rs', 'rupee'])) {
    $cheapest = null; $most_exp = null;
    foreach ($menu_items as $item) {
        if (!$cheapest || $item['price'] < $cheapest['price']) $cheapest = $item;
        if (!$most_exp || $item['price'] > $most_exp['price']) $most_exp = $item;
    }
    $reply = "💰 Our prices range from <strong>Rs. " . number_format($cheapest['price'], 0) . "</strong> ({$cheapest['name']}) to <strong>Rs. " . number_format($most_exp['price'], 0) . "</strong> ({$most_exp['name']}).<br><br>Type the name of a dish or category to see specific prices! 😊";
}

// ── PROMOTIONS ──
elseif (contains($msg, ['promotion', 'promo', 'discount', 'offer', 'deal', 'coupon', 'sale', 'off'])) {
    if (empty($promos)) {
        $reply = "🏷️ No active promotions right now, but check back soon! We regularly offer great deals. 😊<br><br>Browse our full menu for great value dishes!";
    } else {
        $reply = "🎉 Current Promotions:<br><br>";
        foreach ($promos as $p) {
            $reply .= "🏷️ <strong>{$p['title']}</strong> — {$p['discount_percent']}% OFF<br>";
            if ($p['description']) $reply .= "📝 {$p['description']}<br>";
            $reply .= "📅 Valid until: {$p['end_date']}<br><br>";
        }
    }
}

// ── RESERVATION ──
elseif (contains($msg, ['reservation', 'reserve', 'book', 'table', 'booking', 'seat'])) {
    $reply = "📅 <strong>How to Book a Table:</strong><br><br>
1️⃣ Go to the <strong>Reservations</strong> page from the menu<br>
2️⃣ Click <strong>Book a Table</strong><br>
3️⃣ Select your <strong>date & time</strong><br>
4️⃣ Add your phone number & special requests<br>
5️⃣ Click <strong>Confirm Booking</strong><br><br>
Our team will confirm your reservation shortly! 🪑";
}

// ── ORDER ──
elseif (contains($msg, ['order', 'how to order', 'place order', 'buy', 'purchase', 'takeaway', 'take away', 'delivery'])) {
    $reply = "📦 <strong>How to Order Online:</strong><br><br>
1️⃣ Browse our <strong>Menu</strong> page<br>
2️⃣ Click <strong>+ Add</strong> on items you want<br>
3️⃣ Go to <strong>Cart</strong> and review your items<br>
4️⃣ Choose <strong>Takeaway or Dine-In</strong><br>
5️⃣ Select payment method (Cash/Card)<br>
6️⃣ Click <strong>Place Order</strong> ✅<br><br>
You'll get an email confirmation instantly! 📧";
}

// ── PAYMENT ──
elseif (contains($msg, ['pay', 'payment', 'cash', 'card', 'how to pay'])) {
    $reply = "💳 <strong>Payment Methods:</strong><br><br>
💵 <strong>Cash</strong> — Pay when you receive your order<br>
💳 <strong>Card</strong> — Credit/Debit card accepted<br>
📱 <strong>Other</strong> — Other payment methods<br><br>
Select your preferred method when placing your order! 😊";
}

// ── LOCATION / ADDRESS ──
elseif (contains($msg, ['where', 'location', 'address', 'find you', 'directions', 'map'])) {
    $reply = "📍 <strong>Minmi Restaurant</strong><br><br>
Please contact us for our exact location:<br>
📧 minmirestaurant@gmail.com<br><br>
We look forward to welcoming you! 🔥";
}

// ── CONTACT ──
elseif (contains($msg, ['contact', 'phone', 'email', 'call', 'whatsapp', 'reach'])) {
    $reply = "📞 <strong>Contact Minmi Restaurant:</strong><br><br>
📧 Email: <strong>minmirestaurant@gmail.com</strong><br><br>
Feel free to reach out — we're happy to help! 😊";
}

// ── HOURS ──
elseif (contains($msg, ['open', 'hour', 'time', 'when', 'close', 'closing', 'opening'])) {
    $reply = "🕐 <strong>Opening Hours:</strong><br><br>
Please contact us for current opening hours:<br>
📧 minmirestaurant@gmail.com<br><br>
We serve authentic Sri Lankan cuisine every day! 🍛";
}

// ── REGISTER / LOGIN ──
elseif (contains($msg, ['register', 'sign up', 'create account', 'account'])) {
    $reply = "👤 <strong>Create Your Account:</strong><br><br>
1️⃣ Click <strong>Get Started</strong> in the top right<br>
2️⃣ Enter your name, email and password<br>
3️⃣ Click <strong>Create Account</strong><br><br>
Having an account lets you order online, book tables and track orders! 🎉";
}
elseif (contains($msg, ['login', 'log in', 'sign in', 'forgot password'])) {
    $reply = "🔐 <strong>Login to Your Account:</strong><br><br>
1️⃣ Click <strong>Login</strong> in the top right<br>
2️⃣ Enter your email and password<br>
3️⃣ Click <strong>Sign In</strong><br><br>
Forgot your password? Please contact us at minmirestaurant@gmail.com 😊";
}

// ── THANK YOU ──
elseif (contains($msg, ['thank', 'thanks', 'thank you', 'thx', 'ty'])) {
    $replies = [
        "🙏 You're welcome! Enjoy your meal at Minmi Restaurant! 🔥",
        "😊 Happy to help! Come visit us soon! 🍛",
        "🙏 Our pleasure! Let me know if you need anything else! 😊"
    ];
    $reply = $replies[array_rand($replies)];
}

// ── BYE ──
elseif (contains($msg, ['bye', 'goodbye', 'see you', 'later', 'cya'])) {
    $reply = "👋 Goodbye! Thank you for visiting Minmi Restaurant! See you soon! 🔥🍛";
}

// ── FEEDBACK ──
elseif (contains($msg, ['feedback', 'review', 'rating', 'rate', 'complain', 'complaint'])) {
    $reply = "⭐ <strong>Share Your Feedback:</strong><br><br>
We'd love to hear from you! Go to the <strong>Feedback</strong> page to:<br>
• Rate your experience (1-5 stars)<br>
• Write a review<br>
• Link your feedback to a specific order<br><br>
Your feedback helps us improve! 🙏";
}

// ── ABOUT ──
elseif (contains($msg, ['about', 'who are you', 'what is minmi', 'tell me about', 'restaurant'])) {
    $reply = "🔥 <strong>About Minmi Restaurant</strong><br><br>
We are an authentic Sri Lankan restaurant serving fire-crafted flavours every night! 🍛<br><br>
🍽️ <strong>50+ menu items</strong><br>
⭐ <strong>Top-rated cuisine</strong><br>
📦 <strong>Online ordering</strong><br>
📅 <strong>Table reservations</strong><br>
🌶️ <strong>Authentic Sri Lankan recipes</strong><br><br>
Contact: minmirestaurant@gmail.com";
}

// ── SEASONAL ──
elseif (contains($msg, ['seasonal', 'special', 'today special', 'special dish', 'recommend'])) {
    $seasonal = array_filter($menu_items, fn($i) => false); // placeholder
    $seasonal_items = $pdo->query("SELECT name, price FROM menu_items WHERE status='Seasonal' LIMIT 5")->fetchAll();
    if (!empty($seasonal_items)) {
        $reply = "🌿 <strong>Today's Seasonal Specials:</strong><br><br>";
        foreach ($seasonal_items as $s) {
            $reply .= "🌟 <strong>{$s['name']}</strong> — Rs. " . number_format($s['price'], 0) . "<br>";
        }
        $reply .= "<br>These are limited time items — order while they last! 😊";
    } else {
        $reply = "🍽️ I recommend trying our most popular dishes! Type <strong>'menu'</strong> to see all available items. 😊";
    }
}

// ── DEFAULT fallback ──
else {
    $suggestions = [
        "🤔 I'm not sure about that, but I can help you with:<br><br>
🍽️ Type <strong>'menu'</strong> — See our full menu<br>
💰 Type <strong>'price'</strong> — Check prices<br>
📅 Type <strong>'reservation'</strong> — Book a table<br>
📦 Type <strong>'order'</strong> — How to order online<br>
🎉 Type <strong>'promotions'</strong> — See current deals<br>
📞 Type <strong>'contact'</strong> — Get in touch<br><br>
Or email us: minmirestaurant@gmail.com 😊",
    ];
    $reply = $suggestions[0];
}

echo json_encode(['reply' => $reply]);