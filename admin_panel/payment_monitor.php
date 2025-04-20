<?php
session_start();
require_once 'db_connection.php';

// Check if user is logged in as admin
if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit;
}

// Get payment statistics
$stats = $pdo->query("
    SELECT 
        COUNT(*) as total_payments,
        SUM(amount) as total_amount,
        (SELECT COUNT(*) FROM child) as unique_children,
        COUNT(CASE WHEN status = 'completed' THEN 1 END) as completed_payments,
        (SELECT COUNT(DISTINCT c.child_id) 
         FROM child c 
         LEFT JOIN payment p ON c.child_id = p.child_id 
            AND MONTH(p.month_covered) = MONTH(NOW())
         WHERE p.payment_id IS NULL OR p.status != 'completed'
        ) as pending_payments
    FROM payment
")->fetch(PDO::FETCH_ASSOC);

// Get monthly payment data for chart
$monthlyData = $pdo->query("
    SELECT 
        DATE_FORMAT(payment_date, '%Y-%m') as month,
        SUM(amount) as total,
        COUNT(*) as count,
        COUNT(CASE WHEN status = 'completed' THEN 1 END) as completed,
        COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending
    FROM payment
    GROUP BY DATE_FORMAT(payment_date, '%Y-%m')
    ORDER BY month DESC
    LIMIT 12
")->fetchAll(PDO::FETCH_ASSOC);

// Get payment status for unpaid children
$paymentQuery = "
    SELECT 
        c.child_id,
        c.first_name,
        c.last_name,
        c.joined_date,
        DATE_FORMAT(NOW(), '%Y-%m-01') as current_month,
        COALESCE(p.status, 'unpaid') as payment_status,
        COALESCE(p.amount, 0) as amount
    FROM child c
    LEFT JOIN payment p ON c.child_id = p.child_id 
        AND MONTH(p.month_covered) = MONTH(NOW())
    WHERE p.payment_id IS NULL 
        OR p.status != 'completed'
    ORDER BY c.first_name";

$paymentStatus = $pdo->query($paymentQuery)->fetchAll(PDO::FETCH_ASSOC);

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Monitor</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap">
    <link rel="icon" type="image/png" href="../img/favicon/favicon-96x96.png" sizes="96x96" />
    <link rel="shortcut icon" href="../img/favicon/favicon.ico" />
    <link rel="icon" type="image/svg+xml" href="../img/favicon/favicon.svg" />
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        body {
            font-family: 'Poppins', sans-serif;
            background: linear-gradient(135deg, #fbbf24 0%, #ea580c 100%);
            min-height: 100vh;
        }
        .glass-container {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 1rem;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
        }
        .stat-card {
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(5px);
            border-radius: 0.75rem;
            transition: transform 0.3s ease;
        }
        .stat-card:hover {
            transform: translateY(-5px);
        }
        .chart-container {
            background: white;
            border-radius: 1rem;
            padding: 1rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
        }
    </style>
</head>
<body>
    <nav class="bg-white/90 backdrop-blur-sm text-gray-800 shadow-lg">
        <div class="container mx-auto px-4 py-3 flex justify-between items-center">
            <div class="flex items-center space-x-4">
                <h1 class="text-xl font-bold text-yellow-900">Payment Monitor</h1>
            </div>
            <div class="flex items-center space-x-6">
                <a href="dashboard.php" class="bg-yellow-900 hover:bg-yellow-700 text-white px-4 py-2 rounded-lg transition duration-300 shadow-md hover:shadow-lg">
                    Dashboard
                </a>
            </div>
        </div>
    </nav>

    <main class="container mx-auto px-4 py-8">
        <!-- Statistics Cards -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
            <div class="stat-card p-6">
                <h3 class="text-lg font-semibold text-gray-600">Total Payments</h3>
                <p class="text-3xl font-bold text-yellow-600">
                    <?php echo number_format($stats['total_payments']); ?>
                </p>
            </div>
            <div class="stat-card p-6">
                <h3 class="text-lg font-semibold text-gray-600">Total Amount</h3>
                <p class="text-3xl font-bold text-green-600">
                    Rs. <?php echo number_format($stats['total_amount'], 2); ?>
                </p>
            </div>
            <div class="stat-card p-6">
                <h3 class="text-lg font-semibold text-gray-600">Active Children</h3>
                <p class="text-3xl font-bold text-blue-600">
                    <?php echo number_format($stats['unique_children']); ?>
                </p>
            </div>
            <div class="stat-card p-6">
                <h3 class="text-lg font-semibold text-gray-600">Pending Payments</h3>
                <p class="text-3xl font-bold text-red-600">
                    <?php echo number_format($stats['pending_payments']); ?>
                </p>
            </div>
        </div>

        <!-- Charts -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
            <div class="glass-container p-6">
                <h3 class="text-xl font-semibold mb-4">Monthly Payment Trends</h3>
                <div class="chart-container">
                    <canvas id="monthlyPaymentsChart"></canvas>
                </div>
            </div>
            <div class="glass-container p-6">
                <h3 class="text-xl font-semibold mb-4">Payment Status Distribution</h3>
                <div class="chart-container">
                    <canvas id="paymentStatusChart"></canvas>
                </div>
            </div>
        </div>

        <!-- Recent Payments Table -->
        <div class="glass-container p-6">
            <h3 class="text-xl font-semibold mb-4">Recent Payments</h3>
            <div class="overflow-x-auto">
                <table class="min-w-full table-auto">
                    <thead>
                        <tr class="bg-gray-50">
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Child</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Amount</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php
                        $recentPayments = $pdo->query("
                            SELECT p.*, c.first_name, c.last_name
                            FROM payment p
                            JOIN child c ON p.child_id = c.child_id
                            ORDER BY payment_date DESC
                            LIMIT 10
                        ")->fetchAll(PDO::FETCH_ASSOC);

                        foreach ($recentPayments as $payment): ?>
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <?php echo htmlspecialchars($payment['first_name'] . ' ' . $payment['last_name']); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    Rs. <?php echo number_format($payment['amount'], 2); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <?php echo date('Y-m-d', strtotime($payment['payment_date'])); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="px-2 py-1 text-xs rounded-full <?php echo $payment['status'] === 'completed' ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800'; ?>">
                                        <?php echo ucfirst($payment['status']); ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Payment Status by Child -->
        <div class="glass-container p-6 mt-8">
            <h3 class="text-xl font-semibold mb-4">Unpaid Payments for Current Month</h3>
            <div class="overflow-x-auto">
                <table class="min-w-full table-auto">
                    <thead>
                        <tr class="bg-gray-50">
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Child Name</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Month</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                            <!-- <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Amount</th> -->
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php if (empty($paymentStatus)): ?>
                            <tr>
                                <td colspan="4" class="px-6 py-4 text-center text-gray-500">
                                    All payments are up to date for this month
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($paymentStatus as $status): ?>
                                <tr class="hover:bg-gray-50">
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <?php echo htmlspecialchars($status['first_name'] . ' ' . $status['last_name']); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <?php echo date('F Y', strtotime($status['current_month'])); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="px-2 py-1 text-xs rounded-full bg-red-100 text-red-800">
                                            Unpaid
                                        </span>
                                    </td>
                                    <!-- <td class="px-6 py-4 whitespace-nowrap">
                                        Rs. <?php echo number_format($status['amount'], 2); ?>
                                    </td> -->
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>

    <script>
        // Monthly Payments Chart
        const monthlyData = <?php echo json_encode($monthlyData); ?>;
        
        new Chart(document.getElementById('monthlyPaymentsChart'), {
            type: 'line',
            data: {
                labels: monthlyData.map(row => row.month),
                datasets: [{
                    label: 'Total Amount',
                    data: monthlyData.map(row => row.total),
                    borderColor: '#ea580c',
                    tension: 0.4
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'top',
                    }
                }
            }
        });

        // Payment Status Chart
        new Chart(document.getElementById('paymentStatusChart'), {
            type: 'doughnut',
            data: {
                labels: ['Completed', 'Pending'],
                datasets: [{
                    data: [
                        <?php echo $stats['completed_payments']; ?>,
                        <?php echo $stats['pending_payments']; ?>
                    ],
                    backgroundColor: ['#22c55e', '#eab308']
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'top',
                    }
                }
            }
        });
    </script>
</body>
</html>
