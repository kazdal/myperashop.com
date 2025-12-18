<?php
// 1. Start clean. No JSON headers.

// 2. Block direct access to the file
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: index.html");
    exit;
}

// 3. HONEYPOT CHECK (Spam Prevention)
// Ensure your HTML form has the hidden field named 'honeypot_check'
if (!empty($_POST['hp_check'])) {
    // If this field is filled, it's a bot.
    // We redirect them to the home page pretending nothing happened.
    header("Location: index.html");
    exit();
}

// 4. GOOGLE RECAPTCHA CHECK
if (isset($_POST['recaptcha_response'])) {
    $recaptcha_url = 'https://www.google.com/recaptcha/api/siteverify';
    $recaptcha_secret = '6LfgVSYsAAAAANbGJiKPA-O15G58dOpiXvISQZQy';
    $recaptcha_response = $_POST['recaptcha_response'];

    $verify = file_get_contents($recaptcha_url . '?secret=' . $recaptcha_secret . '&response=' . $recaptcha_response);
    $captcha_success = json_decode($verify);

    if ($captcha_success->success == false || $captcha_success->score < 0.5) {
        header("Location: index.html?status=error&message=Bot+Verification+Failed");
        exit();
    }
} else {
    header("Location: index.html?status=error&message=Captcha+Token+Missing");
    exit();
}

// ---- PHPMailer includes ----
require __DIR__ . '/phpmailer/Exception.php';
require __DIR__ . '/phpmailer/PHPMailer.php';
require __DIR__ . '/phpmailer/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// SMTP config
$smtpHost     = getenv('SES_SMTP_HOST');
$smtpUsername = getenv('SES_SMTP_USERNAME');
$smtpPassword = getenv('SES_SMTP_PASSWORD');
$smtpPort     = (int)(getenv('SES_SMTP_PORT') ?: 587);
$smtpSecure   = getenv('SES_SMTP_SECURE') ?: 'tls';

// ---- COLLECT & SANITIZE FIELDS ----
$email = filter_var(trim($_POST['email'] ?? ''), FILTER_SANITIZE_EMAIL);
$phone = htmlspecialchars(trim($_POST['phone'] ?? ''));

// Validate Email Format
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    header("Location: index.html?status=error&message=Invalid+Email+Address");
    exit;
}

// ---- CONFIG ----
$recipient = "support@myperashop.com";
$subject   = "New Form Submission: {$email}";

// ---- BUILD EMAIL BODY ----
$bodyLines = [];
$bodyLines[] = "New Unlock My Reward form submission:";
$bodyLines[] = "<br>";
$bodyLines[] = "<strong>Contact Information</strong>";
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
    $mail->CharSet    = 'UTF-8';

    // Recipients
    $mail->setFrom('support@myperashop.com', 'Unlock My Reward');
    $mail->addAddress($recipient);
    $mail->addReplyTo($email); // Safe because we validated $email above

    // Content
    $mail->isHTML(true);
    $mail->Subject = $subject;
    $mail->Body    = $body;
    $mail->AltBody = strip_tags($body); // Good practice for spam filters

    $mail->send();

    try {

        $response_email_body = '<table style="width:100%"><tr><td style="padding: 20px; font-family: sans-serif; font-size: 16px; background-color: #f1f1f1;" align="center"><table style="width: 800px;"><tr><td style="padding: 20px; background-color: #E7F7FEFF; text-align: center;"><img src="https://myperashop.com/assets/images/logo-1x.png" style="width: 150px;"></td></tr><tr><td style="background-color: #FFFFFF; padding: 20px; color: #4c4c4c;"><p style=" font-size: 24px;">Thank you for choosing My Pera and for your first purchase.</p><p style="">We sincerely appreciate your trust and are delighted to welcome you to the My Pera family.</p><p style=" font-size: 24px; font-weight: bold;">CONGRATULATIONS !</p><p>Your purchase includes a complimentary <strong>30-DAY REFUND OR REPLACEMENT</strong>.</p><p>Should your product not meet your expectations, simply visit your Amazon.com order page to request a refund or replacement.</p><p>In addition, <strong>WE OFFER AN EXTENDED 90-DAY FREE REPLACEMENT</strong> for your peace of mind.</p><p>If you experience any issue with your product, you may return it to us, and we will be pleased to provide a complimentary replacement.</p><p>Return address:<br>My Pera<br>45 East Main Street, Suite 106<br>Freehold, NJ 07728</p><p>Your satisfaction is our highest priority. If you have any questions or require assistance, please contact us directly.</p><p>Our team is always here to ensure your experience with My Pera is exceptional.</p><p>Contact: support@myperashop.com</p><p>Warm regards,<br>My Pera Team</p></td></tr></table></td></tr></table>';

        $response_mail = new PHPMailer(true);
        $response_mail->isSMTP();
        $response_mail->Host       = $smtpHost;
        $response_mail->SMTPAuth   = true;
        $response_mail->Username   = $smtpUsername;
        $response_mail->Password   = $smtpPassword;
        $response_mail->SMTPSecure = $smtpSecure;
        $response_mail->Port       = $smtpPort;
        $response_mail->CharSet    = 'UTF-8';

        // Recipients
        $response_mail->setFrom('support@myperashop.com', 'MyPera Shop');
        $response_mail->addAddress($email);

        // Content
        $response_mail->isHTML(true);
        $response_mail->Subject = $subject;
        $response_mail->Body    = $response_email_body;
        $response_mail->AltBody = strip_tags($response_email_body); // Good practice for spam filters

        $response_mail->send();

        // SUCCESS: Redirect to the confirmation page
        header("Location: confirmation.html");
        exit();

    } catch (Exception $e) {
        // ERROR: Redirect with error message
        // Note: We do NOT send the raw $mail->ErrorInfo to the URL for security reasons
//         header("Location: index.html?status=error&message=Email+Server+Error");
        echo json_encode([
                'success' => false,
                'message' => 'Mailer Error 1: ' . $mail->ErrorInfo,
            ]);
        exit();
    }

} catch (Exception $e) {
    // ERROR: Redirect with error message
    // Note: We do NOT send the raw $mail->ErrorInfo to the URL for security reasons
//     header("Location: index.html?status=error&message=Email+Server+Error");
    echo json_encode([
            'success' => false,
            'message' => 'Mailer Error 2: ' . $mail->ErrorInfo,
        ]);
    exit();
}
?>
