<?php
session_start();
require_once 'db_connection.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = trim($_POST['full_name']);
    $email = trim($_POST['email']);
    $role = trim($_POST['role']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];

    // Validate inputs
    if (empty($full_name) || empty($email) || empty($role) || empty($password)) {
        $error = "All fields are required.";
    } elseif ($password !== $confirm_password) {
        $error = "Passwords do not match.";
    } else {
        try {
            // Check if email already exists
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM admin WHERE email = ?");
            $stmt->execute([$email]);
            if ($stmt->fetchColumn() > 0) {
                $error = "Email already exists.";
            } else {
                // Hash password
                $password_hash = password_hash($password, PASSWORD_DEFAULT);

                // Insert new admin
                $stmt = $pdo->prepare("INSERT INTO admin (full_name, email, password_hash, role) VALUES (?, ?, ?, ?)");
                $stmt->execute([$full_name, $email, $password_hash, $role]);

                $success = "Admin registered successfully!";
            }
        } catch (PDOException $e) {
            $error = "Registration failed: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register New Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap">
    <link rel="icon" type="image/png" href="../img/favicon/favicon-96x96.png" sizes="96x96" />
    <link rel="shortcut icon" href="../img/favicon/favicon.ico" />
    <link rel="icon" type="image/svg+xml" href="../img/favicon/favicon.svg" />
    <style>
        body {
            font-family: 'Poppins', sans-serif;
        }
        .input-focus-effect:focus {
            box-shadow: 0 0 0 3px rgba(255, 179, 71, 0.3);
        }
        .btn-gradient {
            background: linear-gradient(135deg, #FF9500, #FFB700);
            color: white;
            transition: all 0.3s ease;
        }
        .btn-gradient:hover {
            background: linear-gradient(135deg, #FF7A00, #FFA000);
            transform: translateY(-2px);
        }
    </style>
</head>
<body class="bg-gradient-to-b from-yellow-50 to-orange-50 min-h-screen p-4 flex items-center justify-center">
    <div class="fixed -z-10 top-0 left-0 w-full h-full opacity-50">
        <div class="absolute top-10 left-10 w-32 h-32 rounded-full bg-blue-100 blur-3xl"></div>
        <div class="absolute bottom-10 right-10 w-40 h-40 rounded-full bg-orange-100 blur-3xl"></div>
        <div class="absolute top-1/3 right-1/4 w-24 h-24 rounded-full bg-green-100 blur-3xl"></div>
    </div>

    <div class="w-full max-w-md relative">
        <div class="absolute -right-20 -top-20 w-40 h-40 bg-orange-50 rounded-full"></div>
        <div class="absolute -left-12 -bottom-12 w-24 h-24 bg-blue-50 rounded-full"></div>

        <form method="POST" class="bg-white rounded-3xl shadow-xl p-8 border border-orange-100 relative overflow-hidden">
            <div class="flex items-center space-x-3 mb-8">
                <div class="h-10 w-1 bg-orange-500 rounded-full"></div>
                <h2 class="text-3xl font-bold text-orange-800">Register Admin</h2>
            </div>
            
            <?php if ($error): ?>
                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg relative mb-4" role="alert">
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-lg relative mb-4" role="alert">
                    <?php echo htmlspecialchars($success); ?>
                </div>
            <?php endif; ?>

            <div class="space-y-6">
                <div class="group">
                    <label for="full_name" class="block text-sm font-medium text-gray-700 mb-1 group-hover:text-orange-600 transition">Full Name</label>
                    <input class="w-full px-4 py-3 rounded-xl border border-orange-200 focus:outline-none focus:ring-2 focus:ring-orange-400 focus:border-transparent transition input-focus-effect" 
                           id="full_name" name="full_name" type="text" required>
                </div>
                
                <div class="group">
                    <label for="email" class="block text-sm font-medium text-gray-700 mb-1 group-hover:text-orange-600 transition">Email</label>
                    <input class="w-full px-4 py-3 rounded-xl border border-orange-200 focus:outline-none focus:ring-2 focus:ring-orange-400 focus:border-transparent transition input-focus-effect" 
                           id="email" name="email" type="email" required>
                </div>
                
                <div class="group">
                    <label for="role" class="block text-sm font-medium text-gray-700 mb-1 group-hover:text-orange-600 transition">Role</label>
                    <select class="w-full px-4 py-3 rounded-xl border border-orange-200 focus:outline-none focus:ring-2 focus:ring-orange-400 focus:border-transparent transition input-focus-effect" 
                            id="role" name="role" required>
                        <option value="admin">Admin</option>
                        <option value="transportation_manager">Transportation Manager</option>
                        <option value="school_admin">School Admin</option>
                        <option value="support_staff">Support Staff</option>
                    </select>
                </div>
                
                <div class="group">
                    <label for="password" class="block text-sm font-medium text-gray-700 mb-1 group-hover:text-orange-600 transition">Password</label>
                    <input class="w-full px-4 py-3 rounded-xl border border-orange-200 focus:outline-none focus:ring-2 focus:ring-orange-400 focus:border-transparent transition input-focus-effect" 
                           id="password" name="password" type="password" required>
                </div>
                
                <div class="group">
                    <label for="confirm_password" class="block text-sm font-medium text-gray-700 mb-1 group-hover:text-orange-600 transition">Confirm Password</label>
                    <input class="w-full px-4 py-3 rounded-xl border border-orange-200 focus:outline-none focus:ring-2 focus:ring-orange-400 focus:border-transparent transition input-focus-effect" 
                           id="confirm_password" name="confirm_password" type="password" required>
                </div>
            </div>
            
            <div class="flex items-center justify-between mt-8">
                <button class="w-full py-4 px-6 rounded-xl font-medium shadow-lg transition duration-300 ease-in-out transform hover:-translate-y-1 relative overflow-hidden group btn-gradient" 
                        type="submit">
                    <div class="absolute inset-0 bg-gradient-to-r from-yellow-500 via-yellow-500 to-yellow-400"></div>
                    <div class="absolute inset-0 bg-gradient-to-r from-yellow-400 to-yellow-500 opacity-0 group-hover:opacity-100 transition-opacity duration-300"></div>
                    <span class="relative z-10 text-white text-lg font-bold drop-shadow-sm">Register Admin</span>
                </button>
            </div>
            
            <div class="text-center mt-4">
                <a href="dashboard.php" class="text-sm text-orange-500 hover:text-orange-700 transition">
                    Return to Dashboard
                </a>
            </div>
        </form>
    </div>
</body>
</html>