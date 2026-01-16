<?php
// Database connection
$servername = "localhost";
$username = "root"; // Change to your database username
$password = ""; // Change to your database password
$dbname = "jhay_gadget"; // Change to your database name

// Create connection
$db = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($db->connect_error) {
    die("Connection failed: " . $db->connect_error);
}

session_start();

// Create tables if they don't exist
$createTables = [
    "CREATE TABLE IF NOT EXISTS chatbot_messages (
        id INT AUTO_INCREMENT PRIMARY KEY,
        email VARCHAR(255),
        message TEXT NOT NULL,
        type ENUM('general', 'question', 'complaint', 'feedback') DEFAULT 'general',
        is_anonymous TINYINT(1) DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )",
    
    "CREATE TABLE IF NOT EXISTS complaints (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(255) NOT NULL,
        email VARCHAR(255),
        contact VARCHAR(100),
        product_name VARCHAR(255),
        purchase_date DATE,
        issue_type VARCHAR(100),
        complaint_details TEXT NOT NULL,
        is_anonymous TINYINT(1) DEFAULT 0,
        status ENUM('pending', 'in_progress', 'resolved') DEFAULT 'pending',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )"
];

foreach ($createTables as $query) {
    if (!$db->query($query)) {
        error_log("Table creation failed: " . $db->error);
    }
}

// Handle chatbot message submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['send_message'])) {
        $email = $_POST['email'] ?? '';
        $message = trim($_POST['message']);
        $type = $_POST['type'] ?? 'general';
        $is_anonymous = isset($_POST['is_anonymous']) && $_POST['is_anonymous'] == '1' ? 1 : 0;
        
        if (!empty($message)) {
            // Save to chatbot_messages table
            $stmt = $db->prepare("INSERT INTO chatbot_messages (email, message, type, is_anonymous, created_at) VALUES (?, ?, ?, ?, NOW())");
            $stmt->bind_param("sssi", $email, $message, $type, $is_anonymous);
            
            if ($stmt->execute()) {
                $message_id = $stmt->insert_id;
                
                if ($is_anonymous) {
                    $_SESSION['chatbot_success'] = "Thank you for your anonymous message! We'll address your concern while respecting your privacy.";
                } else {
                    $_SESSION['chatbot_success'] = "Thank you for your message! We'll get back to you soon.";
                }
                
                // ALSO SAVE TO COMPLAINTS TABLE IF IT'S A COMPLAINT
                if ($type === 'complaint') {
                    $complaint_stmt = $db->prepare("INSERT INTO complaints (name, email, contact, product_name, purchase_date, issue_type, complaint_details, is_anonymous, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())");
                    
                    // Handle anonymous complaints
                    if ($is_anonymous) {
                        $name = "Anonymous User";
                        $email = ""; // Clear email for anonymous complaints
                        $contact = "Anonymous";
                    } else {
                        // Extract name from email or use default
                        $name = "Chatbot User";
                        if (!empty($email)) {
                            $name = explode('@', $email)[0];
                            $name = ucfirst($name);
                        }
                        $contact = "Via Chatbot";
                    }
                    
                    $product_name = $_POST['product_name'] ?? "Not specified";
                    $purchase_date = $_POST['purchase_date'] ?? date('Y-m-d');
                    
                    // Auto-detect issue type from message content or use provided one
                    $issue_type = $_POST['issue_type'] ?? "Other";
                    $message_lower = strtolower($message);
                    
                    if (empty($_POST['issue_type']) || $_POST['issue_type'] === 'Other') {
                        if (strpos($message_lower, 'defective') !== false || strpos($message_lower, 'not working') !== false || strpos($message_lower, 'broken') !== false) {
                            $issue_type = "Defective Product";
                        } elseif (strpos($message_lower, 'warranty') !== false) {
                            $issue_type = "Warranty Issue";
                        }
                    }
                    
                    $complaint_stmt->bind_param("sssssssi", $name, $email, $contact, $product_name, $purchase_date, $issue_type, $message, $is_anonymous);
                    
                    if ($complaint_stmt->execute()) {
                        if ($is_anonymous) {
                            $_SESSION['chatbot_success'] = "Your anonymous complaint has been submitted successfully. We'll address it while protecting your identity.";
                        } else {
                            $_SESSION['chatbot_success'] = "Your complaint has been submitted successfully. We'll get back to you soon.";
                        }
                    } else {
                        $_SESSION['chatbot_error'] = "Complaint submitted but there was an error saving details.";
                    }
                }
            } else {
                $_SESSION['chatbot_error'] = "Sorry, there was an error sending your message. Please try again.";
            }
        }
    }
    
    // Handle AJAX quick response requests
    if (isset($_POST['get_quick_response'])) {
        $message = trim($_POST['message']);
        $response = getQuickResponse($message);
        echo json_encode(['response' => $response]);
        exit;
    }
}

