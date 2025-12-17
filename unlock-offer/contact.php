<?php
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit;
}

// 1. Check if the token was sent
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['recaptcha_response'])) {

    // 2. Build the verification request
    $recaptcha_url = 'https://www.google.com/recaptcha/api/siteverify';
    $recaptcha_secret = '6LfgVSYsAAAAANbGJiKPA-O15G58dOpiXvISQZQy';
    $recaptcha_response = $_POST['recaptcha_response'];

    // 3. Send request to Google
    $verify = file_get_contents($recaptcha_url . '?secret=' . $recaptcha_secret . '&response=' . $recaptcha_response);
    $captcha_success = json_decode($verify);

    // --- DEBUG START ---
//     echo "<pre>";
//     echo "<strong>Raw Response from Google:</strong><br>";
//     var_dump($captcha_success);
//     echo "</pre>";
//     echo "Token received: " . substr($_POST['recaptcha_response'], 0, 20) . "...<br>";
//     die("Script stopped for debugging.");
    // --- DEBUG END ---

    // 4. Check results
    // reCAPTCHA v3 returns a 'score' (0.0 to 1.0).
    // 0.5 is a common threshold. < 0.5 is likely a bot.
    if ($captcha_success->success == false || $captcha_success->score < 0.5) {
        // Redirect back with an error if it fails
        header("Location: confirmation.html?status=error&message=captcha_error");
        exit();
    }

    // If we pass here, the user is human. Continue with your email code below...

} else {
    // If no token exists, block the request
    header("Location: confirmation.html?status=error&message=captcha_error_token");
    exit();
}


// ---- PHPMailer includes (pick Composer OR manual) ----
require __DIR__ . '/phpmailer/Exception.php';
require __DIR__ . '/phpmailer/PHPMailer.php';
require __DIR__ . '/phpmailer/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// SMTP config (from your client)
$smtpHost     = getenv('SES_SMTP_HOST');
$smtpUsername = getenv('SES_SMTP_USERNAME');
$smtpPassword = getenv('SES_SMTP_PASSWORD');
$smtpPort     = (int)(getenv('SES_SMTP_PORT') ?: 587);
$smtpSecure   = getenv('SES_SMTP_SECURE') ?: 'tls';

// ---- COLLECT FIELDS ----
// adjust these to match your actual form field names
$email     = trim($_POST['email'] ?? '');
$message = trim($_POST['message'] ?? '');

// ---- CONFIG ----
$recipient       = "support@myperashop.com";              // where the email should be sent
$subject         = "New Support Form Submission: {$email}";
$recaptchaSecret = "6LfgVSYsAAAAANbGJiKPA-O15G58dOpiXvISQZQy";   // from Google reCAPTCHA

// reCAPTCHA token
$token = $_POST['g-recaptcha-response'] ?? '';

if (!$message) {
    echo json_encode(['success' => false, 'message' => 'Please fill in all required fields.']);
    exit;
}

// ---- BUILD EMAIL BODY ----
$bodyLines = [];
$bodyLines[] = "New Support form submission:";
$bodyLines[] = "<br>";
$bodyLines[] = "Message Content";
$bodyLines[] = "<br>";
$bodyLines[] = "Email: {$email}<br>";
$bodyLines[] = "<br>";
$bodyLines[] = "Message: {$message}<br>";
$body = implode("\n", $bodyLines);

// ---- SEND EMAIL VIA SMTP ----
$mail = new PHPMailer(true);

try {
    // Server settings
    $mail->isSMTP();
    $mail->Host       = $smtpHost;
    $mail->SMTPAuth   = true;
    $mail->Username   = $smtpUsername;
    $mail->Password   = $smtpPassword;
    $mail->SMTPSecure = $smtpSecure;
    $mail->Port       = $smtpPort;

    // Encoding (optional, but nice)
    $mail->CharSet = 'UTF-8';

    $mail->isHTML(true);

    // Recipients
    $mail->setFrom('support@myperashop.com', 'MyPera Support'); // use a real domain address
    $mail->addAddress($recipient);

    // Let client reply directly to user
    $mail->addReplyTo($email, "{$email}");

    // Content
    $mail->Subject = $subject;
    $mail->Body    = $body;

    $mail->send();

    echo json_encode(['success' => true, 'message' => 'Booking submitted successfully.']);
    header("Location: thank_you.html");
    exit;
} catch (Exception $e) {
    // In production you might hide $mail->ErrorInfo and log it instead
    echo json_encode([
        'success' => false,
        'message' => 'Mailer Error: ' . $mail->ErrorInfo,
    ]);
    header("Location: confirmation.html?status=error&message=mailer_error");
    exit;
}
