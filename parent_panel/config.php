<?php
/**
 * Database Configuration File
 * 
 * This file establishes a PDO connection to your MySQL database
 * and sets up error handling for your application.
 */

// Database credentials
$db_host = 'localhost';     // Database host (usually localhost)
$db_name = 'school_bus_management';    // Your database name
$db_user = 'root';          // Database username
$db_pass = '';              // Database password

// Error reporting settings
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Set default timezone
date_default_timezone_set('Asia/Kolkata'); // Adjust as needed for your location

// Establish database connection using PDO
try {
    $pdo = new PDO(
        "mysql:host=$db_host;dbname=$db_name;charset=utf8mb4",
        $db_user,
        $db_pass,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ]
    );
    
    // Set character set
    $pdo->query('SET NAMES utf8mb4');
    
} catch (PDOException $e) {
    // Log the error and show a user-friendly message
    error_log('Database Connection Error: ' . $e->getMessage());
    die('Sorry, there was a problem connecting to the database. Please try again later.');
}

/**
 * Helper function to safely get values from global arrays
 * 
 * @param array $array The array to search in ($_GET, $_POST, etc.)
 * @param string $key The key to look for
 * @param mixed $default The default value if key is not found
 * @return mixed The value from the array or the default
 */
function getFromArray($array, $key, $default = null) {
    return isset($array[$key]) ? $array[$key] : $default;
}

/**
 * Function to validate date format (YYYY-MM-DD)
 * 
 * @param string $date The date string to validate
 * @return bool True if valid, false otherwise
 */
function isValidDate($date) {
    $format = 'Y-m-d';
    $d = DateTime::createFromFormat($format, $date);
    return $d && $d->format($format) === $date;
}

/**
 * Session security settings
 */
if (session_status() === PHP_SESSION_NONE) {
    // Set secure session parameters
    ini_set('session.use_only_cookies', 1);
    ini_set('session.use_strict_mode', 1);
    
    // Set session cookie parameters
    session_set_cookie_params([
        'lifetime' => 3600,     // 1 hour session lifetime
        'path' => '/',
        'domain' => '',         // Current domain
        'secure' => isset($_SERVER['HTTPS']), // Secure in HTTPS
        'httponly' => true,     // Not accessible via JavaScript
        'samesite' => 'Lax'     // Prevents CSRF
    ]);
    
    // Start the session
    session_start();
}

/**
 * Application Constants
 */
define('APP_NAME', 'School Bus Management System');
define('APP_VERSION', '1.0.0');
define('IS_PRODUCTION', false); // Set to true in production environment

// Maximum allowed file size for uploads (in bytes)
define('MAX_FILE_SIZE', 5 * 1024 * 1024); // 5MB

// Allowed file types for uploads
define('ALLOWED_FILE_TYPES', ['image/jpeg', 'image/png', 'image/gif']);

// Path constants
define('ROOT_PATH', dirname(__FILE__));
define('UPLOAD_PATH', ROOT_PATH . '/uploads');
?>