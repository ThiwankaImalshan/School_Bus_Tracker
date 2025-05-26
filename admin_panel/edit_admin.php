<?php
// Include session check and require admin login
include 'session_check.php';
require_admin_login();

// Database connection
$host = "localhost";
$username = "root";
$password = "";
$database = "school_bus_management";
$conn = new mysqli($host, $username, $password, $database);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$success_message = '';
$error_message = '';

// Check if admin ID is provided
if (!isset($_GET['id'])) {
    header('Location: dashboard.php');
    exit;
}

$admin_id = intval($_GET['id']);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = trim($_POST['full_name']);
    $email = trim($_POST['email']);
    $role = $_POST['role'];
    $new_password = trim($_POST['new_password']);

    try {
        if (empty($full_name) || empty($email) || empty($role)) {
            throw new Exception("All fields except password are required");
        }

        $sql = "UPDATE admin SET full_name = ?, email = ?, role = ?";
        $params = [$full_name, $email, $role];

        // Add password update if provided
        if (!empty($new_password)) {
            $sql .= ", password_hash = ?";
            $params[] = password_hash($new_password, PASSWORD_DEFAULT);
        }

        $sql .= " WHERE admin_id = ?";
        $params[] = $admin_id;

        $stmt = $conn->prepare($sql);
        $stmt->bind_param(str_repeat('s', count($params)), ...$params);
        
        if ($stmt->execute()) {
            $success_message = "Admin details updated successfully!";
        } else {
            throw new Exception("Error updating admin details");
        }
    } catch (Exception $e) {
        $error_message = $e->getMessage();
    }
}

// Fetch admin details
$stmt = $conn->prepare("SELECT * FROM admin WHERE admin_id = ?");
$stmt->bind_param("i", $admin_id);
$stmt->execute();
$result = $stmt->get_result();
$admin = $result->fetch_assoc();

