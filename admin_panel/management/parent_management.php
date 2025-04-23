<?php
// Process form submissions for parent management
$success_message = '';
$error_message = '';

// Hash the password
function hashPassword($password) {
    return password_hash($password, PASSWORD_BCRYPT);
}

// Add new parent
if (isset($_POST['add_parent'])) {
    $full_name = trim($_POST['full_name']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $phone = trim($_POST['phone']);
    $home_address = trim($_POST['home_address']);
    
    // Hash the password
    $password_hash = hashPassword($password);
    
    try {
        $stmt = $pdo->prepare("INSERT INTO parent (full_name, email, password_hash, phone, home_address) 
                              VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$full_name, $email, $password_hash, $phone, $home_address]);
        $success_message = "Parent added successfully!";
    } catch (PDOException $e) {
        $error_message = "Error adding parent: " . $e->getMessage();
    }
}

// Update existing parent
if (isset($_POST['update_parent'])) {
    $parent_id = (int)$_POST['parent_id'];
    $full_name = trim($_POST['full_name']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $home_address = trim($_POST['home_address']);
    
    try {
        // Check if password is being updated
        if (!empty($_POST['password'])) {
            $password_hash = hashPassword($_POST['password']);
            $stmt = $pdo->prepare("UPDATE parent SET full_name = ?, email = ?, password_hash = ?, 
                                  phone = ?, home_address = ? WHERE parent_id = ?");
            $stmt->execute([$full_name, $email, $password_hash, $phone, $home_address, $parent_id]);
        } else {
            $stmt = $pdo->prepare("UPDATE parent SET full_name = ?, email = ?, phone = ?, 
                                  home_address = ? WHERE parent_id = ?");
            $stmt->execute([$full_name, $email, $phone, $home_address, $parent_id]);
        }
        $success_message = "Parent updated successfully!";
    } catch (PDOException $e) {
        $error_message = "Error updating parent: " . $e->getMessage();
    }
}

// Delete parent
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $parent_id = (int)$_GET['delete'];
    
    try {
        // Check if parent has children
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM child WHERE parent_id = ?");
        $stmt->execute([$parent_id]);
        $child_count = $stmt->fetchColumn();
        
        if ($child_count > 0) {
            $error_message = "Cannot delete parent: They have associated children. Remove them first.";
        } else {
            $stmt = $pdo->prepare("DELETE FROM parent WHERE parent_id = ?");
            $stmt->execute([$parent_id]);
            $success_message = "Parent deleted successfully!";
        }
    } catch (PDOException $e) {
        $error_message = "Error deleting parent: " . $e->getMessage();
    }
}

// Get parent for editing
$edit_parent = null;
if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    $parent_id = (int)$_GET['edit'];
    
    try {
        $stmt = $pdo->prepare("SELECT * FROM parent WHERE parent_id = ?");
        $stmt->execute([$parent_id]);
        $edit_parent = $stmt->fetch();
    } catch (PDOException $e) {
        $error_message = "Error fetching parent details: " . $e->getMessage();
    }
}

// Fetch all parents
try {
    // Get parents with their child count
    $stmt = $pdo->prepare("SELECT p.*, COUNT(c.child_id) as child_count 
                          FROM parent p 
                          LEFT JOIN child c ON p.parent_id = c.parent_id 
                          GROUP BY p.parent_id 
                          ORDER BY p.full_name");
    $stmt->execute();
    $parents = $stmt->fetchAll();
} catch (PDOException $e) {
    $error_message = "Error fetching parents: " . $e->getMessage();
    $parents = [];
}
?>

<div class="mb-6">
    <div class="flex flex-col sm:flex-row justify-between items-center gap-3 sm:gap-0 mb-4 sm:mb-6">
        <h2 class="text-xl sm:text-2xl font-bold text-gray-800">
            <i class="fas fa-user-friends mr-1 sm:mr-2 text-yellow-600"></i>Parent Management
        </h2>
        <button id="addParentBtn" class="bg-yellow-600 hover:bg-yellow-700 text-white px-3 sm:px-4 py-1 sm:py-2 text-sm sm:text-base rounded-lg transition duration-300 w-full sm:w-auto">
            <i class="fas fa-plus mr-1 sm:mr-2"></i>Add New Parent
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
    <div id="parentForm" class="bg-white rounded-lg shadow-md p-6 mb-8 <?php echo ($edit_parent || isset($_POST['add_parent']) && !empty($error_message)) ? 'block' : 'hidden'; ?>">
        <h3 class="text-xl font-semibold mb-4 text-gray-800">
            <?php echo $edit_parent ? 'Edit Parent' : 'Add New Parent'; ?>
        </h3>
        <form method="POST" action="">
            <?php if ($edit_parent): ?>
            <input type="hidden" name="parent_id" value="<?php echo $edit_parent['parent_id']; ?>">
            <?php endif; ?>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label for="full_name" class="block text-gray-700 mb-2">Full Name</label>
                    <input type="text" id="full_name" name="full_name" required
                           value="<?php echo $edit_parent ? htmlspecialchars($edit_parent['full_name']) : ''; ?>"
                           class="w-full rounded-lg border-gray-300 border p-3 focus:border-yellow-500 focus:ring focus:ring-yellow-200">
                </div>
                
                <div>
                    <label for="email" class="block text-gray-700 mb-2">Email</label>
                    <input type="email" id="email" name="email" required
                           value="<?php echo $edit_parent ? htmlspecialchars($edit_parent['email']) : ''; ?>"
                           class="w-full rounded-lg border-gray-300 border p-3 focus:border-yellow-500 focus:ring focus:ring-yellow-200">
                </div>
                
                <div>
                    <label for="password" class="block text-gray-700 mb-2">
                        <?php echo $edit_parent ? 'New Password (leave blank to keep current)' : 'Password'; ?>
                    </label>
                    <input type="password" id="password" name="password" 
                           <?php echo !$edit_parent ? 'required' : ''; ?>
                           class="w-full rounded-lg border-gray-300 border p-3 focus:border-yellow-500 focus:ring focus:ring-yellow-200">
                </div>
                
                <div>
                    <label for="phone" class="block text-gray-700 mb-2">Phone</label>
                    <input type="text" id="phone" name="phone" required
                           value="<?php echo $edit_parent ? htmlspecialchars($edit_parent['phone']) : ''; ?>"
                           class="w-full rounded-lg border-gray-300 border p-3 focus:border-yellow-500 focus:ring focus:ring-yellow-200">
                </div>
                
                <div class="md:col-span-2">
                    <label for="home_address" class="block text-gray-700 mb-2">Home Address</label>
                    <textarea id="home_address" name="home_address" rows="3" required
                              class="w-full rounded-lg border-gray-300 border p-3 focus:border-yellow-500 focus:ring focus:ring-yellow-200"><?php echo $edit_parent ? htmlspecialchars($edit_parent['home_address']) : ''; ?></textarea>
                </div>
            </div>
            
            <div class="mt-6 flex space-x-4">
                <button type="submit" name="<?php echo $edit_parent ? 'update_parent' : 'add_parent'; ?>"
                        class="bg-yellow-600 hover:bg-yellow-700 text-white px-6 py-2 rounded-lg transition duration-300">
                    <?php echo $edit_parent ? 'Update Parent' : 'Add Parent'; ?>
                </button>
                <a href="?tab=parent" class="bg-gray-500 hover:bg-gray-600 text-white px-6 py-2 rounded-lg transition duration-300">
                    Cancel
                </a>
            </div>
        </form>
    </div>

    <!-- Parents List -->
    <div class="overflow-x-auto bg-white rounded-lg shadow-md">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Parent</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Contact</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Address</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Children</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Registered</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                <?php if (empty($parents)): ?>
                <tr>
                    <td colspan="6" class="px-6 py-4 text-center text-gray-500">No parents found.</td>
                </tr>
                <?php else: ?>
                <?php foreach ($parents as $parent): ?>
                <tr class="hover:bg-gray-50">
                    <td class="px-6 py-4">
                        <div class="font-medium text-gray-900"><?php echo htmlspecialchars($parent['full_name']); ?></div>
                    </td>
                    <td class="px-6 py-4">
                        <div class="text-sm text-gray-900"><?php echo htmlspecialchars($parent['phone']); ?></div>
                        <div class="text-sm text-gray-500"><?php echo htmlspecialchars($parent['email']); ?></div>
                    </td>
                    <td class="px-6 py-4 max-w-xs">
                        <div class="text-sm text-gray-700 truncate"><?php echo htmlspecialchars($parent['home_address']); ?></div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700">
                        <?php echo $parent['child_count']; ?> children
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700">
                        <?php 
                        echo $parent['created_at'] ? date('d M Y', strtotime($parent['created_at'])) : 'N/A';
                        ?>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                        <a href="?tab=parent&edit=<?php echo $parent['parent_id']; ?>" class="text-blue-600 hover:text-blue-900 mr-4">
                            <i class="fas fa-edit"></i> Edit
                        </a>
                        <a href="javascript:void(0);" onclick="confirmDelete(<?php echo $parent['parent_id']; ?>, 'parent')" class="text-red-600 hover:text-red-900">
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
    const addParentBtn = document.getElementById('addParentBtn');
    const parentForm = document.getElementById('parentForm');
    
    addParentBtn.addEventListener('click', function() {
        parentForm.classList.toggle('hidden');
    });
    
    // JavaScript for delete confirmation
    window.confirmDelete = function(parentId) {
        if (confirm('Are you sure you want to delete this parent?')) {
            window.location.href = '?tab=parent&delete=' + parentId;
        }
    };
});
</script> 