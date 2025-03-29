<?php
// Start session
session_start();

// Function to check if user is logged in
function is_admin_logged_in() {
    return isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;
}

// Function to redirect to login page
function redirect_to_login() {
    header("Location: log_in.html");
    exit;
}

// Function that checks user's login status
// Include this at the top of protected pages
function require_admin_login() {
    if (!is_admin_logged_in()) {
        redirect_to_login();
    }
    
    // Optional: Check if session has expired
    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > 1800)) {
        // If last activity was more than 30 minutes ago
        session_unset();
        session_destroy();
        redirect_to_login();
    }
    
    // Update last activity time
    $_SESSION['last_activity'] = time();
}
?>