// Get quick responses for common questions
function getQuickResponse($message) {
    $message = strtolower(trim($message));
    
    // Shop information responses
    if (strpos($message, 'hour') !== false || strpos($message, 'open') !== false || strpos($message, 'close') !== false || strpos($message, 'time') !== false) {
        return "🕒 **Operating Hours:**\nMonday - Saturday: 9:00 AM - 8:00 PM\nSunday: Closed\n\nWe're here to assist you during these hours!";
    }
    
    if (strpos($message, 'location') !== false || strpos($message, 'address') !== false || strpos($message, 'where') !== false || strpos($message, 'place') !== false) {
        return "📍 **Store Location:**\nChampion Bldg., La Purisima, Zamboanga City, Philippines\n\nWe're located near the city center for your convenience!";
    }
    
    if (strpos($message, 'contact') !== false || strpos($message, 'phone') !== false || strpos($message, 'number') !== false || strpos($message, 'call') !== false || strpos($message, 'email') !== false) {
        return "📞 **Contact Information:**\nPhone: 0905-483-2512\nEmail: jhaygadget@gmail.com\n\nFeel free to reach out anytime!";
    }
    
    if (strpos($message, 'product') !== false || strpos($message, 'sell') !== false || strpos($message, 'offer') !== false || strpos($message, 'item') !== false) {
        return "🛍️ **Our Products:**\nWe specialize in:\n• iPhones & Smartphones\n• Laptops & Computers\n• Tech Accessories\n• Gadgets & Electronics\n\nCheck our products page for the latest offerings and deals!";
    }
    
    if (strpos($message, 'warranty') !== false || strpos($message, 'guarantee') !== false) {
        return "🔧 **Warranty Information:**\nWe offer warranty on our products! Please provide:\n• Product name\n• Purchase date\n• Receipt number\n\nWe'll check your warranty status immediately.";
    }
    
    if (strpos($message, 'price') !== false || strpos($message, 'cost') !== false || strpos($message, 'how much') !== false) {
        return "💰 **Pricing:**\nPrices vary by product and specifications. Please:\n• Check our products page\n• Visit our store\n• Call us at 0905-483-2512\n\nWe offer competitive prices and quality products!";
    }
    
    // Complaint related responses
    if (strpos($message, 'complaint') !== false || strpos($message, 'issue') !== false || strpos($message, 'problem') !== false || strpos($message, 'broken') !== false) {
        return "⚠️ **Issue Reported:**\nI understand you're having a problem. Please provide:\n• Product/Service details\n• When the issue started\n• What exactly happened\n\nWe'll resolve this as quickly as possible!";
    }
    
    if (strpos($message, 'defective') !== false || strpos($message, 'not working') !== false || strpos($message, 'faulty') !== false) {
        return "🔧 **Defective Product:**\nFor defective items, please share:\n• Product name & model\n• Purchase date\n• Specific issue details\n• Receipt/photos if available\n\nWe'll help resolve this quickly!";
    }
    
    // Privacy/Anonymous responses
    if (strpos($message, 'anonymous') !== false || strpos($message, 'privacy') !== false || strpos($message, 'confidential') !== false) {
        return "🕵️ **Anonymous Complaints:**\nYou can submit anonymous complaints! When filing a complaint, check the 'Submit anonymously' option to keep your identity private. We'll still address your concern while respecting your privacy.";
    }
    
    // Feedback responses
    if (strpos($message, 'feedback') !== false || strpos($message, 'suggestion') !== false || strpos($message, 'review') !== false) {
        return "🌟 **Feedback:**\nThank you for your input! We appreciate your suggestions to improve our services. Your feedback helps us serve you better!";
    }
    
    // Urgent issues
    if (strpos($message, 'urgent') !== false || strpos($message, 'emergency') !== false || strpos($message, 'asap') !== false) {
        return "🚨 **Urgent Attention:**\nI've flagged this as urgent! Please call us immediately at 0905-483-2512 for fastest resolution.";
    }
    
    // Greetings
    if (strpos($message, 'hello') !== false || strpos($message, 'hi') !== false || strpos($message, 'hey') !== false) {
        return "👋 **Hello!** Welcome to JHAY GADGET! How can I assist you today?";
    }
    
    if (strpos($message, 'thank') !== false || strpos($message, 'thanks') !== false) {
        return "🙏 **You're welcome!** I'm happy to help! Let me know if you need anything else.";
    }
    
    // Default response for unrecognized messages
    return "🤖 **Thank you for your message!** Our team will review it and get back to you soon. For immediate assistance, call us at 0905-483-2512.";
}

