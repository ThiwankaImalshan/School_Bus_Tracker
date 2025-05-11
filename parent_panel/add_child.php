<?php
// Start session
session_start();

// Check if parent is logged in
if (!isset($_SESSION['parent_id']) || !$_SESSION['logged_in']) {
    // Redirect to login page if not logged in
    header('Location: login.html');
    exit;
}

// Database connection
$host = 'localhost';
$dbname = 'school_bus_management';
$username = 'root'; // Replace with your database username
$password = ''; // Replace with your database password

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Fetch schools for dropdown
    $schoolStmt = $pdo->prepare("SELECT DISTINCT school_id, name, address FROM school ORDER BY name");
    $schoolStmt->execute();
    $schools = $schoolStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Fetch unique covering cities from buses
    $cityStmt = $pdo->prepare("
        SELECT 
            DISTINCT TRIM(SUBSTRING_INDEX(SUBSTRING_INDEX(covering_cities, ',', numbers.n), ',', -1)) as city,
            CASE 
                WHEN TRIM(SUBSTRING_INDEX(SUBSTRING_INDEX(covering_cities, ',', numbers.n), ',', -1)) = 'Kelaniya' 
                THEN '6.9553,79.9220,2' -- lat,lng,radius in km
                WHEN TRIM(SUBSTRING_INDEX(SUBSTRING_INDEX(covering_cities, ',', numbers.n), ',', -1)) = 'Peliyagoda' 
                THEN '6.9615,79.8918,2'
                WHEN TRIM(SUBSTRING_INDEX(SUBSTRING_INDEX(covering_cities, ',', numbers.n), ',', -1)) = 'Kiribathgoda' 
                THEN '6.9750,79.9277,2'
                -- Add more cities as needed
                ELSE NULL
            END as boundaries
        FROM bus
        CROSS JOIN (
            SELECT 1 + units.i + tens.i * 10 as n
            FROM (SELECT 0 as i UNION SELECT 1 UNION SELECT 2 UNION SELECT 3 UNION SELECT 4 UNION SELECT 5 UNION SELECT 6 UNION SELECT 7 UNION SELECT 8 UNION SELECT 9) units
            CROSS JOIN (SELECT 0 as i UNION SELECT 1 UNION SELECT 2 UNION SELECT 3 UNION SELECT 4 UNION SELECT 5 UNION SELECT 6 UNION SELECT 7 UNION SELECT 8 UNION SELECT 9) tens
            WHERE 1 + units.i + tens.i * 10 <= 100
        ) numbers
        WHERE TRIM(SUBSTRING_INDEX(SUBSTRING_INDEX(covering_cities, ',', numbers.n), ',', -1)) != ''
        ORDER BY city
    ");
    $cityStmt->execute();
    $cities = $cityStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Create child photos directory if it doesn't exist
    $upload_dir = "../img/child";
    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }
    
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Parent Portal - Add Child</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.7.1/dist/leaflet.css" />
    <link rel="stylesheet" href="styles.css">
    <link rel="icon" type="image/png" href="../img/favicon/favicon-96x96.png" sizes="96x96" />
    <link rel="shortcut icon" href="../img/favicon/favicon.ico" />
    <link rel="icon" type="image/svg+xml" href="../img/favicon/favicon.svg" />
    
    <!-- Add Leaflet.js for maps -->
    <script src="https://unpkg.com/leaflet@1.7.1/dist/leaflet.js"></script>
    
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
        .btn-secondary {
            background-color: #FFB700; /* Yellow-orange */
            color: white;
            transition: all 0.3s ease;
        }
        .btn-secondary:hover {
            background-color: #FFA000; /* Darker yellow-orange */
            transform: translateY(-2px);
        }
        .btn-accent {
            background-color: #FFCE00; /* Yellow */
            color: #7C4700; /* Dark brown for contrast */
            transition: all 0.3s ease;
        }
        .btn-accent:hover {
            background-color: #FFD500; /* Brighter yellow */
            transform: translateY(-2px);
        }
        /* Custom gradient styles for buttons */
        .btn-gradient {
            background: linear-gradient(135deg, #FF9500, #FFB700);
            color: white;
            transition: all 0.3s ease;
        }
        .btn-gradient:hover {
            background: linear-gradient(135deg, #FF7A00, #FFA000);
            transform: translateY(-2px);
        }
        #map {
            height: 400px;
            width: 100%;
            border-radius: 0.75rem;
        }
        .leaflet-control {
            background-color: white;
            padding: 10px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
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
        <div class="max-w-4xl mx-auto">
            <div class="flex flex-col md:flex-row items-start md:items-center justify-between mb-8 gap-4">
                <div class="flex items-center space-x-3">
                    <div class="h-10 w-1 bg-orange-500 rounded-full"></div>
                    <h2 class="text-3xl font-bold text-orange-800">Add Child</h2>
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
                        <h3 class="text-2xl font-semibold text-gray-800">Child Information</h3>
                        <p class="text-gray-500 text-sm mt-1">Please fill in all required fields</p>
                    </div>
                </div>
                
                <form id="addChildForm" action="add_child_process.php" method="POST" class="space-y-6 relative" enctype="multipart/form-data">
                    <!-- Hidden input for parent_id from session -->
                    <input type="hidden" name="parent_id" value="<?php echo $_SESSION['parent_id']; ?>">
                    <input type="hidden" name="pickup_location" id="pickup_location">
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div class="group">
                            <label for="child_first_name" class="block text-sm font-medium text-gray-700 mb-1 group-hover:text-orange-600 transition">First Name</label>
                            <input type="text" id="child_first_name" name="child_first_name" required class="w-full px-4 py-3 rounded-xl border border-orange-200 focus:outline-none focus:ring-2 focus:ring-orange-400 focus:border-transparent transition input-focus-effect">
                        </div>
                        <div class="group">
                            <label for="child_last_name" class="block text-sm font-medium text-gray-700 mb-1 group-hover:text-orange-600 transition">Last Name</label>
                            <input type="text" id="child_last_name" name="child_last_name" required class="w-full px-4 py-3 rounded-xl border border-orange-200 focus:outline-none focus:ring-2 focus:ring-orange-400 focus:border-transparent transition input-focus-effect">
                        </div>
                    </div>
                    
                    <!-- Add photo upload section after the name fields -->
                    <div class="group">
                        <label for="child_photo" class="block text-sm font-medium text-gray-700 mb-1 group-hover:text-orange-600 transition">
                            Child Photo (Optional)
                        </label>
                        <div class="flex items-center space-x-4">
                            <div class="w-24 h-24 border-2 border-dashed border-orange-200 rounded-lg flex items-center justify-center bg-orange-50">
                                <img id="photo_preview" src="#" alt="Preview" class="hidden max-w-full max-h-full rounded-lg">
                                <div id="upload_placeholder" class="text-center">
                                    <svg class="mx-auto h-8 w-8 text-orange-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                    </svg>
                                </div>
                            </div>
                            <div class="flex-1">
                                <input type="file" id="child_photo" name="child_photo" accept="image/*" class="hidden" onchange="previewImage(this)">
                                <label for="child_photo" class="cursor-pointer btn-gradient px-4 py-2 rounded-lg inline-block text-white">
                                    Choose Photo
                                </label>
                                <p class="text-xs text-gray-500 mt-1">Max file size: 2MB. Accepted formats: JPG, PNG</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="group">
                        <label for="school_id" class="block text-sm font-medium text-gray-700 mb-1 group-hover:text-orange-600 transition">School</label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path d="M12 14l9-5-9-5-9 5 9 5z" />
                                    <path d="M12 14l6.16-3.422a12.083 12.083 0 01.665 6.479A11.952 11.952 0 0012 20.055a11.952 11.952 0 00-6.824-2.998a12.078 12.078 0 01.665-6.479L12 14z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 14l9-5-9-5-9 5 9 5zm0 0l6.16-3.422a12.083 12.083 0 01.665 6.479A11.952 11.952 0 0012 20.055a11.952 11.952 0 00-6.824-2.998a12.078 12.078 0 01.665-6.479L12 14zm-4 6v-7.5l4-2.222" />
                                </svg>
                            </div>
                            <select id="school_id" name="school_id" required class="w-full pl-10 pr-10 py-3 rounded-xl border border-orange-200 focus:outline-none focus:ring-2 focus:ring-orange-400 focus:border-transparent transition appearance-none bg-white input-focus-effect">
                                <option value="" disabled selected>Select school</option>
                                <?php 
                                $displayed_schools = array();
                                foreach($schools as $school): 
                                    // Only display if this school name hasn't been displayed yet
                                    if (!in_array($school['name'], $displayed_schools)):
                                        $displayed_schools[] = $school['name'];
                                ?>
                                    <option value="<?php echo htmlspecialchars($school['school_id']); ?>">
                                        <?php echo htmlspecialchars($school['name']); ?>
                                    </option>
                                <?php 
                                    endif;
                                endforeach; 
                                ?>
                            </select>
                            
                            <div class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                </svg>
                            </div>
                        </div>
                    </div>
                    
                    <!-- City and Bus Selection -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div class="group">
                            <label for="city" class="block text-sm font-medium text-gray-700 mb-1">City</label>
                            <select id="city" name="city" required class="w-full px-4 py-3 rounded-xl border border-orange-200 focus:outline-none focus:ring-2 focus:ring-orange-400">
                                <option value="">Select your city</option>
                                <?php foreach($cities as $city): ?>
                                    <option value="<?php echo htmlspecialchars(trim($city['city'])); ?>">
                                        <?php echo htmlspecialchars(trim($city['city'])); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            </div>
                        
                        <div class="group">
                            <label for="bus_id" class="block text-sm font-medium text-gray-700 mb-1">Available Bus</label>
                            <select id="bus_id" name="bus_id" required class="w-full px-4 py-3 rounded-xl border border-orange-200 focus:outline-none focus:ring-2 focus:ring-orange-400" disabled>
                                <option value="">Select a city first</option>
                            </select>
                        </div>
                    </div>
                    
                    <!-- Map for pickup location -->
                    <div class="group">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Set Pickup Location</label>
                        <div id="map" class="border border-orange-200 rounded-xl"></div>
                    </div>
                    
                    <div class="group">
                        <label for="grade" class="block text-sm font-medium text-gray-700 mb-1 group-hover:text-orange-600 transition">Grade</label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path d="M12 14l9-5-9-5-9 5 9 5z" />
                                    <path d="M12 14l6.16-3.422a12.083 12.083 0 01.665 6.479A11.952 11.952 0 0012 20.055a11.952 11.952 0 00-6.824-2.998a12.078 12.078 0 01.665-6.479L12 14z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 14l9-5-9-5-9 5 9 5zm0 0l6.16-3.422a12.083 12.083 0 01.665 6.479A11.952 11.952 0 0012 20.055a11.952 11.952 0 00-6.824-2.998a12.078 12.078 0 01.665-6.479L12 14zm-4 6v-7.5l4-2.222" />
                                </svg>
                            </div>
                            <select id="grade" name="grade" required class="w-full pl-10 pr-10 py-3 rounded-xl border border-orange-200 focus:outline-none focus:ring-2 focus:ring-orange-400 focus:border-transparent transition appearance-none bg-white input-focus-effect">
                                <option value="" disabled selected>Select grade</option>
                                <option value="pre_k">Pre-K</option>
                                <option value="kindergarten">Kindergarten</option>
                                <option value="1">1st Grade</option>
                                <option value="2">2nd Grade</option>
                                <option value="3">3rd Grade</option>
                                <option value="4">4th Grade</option>
                                <option value="5">5th Grade</option>
                                <option value="6">6th Grade</option>
                                <option value="7">7th Grade</option>
                                <option value="8">8th Grade</option>
                                <option value="9">9th Grade</option>
                                <option value="10">10th Grade</option>
                                <option value="11">11th Grade</option>
                                <option value="12">12th Grade</option>
                            </select>
                            <div class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                </svg>
                            </div>
                        </div>
                    </div>
                    
                    <div class="group">
                        <label for="emergency_contact" class="block text-sm font-medium text-gray-700 mb-1 group-hover:text-orange-600 transition">Emergency Contact</label>
                        <input type="text" id="emergency_contact" name="emergency_contact" class="w-full px-4 py-3 rounded-xl border border-orange-200 focus:outline-none focus:ring-2 focus:ring-orange-400 focus:border-transparent transition input-focus-effect" placeholder="Name and phone number">
                    </div>
                    
                    <div class="group">
                        <label for="medical_notes" class="block text-sm font-medium text-gray-700 mb-1 group-hover:text-orange-600 transition">Medical Notes (Optional)</label>
                        <textarea id="medical_notes" name="medical_notes" class="w-full px-4 py-3 rounded-xl border border-orange-200 focus:outline-none focus:ring-2 focus:ring-orange-400 focus:border-transparent transition input-focus-effect" rows="3" placeholder="Allergies, medications, or other important medical information"></textarea>
                    </div>
                    
                    <div class="pt-6">
                        <!-- Updated submit button with gradient and improved hover effects -->
                        <button type="submit" class="w-full py-4 px-6 rounded-xl font-medium shadow-lg transition duration-300 ease-in-out transform hover:-translate-y-1 relative overflow-hidden group">
                            <!-- Button background with gradient -->
                            <div class="absolute inset-0 bg-gradient-to-r from-yellow-500 via-yellow-500 to-yellow-400"></div>
                            <!-- Yellow overlay on hover -->
                            <div class="absolute inset-0 bg-gradient-to-r from-yellow-400 to-yellow-500 opacity-0 group-hover:opacity-100 transition-opacity duration-300"></div>
                            <!-- Text with shadow for better readability -->
                            <span class="relative z-10 text-white text-lg font-bold drop-shadow-sm">Add Child</span>
                        </button>
                    </div>
                </form>
            </div>
            
            <div class="text-center mt-8 text-sm text-gray-500">
                <p>All information is securely stored and protected</p>
            </div>
        </div>
    </main>

    <script>
        // Add this before the existing scripts
        function previewImage(input) {
            const preview = document.getElementById('photo_preview');
            const placeholder = document.getElementById('upload_placeholder');
            
            if (input.files && input.files[0]) {
                // Check file size
                if (input.files[0].size > 2 * 1024 * 1024) {
                    alert('File size must be less than 2MB');
                    input.value = '';
                    return;
                }

                const reader = new FileReader();
                reader.onload = function(e) {
                    preview.src = e.target.result;
                    preview.classList.remove('hidden');
                    placeholder.classList.add('hidden');
                }
                reader.readAsDataURL(input.files[0]);
            } else {
                preview.classList.add('hidden');
                placeholder.classList.remove('hidden');
            }
        }

        // Initialize map
        const map = L.map('map').setView([6.9271, 79.8612], 13);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '© OpenStreetMap contributors'
        }).addTo(map);

        let marker;
        let cityCircle;
        const cityBoundaries = {
            <?php 
            foreach($cities as $city) {
                if (!empty($city['boundaries'])) {
                    list($lat, $lng, $radius) = explode(',', $city['boundaries']);
                    echo "'" . addslashes($city['city']) . "': {lat: $lat, lng: $lng, radius: $radius},";
                }
            }
            ?>
        };

        // Function to check if a point is within circle
        function isPointInCircle(point, circle) {
            const center = L.latLng(circle.lat, circle.lng);
            const pointLatLng = L.latLng(point.lat, point.lng);
            const distanceInKm = center.distanceTo(pointLatLng) / 1000;
            return distanceInKm <= circle.radius;
        }

        // Handle map clicks
        map.on('click', function(e) {
            const selectedCity = document.getElementById('city').value;
            const cityBoundary = cityBoundaries[selectedCity];

            if (!selectedCity) {
                alert('Please select a city first');
                return;
            }

            // Only check boundaries if the city has defined boundaries
            if (cityBoundary && !isPointInCircle(e.latlng, cityBoundary)) {
                alert('Please select a location within the selected city boundary');
                return;
            }

            if (marker) {
                map.removeLayer(marker);
            }
            marker = L.marker(e.latlng).addTo(map);
            document.getElementById('pickup_location').value = `${e.latlng.lat},${e.latlng.lng}`;
        });

        // Handle city selection
        document.getElementById('city').addEventListener('change', function() {
            const city = this.value;
            const busSelect = document.getElementById('bus_id');
            
            // Clear existing marker and circle
            if (marker) {
                map.removeLayer(marker);
            }
            if (cityCircle) {
                map.removeLayer(cityCircle);
            }

            if (city) {
                const boundary = cityBoundaries[city];
                
                if (boundary) {
                    // Create and add circle for city boundary
                    cityCircle = L.circle([boundary.lat, boundary.lng], {
                        color: 'orange',
                        fillColor: '#FFB700',
                        fillOpacity: 0.2,
                        radius: boundary.radius * 1000 // Convert km to meters
                    }).addTo(map);

                    // Center map on selected city with boundary
                    map.setView([boundary.lat, boundary.lng], 14);
                } else {
                    // For cities without boundaries, show a wider view of Sri Lanka
                    map.setView([7.8731, 80.7718], 8);
                }

                // Fetch available buses
                fetch(`get_available_buses.php?city=${encodeURIComponent(city)}`)
                    .then(response => response.json())
                    .then(data => {
                        busSelect.innerHTML = '<option value="">Select a bus</option>';
                        data.forEach(bus => {
                            busSelect.innerHTML += `<option value="${bus.bus_id}">${bus.bus_number} - ${bus.available_seats} seats available</option>`;
                        });
                        busSelect.disabled = false;
                    })
                    .catch(error => console.error('Error:', error));
            } else {
                busSelect.innerHTML = '<option value="">Select a city first</option>';
                busSelect.disabled = true;
                map.setView([6.9271, 79.8612], 13); // Reset map view
            }

            // Update city info
            updateCityInfo(city);
        });

        // Add custom control to show city info
        const cityInfoControl = L.Control.extend({
            options: {
                position: 'topright'
            },

            onAdd: function(map) {
                const container = L.DomUtil.create('div', 'leaflet-bar leaflet-control bg-white p-3 rounded-lg shadow-lg');
                container.innerHTML = `
                    <div class="text-sm font-medium text-gray-700">
                        <h4 class="font-bold mb-2">Selected Area</h4>
                        <div id="cityInfo">Select a city to see coverage area</div>
                    </div>
                `;
                return container;
            }
        });

        map.addControl(new cityInfoControl());

        // Function to update city info
        function updateCityInfo(city) {
            const cityInfo = document.getElementById('cityInfo');
            
            if (city) {
                const boundary = cityBoundaries[city];
                if (boundary) {
                    cityInfo.innerHTML = `
                        <p class="text-orange-600">${city}</p>
                        <p class="text-xs text-gray-500">Coverage radius: ${boundary.radius}km</p>
                        <p class="text-xs text-gray-500">Select location within highlighted area</p>
                    `;
                } else {
                    cityInfo.innerHTML = `
                        <p class="text-orange-600">${city}</p>
                        <p class="text-xs text-gray-500">No defined boundary</p>
                        <p class="text-xs text-gray-500">Select any pickup location</p>
                    `;
                }
            } else {
                cityInfo.innerHTML = 'Select a city to see coverage area';
            }
        }
    </script>
</body>
</html>