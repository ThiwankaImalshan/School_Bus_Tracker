<?php
// Initialize variables
$success_message = '';
$error_message = '';
$edit_driver = null;

// Get driver for editing first
if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    $driver_id = (int)$_GET['edit'];
    try {
        $stmt = $pdo->prepare("SELECT * FROM driver WHERE driver_id = ?");
        $stmt->execute([$driver_id]);
        $edit_driver = $stmt->fetch();
    } catch (PDOException $e) {
        $error_message = "Error fetching driver details: " . $e->getMessage();
    }
}

// Now fetch all active buses that don't have drivers assigned
try {
    $bus_query = "SELECT b.bus_id, b.bus_number 
                  FROM bus b 
                  LEFT JOIN driver d ON b.bus_id = d.bus_id 
                  WHERE b.is_active = 1 
                  AND (d.driver_id IS NULL";
    
    // If editing, include the current driver's bus in options
    if ($edit_driver) {
        $bus_query .= " OR b.bus_id = " . (int)$edit_driver['bus_id'];
    }
    
    $bus_query .= ") ORDER BY b.bus_number";
    
    $stmt = $pdo->prepare($bus_query);
    $stmt->execute();
    $buses = $stmt->fetchAll();
} catch (PDOException $e) {
    $error_message = "Error fetching buses: " . $e->getMessage();
    $buses = [];
}

// Hash the password
function hashPassword($password) {
    return password_hash($password, PASSWORD_BCRYPT);
}

// Add new driver
if (isset($_POST['add_driver'])) {
    $full_name = trim($_POST['full_name']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $phone = trim($_POST['phone']);
    $license_number = trim($_POST['license_number']);
    $license_expiry_date = $_POST['license_expiry_date'];
    $experience_years = (int)$_POST['experience_years'];
    $age = (int)$_POST['age'];
    $bus_id = !empty($_POST['bus_id']) ? (int)$_POST['bus_id'] : null;
    $joined_date = date('Y-m-d');
    
    // Add validation before insert
    if ($bus_id) {
        // Check if bus is already assigned to another driver
        $check_stmt = $pdo->prepare("SELECT driver_id FROM driver WHERE bus_id = ? AND driver_id != ?");
        $current_driver_id = 0; // No current driver for add operation
        $check_stmt->execute([$bus_id, $current_driver_id]);
        
        if ($check_stmt->rowCount() > 0) {
            $error_message = "This bus is already assigned to another driver.";
            unset($_POST['add_driver']); // Stop the insert process
        }
    }
    
    if (isset($_POST['add_driver'])) {
        // Hash the password
        $password_hash = hashPassword($password);
        
        try {
            $stmt = $pdo->prepare("INSERT INTO driver (full_name, email, password_hash, phone, license_number, 
                                  license_expiry_date, experience_years, age, bus_id, joined_date) 
                                  VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$full_name, $email, $password_hash, $phone, $license_number, 
                           $license_expiry_date, $experience_years, $age, $bus_id, $joined_date]);
            $success_message = "Driver added successfully!";
        } catch (PDOException $e) {
            $error_message = "Error adding driver: " . $e->getMessage();
        }
    }
}