// Get session messages
$chatbotSuccess = $_SESSION['chatbot_success'] ?? '';
$chatbotError = $_SESSION['chatbot_error'] ?? '';
unset($_SESSION['chatbot_success'], $_SESSION['chatbot_error']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Chatbot · JHAY GADGET</title>
<style>
:root {
  --blue: #1e88e5;
  --blue-600: #1976d2;
  --green: #43a047;
  --red: #e53935;
  --orange: #fb8c00;
  --purple: #8e24aa;
  --ink: #0f172a;
  --ink-2: #111827;
  --bg: #000;
  --card: #111;
  --border: #333;
  --muted: #9ca3af;
}

* {
  box-sizing: border-box;
}

body {
  font-family: Inter, system-ui, -apple-system, Segoe UI, Roboto, "Helvetica Neue", Arial;
  background: var(--bg);
  color: #f9fafb;
  margin: 0;
  padding: 0;
  height: 100vh;
  display: flex;
  flex-direction: column;
}

header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 10px 16px;
  background: var(--ink-2);
  box-shadow: 0 2px 0 rgba(0,0,0,.5);
  position: sticky;
  top: 0;
  z-index: 100;
}

.brand {
  display: flex;
  align-items: center;
  gap: 12px;
}

header img {
  width: 48px;
  height: 48px;
  border-radius: 8px;
  border: 3px solid var(--ink-2);
  object-fit: contain;
}

header .title {
  font-weight: 700;
  font-size: 1.5rem;
  color: #f9fafb;
}

.back-btn {
  background: var(--blue);
  color: white;
  border: none;
  padding: 8px 16px;
  border-radius: 8px;
  cursor: pointer;
  text-decoration: none;
  display: inline-flex;
  align-items: center;
  gap: 8px;
  font-size: 14px;
}

.back-btn:hover {
  background: var(--blue-600);
}

.chatbot-container {
  flex: 1;
  display: flex;
  flex-direction: column;
  max-width: 800px;
  margin: 0 auto;
  width: 100%;
  padding: 20px;
  gap: 20px;
}

.chat-window {
  flex: 1;
  background: var(--card);
  border: 1px solid var(--border);
  border-radius: 12px;
  padding: 20px;
  display: flex;
  flex-direction: column;
  gap: 15px;
  min-height: 500px;
}

.chat-header {
  display: flex;
  align-items: center;
  gap: 10px;
  padding-bottom: 15px;
  border-bottom: 1px solid var(--border);
}

.chat-header .bot-avatar {
  width: 40px;
  height: 40px;
  background: var(--blue);
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  font-weight: bold;
}

.chat-messages {
  flex: 1;
  overflow-y: auto;
  max-height: 400px;
  display: flex;
  flex-direction: column;
  gap: 15px;
  padding: 10px 0;
}

.message {
  padding: 12px 16px;
  border-radius: 12px;
  max-width: 80%;
  line-height: 1.4;
}

.message.user {
  background: var(--blue);
  color: white;
  margin-left: auto;
  border-bottom-right-radius: 4px;
}

.message.bot {
  background: var(--ink-2);
  border: 1px solid var(--border);
  margin-right: auto;
  border-bottom-left-radius: 4px;
  white-space: pre-line;
}

.message.typing {
  background: var(--ink-2);
  border: 1px solid var(--border);
  margin-right: auto;
  border-bottom-left-radius: 4px;
  padding: 8px 16px;
}

