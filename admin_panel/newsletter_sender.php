<?php
// newsletter_sender.php - Email newsletter sender with visual editor
session_start();
require_once 'db_connection.php';
require_once '../vendor/autoload.php'; // Path to PHPMailer autoload

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;

// Set timezone for Sri Lanka
date_default_timezone_set('Asia/Colombo');

// Initialize variables
$successMessage = '';
$errorMessage = '';
$emailContent = '';
$emailSubject = '';
$previewMode = false;
$subscriberCount = 0;

// Get active subscriber count
$stmt = $pdo->query("SELECT COUNT(*) FROM newsletter_subscribers WHERE status = 'active'");
$subscriberCount = $stmt->fetchColumn();

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        if ($_POST['action'] === 'preview') {
            // Preview mode
            $emailSubject = $_POST['subject'] ?? '';
            $emailContent = $_POST['content'] ?? '';
            $previewMode = true;
        } elseif ($_POST['action'] === 'send') {
            // Send emails
            $emailSubject = $_POST['subject'] ?? '';
            $emailContent = $_POST['content'] ?? '';
            
            // Validate input
            if (empty($emailSubject)) {
                $errorMessage = "Email subject cannot be empty.";
            } elseif (empty($emailContent)) {
                $errorMessage = "Email content cannot be empty.";
            } else {
                // Get active subscribers
                $stmt = $pdo->query("SELECT * FROM newsletter_subscribers WHERE status = 'active'");
                $subscribers = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                if (count($subscribers) === 0) {
                    $errorMessage = "No active subscribers found.";
                } else {
                    // Initialize PHPMailer
                    $mail = new PHPMailer(true);
                    
                    try {
                        // Server settings
                        $mail->SMTPDebug = SMTP::DEBUG_OFF;
                        $mail->isSMTP();
                        $mail->Host = 'smtp.gmail.com';
                        $mail->SMTPAuth = true;
                        $mail->Username = 'helawiskam2019@gmail.com';
                        $mail->Password = 'hawc rfod mbxk zdyq';
                        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                        $mail->Port = 587;
                        
                        // Sender
                        $mail->setFrom('noreply@safeschoolbus.lk', 'Safe To School');
                        $mail->addReplyTo('info@safeschoolbus.lk', 'Safe To School Info');
                        
                        // Email content
                        $mail->isHTML(true);
                        $mail->Subject = $emailSubject;
                        
                        // Create the HTML email template
                        $emailTemplate = createEmailTemplate($emailContent);
                        
                        // Send to each subscriber
                        $sentCount = 0;
                        $failedCount = 0;
                        
                        foreach ($subscribers as $subscriber) {
                            try {
                                // Clear previous recipients
                                $mail->clearAllRecipients();
                                
                                // Add recipient
                                $mail->addAddress($subscriber['email']);
                                
                                // Personalized content
                                $personalizedContent = str_replace(
                                    ['{{EMAIL}}', '{{DATE}}'],
                                    [$subscriber['email'], date('F j, Y')],
                                    $emailTemplate
                                );
                                
                                $mail->Body = $personalizedContent;
                                $mail->AltBody = strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $emailContent));
                                
                                // Send email
                                $mail->send();
                                $sentCount++;
                                
                                // Log successful send
                                $stmt = $pdo->prepare("INSERT INTO newsletter_logs (subscriber_id, email, subject, sent_at, status) 
                                                      VALUES (?, ?, ?, NOW(), 'sent')");
                                $stmt->execute([$subscriber['id'], $subscriber['email'], $emailSubject]);
                                
                            } catch (Exception $e) {
                                $failedCount++;
                                
                                // Log failed send
                                $stmt = $pdo->prepare("INSERT INTO newsletter_logs (subscriber_id, email, subject, sent_at, status, error_message) 
                                                      VALUES (?, ?, ?, NOW(), 'failed', ?)");
                                $stmt->execute([$subscriber['id'], $subscriber['email'], $emailSubject, $mail->ErrorInfo]);
                                
                                error_log("Failed to send email to {$subscriber['email']}: {$mail->ErrorInfo}");
                            }
                        }
                        
                        // Report results
                        if ($sentCount > 0) {
                            $successMessage = "Successfully sent newsletter to $sentCount subscribers.";
                            if ($failedCount > 0) {
                                $successMessage .= " Failed to send to $failedCount subscribers.";
                            }
                        } else {
                            $errorMessage = "Failed to send any newsletters. Please check the logs.";
                        }
                        
                    } catch (Exception $e) {
                        $errorMessage = "Email system error: " . $e->getMessage();
                        error_log("PHPMailer error: " . $e->getMessage());
                    }
                }
            }
        }
    }
}

