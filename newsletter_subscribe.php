<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

require 'vendor/autoload.php';

header('Content-Type: application/json');

$response = [
    'success' => false,
    'message' => ''
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = filter_var($_POST['email'] ?? '', FILTER_SANITIZE_EMAIL);
    
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $response['message'] = 'Please enter a valid email address.';
        echo json_encode($response);
        exit;
    }

    try {
        $pdo = new PDO("mysql:host=localhost;dbname=school_bus_management", "root", "");
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Check if already subscribed
        $stmt = $pdo->prepare("SELECT id, status FROM newsletter_subscribers WHERE email = ?");
        $stmt->execute([$email]);
        $subscriber = $stmt->fetch();

        if ($subscriber) {
            if ($subscriber['status'] === 'unsubscribed') {
                // Reactivate subscription
                $updateStmt = $pdo->prepare("UPDATE newsletter_subscribers SET status = 'active' WHERE id = ?");
                $updateStmt->execute([$subscriber['id']]);
                $response['message'] = 'Your subscription has been reactivated!';
                $response['success'] = true;
            } else {
                $response['message'] = 'You are already subscribed to our newsletter!';
            }
        } else {
            // New subscription
            $stmt = $pdo->prepare("INSERT INTO newsletter_subscribers (email) VALUES (?)");
            $stmt->execute([$email]);

            // Send welcome email
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
            $mail->setFrom('thiwankaimalshan2001@gmail.com', 'SafeToSchool');
            $mail->addAddress($email);

            // Generate unsubscribe token
            $unsubscribeToken = hash('sha256', $email . 'SafeToSchool_Newsletter_Secret');
            $unsubscribeLink = "http://localhost/School_Bus_Tracker/unsubscribe.php?email=" . urlencode($email) . "&token=" . $unsubscribeToken;

            // Content
            $mail->isHTML(true);
            $mail->Subject = 'Welcome to SafeToSchool Newsletter!';
            $mail->Body = '
                <!DOCTYPE html>
                <html>
                <head>
                    <style>
                        body { font-family: Arial, sans-serif; line-height: 1.6; }
                        .container { max-width: 600px; margin: 0 auto; }
                        .header { background: linear-gradient(135deg, #FF7A00, #FFBB00); padding: 30px; text-align: center; color: white; }
                        .content { padding: 30px; background-color: #ffffff; }
                        .button { display: inline-block; padding: 12px 24px; background: #FF7A00; color: white; text-decoration: none; border-radius: 5px; }
                        .footer { padding: 20px; text-align: center; color: #666; }
                        .social-links { margin-top: 20px; }
                        .social-links a { color: #FF7A00; margin: 0 10px; text-decoration: none; }
                    </style>
                </head>
                <body>
                    <div class="container">
                        <div class="header">
                            <h1 style="color: white;">Welcome to SafeToSchool Newsletter!</h1>
                        </div>
                        <div class="content">
                            <h2 style="color: black;">Thank you for subscribing!</h2>
                            <p style="color: black;">You\'re now part of our community. You\'ll receive regular updates about:</p>
                            <ul style="color: black;">
                                <li>Safety innovations</li>
                                <li>Service updates</li>
                                <li>Transportation tips</li>
                                <li>Special announcements</li>
                            </ul>
                            <div style="text-align: center; margin-top: 30px;">
                                <a href="https://safetoschool.com" class="button" style="color: white; font-weight: bold;">Visit Our Website</a>
                            </div>
                        </div>
                        <div class="footer">
                            <p>Follow us on social media:</p>
                            <div class="social-links">
                                <a href="#">Facebook</a>
                                <a href="#">Twitter</a>
                                <a href="#">Instagram</a>
                            </div>
                            <p style="margin-top: 20px; font-size: 12px;">
                                If you wish to unsubscribe, please <a href="' . $unsubscribeLink . '" style="color: #FF7A00;">click here</a>
                            </p>
                        </div>
                    </div>
                </body>
                </html>
            ';

            $mail->send();
            
            $response['success'] = true;
            $response['message'] = 'Thank you for subscribing! Please check your email for confirmation.';
        }
        
    } catch (PDOException $e) {
        $response['message'] = 'Database error: ' . $e->getMessage();
        error_log("Newsletter subscription database error: " . $e->getMessage());
    } catch (Exception $e) {
        $response['message'] = 'Error sending confirmation email. Please try again later.';
        error_log("Newsletter subscription email error: " . $e->getMessage());
    }
}

echo json_encode($response);
