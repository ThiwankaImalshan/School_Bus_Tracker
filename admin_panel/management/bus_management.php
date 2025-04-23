<?php
// Process form submissions for bus management
$success_message = '';
$error_message = '';

// Add new bus
if (isset($_POST['add_bus'])) {
    $bus_number = trim($_POST['bus_number']);
    $license_plate = trim($_POST['license_plate']);
    $capacity = (int)$_POST['capacity'];
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    $starting_location = trim($_POST['starting_location']);
    $covering_cities = trim($_POST['covering_cities']);

    try {
        $stmt = $pdo->prepare("INSERT INTO bus (bus_number, license_plate, capacity, is_active, 
                              starting_location, covering_cities) 
                              VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$bus_number, $license_plate, $capacity, $is_active, $starting_location, $covering_cities]);
        $success_message = "Bus added successfully!";
    } catch (PDOException $e) {
        $error_message = "Error adding bus: " . $e->getMessage();
    }
}

// Update existing bus
if (isset($_POST['update_bus'])) {
    $bus_id = (int)$_POST['bus_id'];
    $bus_number = trim($_POST['bus_number']);
    $license_plate = trim($_POST['license_plate']);
    $capacity = (int)$_POST['capacity'];
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    $starting_location = trim($_POST['starting_location']);
    $covering_cities = trim($_POST['covering_cities']);

    try {
        $stmt = $pdo->prepare("UPDATE bus SET bus_number = ?, license_plate = ?, 
                              capacity = ?, is_active = ?, starting_location = ?, 
                              covering_cities = ? WHERE bus_id = ?");
        $stmt->execute([$bus_number, $license_plate, $capacity, $is_active, 
                      $starting_location, $covering_cities, $bus_id]);
        $success_message = "Bus updated successfully!";
    } catch (PDOException $e) {
        $error_message = "Error updating bus: " . $e->getMessage();
    }
}

// Delete bus
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $bus_id = (int)$_GET['delete'];
    
    try {
        // Check if bus has drivers assigned
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM driver WHERE bus_id = ?");
        $stmt->execute([$bus_id]);
        $driver_count = $stmt->fetchColumn();
        
        // Check if bus has children assigned
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM child WHERE bus_id = ?");
        $stmt->execute([$bus_id]);
        $child_count = $stmt->fetchColumn();
        
        if ($driver_count > 0 || $child_count > 0) {
            $error_message = "Cannot delete bus: It has associated drivers or children. Reassign them first.";
        } else {
            // Delete bus_seat records for this bus
            $stmt = $pdo->prepare("DELETE FROM bus_seat WHERE bus_id = ?");
            $stmt->execute([$bus_id]);
            
            // Delete bus_school records for this bus
            $stmt = $pdo->prepare("DELETE FROM bus_school WHERE bus_id = ?");
            $stmt->execute([$bus_id]);
            
            // Delete the bus
            $stmt = $pdo->prepare("DELETE FROM bus WHERE bus_id = ?");
            $stmt->execute([$bus_id]);
            
            $success_message = "Bus deleted successfully!";
        }
    } catch (PDOException $e) {
        $error_message = "Error deleting bus: " . $e->getMessage();
    }
}

// Get bus for editing
$edit_bus = null;
if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    $bus_id = (int)$_GET['edit'];
    
    try {
        $stmt = $pdo->prepare("SELECT * FROM bus WHERE bus_id = ?");
        $stmt->execute([$bus_id]);
        $edit_bus = $stmt->fetch();
    } catch (PDOException $e) {
        $error_message = "Error fetching bus details: " . $e->getMessage();
    }
}

// Fetch all buses
try {
    $stmt = $pdo->prepare("SELECT * FROM bus ORDER BY bus_number");
    $stmt->execute();
    $buses = $stmt->fetchAll();
} catch (PDOException $e) {
    $error_message = "Error fetching buses: " . $e->getMessage();
    $buses = [];
}
?>

