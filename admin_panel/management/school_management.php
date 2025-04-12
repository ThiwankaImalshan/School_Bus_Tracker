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
    
    try {
        $stmt = $pdo->prepare("INSERT INTO school (name, location, address, arrival_time, 
                              departure_time, contact_number) 
                              VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$name, $location, $address, $arrival_time, $departure_time, $contact_number]);
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
    
    try {
        $stmt = $pdo->prepare("UPDATE school SET name = ?, location = ?, address = ?, 
                              arrival_time = ?, departure_time = ?, contact_number = ? 
                              WHERE school_id = ?");
        $stmt->execute([$name, $location, $address, $arrival_time, $departure_time, 
                       $contact_number, $school_id]);
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
    $stmt = $pdo->prepare("SELECT s.*, COUNT(c.child_id) as child_count 
                          FROM school s 
                          LEFT JOIN child c ON s.school_id = c.school_id 
                          GROUP BY s.school_id 
                          ORDER BY s.name");
    $stmt->execute();
    $schools = $stmt->fetchAll();
} catch (PDOException $e) {
    $error_message = "Error fetching schools: " . $e->getMessage();
    $schools = [];
}
?>

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
                <?php foreach ($schools as $school): ?>
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
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const addSchoolBtn = document.getElementById('addSchoolBtn');
    const schoolForm = document.getElementById('schoolForm');
    
    addSchoolBtn.addEventListener('click', function() {
        schoolForm.classList.toggle('hidden');
    });
    
    // JavaScript for delete confirmation
    window.confirmDelete = function(schoolId) {
        if (confirm('Are you sure you want to delete this school?')) {
            window.location.href = '?tab=school&delete=' + schoolId;
        }
    };
});
</script> 