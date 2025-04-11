<?php
require_once 'vendor/autoload.php';

$token = $_GET['token'] ?? '';
$email = $_GET['email'] ?? '';

// Verify token
$expectedToken = hash('sha256', $email . 'SafeToSchool_Newsletter_Secret');

if ($token !== $expectedToken) {
    die('Invalid unsubscribe link');
}

try {
    $pdo = new PDO("mysql:host=localhost;dbname=school_bus_management", "root", "");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $stmt = $pdo->prepare("UPDATE newsletter_subscribers SET status = 'unsubscribed' WHERE email = ?");
    $stmt->execute([$email]);

    // Show confirmation page
    echo '<!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <title>Unsubscribe Confirmation</title>
        <script src="https://cdn.tailwindcss.com"></script>
        <meta http-equiv="refresh" content="5;url=home.html">
    </head>
    <body class="bg-gray-50 min-h-screen flex items-center justify-center">
        <div class="max-w-md w-full mx-auto p-8">
            <div class="bg-white rounded-lg shadow-lg p-6 text-center">
                <div class="w-16 h-16 bg-orange-100 rounded-full flex items-center justify-center mx-auto mb-4">
                    <svg class="w-8 h-8 text-orange-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                    </svg>
                </div>
                <h2 class="text-2xl font-bold text-gray-800 mb-4">Successfully Unsubscribed</h2>
                <p class="text-gray-600 mb-6">You have been unsubscribed from our newsletter. We\'re sorry to see you go!</p>
                <div class="text-sm text-gray-500">Redirecting to homepage in 5 seconds...</div>
                <a href="home.html" class="mt-6 inline-block bg-orange-500 text-white px-6 py-2 rounded-lg hover:bg-orange-600 transition-colors">
                    Return to Homepage
                </a>
            </div>
        </div>
    </body>
    </html>';

} catch (PDOException $e) {
    error_log("Unsubscribe error: " . $e->getMessage());
    die('An error occurred while processing your request');
}
?>
