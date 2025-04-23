<?php
// Process form submissions for child management
$success_message = '';
$error_message = '';

// Add new child
if (isset($_POST['add_child'])) {
    $parent_id = (int)$_POST['parent_id'];
    $school_id = !empty($_POST['school_id']) ? (int)$_POST['school_id'] : null;
    $bus_id = !empty($_POST['bus_id']) ? (int)$_POST['bus_id'] : null;
    $first_name = trim($_POST['first_name']);
    $last_name = trim($_POST['last_name']);
    $grade = trim($_POST['grade']);
    $pickup_location = trim($_POST['pickup_location']);
    $medical_notes = trim($_POST['medical_notes']);
    $emergency_contact = trim($_POST['emergency_contact']);
    
    try {
        $stmt = $pdo->prepare("INSERT INTO child (parent_id, school_id, bus_id, first_name, last_name, 
                              grade, pickup_location, medical_notes, emergency_contact) 
                              VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$parent_id, $school_id, $bus_id, $first_name, $last_name, 
                       $grade, $pickup_location, $medical_notes, $emergency_contact]);
        $success_message = "Child added successfully!";
    } catch (PDOException $e) {
        $error_message = "Error adding child: " . $e->getMessage();
    }
}

// Update existing child
if (isset($_POST['update_child'])) {
    $child_id = (int)$_POST['child_id'];
    $parent_id = (int)$_POST['parent_id'];
    $school_id = !empty($_POST['school_id']) ? (int)$_POST['school_id'] : null;
    $bus_id = !empty($_POST['bus_id']) ? (int)$_POST['bus_id'] : null;
    $first_name = trim($_POST['first_name']);
    $last_name = trim($_POST['last_name']);
    $grade = trim($_POST['grade']);
    $pickup_location = trim($_POST['pickup_location']);
    $medical_notes = trim($_POST['medical_notes']);
    $emergency_contact = trim($_POST['emergency_contact']);
    
    try {
        $stmt = $pdo->prepare("UPDATE child SET parent_id = ?, school_id = ?, bus_id = ?, 
                              first_name = ?, last_name = ?, grade = ?, pickup_location = ?, 
                              medical_notes = ?, emergency_contact = ? WHERE child_id = ?");
        $stmt->execute([$parent_id, $school_id, $bus_id, $first_name, $last_name, $grade, 
                       $pickup_location, $medical_notes, $emergency_contact, $child_id]);
        $success_message = "Child updated successfully!";
    } catch (PDOException $e) {
        $error_message = "Error updating child: " . $e->getMessage();
    }
}

// Delete child
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $child_id = (int)$_GET['delete'];
    
    try {
        // First delete any child reservations
        $stmt = $pdo->prepare("DELETE FROM child_reservation WHERE child_id = ?");
        $stmt->execute([$child_id]);
        
        // Delete attendances
        $stmt = $pdo->prepare("DELETE FROM attendance WHERE child_id = ?");
        $stmt->execute([$child_id]);
        
        // Delete payments
        $stmt = $pdo->prepare("DELETE FROM payment WHERE child_id = ?");
        $stmt->execute([$child_id]);
        
        // Delete the child
        $stmt = $pdo->prepare("DELETE FROM child WHERE child_id = ?");
        $stmt->execute([$child_id]);
        
        $success_message = "Child deleted successfully!";
    } catch (PDOException $e) {
        $error_message = "Error deleting child: " . $e->getMessage();
    }
}

// Get child for editing
$edit_child = null;
if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    $child_id = (int)$_GET['edit'];
    
    try {
        $stmt = $pdo->prepare("SELECT * FROM child WHERE child_id = ?");
        $stmt->execute([$child_id]);
        $edit_child = $stmt->fetch();
    } catch (PDOException $e) {
        $error_message = "Error fetching child details: " . $e->getMessage();
    }
}

// Fetch all children with parent, school, and bus information
try {
    $stmt = $pdo->prepare("SELECT c.*, 
                          p.full_name AS parent_name, 
                          s.name AS school_name,
                          b.bus_number
                          FROM child c 
                          LEFT JOIN parent p ON c.parent_id = p.parent_id
                          LEFT JOIN school s ON c.school_id = s.school_id
                          LEFT JOIN bus b ON c.bus_id = b.bus_id
                          ORDER BY c.last_name, c.first_name");
    $stmt->execute();
    $children = $stmt->fetchAll();
} catch (PDOException $e) {
    $error_message = "Error fetching children: " . $e->getMessage();
    $children = [];
}

// Fetch all parents for dropdown
try {
    $stmt = $pdo->prepare("SELECT parent_id, full_name FROM parent ORDER BY full_name");
    $stmt->execute();
    $parents = $stmt->fetchAll();
} catch (PDOException $e) {
    $error_message = "Error fetching parents: " . $e->getMessage();
    $parents = [];
}

// Fetch all schools for dropdown with no duplicates
try {
    $stmt = $pdo->prepare("SELECT DISTINCT school_id, name FROM school GROUP BY school_id ORDER BY name");
    $stmt->execute();
    $schools = $stmt->fetchAll();
} catch (PDOException $e) {
    $error_message = "Error fetching schools: " . $e->getMessage();
    $schools = [];
}

// Fetch all active buses for dropdown
try {
    $stmt = $pdo->prepare("SELECT bus_id, bus_number FROM bus WHERE is_active = 1 ORDER BY bus_number");
    $stmt->execute();
    $buses = $stmt->fetchAll();
} catch (PDOException $e) {
    $error_message = "Error fetching buses: " . $e->getMessage();
    $buses = [];
}
?>

<head>
    <!-- Add these lines in the head section -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <style>
        #map {
            height: 400px;
            width: 100%;
            border-radius: 0.5rem;
        }
    </style>
