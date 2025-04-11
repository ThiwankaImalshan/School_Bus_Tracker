<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

require '../vendor/autoload.php';

header('Content-Type: application/json');
session_start();

$response = ['success' => false, 'message' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $pdo = new PDO("mysql:host=localhost;dbname=school_bus_management", "root", "");
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $email = filter_var($_POST['email'] ?? '', FILTER_SANITIZE_EMAIL);
        
        // Check if email exists in driver table
        $stmt = $pdo->prepare("SELECT driver_id, full_name FROM driver WHERE email = ?");
        $stmt->execute([$email]);
        $driver = $stmt->fetch();

        if ($driver) {
            // Generate verification code
            $verificationCode = substr(str_shuffle("0123456789"), 0, 6);
            $_SESSION['reset_data'] = [
                'driver_id' => $driver['driver_id'],
                'email' => $email,
                'code' => $verificationCode,
                'expires' => time() + 3600 // 1 hour expiry
            ];

            // Send email
            $mail = new PHPMailer(true);
            $mail->isSMTP();
            $mail->Host = 'smtp.gmail.com';
            $mail->SMTPAuth = true;
            $mail->Username = 'helawiskam2019@gmail.com';
            $mail->Password = 'hawc rfod mbxk zdyq';
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = 587;

            $mail->setFrom('helawiskam2019@gmail.com', 'SafeToSchool');
            $mail->addAddress($email, $driver['full_name']);

            $mail->isHTML(true);
            $mail->Subject = 'Password Reset Verification - SafeToSchool Driver Portal';
            $mail->Body = '
                <!DOCTYPE html>
                <html>
                <head>
                    <style>
                        body { font-family: Arial, sans-serif; line-height: 1.6; }
                        .container { max-width: 600px; margin: 0 auto; }
                        .header { background: linear-gradient(135deg, #FF7A00, #FFBB00); padding: 20px; text-align: center; color: white; }
                        .content { padding: 20px; background-color: #f8f9fa; }
                        .code { font-size: 32px; text-align: center; letter-spacing: 5px; color: #FF7A00; margin: 20px 0; }
                    </style>
                </head>
                <body>
                    <div class="container">
                        <div class="header">
                            <h1>Password Reset Verification</h1>
                        </div>
                        <div class="content">
                            <p>Hello ' . htmlspecialchars($driver['full_name']) . ',</p>
                            <p>Your verification code is:</p>
                            <div class="code">
                                <strong>' . $verificationCode . '</strong>
                            </div>
                            <p>This code will expire in 60 minutes.</p>
                            <p>If you did not request this password reset, please ignore this email.</p>
                        </div>
                    </div>
                </body>
                </html>
            ';

            $mail->send();
            $response['success'] = true;
            $response['message'] = 'Verification code sent successfully!';

        } else {
            $response['message'] = 'Email address not found in our records.';
        }

    } catch (Exception $e) {
        $response['message'] = 'Error: ' . $e->getMessage();
        error_log('Password reset error: ' . $e->getMessage());
    }
}

echo json_encode($response);
