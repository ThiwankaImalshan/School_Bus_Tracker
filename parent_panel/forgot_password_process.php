<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

require '../vendor/autoload.php';

header('Content-Type: application/json');
session_start();

$response = ['success' => false, 'message' => '', 'step' => 1];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $pdo = new PDO("mysql:host=localhost;dbname=school_bus_management", "root", "");
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $email = filter_var($_POST['email'] ?? '', FILTER_SANITIZE_EMAIL);
        
        // Check if email exists
        $stmt = $pdo->prepare("SELECT parent_id, full_name FROM parent WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user) {
            // Generate reset token and expiry
            $token = bin2hex(random_bytes(32));
            $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));
            
            // Store reset token in session (in production, store in database)
            $_SESSION['reset_token'] = [
                'token' => $token,
                'email' => $email,
                'expires' => $expires
            ];

            // Send email using PHPMailer
            $mail = new PHPMailer(true);

            // Server settings
            $mail->isSMTP();
            $mail->Host = 'smtp.gmail.com';
            $mail->SMTPAuth = true;
            $mail->Username = 'helawiskam2019@gmail.com';
            $mail->Password = 'hawc rfod mbxk zdyq';
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = 587;

            // Recipients
            $mail->setFrom('helawiskam2019@gmail.com', 'SafeToSchool');
            $mail->addAddress($email, $user['full_name']);

            // Content
            $resetLink = "http://localhost/School_Bus_Tracker/parent_panel/reset_password.php?token=" . $token;
            $verificationCode = substr(str_shuffle("0123456789"), 0, 6);
            $_SESSION['verification_code'] = $verificationCode;

            $mail->isHTML(true);
            $mail->Subject = 'Password Reset Request - SafeToSchool';
            $mail->Body = '
                <!DOCTYPE html>
                <html>
                <head>
                    <style>
                        body { font-family: Arial, sans-serif; line-height: 1.6; }
                        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                        .header { background: linear-gradient(135deg, #FF7A00, #FFBB00); padding: 20px; text-align: center; color: white; }
                        .content { padding: 20px; background-color: #f8f9fa; }
                        .verification-code { font-size: 32px; text-align: center; letter-spacing: 5px; color: #FF7A00; margin: 20px 0; }
                        .footer { text-align: center; margin-top: 20px; color: #666; }
                    </style>
                </head>
                <body>
                    <div class="container">
                        <div class="header">
                            <h1>Password Reset Verification</h1>
                        </div>
                        <div class="content">
                            <p>Hello ' . htmlspecialchars($user['full_name']) . ',</p>
                            <p>We received a request to reset your password. Here is your verification code:</p>
                            <div class="verification-code">
                                <strong>' . $verificationCode . '</strong>
                            </div>
                            <p>This code will expire in 60 minutes.</p>
                            <p>If you did not request this password reset, please ignore this email.</p>
                        </div>
                        <div class="footer">
                            <p>&copy; ' . date('Y') . ' SafeToSchool. All rights reserved.</p>
                        </div>
                    </div>
                </body>
                </html>
            ';

            $mail->send();
            
            $response['success'] = true;
            $response['message'] = 'Verification code sent successfully!';
            $response['step'] = 2;
            
        } else {
            $response['message'] = 'Email address not found.';
        }

    } catch (Exception $e) {
        $response['message'] = 'Error: ' . $e->getMessage();
    }
}

echo json_encode($response);
