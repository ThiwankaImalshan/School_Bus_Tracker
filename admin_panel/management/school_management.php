<?php
// Process form submissions for school management
$success_message = '';
$error_message = '';

// Add new school
if (isset($_POST['add_school'])) {
    $name = trim($_POST['name']);
    $location = trim($_POST['location']);
    $address = trim($_POST['address']);
    $arrival_time = !empty($_POST['arrival_time']) ? $_POST['arrival_time'] : null;
    $departure_time = !empty($_POST['departure_time']) ? $_POST['departure_time'] : null;
    $contact_number = trim($_POST['contact_number']);
    $location_map = trim($_POST['location_map']); // Add this line
    
    try {
        $stmt = $pdo->prepare("INSERT INTO school (name, location, address, arrival_time, 
                              departure_time, contact_number, location_map) 
                              VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$name, $location, $address, $arrival_time, 
                       $departure_time, $contact_number, $location_map]);
        $success_message = "School added successfully!";
    } catch (PDOException $e) {
        $error_message = "Error adding school: " . $e->getMessage();
    }
}

// Update existing school
if (isset($_POST['update_school'])) {
    $school_id = (int)$_POST['school_id'];
    $name = trim($_POST['name']);
    $location = trim($_POST['location']);
    $address = trim($_POST['address']);
    $arrival_time = !empty($_POST['arrival_time']) ? $_POST['arrival_time'] : null;
    $departure_time = !empty($_POST['departure_time']) ? $_POST['departure_time'] : null;
    $contact_number = trim($_POST['contact_number']);
    $location_map = trim($_POST['location_map']); // Add this line
    
    try {
        $stmt = $pdo->prepare("UPDATE school SET name = ?, location = ?, address = ?, 
                              arrival_time = ?, departure_time = ?, contact_number = ?, 
                              location_map = ? WHERE school_id = ?");
        $stmt->execute([$name, $location, $address, $arrival_time, $departure_time, 
                       $contact_number, $location_map, $school_id]);
        $success_message = "School updated successfully!";
    } catch (PDOException $e) {
        $error_message = "Error updating school: " . $e->getMessage();
    }
}

// Delete school
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $school_id = (int)$_GET['delete'];
    
    try {
        // Check if school has children assigned
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM child WHERE school_id = ?");
        $stmt->execute([$school_id]);
        $child_count = $stmt->fetchColumn();
        
        if ($child_count > 0) {
            $error_message = "Cannot delete school: It has associated children. Reassign them first.";
        } else {
            // Delete bus_school associations
            $stmt = $pdo->prepare("DELETE FROM bus_school WHERE school_id = ?");
            $stmt->execute([$school_id]);
            
            // Delete route_school associations
            $stmt = $pdo->prepare("DELETE FROM route_school WHERE school_id = ?");
            $stmt->execute([$school_id]);
            
            // Delete the school
            $stmt = $pdo->prepare("DELETE FROM school WHERE school_id = ?");
            $stmt->execute([$school_id]);
            
            $success_message = "School deleted successfully!";
        }
    } catch (PDOException $e) {
        $error_message = "Error deleting school: " . $e->getMessage();
    }
}

// Get school for editing
$edit_school = null;
if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    $school_id = (int)$_GET['edit'];
    
    try {
        $stmt = $pdo->prepare("SELECT * FROM school WHERE school_id = ?");
        $stmt->execute([$school_id]);
        $edit_school = $stmt->fetch();
    } catch (PDOException $e) {
        $error_message = "Error fetching school details: " . $e->getMessage();
    }
}

// Fetch all schools with child counts
try {
    $stmt = $pdo->prepare("SELECT DISTINCT s.*, COUNT(c.child_id) as child_count 
                          FROM school s 
                          LEFT JOIN child c ON s.school_id = c.school_id 
                          GROUP BY s.name, s.school_id 
                          ORDER BY s.name");
    $stmt->execute();
    $schools = $stmt->fetchAll();
} catch (PDOException $e) {
    $error_message = "Error fetching schools: " . $e->getMessage();
    $schools = [];
}
?>

<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<style>
    #map {
        height: 400px;
        width: 100%;
        border-radius: 0.5rem;
    }
</style>