<div class="mb-6">
    <div class="flex flex-col sm:flex-row justify-between items-center gap-3 sm:gap-0 mb-4 sm:mb-6">
        <h2 class="text-xl sm:text-2xl font-bold text-gray-800">
            <i class="fas fa-bus mr-1 sm:mr-2 text-yellow-600"></i>Bus Management
        </h2>
        <!-- <button id="addBusBtn" class="bg-yellow-600 hover:bg-yellow-700 text-white px-4 py-2 rounded-lg transition duration-300">
            <i class="fas fa-plus mr-2"></i>Add New Bus
        </button> -->
        <a href="../add_bus.php" class="bg-yellow-600 hover:bg-yellow-700 text-white px-3 sm:px-4 py-1 sm:py-2 text-sm sm:text-base rounded-lg transition duration-300 w-full sm:w-auto">
            <i class="fas fa-plus mr-1 sm:mr-2"></i>Add New Bus
        </a>
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
    <div id="busForm" class="bg-white rounded-lg shadow-md p-6 mb-8 <?php echo ($edit_bus || isset($_POST['add_bus']) && !empty($error_message)) ? 'block' : 'hidden'; ?>">
        <h3 class="text-xl font-semibold mb-4 text-gray-800">
            <?php echo $edit_bus ? 'Edit Bus' : 'Add New Bus'; ?>
        </h3>
        <form method="POST" action="">
            <?php if ($edit_bus): ?>
            <input type="hidden" name="bus_id" value="<?php echo $edit_bus['bus_id']; ?>">
            <?php endif; ?>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label for="bus_number" class="block text-gray-700 mb-2">Bus Number</label>
                    <input type="text" id="bus_number" name="bus_number" required
                           value="<?php echo $edit_bus ? htmlspecialchars($edit_bus['bus_number']) : ''; ?>"
                           class="w-full rounded-lg border-gray-300 border p-3 focus:border-yellow-500 focus:ring focus:ring-yellow-200">
                </div>
                
                <div>
                    <label for="license_plate" class="block text-gray-700 mb-2">License Plate</label>
                    <input type="text" id="license_plate" name="license_plate" required
                           value="<?php echo $edit_bus ? htmlspecialchars($edit_bus['license_plate']) : ''; ?>"
                           class="w-full rounded-lg border-gray-300 border p-3 focus:border-yellow-500 focus:ring focus:ring-yellow-200">
                </div>
                
                <!-- <div>
                    <label for="capacity" class="block text-gray-700 mb-2">Capacity</label>
                    <input type="number" id="capacity" name="capacity" required min="1" max="100"
                           value="<?php echo $edit_bus ? htmlspecialchars($edit_bus['capacity']) : '40'; ?>"
                           class="w-full rounded-lg border-gray-300 border p-3 focus:border-yellow-500 focus:ring focus:ring-yellow-200">
                </div> -->
                
                <div>
                    <label for="starting_location" class="block text-gray-700 mb-2">Starting Location</label>
                    <input type="text" id="starting_location" name="starting_location"
                           value="<?php echo $edit_bus ? htmlspecialchars($edit_bus['starting_location']) : ''; ?>"
                           class="w-full rounded-lg border-gray-300 border p-3 focus:border-yellow-500 focus:ring focus:ring-yellow-200">
                </div>
                
                <div class="md:col-span-2">
                    <label for="covering_cities" class="block text-gray-700 mb-2">Covering Cities</label>
                    <textarea id="covering_cities" name="covering_cities" rows="3"
                              class="w-full rounded-lg border-gray-300 border p-3 focus:border-yellow-500 focus:ring focus:ring-yellow-200"><?php echo $edit_bus ? htmlspecialchars($edit_bus['covering_cities']) : ''; ?></textarea>
                </div>
                
                <div>
                    <label class="flex items-center">
                        <input type="checkbox" name="is_active" class="h-5 w-5 text-yellow-600 rounded focus:ring-yellow-500"
                               <?php echo (!$edit_bus || $edit_bus['is_active']) ? 'checked' : ''; ?>>
                        <span class="ml-2 text-gray-700">Active</span>
                    </label>
                </div>
            </div>
            
            <div class="mt-6 flex space-x-4">
                <button type="submit" name="<?php echo $edit_bus ? 'update_bus' : 'add_bus'; ?>"
                        class="bg-yellow-600 hover:bg-yellow-700 text-white px-6 py-2 rounded-lg transition duration-300">
                    <?php echo $edit_bus ? 'Update Bus' : 'Add Bus'; ?>
                </button>
                <a href="?tab=bus" class="bg-gray-500 hover:bg-gray-600 text-white px-6 py-2 rounded-lg transition duration-300">
                    Cancel
                </a>
            </div>
        </form>
    </div>

    <!-- Buses List -->
    <div class="overflow-x-auto bg-white rounded-lg shadow-md">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Bus Number</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">License Plate</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Capacity</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Starting Location</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                <?php if (empty($buses)): ?>
                <tr>
                    <td colspan="6" class="px-6 py-4 text-center text-gray-500">No buses found.</td>
                </tr>
                <?php else: ?>
                <?php foreach ($buses as $bus): ?>
                <tr class="hover:bg-gray-50">
                    <td class="px-6 py-4 whitespace-nowrap">
                        <div class="font-medium text-gray-900"><?php echo htmlspecialchars($bus['bus_number']); ?></div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-gray-700">
                        <?php echo htmlspecialchars($bus['license_plate']); ?>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-gray-700">
                        <?php echo htmlspecialchars($bus['capacity']); ?> seats
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-gray-700">
                        <?php echo htmlspecialchars($bus['starting_location'] ?: 'N/A'); ?>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <?php if ($bus['is_active']): ?>
                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">
                            Active
                        </span>
                        <?php else: ?>
                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-red-100 text-red-800">
                            Inactive
                        </span>
                        <?php endif; ?>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                        <a href="?tab=bus&edit=<?php echo $bus['bus_id']; ?>" class="text-blue-600 hover:text-blue-900 mr-4">
                            <i class="fas fa-edit"></i> Edit
                        </a>
                        <a href="javascript:void(0);" onclick="confirmDelete(<?php echo $bus['bus_id']; ?>, 'bus')" class="text-red-600 hover:text-red-900">
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
    const addBusBtn = document.getElementById('addBusBtn');
    const busForm = document.getElementById('busForm');
    
    addBusBtn.addEventListener('click', function() {
        busForm.classList.toggle('hidden');
    });
    
    // JavaScript for delete confirmation
    window.confirmDelete = function(busId) {
        if (confirm('Are you sure you want to delete this bus?')) {
            window.location.href = '?tab=bus&delete=' + busId;
        }
    };
});
</script> 