.typing-indicator {
  display: flex;
  gap: 4px;
  align-items: center;
}

.typing-dot {
  width: 6px;
  height: 6px;
  background: var(--muted);
  border-radius: 50%;
  animation: typingAnimation 1.4s infinite ease-in-out;
}

.typing-dot:nth-child(1) { animation-delay: -0.32s; }
.typing-dot:nth-child(2) { animation-delay: -0.16s; }

@keyframes typingAnimation {
  0%, 80%, 100% { transform: scale(0.8); opacity: 0.5; }
  40% { transform: scale(1); opacity: 1; }
}

.message-time {
  font-size: 11px;
  color: var(--muted);
  margin-top: 5px;
  text-align: right;
}

.chat-input-form {
  display: flex;
  gap: 10px;
  align-items: flex-end;
  border-top: 1px solid var(--border);
  padding-top: 15px;
}

.message-type-selector {
  display: flex;
  gap: 8px;
  margin-bottom: 10px;
  flex-wrap: wrap;
}

.type-btn {
  background: var(--ink-2);
  border: 1px solid var(--border);
  border-radius: 20px;
  padding: 8px 16px;
  color: var(--muted);
  cursor: pointer;
  font-size: 14px;
}

.type-btn.active {
  background: var(--blue);
  border-color: var(--blue);
  color: white;
}

.type-btn.complaint {
  border-color: var(--orange);
  color: var(--orange);
}

.type-btn.complaint.active {
  background: var(--orange);
  color: white;
}

.type-btn.feedback {
  border-color: var(--green);
  color: var(--green);
}

.type-btn.feedback.active {
  background: var(--green);
  color: white;
}

.input-group {
  flex: 1;
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.email-input {
  background: var(--ink-2);
  border: 1px solid var(--border);
  border-radius: 8px;
  padding: 10px;
  color: #f9fafb;
  font-size: 14px;
}

.message-input {
  background: var(--ink-2);
  border: 1px solid var(--border);
  border-radius: 8px;
  padding: 12px;
  color: #f9fafb;
  font-size: 14px;
  resize: none;
  min-height: 60px;
  font-family: inherit;
}

.send-btn {
  background: var(--blue);
  border: none;
  border-radius: 8px;
  padding: 12px 20px;
  color: white;
  cursor: pointer;
  display: flex;
  align-items: center;
  gap: 8px;
  white-space: nowrap;
}

.send-btn:hover {
  background: var(--blue-600);
}

.alert {
  padding: 12px 16px;
  border-radius: 8px;
  margin-bottom: 15px;
  font-size: 14px;
}

.alert-success {
  background: var(--green);
  color: white;
}

.alert-error {
  background: var(--red);
  color: white;
}

/* Modal Styles */
.modal {
  display: none;
  position: fixed;
  top: 0;
  left: 0;
  width: 100%;
  height: 100%;
  background: rgba(0, 0, 0, 0.8);
  z-index: 1000;
}

.modal-content {
  position: absolute;
  top: 50%;
  left: 50%;
  transform: translate(-50%, -50%);
  background: var(--card);
  border: 1px solid var(--border);
  border-radius: 12px;
  padding: 30px;
  width: 90%;
  max-width: 500px;
  max-height: 80vh;
  overflow-y: auto;
}

.modal-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 20px;
  padding-bottom: 15px;
  border-bottom: 1px solid var(--border);
}

.modal-title {
  display: flex;
  align-items: center;
  gap: 10px;
  font-size: 1.25rem;
  font-weight: 600;
  margin: 0;
}

.close-modal {
  background: none;
  border: none;
  color: var(--muted);
  cursor: pointer;
  padding: 5px;
}

.complaint-form, .feedback-form {
  display: flex;
  flex-direction: column;
  gap: 15px;
}

