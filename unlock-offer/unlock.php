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
    $recaptcha_secret = '6LfgVSYsAAAAANbGJiKPA-O15G58dOpiXvISQZQy'; // <--- PUT YOUR PRIVATE SECRET KEY HERE
    $recaptcha_response = $_POST['recaptcha_response'];

    // 3. Send request to Google
    $verify = file_get_contents($recaptcha_url . '?secret=' . $recaptcha_secret . '&response=' . $recaptcha_response);
    $captcha_success = json_decode($verify);

    // 4. Check results
    // reCAPTCHA v3 returns a 'score' (0.0 to 1.0).
    // 0.5 is a common threshold. < 0.5 is likely a bot.
    if ($captcha_success->success == false || $captcha_success->score < 0.5) {
        // Redirect back with an error if it fails
        header("Location: index.html?status=error&message=captcha_error");
        exit();
    }

    // If we pass here, the user is human. Continue with your email code below...

} else {
    // If no token exists, block the request
    header("Location: index.html?status=error&message=captcha_error_token");
    exit();
}


// ---- PHPMailer includes (pick Composer OR manual) ----
require __DIR__ . '/phpmailer/Exception.php';
require __DIR__ . '/phpmailer/PHPMailer.php';
require __DIR__ . '/phpmailer/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// ---- CONFIG ----
$recipient       = "kazdal@gmail.com";              // where the email should be sent
$subject         = "New Form Submission";
$recaptchaSecret = "6LfgVSYsAAAAANbGJiKPA-O15G58dOpiXvISQZQy";   // from Google reCAPTCHA

// SMTP config (from your client)
$smtpHost     = "email-smtp.us-east-1.amazonaws.com";
$smtpUsername = "AKIAQWX6AMAFJPBDE4YH";
$smtpPassword = "BKa7DMRM+ClOHg5ZI4jkg/O0vKMXeiYQMISZISemugDj";
$smtpPort     = 587; // 465 for SMTPS if needed
$smtpSecure   = 'tls'; // or PHPMailer::ENCRYPTION_SMTPS

// ---- COLLECT FIELDS ----
// adjust these to match your actual form field names
$email     = trim($_POST['email'] ?? '');
$phone     = trim($_POST['phone'] ?? '');

// reCAPTCHA token
$token = $_POST['g-recaptcha-response'] ?? '';

if (!$email) {
    echo json_encode(['success' => false, 'message' => 'Please fill in all required fields.']);
    exit;
}

// ---- BUILD EMAIL BODY ----
$bodyLines = [];
$bodyLines[] = "New Unlock My Reward form submission:";
$bodyLines[] = "<br>";
$bodyLines[] = "Contact Information";
$bodyLines[] = "<br>";
$bodyLines[] = "Email: {$email}<br>";
$bodyLines[] = "Phone: {$phone}<br>";
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
    $mail->setFrom('support@myperashop.com', 'Unlock My Reward'); // use a real domain address
    $mail->addAddress($recipient);

    // Let client reply directly to user
    $mail->addReplyTo($email, "{$email}");

    // Content
    $mail->Subject = $subject;
    $mail->Body    = $body;

    $mail->send();

    echo json_encode(['success' => true, 'message' => 'Booking submitted successfully.']);
    header("Location: confirmation.html");
    exit;
} catch (Exception $e) {
    // In production you might hide $mail->ErrorInfo and log it instead
    echo json_encode([
        'success' => false,
        'message' => 'Mailer Error: ' . $mail->ErrorInfo,
    ]);
    header("Location: index.html?status=error&message=mailer_error");
    exit;
}