</head>

<div class="mb-6">
    <div class="flex flex-col sm:flex-row justify-between items-center gap-3 sm:gap-0 mb-4 sm:mb-6">
        <h2 class="text-xl sm:text-2xl font-bold text-gray-800">
            <i class="fas fa-child mr-1 sm:mr-2 text-yellow-600"></i>Child Management
        </h2>
        <button id="addChildBtn" class="bg-yellow-600 hover:bg-yellow-700 text-white px-3 sm:px-4 py-1 sm:py-2 text-sm sm:text-base rounded-lg transition duration-300 w-full sm:w-auto">
            <i class="fas fa-plus mr-1 sm:mr-2"></i>Add New Child
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
    <div id="childForm" class="bg-white rounded-lg shadow-md p-6 mb-8 <?php echo ($edit_child || isset($_POST['add_child']) && !empty($error_message)) ? 'block' : 'hidden'; ?>">
        <h3 class="text-xl font-semibold mb-4 text-gray-800">
            <?php echo $edit_child ? 'Edit Child' : 'Add New Child'; ?>
        </h3>
        <form method="POST" action="">
            <?php if ($edit_child): ?>
            <input type="hidden" name="child_id" value="<?php echo $edit_child['child_id']; ?>">
            <?php endif; ?>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label for="first_name" class="block text-gray-700 mb-2">First Name</label>
                    <input type="text" id="first_name" name="first_name" required
                           value="<?php echo $edit_child ? htmlspecialchars($edit_child['first_name']) : ''; ?>"
                           class="w-full rounded-lg border-gray-300 border p-3 focus:border-yellow-500 focus:ring focus:ring-yellow-200">
                </div>
                
                <div>
                    <label for="last_name" class="block text-gray-700 mb-2">Last Name</label>
                    <input type="text" id="last_name" name="last_name" required
                           value="<?php echo $edit_child ? htmlspecialchars($edit_child['last_name']) : ''; ?>"
                           class="w-full rounded-lg border-gray-300 border p-3 focus:border-yellow-500 focus:ring focus:ring-yellow-200">
                </div>
                
                <div>
                    <label for="parent_id" class="block text-gray-700 mb-2">Parent</label>
                    <select id="parent_id" name="parent_id" required
                            class="w-full rounded-lg border-gray-300 border p-3 focus:border-yellow-500 focus:ring focus:ring-yellow-200">
                        <option value="">-- Select Parent --</option>
                        <?php foreach ($parents as $parent): ?>
                        <option value="<?php echo $parent['parent_id']; ?>" 
                                <?php echo ($edit_child && $edit_child['parent_id'] == $parent['parent_id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($parent['full_name']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div>
                    <label for="grade" class="block text-gray-700 mb-2">Grade/Class</label>
                    <input type="text" id="grade" name="grade"
                           value="<?php echo $edit_child ? htmlspecialchars($edit_child['grade']) : ''; ?>"
                           class="w-full rounded-lg border-gray-300 border p-3 focus:border-yellow-500 focus:ring focus:ring-yellow-200">
                </div>
                
                <div>
                    <label for="school_id" class="block text-gray-700 mb-2">School</label>
                    <select id="school_id" name="school_id"
                            class="w-full rounded-lg border-gray-300 border p-3 focus:border-yellow-500 focus:ring focus:ring-yellow-200">
                        <option value="">-- Select School --</option>
                        <?php 
                        $displayed_schools = array();
                        foreach ($schools as $school):
                            if (!in_array($school['name'], $displayed_schools)):
                                $displayed_schools[] = $school['name'];
                        ?>
                        <option value="<?php echo $school['school_id']; ?>" 
                                <?php echo ($edit_child && $edit_child['school_id'] == $school['school_id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($school['name']); ?>
                        </option>
                        <?php 
                            endif;
                        endforeach; 
                        ?>
                    </select>
                </div>
                
                <div>
                    <label for="bus_id" class="block text-gray-700 mb-2">Assigned Bus</label>
                    <select id="bus_id" name="bus_id"
                            class="w-full rounded-lg border-gray-300 border p-3 focus:border-yellow-500 focus:ring focus:ring-yellow-200">
                        <option value="">-- Select Bus --</option>
                        <?php foreach ($buses as $bus): ?>
                        <option value="<?php echo $bus['bus_id']; ?>" 
                                <?php echo ($edit_child && $edit_child['bus_id'] == $bus['bus_id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($bus['bus_number']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div>
                    <label for="emergency_contact" class="block text-gray-700 mb-2">Emergency Contact</label>
                    <input type="text" id="emergency_contact" name="emergency_contact"
                           value="<?php echo $edit_child ? htmlspecialchars($edit_child['emergency_contact']) : ''; ?>"
                           class="w-full rounded-lg border-gray-300 border p-3 focus:border-yellow-500 focus:ring focus:ring-yellow-200">
                </div>
                
                <div class="md:col-span-2">
                    <label for="pickup_location" class="block text-gray-700 mb-2">Pickup Location</label>
                    <div id="map" class="mb-3"></div>
                    <input type="text" id="pickup_location" name="pickup_location" readonly
                           placeholder="Click on the map to set pickup location"
                           value="<?php echo $edit_child ? htmlspecialchars($edit_child['pickup_location']) : ''; ?>"
                           class="w-full rounded-lg border-gray-300 border p-3 focus:border-yellow-500 focus:ring focus:ring-yellow-200">
                </div>
                
                <div class="md:col-span-2">
                    <label for="medical_notes" class="block text-gray-700 mb-2">Medical Notes</label>
                    <textarea id="medical_notes" name="medical_notes" rows="3"
                              class="w-full rounded-lg border-gray-300 border p-3 focus:border-yellow-500 focus:ring focus:ring-yellow-200"><?php echo $edit_child ? htmlspecialchars($edit_child['medical_notes']) : ''; ?></textarea>
                </div>
            </div>
            
            <div class="mt-6 flex space-x-4">
                <button type="submit" name="<?php echo $edit_child ? 'update_child' : 'add_child'; ?>"
                        class="bg-yellow-600 hover:bg-yellow-700 text-white px-6 py-2 rounded-lg transition duration-300">
                    <?php echo $edit_child ? 'Update Child' : 'Add Child'; ?>
                </button>
                <a href="?tab=child" class="bg-gray-500 hover:bg-gray-600 text-white px-6 py-2 rounded-lg transition duration-300">
                    Cancel
                </a>
            </div>
        </form>
    </div>

    <!-- Children List -->
    <div class="overflow-x-auto bg-white rounded-lg shadow-md">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Child</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Parent</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">School & Grade</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Bus</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Pickup Location</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                <?php if (empty($children)): ?>
                <tr>
                    <td colspan="6" class="px-6 py-4 text-center text-gray-500">No children found.</td>
                </tr>
                <?php else: ?>
                <?php foreach ($children as $child): ?>
                <tr class="hover:bg-gray-50">
                    <td class="px-6 py-4">
                        <div class="font-medium text-gray-900">
                            <?php echo htmlspecialchars($child['first_name'] . ' ' . $child['last_name']); ?>
                        </div>
                        <div class="text-sm text-gray-500">
                            Emergency: <?php echo htmlspecialchars($child['emergency_contact'] ?: 'N/A'); ?>
                        </div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700">
                        <?php echo htmlspecialchars($child['parent_name']); ?>
                    </td>
                    <td class="px-6 py-4">
                        <div class="text-sm text-gray-900">
                            <?php echo htmlspecialchars($child['school_name'] ?: 'N/A'); ?>
                        </div>
                        <div class="text-sm text-gray-500">
                            Grade: <?php echo htmlspecialchars($child['grade'] ?: 'N/A'); ?>
                        </div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700">
                        <?php echo $child['bus_number'] ? htmlspecialchars($child['bus_number']) : 'Not Assigned'; ?>
                    </td>
                    <td class="px-6 py-4 max-w-xs">
                        <div class="text-sm text-gray-700 truncate">
                            <?php echo htmlspecialchars($child['pickup_location'] ?: 'N/A'); ?>
                        </div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                        <a href="?tab=child&edit=<?php echo $child['child_id']; ?>" class="text-blue-600 hover:text-blue-900 mr-4">
                            <i class="fas fa-edit"></i> Edit
                        </a>
                        <a href="javascript:void(0);" onclick="confirmDelete(<?php echo $child['child_id']; ?>, 'child')" class="text-red-600 hover:text-red-900">
                            <i class="fas fa-trash-alt"></i> Delete
                        </a>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const addChildBtn = document.getElementById('addChildBtn');
    const childForm = document.getElementById('childForm');
    
    addChildBtn.addEventListener('click', function() {
        childForm.classList.toggle('hidden');
    });
    
    // JavaScript for delete confirmation
    window.confirmDelete = function(childId) {
        if (confirm('Are you sure you want to delete this child?')) {
            window.location.href = '?tab=child&delete=' + childId;
        }
    };

    // Initialize the map
    let map = L.map('map').setView([6.9271, 79.8612], 13); // Default center on Colombo
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution: '© OpenStreetMap contributors'
    }).addTo(map);

    let marker;
    const pickupLocationInput = document.getElementById('pickup_location');

    // If editing and coordinates exist, show marker
    if (pickupLocationInput.value) {
        const coords = pickupLocationInput.value.split(',');
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
        pickupLocationInput.value = `${lat},${lng}`;
    });
});
</script>