.form-group {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.form-label {
  color: #f9fafb;
  font-size: 14px;
  font-weight: 500;
}

.form-input, .form-select, .form-textarea {
  background: var(--ink-2);
  border: 1px solid var(--border);
  border-radius: 8px;
  padding: 12px;
  color: #f9fafb;
  font-size: 14px;
  font-family: inherit;
}

.form-textarea {
  resize: vertical;
  min-height: 100px;
}

.checkbox-group {
  display: flex;
  align-items: center;
  gap: 8px;
  margin: 10px 0;
}

.checkbox-group input[type="checkbox"] {
  width: 16px;
  height: 16px;
}

.checkbox-label {
  font-size: 14px;
  color: #f9fafb;
}

.anonymous-note {
  background: var(--ink);
  border: 1px solid var(--border);
  border-radius: 8px;
  padding: 12px;
  font-size: 13px;
  color: var(--muted);
  margin-top: 5px;
}

.submit-complaint {
  background: var(--orange);
  color: white;
  border: none;
  border-radius: 8px;
  padding: 12px 20px;
  font-size: 14px;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  margin-top: 10px;
}

.submit-complaint:hover {
  background: #f57c00;
}

.submit-feedback {
  background: var(--green);
  color: white;
  border: none;
  border-radius: 8px;
  padding: 12px 20px;
  font-size: 14px;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  margin-top: 10px;
}

.submit-feedback:hover {
  background: #388e3c;
}

@media (max-width: 768px) {
  .chatbot-container {
    padding: 15px;
  }
  
  .message {
    max-width: 90%;
  }
  
  .chat-input-form {
    flex-direction: column;
  }
  
  .send-btn {
    width: 100%;
    justify-content: center;
  }
  
  .modal-content {
    width: 95%;
    padding: 20px;
  }
}
</style>
</head>
<body>
  <header>
    <div class="brand">
      <img src="img/jhay-gadget-logo.png.jpg" alt="JHAY GADGET">
      <div class="title">JHAY GADGET · Chatbot</div>
    </div>
    <a href="products-view.php" class="back-btn">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <line x1="19" y1="12" x2="5" y2="12"></line>
        <polyline points="12 19 5 12 12 5"></polyline>
      </svg>
      Back to Products
    </a>
  </header>

  <div class="chatbot-container">
    <?php if ($chatbotSuccess): ?>
      <div class="alert alert-success"><?php echo $chatbotSuccess; ?></div>
    <?php endif; ?>
    
    <?php if ($chatbotError): ?>
      <div class="alert alert-error"><?php echo $chatbotError; ?></div>
    <?php endif; ?>

    <!-- Chat Window -->
    <div class="chat-window">
      <div class="chat-header">
        <div class="bot-avatar">JG</div>
        <div>
          <strong>JHAY GADGET Assistant</strong>
          <div style="font-size: 12px; color: var(--muted);">Online</div>
        </div>
      </div>

      <div class="chat-messages" id="chatMessages">
        <!-- Initial bot message -->
        <div class="message bot">
          👋 **Hello! I'm your JHAY GADGET assistant!** 

Type your message below or use the buttons to submit complaints or feedback.

🕵️ **Privacy Feature:** You can submit complaints anonymously to protect your identity!
          <div class="message-time"><?php echo date('g:i A'); ?></div>
        </div>
      </div>

      <form method="POST" class="chat-input-form" id="chatForm">
        <div class="message-type-selector">
          <span style="color: var(--muted); font-size: 14px;">Message type:</span>
          <button type="button" class="type-btn feedback" data-type="feedback" onclick="openFeedbackModal()">💬 Feedback</button>
          <button type="button" class="type-btn complaint" data-type="complaint" onclick="openComplaintModal()">⚠️ Complaint</button>
          <button type="button" class="type-btn active" data-type="question">❓ Question</button>
          <button type="button" class="type-btn" data-type="general">💭 General</button>
        </div>
        
        <div class="input-group">
          <input 
            type="email" 
            name="email" 
            class="email-input" 
            placeholder="Your email (optional for follow-up)"
            value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>"
          >
          <textarea 
            name="message" 
            class="message-input" 
            placeholder="Type your message here... Press Enter to send" 
            required
            id="messageInput"
            rows="3"
          ><?php echo isset($_POST['message']) ? htmlspecialchars($_POST['message']) : ''; ?></textarea>
        </div>
        
        <input type="hidden" name="type" id="messageType" value="question">
        <button type="submit" name="send_message" class="send-btn" id="sendButton">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <line x1="22" y1="2" x2="11" y2="13"></line>
            <polygon points="22 2 15 22 11 13 2 9 22 2"></polygon>
          </svg>
          Send Message
        </button>
      </form>
    </div>
  </div>

  <!-- Complaint Modal -->
  <div id="complaintModal" class="modal">
    <div class="modal-content">
      <div class="modal-header">
        <h3 class="modal-title complaint">
          Submit a Complaint
        </h3>
        <button class="close-modal" onclick="closeComplaintModal()">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <line x1="18" y1="6" x2="6" y2="18"></line>
            <line x1="6" y1="6" x2="18" y2="18"></line>
          </svg>
        </button>
      </div>
      
      <form class="complaint-form" method="POST" id="complaintForm">
        <div class="form-group">
          <label class="form-label">Your Email *</label>
          <input type="email" class="form-input" name="email" id="complaintEmail" required placeholder="Enter your email for follow-up">
        </div>
        
        <div class="form-group">
          <label class="form-label">Issue Type *</label>
          <select class="form-select" name="issue_type" required>
            <option value="">Select issue type</option>
            <option value="Defective Product">Defective Product</option>
            <option value="Warranty Issue">Warranty Issue</option>
            <option value="Service Issue">Service Issue</option>
            <option value="Other">Other</option>
          </select>
        </div>
        
        <div class="form-group">
          <label class="form-label">Product Name (Optional)</label>
          <input type="text" class="form-input" name="product_name" placeholder="Enter product name if applicable">
        </div>
        
        <div class="form-group">
          <label class="form-label">Purchase Date (Optional)</label>
          <input type="date" class="form-input" name="purchase_date">
        </div>
        
        <div class="form-group">
          <label class="form-label">Complaint Details *</label>
          <textarea class="form-textarea" name="message" required placeholder="Please describe your complaint in detail..."></textarea>
        </div>
        
        <div class="checkbox-group">
          <input type="checkbox" name="is_anonymous" id="is_anonymous" value="1" onchange="toggleAnonymous()">
          <label class="checkbox-label" for="is_anonymous">Submit anonymously</label>
        </div>
        
        <div class="anonymous-note" id="anonymousNote" style="display: none;">
          🔒 <strong>Your identity will be protected:</strong> Your name and email will not be stored with this complaint. We'll still address your concern while respecting your privacy.
        </div>
        
        <input type="hidden" name="type" value="complaint">
        <button type="submit" name="send_message" class="submit-complaint">
          Submit Complaint
        </button>
      </form>
    </div>
  </div>

  <!-- Feedback Modal -->
  <div id="feedbackModal" class="modal">
    <div class="modal-content">
      <div class="modal-header">
        <h3 class="modal-title feedback">
          Share Your Feedback
        </h3>
        <button class="close-modal" onclick="closeFeedbackModal()">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <line x1="18" y1="6" x2="6" y2="18"></line>
            <line x1="6" y1="6" x2="18" y2="18"></line>
          </svg>
        </button>
      </div>
      
      <form class="feedback-form" method="POST" id="feedbackForm">
        <div class="form-group">
          <label class="form-label">Your Email (Optional)</label>
          <input type="email" class="form-input" name="email" placeholder="Enter your email if you'd like a response">
        </div>
        
        <input type="hidden" name="type" value="feedback">
        
        <div class="form-group">
          <label class="form-label">Your Feedback *</label>
          <textarea class="form-textarea" name="message" required placeholder="Please share your thoughts, suggestions, or experience with us..."></textarea>
        </div>
        
        <button type="submit" name="send_message" class="submit-feedback">
          Submit Feedback
        </button>
      </form>
    </div>
  </div>

  <script>
    // Message type selection
    const typeButtons = document.querySelectorAll('.type-btn');
    const messageTypeInput = document.getElementById('messageType');
    
    typeButtons.forEach(button => {
      button.addEventListener('click', () => {
        if (!button.classList.contains('complaint') && !button.classList.contains('feedback')) {
          typeButtons.forEach(btn => btn.classList.remove('active'));
          button.classList.add('active');
          messageTypeInput.value = button.dataset.type;
        }
      });
    });

    // Modal Functions
    function openComplaintModal() {
      document.getElementById('complaintModal').style.display = 'block';
    }

    function closeComplaintModal() {
      document.getElementById('complaintModal').style.display = 'none';
    }

    function openFeedbackModal() {
      document.getElementById('feedbackModal').style.display = 'block';
    }

    function closeFeedbackModal() {
      document.getElementById('feedbackModal').style.display = 'none';
    }

    // Toggle anonymous complaint
    function toggleAnonymous() {
      const isAnonymous = document.getElementById('is_anonymous').checked;
      const emailField = document.getElementById('complaintEmail');
      const anonymousNote = document.getElementById('anonymousNote');
      
      if (isAnonymous) {
        emailField.required = false;
        emailField.placeholder = "Email (optional for anonymous complaints)";
        anonymousNote.style.display = 'block';
      } else {
        emailField.required = true;
        emailField.placeholder = "Enter your email for follow-up *";
        anonymousNote.style.display = 'none';
      }
    }

    // Close modals when clicking outside
    window.addEventListener('click', function(event) {
      const complaintModal = document.getElementById('complaintModal');
      const feedbackModal = document.getElementById('feedbackModal');
      
      if (event.target === complaintModal) {
        closeComplaintModal();
      }
      if (event.target === feedbackModal) {
        closeFeedbackModal();
      }
    });

    // Chat functionality
    const chatMessages = document.getElementById('chatMessages');
    const chatForm = document.getElementById('chatForm');
    const messageInput = document.getElementById('messageInput');

    // Add message to chat
    function addMessage(text, isUser = false) {
        const messageDiv = document.createElement('div');
        messageDiv.className = isUser ? 'message user' : 'message bot';
        messageDiv.innerHTML = `${text}<div class="message-time">${getCurrentTime()}</div>`;
        chatMessages.appendChild(messageDiv);
        chatMessages.scrollTop = chatMessages.scrollHeight;
    }

    function getCurrentTime() {
        const now = new Date();
        return now.toLocaleTimeString('en-US', { hour: 'numeric', minute: 'numeric', hour12: true });
    }

    // Get quick response from server
    async function getQuickResponse(message) {
        try {
            const response = await fetch('', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `get_quick_response=true&message=${encodeURIComponent(message)}`
            });
            
            const data = await response.json();
            return data.response;
        } catch (error) {
            console.error('Error getting quick response:', error);
            return "I apologize, but I'm having trouble responding right now. Please try again later.";
        }
    }

    // Handle form submission
    chatForm.addEventListener('submit', async function(e) {
        e.preventDefault();
        
        const message = messageInput.value.trim();
        const messageType = messageTypeInput.value;
        
        if (message) {
            // Add user message to chat immediately
            addMessage(message, true);
            
            // Clear input
            messageInput.value = '';
            
            // Show typing indicator
            const typingDiv = document.createElement('div');
            typingDiv.className = 'message typing';
            typingDiv.id = 'typingIndicator';
            typingDiv.innerHTML = `
            <div class="typing-indicator">
                <div class="typing-dot"></div>
                <div class="typing-dot"></div>
                <div class="typing-dot"></div>
                <span style="margin-left: 8px; color: var(--muted); font-size: 12px;">JHAY GADGET is typing...</span>
            </div>
            `;
            chatMessages.appendChild(typingDiv);
            chatMessages.scrollTop = chatMessages.scrollHeight;
            
            try {
                // Get quick response
                const botResponse = await getQuickResponse(message);
                
                // Remove typing indicator
                const typingIndicator = document.getElementById('typingIndicator');
                if (typingIndicator) {
                    typingIndicator.remove();
                }
                
                // Add bot response
                setTimeout(() => {
                    addMessage(botResponse, false);
                }, 1000);
                
            } catch (error) {
                // Remove typing indicator
                const typingIndicator = document.getElementById('typingIndicator');
                if (typingIndicator) {
                    typingIndicator.remove();
                }
                
                // Add error message
                addMessage("Sorry, I encountered an error. Please try again.", false);
            }
            
            // Submit form data to server for storage
            const formData = new FormData(chatForm);
            fetch('', {
                method: 'POST',
                body: formData
            }).catch(error => {
                console.error('Error submitting form:', error);
            });
        }
    });

    // Enter key to send message
    messageInput.addEventListener('keydown', function(e) {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            chatForm.dispatchEvent(new Event('submit'));
        }
    });

    // Auto-focus message input on load
    window.addEventListener('load', function() {
        messageInput.focus();
    });
  </script>
</body>
</html>