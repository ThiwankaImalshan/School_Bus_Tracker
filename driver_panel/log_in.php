<?php
// login.php
session_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Driver Portal - Login</title>
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
        .animate-float {
            animation: float 6s ease-in-out infinite;
        }
        @keyframes float {
            0% { transform: translateY(0px); }
            50% { transform: translateY(-10px); }
            100% { transform: translateY(0px); }
        }
        .btn-primary {
            background-color: #FF9500;
            color: white;
            transition: all 0.3s ease;
        }
        .btn-primary:hover {
            background-color: #FF7A00;
            transform: translateY(-2px);
        }
    </style>
</head>
<body class="bg-gradient-to-b from-yellow-50 to-orange-50 min-h-screen p-4">
    <main class="container mx-auto py-16 md:py-20 relative">
        <div class="max-w-md mx-auto">
            <div class="flex flex-col md:flex-row items-start md:items-center justify-between mb-8 gap-4">
                <div class="flex items-center space-x-3">
                    <div class="h-10 w-1 bg-orange-500 rounded-full"></div>
                    <h2 class="text-3xl font-bold text-orange-800">Driver Portal</h2>
                </div>
            </div>

            <div class="bg-white rounded-3xl shadow-xl p-6 md:p-8 border border-orange-100 relative overflow-hidden">
                <div class="absolute -right-20 -top-20 w-40 h-40 bg-orange-50 rounded-full"></div>
                <div class="absolute -left-12 -bottom-12 w-24 h-24 bg-blue-50 rounded-full"></div>
                
                <div class="flex justify-between items-center mb-8 relative">
                    <div>
                        <h3 class="text-2xl font-semibold text-gray-800">Sign In</h3>
                        <p class="text-gray-500 text-sm mt-1">Please enter your driver credentials</p>
                    </div>
                    <div class="w-14 h-14 rounded-2xl bg-gradient-to-br from-yellow-500 to-yellow-500 flex items-center justify-center shadow-lg transform rotate-6 animate-float">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-7 w-7 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                        </svg>
                    </div>
                </div>
                
                <!-- Error Message -->
                <?php if(isset($_SESSION['error'])): ?>
                    <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">
                        <?php 
                            echo $_SESSION['error']; 
                            unset($_SESSION['error']);
                        ?>
                    </div>
                <?php endif; ?>

                <form id="loginForm" action="login_process.php" method="POST" class="space-y-6 relative">
                    <div class="group">
                        <label for="login_email" class="block text-sm font-medium text-gray-700 mb-1 group-hover:text-orange-600 transition">Email Address</label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                                </svg>
                            </div>
                            <input type="email" id="login_email" name="email" required class="w-full pl-10 pr-4 py-3 rounded-xl border border-orange-200 focus:outline-none focus:ring-2 focus:ring-orange-400 focus:border-transparent transition input-focus-effect">
                        </div>
                    </div>
                    
                    <div class="group">
                        <div class="flex items-center justify-between mb-1">
                            <label for="login_password" class="block text-sm font-medium text-gray-700 group-hover:text-orange-600 transition">Password</label>
                            <a href="forgot_password.html" class="text-sm text-orange-600 hover:text-orange-700 font-medium">Forgot password?</a>
                        </div>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                                </svg>
                            </div>
                            <input type="password" id="login_password" name="password" required class="w-full pl-10 pr-4 py-3 rounded-xl border border-orange-200 focus:outline-none focus:ring-2 focus:ring-orange-400 focus:border-transparent transition input-focus-effect">
                        </div>
                    </div>
                    
                    <div class="pt-6">
                        <button type="submit" class="w-full py-4 px-6 rounded-xl font-medium shadow-lg transition duration-300 ease-in-out transform hover:-translate-y-1 relative overflow-hidden group btn-primary">
                            <span class="relative z-10 text-white text-lg font-bold drop-shadow-sm">Sign In</span>
                        </button>
                    </div>
                </form>
            </div>
            
            <div class="text-center mt-8">
                <p class="text-gray-600">
                    Don't have an account? 
                    <a href="new_register.html" class="text-orange-600 font-medium hover:text-orange-700">Sign up</a>
                </p>
            </div>
        </div>
    </main>
</body>
</html>