<div class="mb-6">
    <div class="flex justify-between items-center mb-6">
        <h2 class="text-2xl font-bold text-gray-800">
            <i class="fas fa-school mr-2 text-yellow-600"></i>School Management
        </h2>
        <button id="addSchoolBtn" class="bg-yellow-600 hover:bg-yellow-700 text-white px-4 py-2 rounded-lg transition duration-300">
            <i class="fas fa-plus mr-2"></i>Add New School
        </button>
    </div>

    <?php if (!empty($success_message)): ?>
    <div id="success-message" class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6 rounded transition-opacity duration-1000">
        <p><i class="fas fa-check-circle mr-2"></i><?php echo $success_message; ?></p>
    </div>
    <?php endif; ?>

    <?php if (!empty($error_message)): ?>
    <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6 rounded">
        <p><i class="fas fa-exclamation-circle mr-2"></i><?php echo $error_message; ?></p>
    </div>
    <?php endif; ?>

    <!-- Add/Edit Form -->
    <div id="schoolForm" class="bg-white rounded-lg shadow-md p-6 mb-8 <?php echo ($edit_school || isset($_POST['add_school']) && !empty($error_message)) ? 'block' : 'hidden'; ?>">
        <h3 class="text-xl font-semibold mb-4 text-gray-800">
            <?php echo $edit_school ? 'Edit School' : 'Add New School'; ?>
        </h3>
        <form method="POST" action="">
            <?php if ($edit_school): ?>
            <input type="hidden" name="school_id" value="<?php echo $edit_school['school_id']; ?>">
            <?php endif; ?>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label for="name" class="block text-gray-700 mb-2">School Name</label>
                    <input type="text" id="name" name="name" required
                           value="<?php echo $edit_school ? htmlspecialchars($edit_school['name']) : ''; ?>"
                           class="w-full rounded-lg border-gray-300 border p-3 focus:border-yellow-500 focus:ring focus:ring-yellow-200">
                </div>
                
                <div>
                    <label for="location" class="block text-gray-700 mb-2">Location (Area/City)</label>
                    <input type="text" id="location" name="location" required
                           value="<?php echo $edit_school ? htmlspecialchars($edit_school['location']) : ''; ?>"
                           class="w-full rounded-lg border-gray-300 border p-3 focus:border-yellow-500 focus:ring focus:ring-yellow-200">
                </div>
                
                <div class="md:col-span-2">
                    <label for="address" class="block text-gray-700 mb-2">Full Address</label>
                    <textarea id="address" name="address" rows="2" required
                              class="w-full rounded-lg border-gray-300 border p-3 focus:border-yellow-500 focus:ring focus:ring-yellow-200"><?php echo $edit_school ? htmlspecialchars($edit_school['address']) : ''; ?></textarea>
                </div>
                
                <div>
                    <label for="contact_number" class="block text-gray-700 mb-2">Contact Number</label>
                    <input type="text" id="contact_number" name="contact_number"
                           value="<?php echo $edit_school ? htmlspecialchars($edit_school['contact_number']) : ''; ?>"
                           class="w-full rounded-lg border-gray-300 border p-3 focus:border-yellow-500 focus:ring focus:ring-yellow-200">
                </div>
                
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label for="arrival_time" class="block text-gray-700 mb-2">Arrival Time</label>
                        <input type="time" id="arrival_time" name="arrival_time"
                               value="<?php echo $edit_school ? htmlspecialchars($edit_school['arrival_time']) : ''; ?>"
                               class="w-full rounded-lg border-gray-300 border p-3 focus:border-yellow-500 focus:ring focus:ring-yellow-200">
                    </div>
                    
                    <div>
                        <label for="departure_time" class="block text-gray-700 mb-2">Departure Time</label>
                        <input type="time" id="departure_time" name="departure_time"
                               value="<?php echo $edit_school ? htmlspecialchars($edit_school['departure_time']) : ''; ?>"
                               class="w-full rounded-lg border-gray-300 border p-3 focus:border-yellow-500 focus:ring focus:ring-yellow-200">
                    </div>
                </div>

                <div class="md:col-span-2">
                    <label class="block text-gray-700 mb-2">School Location on Map</label>
                    <div class="mb-3">
                        <div class="flex gap-2 mb-3">
                            <div class="flex-1 relative">
                                <input type="text" id="schoolSearch" 
                                       placeholder="Search for school location" 
                                       autocomplete="off"
                                       class="w-full rounded-lg border-gray-300 border p-3 focus:border-yellow-500 focus:ring focus:ring-yellow-200">
                                <div id="searchSuggestions" class="hidden absolute w-full mt-1 bg-white border border-gray-200 rounded-lg shadow-lg z-50 max-h-60 overflow-y-auto"></div>
                            </div>
                            <button type="button" id="searchBtn" 
                                    class="bg-yellow-600 hover:bg-yellow-700 text-white px-4 py-2 rounded-lg transition duration-300">
                                <i class="fas fa-search mr-2"></i>Search
                            </button>
                        </div>
                        <div id="searchResults" class="hidden mb-3 bg-white border border-gray-200 rounded-lg"></div>
                    </div>
                    <div id="map" class="mb-3"></div>
                    <input type="text" id="location_map" name="location_map" readonly
                           placeholder="Click on the map to set location"
                           value="<?php echo $edit_school ? htmlspecialchars($edit_school['location_map']) : ''; ?>"
                           class="w-full rounded-lg border-gray-300 border p-3 focus:border-yellow-500 focus:ring focus:ring-yellow-200">
                </div>
            </div>
            
            <div class="mt-6 flex space-x-4">
                <button type="submit" name="<?php echo $edit_school ? 'update_school' : 'add_school'; ?>"
                        class="bg-yellow-600 hover:bg-yellow-700 text-white px-6 py-2 rounded-lg transition duration-300">
                    <?php echo $edit_school ? 'Update School' : 'Add School'; ?>
                </button>
                <a href="?tab=school" class="bg-gray-500 hover:bg-gray-600 text-white px-6 py-2 rounded-lg transition duration-300">
                    Cancel
                </a>
            </div>
        </form>
    </div>

    <!-- Schools List -->
    <div class="overflow-x-auto bg-white rounded-lg shadow-md">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">School</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Location</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Contact</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">School Hours</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Students</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                <?php if (empty($schools)): ?>
                <tr>
                    <td colspan="6" class="px-6 py-4 text-center text-gray-500">No schools found.</td>
                </tr>
                <?php else: ?>
                <?php 
                $displayed_schools = array();
                foreach ($schools as $school):
                    // Only display if this school name hasn't been displayed yet
                    if (!in_array($school['name'], $displayed_schools)):
                        $displayed_schools[] = $school['name'];
                ?>
                <tr class="hover:bg-gray-50">
                    <td class="px-6 py-4 whitespace-nowrap">
                        <div class="font-medium text-gray-900"><?php echo htmlspecialchars($school['name']); ?></div>
                    </td>
                    <td class="px-6 py-4">
                        <div class="text-sm text-gray-900"><?php echo htmlspecialchars($school['location']); ?></div>
                        <div class="text-sm text-gray-500 max-w-xs truncate"><?php echo htmlspecialchars($school['address']); ?></div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700">
                        <?php echo htmlspecialchars($school['contact_number'] ?: 'N/A'); ?>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700">
                        <?php if ($school['arrival_time'] && $school['departure_time']): ?>
                        <div>Arrival: <?php echo date('h:i A', strtotime($school['arrival_time'])); ?></div>
                        <div>Departure: <?php echo date('h:i A', strtotime($school['departure_time'])); ?></div>
                        <?php else: ?>
                        Not specified
                        <?php endif; ?>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700">
                        <?php echo $school['child_count']; ?> students
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                        <a href="?tab=school&edit=<?php echo $school['school_id']; ?>" class="text-blue-600 hover:text-blue-900 mr-4">
                            <i class="fas fa-edit"></i> Edit
                        </a>
                        <a href="javascript:void(0);" onclick="confirmDelete(<?php echo $school['school_id']; ?>, 'school')" class="text-red-600 hover:text-red-900">
                            <i class="fas fa-trash-alt"></i> Delete
                        </a>
                    </td>
                </tr>
                <?php 
                    endif;
                endforeach; 
                ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const addSchoolBtn = document.getElementById('addSchoolBtn');
    const schoolForm = document.getElementById('schoolForm');
    let map = null;
    let marker = null;
    
    // Function to initialize map
    function initializeMap() {
        if (map !== null) {
            map.remove(); // Remove existing map instance
        }
        
        // Initialize map with a slight delay to ensure container is visible
        setTimeout(() => {
            map = L.map('map').setView([6.9271, 79.8612], 13);
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                maxZoom: 19,
                attribution: '© OpenStreetMap contributors'
            }).addTo(map);

            const locationInput = document.getElementById('location_map');

            // If editing and coordinates exist, show marker
            if (locationInput.value) {
                const coords = locationInput.value.split(',');
                if (coords.length === 2) {
                    const lat = parseFloat(coords[0]);
                    const lng = parseFloat(coords[1]);
                    marker = L.marker([lat, lng]).addTo(map);
                    map.setView([lat, lng], 15);
                }
            }

            map.on('click', function(e) {
                const lat = e.latlng.lat;
                const lng = e.latlng.lng;
                
                // Update or create marker
                if (marker) {
                    marker.setLatLng(e.latlng);
                } else {
                    marker = L.marker(e.latlng).addTo(map);
                }
                
                // Update input with coordinates
                locationInput.value = `${lat},${lng}`;
            });

            // Force map to refresh
            map.invalidateSize();
        }, 100);
    }
    
    addSchoolBtn.addEventListener('click', function() {
        schoolForm.classList.toggle('hidden');
        if (!schoolForm.classList.contains('hidden')) {
            initializeMap();
        }
    });
    
    // Initialize map if form is visible on page load (edit mode)
    if (!schoolForm.classList.contains('hidden')) {
        initializeMap();
    }
    
    // JavaScript for delete confirmation
    window.confirmDelete = function(schoolId) {
        if (confirm('Are you sure you want to delete this school?')) {
            window.location.href = '?tab=school&delete=' + schoolId;
        }
    };

    // School search functionality
    const schoolSearch = document.getElementById('schoolSearch');
    const searchSuggestions = document.getElementById('searchSuggestions');
    const searchBtn = document.getElementById('searchBtn');
    
    // Nominatim school search
    schoolSearch.addEventListener('input', debounce(function() {
        const searchTerm = this.value.trim();
        
        if (searchTerm.length < 3) {
            searchSuggestions.classList.add('hidden');
            return;
        }

        // Search in Sri Lanka with school-related keywords
        fetch(`https://nominatim.openstreetmap.org/search?format=json&q=${encodeURIComponent(searchTerm + ' school')}+Sri+Lanka&countrycodes=lk&limit=5`)
            .then(response => response.json())
            .then(places => {
                searchSuggestions.innerHTML = '';
                
                if (places.length > 0) {
                    places.forEach(place => {
                        const div = document.createElement('div');
                        div.className = 'p-3 hover:bg-gray-100 cursor-pointer';
                        div.textContent = place.display_name;
                        
                        div.onclick = function() {
                            const lat = parseFloat(place.lat);
                            const lon = parseFloat(place.lon);
                            
                            if (marker) {
                                marker.setLatLng([lat, lon]);
                            } else {
                                marker = L.marker([lat, lon]).addTo(map);
                            }
                            
                            map.setView([lat, lon], 17);
                            document.getElementById('location_map').value = `${lat},${lon}`;
                            schoolSearch.value = place.display_name;
                            searchSuggestions.classList.add('hidden');
                        };
                        
                        searchSuggestions.appendChild(div);
                    });
                    searchSuggestions.classList.remove('hidden');
                } else {
                    searchSuggestions.classList.add('hidden');
                }
            })
            .catch(error => console.error('Error:', error));
    }, 300));

    // Search button click handler
    searchBtn.addEventListener('click', function() {
        const searchTerm = schoolSearch.value.trim();
        if (searchTerm.length < 2) return;

        // Search using Nominatim
        fetch(`https://nominatim.openstreetmap.org/search?format=json&q=${encodeURIComponent(searchTerm)}+Sri+Lanka&countrycodes=lk&limit=5`)
            .then(response => response.json())
            .then(places => {
                const searchResults = document.getElementById('searchResults');
                searchResults.innerHTML = '';
                
                if (places.length > 0) {
                    places.forEach(place => {
                        const div = document.createElement('div');
                        div.className = 'p-4 hover:bg-gray-50 border-b last:border-0 cursor-pointer';
                        div.innerHTML = `
                            <div class="font-medium">${place.display_name.split(',')[0]}</div>
                            <div class="text-sm text-gray-600">${place.display_name}</div>
                        `;
                        
                        div.onclick = function() {
                            const lat = parseFloat(place.lat);
                            const lon = parseFloat(place.lon);
                            
                            if (marker) {
                                marker.setLatLng([lat, lon]);
                            } else {
                                marker = L.marker([lat, lon]).addTo(map);
                            }
                            
                            map.setView([lat, lon], 17);
                            document.getElementById('location_map').value = `${lat},${lon}`;
                            schoolSearch.value = place.display_name;
                            searchResults.classList.add('hidden');
                            searchSuggestions.classList.add('hidden');
                        };
                        
                        searchResults.appendChild(div);
                    });
                    searchResults.classList.remove('hidden');
                } else {
                    searchResults.innerHTML = '<div class="p-4 text-gray-500">No results found</div>';
                    searchResults.classList.remove('hidden');
                }
            })
            .catch(error => console.error('Error:', error));
    });

    // Also trigger search on Enter key
    schoolSearch.addEventListener('keypress', function(e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            searchBtn.click();
        }
    });

    // Debounce function to limit API calls
    function debounce(func, wait) {
        let timeout;
        return function executedFunction(...args) {
            const later = () => {
                clearTimeout(timeout);
                func.apply(this, args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    }

    // Close suggestions when clicking outside
    document.addEventListener('click', function(e) {
        if (!searchSuggestions.contains(e.target) && e.target !== schoolSearch) {
            searchSuggestions.classList.add('hidden');
        }
    });
});
</script>