// Update existing driver
if (isset($_POST['update_driver'])) {
    $driver_id = (int)$_POST['driver_id'];
    $full_name = trim($_POST['full_name']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $license_number = trim($_POST['license_number']);
    $license_expiry_date = $_POST['license_expiry_date'];
    $experience_years = (int)$_POST['experience_years'];
    $age = (int)$_POST['age'];
    $bus_id = !empty($_POST['bus_id']) ? (int)$_POST['bus_id'] : null;
    
    // Add validation before update
    if ($bus_id) {
        // Check if bus is already assigned to another driver
        $check_stmt = $pdo->prepare("SELECT driver_id FROM driver WHERE bus_id = ? AND driver_id != ?");
        $check_stmt->execute([$bus_id, $driver_id]);
        
        if ($check_stmt->rowCount() > 0) {
            $error_message = "This bus is already assigned to another driver.";
            unset($_POST['update_driver']); // Stop the update process
        }
    }
    
    if (isset($_POST['update_driver'])) {
        try {
            // Check if password is being updated
            if (!empty($_POST['password'])) {
                $password_hash = hashPassword($_POST['password']);
                $stmt = $pdo->prepare("UPDATE driver SET full_name = ?, email = ?, password_hash = ?, 
                                      phone = ?, license_number = ?, license_expiry_date = ?, 
                                      experience_years = ?, age = ?, bus_id = ? WHERE driver_id = ?");
                $stmt->execute([$full_name, $email, $password_hash, $phone, $license_number, 
                               $license_expiry_date, $experience_years, $age, $bus_id, $driver_id]);
            } else {
                $stmt = $pdo->prepare("UPDATE driver SET full_name = ?, email = ?, phone = ?, 
                                      license_number = ?, license_expiry_date = ?, experience_years = ?, 
                                      age = ?, bus_id = ? WHERE driver_id = ?");
                $stmt->execute([$full_name, $email, $phone, $license_number, $license_expiry_date, 
                               $experience_years, $age, $bus_id, $driver_id]);
            }
            $success_message = "Driver updated successfully!";
        } catch (PDOException $e) {
            $error_message = "Error updating driver: " . $e->getMessage();
        }
    }
}

// Delete driver
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $driver_id = (int)$_GET['delete'];
    
    try {
        $stmt = $pdo->prepare("DELETE FROM driver WHERE driver_id = ?");
        $stmt->execute([$driver_id]);
        $success_message = "Driver deleted successfully!";
    } catch (PDOException $e) {
        $error_message = "Error deleting driver: " . $e->getMessage();
    }
}

// Fetch all drivers with bus information
try {
    $stmt = $pdo->prepare("SELECT d.*, b.bus_number 
                          FROM driver d 
                          LEFT JOIN bus b ON d.bus_id = b.bus_id 
                          ORDER BY d.full_name");
    $stmt->execute();
    $drivers = $stmt->fetchAll();
} catch (PDOException $e) {
    $error_message = "Error fetching drivers: " . $e->getMessage();
    $drivers = [];
}
?>

<div class="mb-6">
    <div class="flex flex-col sm:flex-row justify-between items-center gap-3 sm:gap-0 mb-4 sm:mb-6">
        <h2 class="text-xl sm:text-2xl font-bold text-gray-800">
            <i class="fas fa-id-card mr-1 sm:mr-2 text-yellow-600"></i>Driver Management
        </h2>
        <button id="addDriverBtn" class="bg-yellow-600 hover:bg-yellow-700 text-white px-3 sm:px-4 py-1 sm:py-2 text-sm sm:text-base rounded-lg transition duration-300 w-full sm:w-auto">
            <i class="fas fa-plus mr-1 sm:mr-2"></i>Add New Driver
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
    <div id="driverForm" class="bg-white rounded-lg shadow-md p-6 mb-8 <?php echo ($edit_driver || isset($_POST['add_driver']) && !empty($error_message)) ? 'block' : 'hidden'; ?>">
        <h3 class="text-xl font-semibold mb-4 text-gray-800">
            <?php echo $edit_driver ? 'Edit Driver' : 'Add New Driver'; ?>
        </h3>
        <form method="POST" action="">
            <?php if ($edit_driver): ?>
            <input type="hidden" name="driver_id" value="<?php echo $edit_driver['driver_id']; ?>">
            <?php endif; ?>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label for="full_name" class="block text-gray-700 mb-2">Full Name</label>
                    <input type="text" id="full_name" name="full_name" required
                           value="<?php echo $edit_driver ? htmlspecialchars($edit_driver['full_name']) : ''; ?>"
                           class="w-full rounded-lg border-gray-300 border p-3 focus:border-yellow-500 focus:ring focus:ring-yellow-200">
                </div>
                
                <div>
                    <label for="email" class="block text-gray-700 mb-2">Email</label>
                    <input type="email" id="email" name="email" required
                           value="<?php echo $edit_driver ? htmlspecialchars($edit_driver['email']) : ''; ?>"
                           class="w-full rounded-lg border-gray-300 border p-3 focus:border-yellow-500 focus:ring focus:ring-yellow-200">
                </div>
                
                <div>
                    <label for="password" class="block text-gray-700 mb-2">
                        <?php echo $edit_driver ? 'New Password (leave blank to keep current)' : 'Password'; ?>
                    </label>
                    <input type="password" id="password" name="password" 
                           <?php echo !$edit_driver ? 'required' : ''; ?>
                           class="w-full rounded-lg border-gray-300 border p-3 focus:border-yellow-500 focus:ring focus:ring-yellow-200">
                </div>
                
                <div>
                    <label for="phone" class="block text-gray-700 mb-2">Phone</label>
                    <input type="text" id="phone" name="phone" required
                           value="<?php echo $edit_driver ? htmlspecialchars($edit_driver['phone']) : ''; ?>"
                           class="w-full rounded-lg border-gray-300 border p-3 focus:border-yellow-500 focus:ring focus:ring-yellow-200">
                </div>
                
                <div>
                    <label for="license_number" class="block text-gray-700 mb-2">License Number</label>
                    <input type="text" id="license_number" name="license_number" required
                           value="<?php echo $edit_driver ? htmlspecialchars($edit_driver['license_number']) : ''; ?>"
                           class="w-full rounded-lg border-gray-300 border p-3 focus:border-yellow-500 focus:ring focus:ring-yellow-200">
                </div>
                
                <div>
                    <label for="license_expiry_date" class="block text-gray-700 mb-2">License Expiry Date</label>
                    <input type="date" id="license_expiry_date" name="license_expiry_date" required
                           value="<?php echo $edit_driver ? htmlspecialchars($edit_driver['license_expiry_date']) : ''; ?>"
                           class="w-full rounded-lg border-gray-300 border p-3 focus:border-yellow-500 focus:ring focus:ring-yellow-200">
                </div>
                
                <div>
                    <label for="experience_years" class="block text-gray-700 mb-2">Experience (Years)</label>
                    <input type="number" id="experience_years" name="experience_years" min="0" max="50"
                           value="<?php echo $edit_driver ? htmlspecialchars($edit_driver['experience_years']) : '0'; ?>"
                           class="w-full rounded-lg border-gray-300 border p-3 focus:border-yellow-500 focus:ring focus:ring-yellow-200">
                </div>
                
                <div>
                    <label for="age" class="block text-gray-700 mb-2">Age</label>
                    <input type="number" id="age" name="age" min="18" max="75"
                           value="<?php echo $edit_driver ? htmlspecialchars($edit_driver['age']) : ''; ?>"
                           class="w-full rounded-lg border-gray-300 border p-3 focus:border-yellow-500 focus:ring focus:ring-yellow-200">
                </div>
                
                <div>
                    <label for="bus_id" class="block text-gray-700 mb-2">Assigned Bus</label>
                    <select id="bus_id" name="bus_id" class="w-full rounded-lg border-gray-300 border p-3 focus:border-yellow-500 focus:ring focus:ring-yellow-200">
                        <option value="">-- Select Bus --</option>
                        <?php foreach ($buses as $bus): ?>
                        <option value="<?php echo $bus['bus_id']; ?>" 
                                <?php echo ($edit_driver && $edit_driver['bus_id'] == $bus['bus_id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($bus['bus_number']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            
            <div class="mt-6 flex space-x-4">
                <button type="submit" name="<?php echo $edit_driver ? 'update_driver' : 'add_driver'; ?>"
                        class="bg-yellow-600 hover:bg-yellow-700 text-white px-6 py-2 rounded-lg transition duration-300">
                    <?php echo $edit_driver ? 'Update Driver' : 'Add Driver'; ?>
                </button>
                <a href="?tab=driver" class="bg-gray-500 hover:bg-gray-600 text-white px-6 py-2 rounded-lg transition duration-300">
                    Cancel
                </a>
            </div>
        </form>
    </div>

    <!-- Drivers List -->
    <div class="overflow-x-auto bg-white rounded-lg shadow-md">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Driver</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Contact</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">License</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Experience</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Assigned Bus</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                <?php if (empty($drivers)): ?>
                <tr>
                    <td colspan="6" class="px-6 py-4 text-center text-gray-500">No drivers found.</td>
                </tr>
                <?php else: ?>
                <?php foreach ($drivers as $driver): ?>
                <tr class="hover:bg-gray-50">
                    <td class="px-6 py-4">
                        <div class="font-medium text-gray-900"><?php echo htmlspecialchars($driver['full_name']); ?></div>
                        <div class="text-sm text-gray-500">Age: <?php echo htmlspecialchars($driver['age'] ?: 'N/A'); ?></div>
                    </td>
                    <td class="px-6 py-4">
                        <div class="text-sm text-gray-900"><?php echo htmlspecialchars($driver['phone']); ?></div>
                        <div class="text-sm text-gray-500"><?php echo htmlspecialchars($driver['email']); ?></div>
                    </td>
                    <td class="px-6 py-4">
                        <div class="text-sm text-gray-900"><?php echo htmlspecialchars($driver['license_number']); ?></div>
                        <div class="text-sm text-gray-500">
                            Expires: <?php echo date('d M Y', strtotime($driver['license_expiry_date'])); ?>
                        </div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700">
                        <?php echo htmlspecialchars($driver['experience_years'] ?: '0'); ?> years
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700">
                        <?php echo $driver['bus_number'] ? htmlspecialchars($driver['bus_number']) : 'Not Assigned'; ?>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                        <a href="?tab=driver&edit=<?php echo $driver['driver_id']; ?>" class="text-blue-600 hover:text-blue-900 mr-4">
                            <i class="fas fa-edit"></i> Edit
                        </a>
                        <a href="javascript:void(0);" onclick="confirmDelete(<?php echo $driver['driver_id']; ?>, 'driver')" class="text-red-600 hover:text-red-900">
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
    const addDriverBtn = document.getElementById('addDriverBtn');
    const driverForm = document.getElementById('driverForm');
    
    addDriverBtn.addEventListener('click', function() {
        // Clear all form fields
        const form = driverForm.querySelector('form');
        form.reset();
        
        // Remove any previous driver_id hidden input
        const oldDriverId = form.querySelector('input[name="driver_id"]');
        if (oldDriverId) {
            oldDriverId.remove();
        }
        
        // Update form title to "Add New Driver"
        driverForm.querySelector('h3').textContent = 'Add New Driver';
        
        // Show the form
        driverForm.classList.remove('hidden');
        
        // Make password field required
        document.getElementById('password').required = true;
        
        // Reset bus selection
        document.getElementById('bus_id').selectedIndex = 0;
    });
    
    // JavaScript for delete confirmation
    window.confirmDelete = function(driverId) {
        if (confirm('Are you sure you want to delete this driver?')) {
            window.location.href = '?tab=driver&delete=' + driverId;
        }
    };
});
</script>