<?php
// Database connection
$host = 'localhost';
$dbname = 'school_bus_management';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Modify the SQL query to get the correct school_id
    $stmt = $pdo->prepare("
        SELECT c.*, c.school_id as child_school_id, s.name as school_name, b.bus_number 
        FROM child c
        LEFT JOIN school s ON c.school_id = s.school_id
        LEFT JOIN bus b ON c.bus_id = b.bus_id
        ORDER BY c.first_name
    ");
    $stmt->execute();
    $children = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch(PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Generate Student Card</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <style>
        /* Custom styles for background effects */
    </style>
</head>
<body class="bg-gradient-to-b from-yellow-50 to-orange-50 min-h-screen p-4">
    <div class="fixed -z-10 top-0 left-0 w-full h-full opacity-50">
        <div class="absolute top-10 left-10 w-32 h-32 rounded-full bg-blue-100 blur-3xl"></div>
        <div class="absolute bottom-10 right-10 w-40 h-40 rounded-full bg-orange-100 blur-3xl"></div>
    </div>

    <main class="container mx-auto py-6 md:py-10 relative">
        <div class="max-w-4xl mx-auto">
            <div class="flex flex-col md:flex-row items-start md:items-center justify-between mb-8 gap-4">
                <div class="flex items-center space-x-3">
                    <div class="h-10 w-1 bg-orange-500 rounded-full"></div>
                    <h2 class="text-3xl font-bold text-orange-800">Generate Student Card</h2>
                </div>
                <a href="../dashboard.php" class="bg-yellow-500 text-white px-4 py-2 rounded-full hover:bg-yellow-600 transition-colors flex items-center space-x-2">
                    <i class="fas fa-arrow-left"></i>
                    <span>Back to Dashboard</span>
                </a>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                <?php foreach($children as $child): ?>
                    <div class="bg-white rounded-xl shadow-lg overflow-hidden border border-orange-100">
                        <div class="p-4">
                            <div class="flex flex-col items-center">
                                <?php 
                                $hasPhoto = !empty($child['photo_url']);
                                $photoPath = $hasPhoto ? "../../img/child/" . $child['photo_url'] : "../../img/default-avatar.png";
                                ?>
                                <div class="relative group">
                                    <img src="<?php echo htmlspecialchars($photoPath); ?>" 
                                         alt="<?php echo htmlspecialchars($child['first_name']); ?>'s photo"
                                         class="w-32 h-32 rounded-full object-cover border-4 border-orange-200"
                                         onerror="this.src='../../img/default-avatar.png'">
                                    
                                    <?php if (!$hasPhoto): ?>
                                    <div class="absolute inset-0 bg-black bg-opacity-50 rounded-full flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity cursor-pointer"
                                         onclick="openPhotoUpload(<?php echo $child['child_id']; ?>)">
                                        <span class="text-white text-sm">Add Photo</span>
                                    </div>
                                    <input type="file" 
                                           id="photo_upload_<?php echo $child['child_id']; ?>"
                                           class="hidden"
                                           accept="image/*"
                                           onchange="uploadPhoto(<?php echo $child['child_id']; ?>, this)">
                                    <?php endif; ?>
                                </div>

                                <h3 class="text-lg font-semibold text-gray-800">
                                    <?php echo htmlspecialchars($child['first_name'] . ' ' . $child['last_name']); ?>
                                </h3>
                                <p class="text-sm text-gray-500">
                                    Grade <?php echo htmlspecialchars($child['grade']); ?>
                                </p>
                                <p class="text-sm text-gray-500">
                                    <?php echo htmlspecialchars($child['school_name'] ?? 'No School Assigned'); ?>
                                </p>
                            </div>

                            <div class="mt-4 flex justify-center">
                                <button onclick="generateCard(<?php echo htmlspecialchars(json_encode($child)); ?>)"
                                        class="bg-yellow-500 text-white px-6 py-2 rounded-lg hover:bg-yellow-600 transition-colors">
                                    Generate Card
                                </button>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </main>

    <script>
        function openPhotoUpload(childId) {
            document.getElementById(`photo_upload_${childId}`).click();
        }

        function uploadPhoto(childId, input) {
            if (!input.files || !input.files[0]) return;
            
            const formData = new FormData();
            formData.append('photo', input.files[0]);
            formData.append('child_id', childId);
            
            fetch('upload_photo.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    window.location.reload();
                } else {
                    alert('Failed to upload photo: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Failed to upload photo');
            });
        }

        function generateCard(childData) {
            const queryParams = new URLSearchParams({
                child_id: childData.child_id,
                first_name: childData.first_name,
                last_name: childData.last_name,
                grade: childData.grade || '',
                school: childData.school_name || '',
                school_id: childData.child_school_id, // Use child_school_id instead
                bus: childData.bus_number || '',
                photo: childData.photo_url || ''
            });

            window.open(`card.html?${queryParams.toString()}`, '_blank');
        }
    </script>
</body>
</html>