// Create email template with the given content
function createEmailTemplate($content) {
    return '
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Safe To School Newsletter</title>
        <style>
            body {
                font-family: "Poppins", Arial, sans-serif;
                line-height: 1.6;
                color: #333;
                margin: 0;
                padding: 0;
                background-color: #f9f9f9;
            }
            .container {
                max-width: 600px;
                margin: 0 auto;
                background-color: #ffffff;
                padding: 20px;
                border-radius: 8px;
                box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            }
            .header {
                background: linear-gradient(135deg, #fbbf24 0%, #ea580c 100%);
                color: white;
                padding: 20px;
                text-align: center;
                border-radius: 8px 8px 0 0;
                margin: -20px -20px 20px;
            }
            .logo {
                font-size: 24px;
                font-weight: bold;
                margin-bottom: 10px;
            }
            .content {
                padding: 20px 0;
            }
            .footer {
                text-align: center;
                padding-top: 20px;
                border-top: 1px solid #eee;
                color: #888;
                font-size: 12px;
            }
            .button {
                display: inline-block;
                background-color: #ea580c;
                color: white;
                padding: 12px 24px;
                text-decoration: none;
                border-radius: 4px;
                font-weight: bold;
                margin: 10px 0;
            }
            .unsubscribe {
                color: #888;
                text-decoration: underline;
            }
            @media only screen and (max-width: 600px) {
                .container {
                    width: 100%;
                    border-radius: 0;
                }
            }
            h1, h2, h3 {
                color: #333;
            }
            p {
                margin-bottom: 16px;
            }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="header">
                <div class="logo">Safe To School</div>
                <div>Newsletter - ' . date('F Y') . '</div>
            </div>
            <div class="content">
                ' . $content . '
            </div>
            <div class="footer">
                <p>You received this email because you\'re subscribed to Safe To School newsletters.</p>
                <p>Email sent to: {{EMAIL}} on {{DATE}}</p>
                <p><a href="https://safeschoolbus.lk/unsubscribe?email={{EMAIL}}" class="unsubscribe">Unsubscribe</a></p>
                <p>&copy; ' . date('Y') . ' Safe To School. All rights reserved.</p>
            </div>
        </div>
    </body>
    </html>
    ';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Newsletter Sender</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap">
    <link rel="icon" type="image/png" href="../img/favicon/favicon-96x96.png" sizes="96x96" />
    <link rel="shortcut icon" href="../img/favicon/favicon.ico" />
    <link rel="icon" type="image/svg+xml" href="../img/favicon/favicon.svg" />
    <!-- Quill Editor CSS -->
    <link href="https://cdn.quilljs.com/1.3.6/quill.snow.css" rel="stylesheet">
    <style>
        body {
            font-family: 'Poppins', sans-serif;
            background: linear-gradient(135deg, #fbbf24 0%, #ea580c 100%);
            min-height: 100vh;
        }
        .glass-container {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 1rem;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
        }
        .editor-container {
            height: 400px;
            margin-bottom: 20px;
            border-radius: 0.5rem;
            overflow: hidden;
        }
        #editor {
            height: 100%;
            font-family: 'Poppins', sans-serif;
            font-size: 16px;
        }
        .ql-editor {
            min-height: 350px;
        }
        .ql-toolbar.ql-snow {
            border-top-left-radius: 0.5rem;
            border-top-right-radius: 0.5rem;
            background-color: #f8fafc;
        }
        .preview-container {
            border: 1px solid #e2e8f0;
            border-radius: 0.5rem;
            padding: 1rem;
            height: 500px;
            overflow-y: auto;
            background-color: white;
        }
    </style>
</head>
<body class="min-h-screen">
    <nav class="bg-white/90 backdrop-blur-sm text-gray-800 shadow-lg">
        <div class="container mx-auto px-4 py-3 flex justify-between items-center">
            <div class="flex items-center space-x-4">
                <h1 class="text-xl font-bold text-yellow-900">Safe To School</h1>
            </div>
            <div class="flex items-center space-x-6">
                <a href="dashboard.php" class="bg-yellow-900 hover:bg-yellow-700 text-white px-4 py-2 rounded-lg transition duration-300 shadow-md hover:shadow-lg">Dashboard</a>
            </div>
        </div>
    </nav>

    <main class="container mx-auto px-4 py-8">
        <div class="glass-container p-6 mb-8">
            <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-6">
                <div>
                    <h2 class="text-2xl font-bold text-gray-800">Newsletter Sender</h2>
                    <p class="text-gray-600">
                        Send newsletters to <span class="font-semibold"><?php echo $subscriberCount; ?></span> active subscribers
                    </p>
                </div>
            </div>

            <?php if (!empty($successMessage)): ?>
                <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6" role="alert">
                    <p><?php echo $successMessage; ?></p>
                </div>
            <?php endif; ?>

            <?php if (!empty($errorMessage)): ?>
                <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6" role="alert">
                    <p><?php echo $errorMessage; ?></p>
                </div>
            <?php endif; ?>

            <?php if ($previewMode): ?>
                <div class="bg-blue-100 border-l-4 border-blue-500 text-blue-700 p-4 mb-6" role="alert">
                    <p class="font-medium">Preview Mode</p>
                    <p>This is how your newsletter will appear to subscribers.</p>
                </div>
                
                <div class="mb-6">
                    <h3 class="text-lg font-semibold mb-2">Email Preview</h3>
                    <div class="preview-container">
                        <iframe id="preview-frame" class="w-full h-full border-0"></iframe>
                    </div>
                </div>
                
                <form method="post" class="flex space-x-3">
                    <input type="hidden" name="subject" value="<?php echo htmlspecialchars($emailSubject); ?>">
                    <input type="hidden" name="content" value="<?php echo htmlspecialchars($emailContent); ?>">
                    <button type="submit" name="action" value="edit" class="bg-gray-500 hover:bg-gray-600 text-white px-6 py-2 rounded-lg transition">
                        Edit Newsletter
                    </button>
                    <button type="submit" name="action" value="send" class="bg-green-600 hover:bg-green-700 text-white px-6 py-2 rounded-lg transition">
                        Send Newsletter
                    </button>
                </form>
            <?php else: ?>
                <form method="post" id="newsletter-form">
                    <div class="mb-6">
                        <label for="subject" class="block text-sm font-medium text-gray-700 mb-1">Email Subject</label>
                        <input type="text" name="subject" id="subject" class="w-full px-4 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-yellow-500" value="<?php echo htmlspecialchars($emailSubject); ?>" required>
                    </div>
                    
                    <div class="mb-6">
                        <label for="editor" class="block text-sm font-medium text-gray-700 mb-1">Email Content</label>
                        <div class="editor-container">
                            <div id="editor"><?php echo htmlspecialchars_decode($emailContent); ?></div>
                        </div>
                        <input type="hidden" name="content" id="content">
                    </div>
                    
                    <div class="flex space-x-3">
                        <button type="submit" name="action" value="preview" class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded-lg transition">
                            Preview Newsletter
                        </button>
                    </div>
                </form>
            <?php endif; ?>
        </div>
    </main>

    <!-- Quill Editor JS -->
    <script src="https://cdn.quilljs.com/1.3.6/quill.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            <?php if (!$previewMode): ?>
                // Initialize Quill editor
                var quill = new Quill('#editor', {
                    theme: 'snow',
                    modules: {
                        toolbar: [
                            [{ 'header': [1, 2, 3, 4, 5, 6, false] }],
                            ['bold', 'italic', 'underline', 'strike'],
                            [{ 'color': [] }, { 'background': [] }],
                            [{ 'list': 'ordered'}, { 'list': 'bullet' }],
                            [{ 'align': [] }],
                            ['link', 'image'],
                            ['clean']
                        ]
                    },
                    placeholder: 'Compose your newsletter content here...'
                });

                // Restore content from localStorage if available
                const savedSubject = localStorage.getItem('newsletterSubject');
                const savedContent = localStorage.getItem('newsletterContent');
                if (savedSubject) {
                    document.getElementById('subject').value = savedSubject;
                }
                if (savedContent) {
                    quill.root.innerHTML = savedContent;
                }

                // Form submission - transfer Quill content to hidden input and save to localStorage
                const form = document.getElementById('newsletter-form');
                form.addEventListener('submit', function(e) {
                    const content = quill.root.innerHTML;
                    const subject = document.getElementById('subject').value;
                    
                    document.getElementById('content').value = content;
                    
                    // Save to localStorage
                    localStorage.setItem('newsletterContent', content);
                    localStorage.setItem('newsletterSubject', subject);
                });
            <?php else: ?>
                // In preview mode, keep the content in localStorage
                const content = <?php echo json_encode($emailContent); ?>;
                const subject = <?php echo json_encode($emailSubject); ?>;
                localStorage.setItem('newsletterContent', content);
                localStorage.setItem('newsletterSubject', subject);

                // Clear localStorage if send button is clicked
                document.querySelector('button[value="send"]')?.addEventListener('click', function() {
                    localStorage.removeItem('newsletterContent');
                    localStorage.removeItem('newsletterSubject');
                });

                // Preview mode - generate email preview
                const previewFrame = document.getElementById('preview-frame');
                const previewDoc = previewFrame.contentDocument || previewFrame.contentWindow.document;
                previewDoc.open();
                previewDoc.write(`
                    <!DOCTYPE html>
                    <html lang="en">
                    <head>
                        <meta charset="UTF-8">
                        <meta name="viewport" content="width=device-width, initial-scale=1.0">
                        <title>Newsletter Preview</title>
                        <style>
                            body {
                                font-family: "Poppins", Arial, sans-serif;
                                line-height: 1.6;
                                color: #333;
                                margin: 0;
                                padding: 0;
                                background-color: #f9f9f9;
                            }
                            .container {
                                max-width: 600px;
                                margin: 0 auto;
                                background-color: #ffffff;
                                padding: 20px;
                                border-radius: 8px;
                                box-shadow: 0 2px 5px rgba(0,0,0,0.1);
                            }
                            .header {
                                background: linear-gradient(135deg, #fbbf24 0%, #ea580c 100%);
                                color: white;
                                padding: 20px;
                                text-align: center;
                                border-radius: 8px 8px 0 0;
                                margin: -20px -20px 20px;
                            }
                            .logo {
                                font-size: 24px;
                                font-weight: bold;
                                margin-bottom: 10px;
                            }
                            .content {
                                padding: 20px 0;
                            }
                            .footer {
                                text-align: center;
                                padding-top: 20px;
                                border-top: 1px solid #eee;
                                color: #888;
                                font-size: 12px;
                            }
                            .button {
                                display: inline-block;
                                background-color: #ea580c;
                                color: white;
                                padding: 12px 24px;
                                text-decoration: none;
                                border-radius: 4px;
                                font-weight: bold;
                                margin: 10px 0;
                            }
                            .unsubscribe {
                                color: #888;
                                text-decoration: underline;
                            }
                            h1, h2, h3 {
                                color: #333;
                            }
                            p {
                                margin-bottom: 16px;
                            }
                        </style>
                    </head>
                    <body>
                        <div class="container">
                            <div class="header">
                                <div class="logo">Safe To School</div>
                                <div>Newsletter - ${new Date().toLocaleString('default', { month: 'long', year: 'numeric' })}</div>
                            </div>
                            <div class="content">
                                ${content}
                            </div>
                            <div class="footer">
                                <p>You received this email because you're subscribed to Safe To School newsletters.</p>
                                <p>Email sent to: preview@example.com on ${new Date().toLocaleDateString()}</p>
                                <p><a href="#" class="unsubscribe">Unsubscribe</a></p>
                                <p>&copy; ${new Date().getFullYear()} Safe To School. All rights reserved.</p>
                            </div>
                        </div>
                    </body>
                    </html>
                `);
                previewDoc.close();
            <?php endif; ?>
        });
    </script>
</body>
</html>