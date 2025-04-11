<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

// Load Composer's autoloader
require 'vendor/autoload.php';

// Initialize response array
$response = [
    'success' => false,
    'message' => ''
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get form data
    $name = $_POST['name'] ?? '';
    $email = $_POST['email'] ?? '';
    $subject = $_POST['subject'] ?? '';
    $message = $_POST['message'] ?? '';

    // Validate inputs
    if (empty($name) || empty($email) || empty($subject) || empty($message)) {
        $response['message'] = 'Please fill in all fields.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $response['message'] = 'Please enter a valid email address.';
    } else {
        try {
            // Create a new PHPMailer instance
            $mail = new PHPMailer(true);

            // Server settings
            $mail->isSMTP();
            $mail->Host = 'smtp.gmail.com'; // Use your SMTP host
            $mail->SMTPAuth = true;
            $mail->Username = 'helawiskam2019@gmail.com'; // Your SMTP username
            $mail->Password = 'hawc rfod mbxk zdyq'; // Your SMTP password
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = 587;

            // Recipients
            $mail->setFrom($email, $name);
            // $mail->addAddress('info@safetoschool.com'); // Add recipient
            $mail->addAddress('thiwankaimalshan2001@gmail.com'); // Add recipient
            $mail->addReplyTo($email, $name);

            // Build HTML email template
            $emailTemplate = '
            <!DOCTYPE html>
            <html>
            <head>
                <style>
                    body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                    .email-container { max-width: 600px; margin: 0 auto; background-color: #fff; }
                    .email-header { background: linear-gradient(135deg, #FF7A00, #FFA500); padding: 30px; text-align: center; }
                    .email-header h1 { color: white; margin: 0; font-size: 24px; }
                    .email-body { padding: 30px; background-color: #fff; }
                    .email-footer { background-color: #f8f8f8; padding: 20px; text-align: center; color: #666; }
                    .content-block { margin-bottom: 20px; padding: 20px; border-left: 4px solid #FF7A00; background-color: #FFF8F3; }
                    .label { font-weight: bold; color: #FF7A00; margin-bottom: 5px; }
                    .value { color: #333; }
                </style>
            </head>
            <body>
                <div class="email-container">
                    <div class="email-header">
                        <h1>New Contact Form Submission</h1>
                    </div>
                    <div class="email-body">
                        <div class="content-block">
                            <div class="label">From:</div>
                            <div class="value">' . htmlspecialchars($name) . ' (' . htmlspecialchars($email) . ')</div>
                        </div>
                        <div class="content-block">
                            <div class="label">Subject:</div>
                            <div class="value">' . htmlspecialchars($subject) . '</div>
                        </div>
                        <div class="content-block">
                            <div class="label">Message:</div>
                            <div class="value">' . nl2br(htmlspecialchars($message)) . '</div>
                        </div>
                    </div>
                    <div class="email-footer">
                        <p>This email was sent from the SafeToSchool contact form.</p>
                    </div>
                </div>
            </body>
            </html>';

            // Set email content
            $mail->isHTML(true);
            $mail->Subject = 'Contact Form: ' . $subject;
            $mail->Body = $emailTemplate;
            $mail->AltBody = strip_tags($message);

            // Send email
            $mail->send();
            
            // Output success page with redirect
            echo '<!DOCTYPE html>
            <html>
            <head>
                <meta charset="UTF-8">
                <title>Message Sent Successfully</title>
                <meta http-equiv="refresh" content="30;url=home.html">
                <style>
                    body { font-family: Arial, sans-serif; background-color: #FFF8F3; margin: 0; padding: 0; display: flex; justify-content: center; align-items: center; min-height: 100vh; }
                    .success-container { max-width: 500px; background: white; padding: 40px; border-radius: 20px; box-shadow: 0 10px 30px rgba(255, 122, 0, 0.1); text-align: center; }
                    .success-icon { width: 80px; height: 80px; background: linear-gradient(135deg, #FF7A00, #FFA500); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 20px; }
                    .success-icon svg { width: 40px; height: 40px; color: white; }
                    h1 { color: #FF7A00; margin: 0 0 20px; font-size: 24px; }
                    p { color: #666; margin-bottom: 20px; line-height: 1.6; }
                    .timer { font-size: 14px; color: #999; }
                    .redirect-btn { display: inline-block; background: linear-gradient(135deg, #FF7A00, #FFA500); color: white; text-decoration: none; padding: 12px 30px; border-radius: 25px; margin-top: 20px; transition: transform 0.2s; }
                    .redirect-btn:hover { transform: translateY(-2px); }
                </style>
            </head>
            <body>
                <div class="success-container">
                    <div class="success-icon">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                    </div>
                    <h1>Message Sent Successfully!</h1>
                    <p>Thank you for contacting us. We will get back to you soon.</p>
                    <div class="timer">Redirecting to homepage in <span id="countdown">30</span> seconds</div>
                    <a href="home.html" class="redirect-btn">Return to Homepage</a>
                </div>
                <script>
                    let timeLeft = 30;
                    const countdownElement = document.getElementById("countdown");
                    
                    const countdown = setInterval(() => {
                        timeLeft--;
                        countdownElement.textContent = timeLeft;
                        if (timeLeft <= 0) clearInterval(countdown);
                    }, 1000);
                </script>
            </body>
            </html>';
            exit;
            
        } catch (Exception $e) {
            $response['message'] = "Message could not be sent. Mailer Error: {$mail->ErrorInfo}";
        }
    }
}

// Send JSON response
header('Content-Type: application/json');
echo json_encode($response);