if (!$admin) {
    header('Location: dashboard.php');
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Administrator - Admin Portal</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap">
    <style>
        body {
            font-family: 'Poppins', sans-serif;
            background: linear-gradient(135deg, #fef3c7, #ffedd5);
        }
        .animate-fade-in {
            animation: fadeIn 0.5s ease-in-out;
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .input-focus-effect:focus {
            box-shadow: 0 0 0 3px rgba(251, 146, 60, 0.3);
        }
    </style>
</head>
<body class="min-h-screen">
    <div class="min-h-screen py-6 flex flex-col justify-center sm:py-12">
        <div class="relative px-4 sm:px-0 sm:max-w-xl sm:mx-auto w-full">
            <div class="relative px-4 py-8 sm:px-10 sm:py-10 bg-white shadow-lg rounded-3xl sm:rounded-3xl animate-fade-in border-2 border-orange-200">
                <div class="max-w-md mx-auto">
                    <div class="divide-y divide-gray-200">
                        <div class="py-4 sm:py-8 text-base leading-6 space-y-4 text-gray-700 sm:text-lg sm:leading-7">
                            <div class="flex flex-col sm:flex-row items-center mb-6 sm:mb-8">
                                <div class="h-12 w-12 sm:h-16 sm:w-16 bg-gradient-to-r from-yellow-400 to-yellow-500 rounded-full flex flex-shrink-0 justify-center items-center text-white text-xl sm:text-2xl font-mono shadow-lg mb-3 sm:mb-0">
                                    <?php echo strtoupper(substr($admin['full_name'], 0, 2)); ?>
                                </div>
                                <div class="sm:ml-6 text-center sm:text-left">
                                    <h2 class="text-2xl sm:text-3xl font-bold text-gray-800">Edit Administrator</h2>
                                    <p class="text-gray-500 text-xs sm:text-sm mt-1">Update administrator information</p>
                                </div>
                            </div>

                            <?php if ($success_message): ?>
                                <div class="mb-4 p-3 sm:p-4 bg-green-50 border-l-4 border-green-500 text-green-700 animate-fade-in text-sm sm:text-base">
                                    <p class="font-medium"><?php echo htmlspecialchars($success_message); ?></p>
                                </div>
                            <?php endif; ?>

                            <?php if ($error_message): ?>
                                <div class="mb-4 p-3 sm:p-4 bg-red-50 border-l-4 border-red-500 text-red-700 animate-fade-in text-sm sm:text-base">
                                    <p class="font-medium"><?php echo htmlspecialchars($error_message); ?></p>
                                </div>
                            <?php endif; ?>

                            <form method="POST" class="space-y-4 sm:space-y-6">
                                <div>
                                    <label class="block text-xs sm:text-sm font-medium text-gray-700 mb-2">Full Name</label>
                                    <input type="text" name="full_name" value="<?php echo htmlspecialchars($admin['full_name']); ?>" 
                                           class="w-full px-3 sm:px-4 py-2 text-sm sm:text-base border border-gray-300 rounded-lg focus:ring-2 focus:ring-orange-300 focus:border-orange-400 transition-all duration-200 input-focus-effect" 
                                           required>
                                </div>

                                <div>
                                    <label class="block text-xs sm:text-sm font-medium text-gray-700 mb-2">Email Address</label>
                                    <input type="email" name="email" value="<?php echo htmlspecialchars($admin['email']); ?>" 
                                           class="w-full px-3 sm:px-4 py-2 text-sm sm:text-base border border-gray-300 rounded-lg focus:ring-2 focus:ring-orange-300 focus:border-orange-400 transition-all duration-200 input-focus-effect" 
                                           required>
                                </div>

                                <div>
                                    <label class="block text-xs sm:text-sm font-medium text-gray-700 mb-2">Role</label>
                                    <select name="role" 
                                            class="w-full px-3 sm:px-4 py-2 text-sm sm:text-base border border-gray-300 rounded-lg focus:ring-2 focus:ring-orange-300 focus:border-orange-400 transition-all duration-200 input-focus-effect">
                                        <option value="admin" <?php echo $admin['role'] === 'admin' ? 'selected' : ''; ?>>Admin</option>
                                        <option value="transportation_manager" <?php echo $admin['role'] === 'transportation_manager' ? 'selected' : ''; ?>>Transportation Manager</option>
                                        <option value="school_admin" <?php echo $admin['role'] === 'school_admin' ? 'selected' : ''; ?>>School Admin</option>
                                        <option value="support_staff" <?php echo $admin['role'] === 'support_staff' ? 'selected' : ''; ?>>Support Staff</option>
                                    </select>
                                </div>

                                <div>
                                    <label class="block text-xs sm:text-sm font-medium text-gray-700 mb-2">New Password (Optional)</label>
                                    <input type="password" name="new_password" 
                                           class="w-full px-3 sm:px-4 py-2 text-sm sm:text-base border border-gray-300 rounded-lg focus:ring-2 focus:ring-yellow-300 focus:border-yellow-400 transition-all duration-200 input-focus-effect"
                                           placeholder="Leave blank to keep current password">
                                </div>

                                <div class="pt-4 sm:pt-6 flex flex-col sm:flex-row items-center space-y-3 sm:space-y-0 sm:space-x-4">
                                    <a href="dashboard.php" 
                                       class="w-full sm:flex-1 px-4 py-2 bg-gray-50 text-gray-800 rounded-lg hover:bg-gray-100 transition duration-200 text-center border border-gray-200 text-sm sm:text-base">
                                        Cancel
                                    </a>
                                    <button type="submit" 
                                            class="w-full sm:flex-1 px-4 py-2 bg-gradient-to-r from-yellow-500 to-yellow-500 text-white rounded-lg hover:from-yellow-600 hover:to-yellow-600 transition duration-200 text-sm sm:text-base">
                                        Update Admin
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Add smooth transitions for form feedback
        document.querySelectorAll('input, select').forEach(element => {
            element.addEventListener('focus', () => element.classList.add('ring-2'));
            element.addEventListener('blur', () => element.classList.remove('ring-2'));
        });
    </script>
</body>
</html>
