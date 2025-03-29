<?php
// Database connection parameters
$host = "localhost";
$username = "root";
$password = "";
$database = "school_bus_management";

// Create database connection
$conn = new mysqli($host, $username, $password, $database);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Get 5 most recently active parents
$query = "
    SELECT p.parent_id, p.full_name, p.last_login
    FROM parent p
    WHERE p.last_login IS NOT NULL
    ORDER BY p.last_login DESC
    LIMIT 5
";

$result = $conn->query($query);

// Prepare data array
$parents = [];
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $parents[] = [
            'id' => $row['parent_id'],
            'name' => $row['full_name'],
            'last_login' => $row['last_login']
        ];
    }
}

// Get children for each parent
foreach ($parents as &$parent) {
    $childQuery = "
        SELECT c.first_name, c.last_name
        FROM child c
        WHERE c.parent_id = {$parent['id']}
    ";
    
    $childResult = $conn->query($childQuery);
    $children = [];
    
    if ($childResult && $childResult->num_rows > 0) {
        while ($childRow = $childResult->fetch_assoc()) {
            $children[] = $childRow['first_name'] . " " . substr($childRow['last_name'], 0, 1) . ".";
        }
    }
    
    $parent['children'] = $children;
}

// Close connection
$conn->close();

// Calculate active time ago
function timeAgo($datetime) {
    $now = new DateTime();
    $past = new DateTime($datetime);
    $diff = $now->diff($past);
    
    if ($diff->y > 0) {
        return $diff->y . ' year' . ($diff->y > 1 ? 's' : '') . ' ago';
    }
    if ($diff->m > 0) {
        return $diff->m . ' month' . ($diff->m > 1 ? 's' : '') . ' ago';
    }
    if ($diff->d > 0) {
        return $diff->d . ' day' . ($diff->d > 1 ? 's' : '') . ' ago';
    }
    if ($diff->h > 0) {
        return $diff->h . ' hour' . ($diff->h > 1 ? 's' : '') . ' ago';
    }
    if ($diff->i > 0) {
        return $diff->i . ' minute' . ($diff->i > 1 ? 's' : '') . ' ago';
    }
    return 'just now';
}

// Background colors for parent icons
$bgColors = ['blue', 'purple', 'yellow', 'green', 'red'];
?>

<?php foreach ($parents as $index => $parent): ?>
    <?php $colorIndex = $index % count($bgColors); ?>
    <div class="border border-gray-100 rounded-xl p-4">
        <div class="flex items-center mb-2">
            <div class="w-10 h-10 bg-<?php echo $bgColors[$colorIndex]; ?>-100 rounded-full flex items-center justify-center mr-3">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-<?php echo $bgColors[$colorIndex]; ?>-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                </svg>
            </div>
            <h5 class="text-md font-medium text-gray-800"><?php echo htmlspecialchars($parent['name']); ?></h5>
        </div>
        <div class="pl-12 space-y-1">
            <p class="text-xs text-gray-600">Last active: <?php echo timeAgo($parent['last_login']); ?></p>
            <div class="flex">
                <span class="w-24 text-xs font-medium text-gray-500">Children:</span>
                <span class="flex-1 text-xs text-gray-800"><?php echo htmlspecialchars(implode(', ', $parent['children'])); ?></span>
            </div>
        </div>
    </div>
<?php endforeach; ?>

<?php if (count($parents) === 0): ?>
    <div class="text-center p-4">
        <p class="text-gray-500">No parent activity found.</p>
    </div>
<?php endif; ?>