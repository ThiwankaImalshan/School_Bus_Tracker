<?php
session_start();
require_once 'db_connection.php';
require '../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = filter_var($_POST['email'], FILTER_SANITIZE_EMAIL);
    
    // Check if email exists in admin table
    $stmt = $conn->prepare("SELECT admin_id FROM admin WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Email not found']);
        exit;
    }
    
    // Generate verification code
    $verification_code = sprintf("%06d", mt_rand(0, 999999));
    $_SESSION['reset_email'] = $email;
    $_SESSION['verification_code'] = $verification_code;
    $_SESSION['code_expiry'] = time() + (15 * 60); // 15 minutes expiry
    
    // Create PHPMailer instance
    $mail = new PHPMailer(true);
    
    try {
        // Server settings
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'helawiskam2019@gmail.com';
        $mail->Password = 'hawc rfod mbxk zdyq';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;
        
        // Recipients
        $mail->setFrom('helawiskam2019@gmail.com', 'School Bus Admin');
        $mail->addAddress($email);
        
        // Content
        $mail->isHTML(true);
        $mail->Subject = 'Password Reset Verification Code';
        $mail->Body = '
        <!DOCTYPE html>
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; }
                .container {
                    max-width: 600px;
                    margin: 0 auto;
                    padding: 20px;
                    background-color: #FFF9F5;
                }
                .header {
                    background: linear-gradient(135deg, #FF9500, #FFB700);
                    color: white;
                    padding: 20px;
                    text-align: center;
                    border-radius: 10px 10px 0 0;
                }
                .content {
                    background: white;
                    padding: 30px;
                    border-radius: 0 0 10px 10px;
                    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
                }
                .code {
                    background: #FFF9F5;
                    color: #FF9500;
                    font-size: 32px;
                    font-weight: bold;
                    text-align: center;
                    padding: 20px;
                    margin: 20px 0;
                    border: 2px dashed #FFB700;
                    border-radius: 10px;
                    letter-spacing: 5px;
                }
                .footer {
                    text-align: center;
                    color: #666;
                    font-size: 12px;
                    margin-top: 20px;
                }
                .warning {
                    color: #FF7A00;
                    font-size: 14px;
                    text-align: center;
                    margin-top: 15px;
                }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="header">
                    <h1>Password Reset</h1>
                </div>
                <div class="content">
                    <h2 style="color: #FF9500; margin-bottom: 20px;">Verification Code</h2>
                    <p>Hello,</p>
                    <p>You have requested to reset your password. Please use the following verification code to continue the process:</p>
                    
                    <div class="code">' . $verification_code . '</div>
                    
                    <div class="warning">
                        This code will expire in 15 minutes for security purposes.
                    </div>
                    
                    <p style="margin-top: 30px;">If you did not request this password reset, please ignore this email or contact support if you have concerns.</p>
                    
                    <div class="footer">
                        <p>This is an automated message, please do not reply to this email.</p>
                        <p>© ' . date('Y') . ' School Bus Management System. All rights reserved.</p>
                    </div>
                </div>
            </div>
        </body>
        </html>';
        
        $mail->AltBody = "Your verification code is: {$verification_code}\nThis code will expire in 15 minutes.";
        
        $mail->send();
        echo json_encode(['success' => true, 'message' => 'Verification code sent']);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => "Mail error: {$mail->ErrorInfo}"]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}
