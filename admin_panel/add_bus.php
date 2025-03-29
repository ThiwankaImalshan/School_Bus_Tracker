<?php
// Start session
// session_start();

// Check if admin is logged in (you might want to adjust this based on your authentication system)
// if (!isset($_SESSION['admin_id']) || !$_SESSION['logged_in']) {
    // Redirect to login page if not logged in
//     header('Location: login.html');
//     exit;
// }

// Database connection
$host = 'localhost';
$dbname = 'school_bus_management';
$username = 'root'; // Replace with your database username
$password = ''; // Replace with your database password

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Use GROUP BY to ensure unique schools
    $schoolStmt = $pdo->prepare("SELECT DISTINCT school_id, name FROM school GROUP BY school_id, name ORDER BY name");
    $schoolStmt->execute();
    $schools = $schoolStmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Parent Portal - Add Bus</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap">
    <style>
        body {
            font-family: 'Poppins', sans-serif;
        }
        .input-focus-effect:focus {
            box-shadow: 0 0 0 3px rgba(255, 179, 71, 0.3);
        }
        /* Custom button styles for analogous color palette */
        .btn-primary {
            background-color: #FF9500; /* Main orange */
            color: white;
            transition: all 0.3s ease;
        }
        .btn-primary:hover {
            background-color: #FF7A00; /* Darker orange */
            transform: translateY(-2px);
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
<body class="bg-gradient-to-b from-yellow-50 to-orange-50 min-h-screen p-4">
    <div class="fixed -z-10 top-0 left-0 w-full h-full opacity-50">
        <div class="absolute top-10 left-10 w-32 h-32 rounded-full bg-blue-100 blur-3xl"></div>
        <div class="absolute bottom-10 right-10 w-40 h-40 rounded-full bg-orange-100 blur-3xl"></div>
        <div class="absolute top-1/3 right-1/4 w-24 h-24 rounded-full bg-green-100 blur-3xl"></div>
    </div>

    <main class="container mx-auto py-6 md:py-10 relative">
        <div class="max-w-2xl mx-auto">
            <div class="flex flex-col md:flex-row items-start md:items-center justify-between mb-8 gap-4">
                <div class="flex items-center space-x-3">
                    <div class="h-10 w-1 bg-orange-500 rounded-full"></div>
                    <h2 class="text-3xl font-bold text-orange-800">Add Bus</h2>
                </div>
                <div class="flex items-center">
                    <!-- Updated dashboard button with gradient and hover effect -->
                    <a href="dashboard.php" class="flex items-center btn-gradient px-4 py-2 rounded-full shadow-md font-bold mr-3 group">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2 transition-transform group-hover:-translate-x-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                        </svg>
                        <span>Dashboard</span>
                    </a>
                </div>
            </div>

            <div class="bg-white rounded-3xl shadow-xl p-6 md:p-8 border border-orange-100 relative overflow-hidden">
                <div class="absolute -right-20 -top-20 w-40 h-40 bg-orange-50 rounded-full"></div>
                <div class="absolute -left-12 -bottom-12 w-24 h-24 bg-blue-50 rounded-full"></div>
                
                <div class="flex justify-between items-center mb-8 relative">
                    <div>
                        <h3 class="text-2xl font-semibold text-gray-800">Bus Information</h3>
                        <p class="text-gray-500 text-sm mt-1">Please fill in all required fields</p>
                    </div>
                </div>
                
                <form id="addBusForm" action="add_bus_process.php" method="POST" class="space-y-6 relative">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div class="group">
                            <label for="bus_number" class="block text-sm font-medium text-gray-700 mb-1 group-hover:text-orange-600 transition">Bus Number</label>
                            <input type="text" id="bus_number" name="bus_number" required 
                                   class="w-full px-4 py-3 rounded-xl border border-orange-200 focus:outline-none focus:ring-2 focus:ring-orange-400 focus:border-transparent transition input-focus-effect">
                        </div>
                        
                        <div class="group">
                            <label for="license_plate" class="block text-sm font-medium text-gray-700 mb-1 group-hover:text-orange-600 transition">License Plate</label>
                            <input type="text" id="license_plate" name="license_plate" required 
                                   class="w-full px-4 py-3 rounded-xl border border-orange-200 focus:outline-none focus:ring-2 focus:ring-orange-400 focus:border-transparent transition input-focus-effect">
                        </div>
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div class="group">
                            <label for="seat_capacity" class="block text-sm font-medium text-gray-700 mb-1 group-hover:text-orange-600 transition">Seat Capacity</label>
                            <div class="relative">
                                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m6 11h2M6 4v1m-2 11h2m0 0h16m-16 0a2 2 0 01-2-2V8a2 2 0 012-2h16a2 2 0 012 2v8a2 2 0 01-2 2M6 9h12M6 6h12" />
                                    </svg>
                                </div>
                                <input type="number" id="seat_capacity" name="seat_capacity" min="1" required 
                                       class="w-full pl-10 pr-4 py-3 rounded-xl border border-orange-200 focus:outline-none focus:ring-2 focus:ring-orange-400 focus:border-transparent transition input-focus-effect">
                            </div>
                        </div>
                        
                        <div class="group">
                            <label for="starting_location" class="block text-sm font-medium text-gray-700 mb-1 group-hover:text-orange-600 transition">Starting City</label>
                            <div class="relative">
                                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.243-4.243a8 8 0 1111.314 0z" />
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                                    </svg>
                                </div>
                                <input type="text" id="starting_location" name="starting_location" required 
                                       class="w-full pl-10 pr-4 py-3 rounded-xl border border-orange-200 focus:outline-none focus:ring-2 focus:ring-orange-400 focus:border-transparent transition input-focus-effect">
                            </div>
                        </div>
                    </div>
                    
                    <div class="group">
                        <label for="covering_cities" class="block text-sm font-medium text-gray-700 mb-1 group-hover:text-orange-600 transition">Covering Cities (comma-separated)</label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.618V7.382a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7" />
                                </svg>
                            </div>
                            <input type="text" id="covering_cities" name="covering_cities" 
                                   class="w-full pl-10 pr-4 py-3 rounded-xl border border-orange-200 focus:outline-none focus:ring-2 focus:ring-orange-400 focus:border-transparent transition input-focus-effect">
                        </div>
                    </div>
                    
                    <div class="bg-orange-50 rounded-xl p-4 border border-orange-100">
                        <label class="block text-sm font-medium text-gray-700 mb-3">Select Covering Schools</label>
                        <div class="grid grid-cols-2 md:grid-cols-3 gap-3">
                            <?php 
                            $displayed_schools = array();
                            foreach($schools as $school):
                                // Only display if this school name hasn't been displayed yet
                                if (!in_array($school['name'], $displayed_schools)):
                                    $displayed_schools[] = $school['name'];
                            ?>
                                <div class="flex items-center hover:bg-orange-100 p-2 rounded-lg transition-colors">
                                    <input type="checkbox" 
                                        id="school_<?php echo $school['school_id']; ?>" 
                                        name="schools[]" 
                                        value="<?php echo $school['school_id']; ?>"
                                        class="h-5 w-5 text-orange-500 rounded focus:ring-orange-400">
                                    <label for="school_<?php echo $school['school_id']; ?>" class="ml-3 text-gray-700 text-sm">
                                        <?php echo htmlspecialchars($school['name']); ?>
                                    </label>
                                </div>
                            <?php 
                                endif;
                            endforeach; 
                            ?>
                        </div>
                    </div>
                    
                    <div class="pt-6">
                        <button type="submit" class="w-full py-4 px-6 rounded-xl font-medium shadow-lg transition duration-300 ease-in-out transform hover:-translate-y-1 relative overflow-hidden group">
                            <div class="absolute inset-0 bg-gradient-to-r from-yellow-500 via-yellow-500 to-yellow-400"></div>
                            <div class="absolute inset-0 bg-gradient-to-r from-yellow-400 to-yellow-500 opacity-0 group-hover:opacity-100 transition-opacity duration-300"></div>
                            <span class="relative z-10 text-white text-lg font-bold drop-shadow-sm">Add Bus</span>
                        </button>
                    </div>
                </form>
            </div>
            
            <div class="text-center mt-8 text-sm text-gray-500">
                <p>All information is securely stored and protected</p>
            </div>
        </div>
    </main>
</body>
</html>