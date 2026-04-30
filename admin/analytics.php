<?php
session_start();
require '../config.php';

if(!isset($_SESSION['user_id']) || $_SESSION['user_type'] != 'admin'){
    header("Location: /water_refill_project/login.php");
    exit;
}

// Sales trends - daily for last 30 days
$salesTrend = $conn->query("
    SELECT DATE(created_at) as order_date, COUNT(*) as order_count, SUM(gallons) as total_gallons
    FROM tb_orders
    WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
    GROUP BY DATE(created_at)
    ORDER BY order_date ASC
")->fetchAll(PDO::FETCH_ASSOC);

// Monthly sales for last 6 months
$monthlySales = $conn->query("
    SELECT DATE_FORMAT(created_at, '%Y-%m') as month, COUNT(*) as order_count, SUM(gallons) as total_gallons
    FROM tb_orders
    WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
    GROUP BY DATE_FORMAT(created_at, '%Y-%m')
    ORDER BY month ASC
")->fetchAll(PDO::FETCH_ASSOC);

// Top barangays by customer count
$topBarangaysCustomers = $conn->query("
    SELECT brgy, COUNT(*) as customer_count
    FROM tb_users
    WHERE user_type = 'customer' AND brgy IS NOT NULL AND brgy != ''
    GROUP BY brgy
    ORDER BY customer_count DESC
    LIMIT 10
")->fetchAll(PDO::FETCH_ASSOC);

// Top barangays by order volume
$topBarangaysOrders = $conn->query("
    SELECT u.brgy, COUNT(o.order_id) as order_count, SUM(o.gallons) as total_gallons
    FROM tb_orders o
    JOIN tb_users u ON o.user_id = u.id
    WHERE u.brgy IS NOT NULL AND u.brgy != ''
    GROUP BY u.brgy
    ORDER BY order_count DESC
    LIMIT 10
")->fetchAll(PDO::FETCH_ASSOC);

// Order type distribution
$orderTypeDist = $conn->query("
    SELECT order_type, COUNT(*) as count
    FROM tb_orders
    GROUP BY order_type
")->fetchAll(PDO::FETCH_ASSOC);

// Container type distribution
$containerTypeDist = $conn->query("
    SELECT container_type, COUNT(*) as count
    FROM tb_orders
    WHERE container_type IS NOT NULL AND container_type != ''
    GROUP BY container_type
")->fetchAll(PDO::FETCH_ASSOC);

// Order status distribution
$statusDist = $conn->query("
    SELECT order_status, COUNT(*) as count
    FROM tb_orders
    GROUP BY order_status
")->fetchAll(PDO::FETCH_ASSOC);

// Total revenue (walk-in = 20 per gallon, delivery = 20 per gallon + shipping)
$revenueData = $conn->query("
    SELECT 
        SUM(CASE WHEN order_type = 'walk-in' THEN gallons * 20 ELSE 0 END) as walkin_revenue,
        SUM(CASE WHEN order_type = 'delivery' THEN gallons * 20 ELSE 0 END) as delivery_gallon_revenue,
        COUNT(CASE WHEN order_type = 'delivery' THEN 1 END) as delivery_count
    FROM tb_orders
    WHERE is_reward = 0
")->fetch(PDO::FETCH_ASSOC);

$shippingFee = (int)$conn->query("SELECT setting_value FROM tb_settings WHERE setting_key = 'shipping_fee'")->fetchColumn();
$totalRevenue = ($revenueData['walkin_revenue'] ?? 0) + ($revenueData['delivery_gallon_revenue'] ?? 0) + (($revenueData['delivery_count'] ?? 0) * $shippingFee);

// Total orders and gallons
$totals = $conn->query("
    SELECT COUNT(*) as total_orders, SUM(gallons) as total_gallons, COUNT(DISTINCT user_id) as unique_customers
    FROM tb_orders
")->fetch(PDO::FETCH_ASSOC);

// Demand forecasting - simple linear projection based on last 7 days avg
$last7Days = $conn->query("
    SELECT COUNT(*) as order_count, SUM(gallons) as total_gallons
    FROM tb_orders
    WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
")->fetch(PDO::FETCH_ASSOC);

$last30Days = $conn->query("
    SELECT COUNT(*) as order_count, SUM(gallons) as total_gallons
    FROM tb_orders
    WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
")->fetch(PDO::FETCH_ASSOC);

$dailyAvg7 = $last7Days['order_count'] / 7;
$dailyAvg30 = $last30Days['order_count'] / 30;
$projectedNext7Days = round($dailyAvg7 * 7);
$projectedNext30Days = round($dailyAvg30 * 30);

// Top customers
$topCustomers = $conn->query("
    SELECT u.full_name, COUNT(o.order_id) as order_count, SUM(o.gallons) as total_gallons
    FROM tb_orders o
    JOIN tb_users u ON o.user_id = u.id
    GROUP BY o.user_id, u.full_name
    ORDER BY total_gallons DESC
    LIMIT 10
")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Analytics & Forecasting - Water Refill</title>
<link rel="stylesheet" href="/water_refill_project/css/style.css">
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<style>
.page-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 10px;
    margin-bottom: 18px;
}
.page-header h1 { margin: 0; }
.btn-back {
    background: #1e88e5;
    color: #fff;
    padding: 8px 16px;
    border-radius: 8px;
    text-decoration: none;
    font-size: 0.88rem;
    font-weight: 600;
    box-shadow: 0 2px 8px rgba(0,0,0,0.12);
    transition: background 0.2s;
    white-space: nowrap;
}
.btn-back:hover { background: #1565c0; }

.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
    gap: 14px;
    margin-bottom: 24px;
}
.stat-card {
    background: linear-gradient(135deg, #1e88e5, #1565c0);
    color: #fff;
    padding: 18px;
    border-radius: 12px;
    text-align: center;
    box-shadow: 0 4px 12px rgba(30,136,229,0.25);
}
.stat-card h3 {
    margin: 0 0 6px;
    font-size: 0.8rem;
    font-weight: 500;
    opacity: 0.9;
}
.stat-card .value {
    font-size: 1.6rem;
    font-weight: 700;
}
.stat-card.revenue {
    background: linear-gradient(135deg, #0097a7, #006064);
    box-shadow: 0 4px 12px rgba(0,151,167,0.25);
}
.stat-card.forecast {
    background: linear-gradient(135deg, #f57f17, #e65100);
    box-shadow: 0 4px 12px rgba(245,127,23,0.25);
}
.stat-card.customers {
    background: linear-gradient(135deg, #7cb342, #558b2f);
    box-shadow: 0 4px 12px rgba(124,179,66,0.25);
}

.chart-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(380px, 1fr));
    gap: 20px;
    margin-bottom: 24px;
}
.chart-box {
    background: #fff;
    border-radius: 12px;
    padding: 18px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.06);
}
.chart-box h3 {
    margin: 0 0 14px;
    font-size: 0.95rem;
    color: #1e3a5f;
    font-weight: 600;
}
.chart-canvas {
    max-height: 280px;
}

.table-wrapper {
    background: #fff;
    border-radius: 12px;
    padding: 18px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.06);
    margin-bottom: 20px;
}
.table-wrapper h3 {
    margin: 0 0 14px;
    font-size: 0.95rem;
    color: #1e3a5f;
    font-weight: 600;
}
.data-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.85rem;
}
.data-table th {
    background: #1e88e5;
    color: #fff;
    padding: 10px 12px;
    text-align: left;
    font-weight: 600;
}
.data-table td {
    padding: 10px 12px;
    border-bottom: 1px solid #e3f2fd;
}
.data-table tr:nth-child(even) {
    background: #f0f7ff;
}
.data-table tr:hover {
    background: #dceeff;
}

.forecast-box {
    background: #fff3e0;
    border-left: 4px solid #f57f17;
    border-radius: 8px;
    padding: 14px 18px;
    margin-bottom: 20px;
}
.forecast-box h4 {
    margin: 0 0 8px;
    color: #e65100;
    font-size: 0.9rem;
}
.forecast-box p {
    margin: 0;
    font-size: 0.85rem;
    color: #555;
}
</style>
</head>
<body>

<div class="dashboard">

    <div class="page-header">
        <h1>📊 Data Analytics & Demand Forecasting</h1>
        <a href="/water_refill_project/admin/admin_dashboard.php" class="btn-back">⬅ Back to Dashboard</a>
    </div>

    <!-- Key Stats -->
    <div class="stats-grid">
        <div class="stat-card">
            <h3>Total Orders</h3>
            <div class="value"><?php echo number_format($totals['total_orders']); ?></div>
        </div>
        <div class="stat-card customers">
            <h3>Unique Customers</h3>
            <div class="value"><?php echo number_format($totals['unique_customers']); ?></div>
        </div>
        <div class="stat-card">
            <h3>Total Gallons</h3>
            <div class="value"><?php echo number_format($totals['total_gallons']); ?></div>
        </div>
        <div class="stat-card revenue">
            <h3>Est. Revenue</h3>
            <div class="value">₱<?php echo number_format($totalRevenue); ?></div>
        </div>
        <div class="stat-card forecast">
            <h3>Projected (7 Days)</h3>
            <div class="value"><?php echo number_format($projectedNext7Days); ?> orders</div>
        </div>
        <div class="stat-card forecast">
            <h3>Projected (30 Days)</h3>
            <div class="value"><?php echo number_format($projectedNext30Days); ?> orders</div>
        </div>
    </div>

    <!-- Forecasting Insight -->
    <div class="forecast-box">
        <h4>🔮 Demand Forecasting Insight</h4>
        <p>
            Based on the last 7 days average (<?php echo round($dailyAvg7, 1); ?> orders/day) 
            and last 30 days average (<?php echo round($dailyAvg30, 1); ?> orders/day), 
            projected demand for the next week is <strong><?php echo number_format($projectedNext7Days); ?> orders</strong>.
            <?php if($dailyAvg7 > $dailyAvg30): ?>
                Recent trend shows <strong>increasing demand</strong> compared to monthly average.
            <?php elseif($dailyAvg7 < $dailyAvg30): ?>
                Recent trend shows <strong>decreasing demand</strong> compared to monthly average.
            <?php else: ?>
                Demand is <strong>stable</strong> and consistent with monthly average.
            <?php endif; ?>
        </p>
    </div>

    <!-- Charts Row 1 -->
    <div class="chart-grid">
        <div class="chart-box">
            <h3>📈 Daily Sales Trend (Last 30 Days)</h3>
            <canvas id="dailySalesChart" class="chart-canvas"></canvas>
        </div>
        <div class="chart-box">
            <h3>📊 Monthly Sales Trend (Last 6 Months)</h3>
            <canvas id="monthlySalesChart" class="chart-canvas"></canvas>
        </div>
    </div>

    <!-- Charts Row 2 -->
    <div class="chart-grid">
        <div class="chart-box">
            <h3>🏘️ Top Barangays by Order Volume</h3>
            <canvas id="barangayOrdersChart" class="chart-canvas"></canvas>
        </div>
        <div class="chart-box">
            <h3>📦 Order Type Distribution</h3>
            <canvas id="orderTypeChart" class="chart-canvas"></canvas>
        </div>
    </div>

    <!-- Charts Row 3 -->
    <div class="chart-grid">
        <div class="chart-box">
            <h3>🫙 Container Type Distribution</h3>
            <canvas id="containerTypeChart" class="chart-canvas"></canvas>
        </div>
        <div class="chart-box">
            <h3>📋 Order Status Breakdown</h3>
            <canvas id="statusChart" class="chart-canvas"></canvas>
        </div>
    </div>

    <!-- Top Barangays by Customers -->
    <div class="table-wrapper">
        <h3>🏘️ Top Barangays by Customer Count</h3>
        <table class="data-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Barangay</th>
                    <th>Customers</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach($topBarangaysCustomers as $i => $row): ?>
                <tr>
                    <td><?php echo $i + 1; ?></td>
                    <td><?php echo htmlspecialchars($row['brgy']); ?></td>
                    <td><?php echo number_format($row['customer_count']); ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- Top Customers -->
    <div class="table-wrapper">
        <h3>⭐ Top Customers by Gallons Ordered</h3>
        <table class="data-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Customer Name</th>
                    <th>Orders</th>
                    <th>Total Gallons</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach($topCustomers as $i => $row): ?>
                <tr>
                    <td><?php echo $i + 1; ?></td>
                    <td><?php echo htmlspecialchars($row['full_name']); ?></td>
                    <td><?php echo number_format($row['order_count']); ?></td>
                    <td><?php echo number_format($row['total_gallons']); ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

</div>

<script>
// Daily Sales Chart
const dailyCtx = document.getElementById('dailySalesChart').getContext('2d');
new Chart(dailyCtx, {
    type: 'line',
    data: {
        labels: <?php echo json_encode(array_map(function($r){ return date('M d', strtotime($r['order_date'])); }, $salesTrend)); ?>,
        datasets: [{
            label: 'Orders',
            data: <?php echo json_encode(array_map(function($r){ return (int)$r['order_count']; }, $salesTrend)); ?>,
            borderColor: '#1e88e5',
            backgroundColor: 'rgba(30,136,229,0.1)',
            fill: true,
            tension: 0.3
        }, {
            label: 'Gallons',
            data: <?php echo json_encode(array_map(function($r){ return (int)$r['total_gallons']; }, $salesTrend)); ?>,
            borderColor: '#0097a7',
            backgroundColor: 'rgba(0,151,167,0.1)',
            fill: true,
            tension: 0.3
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { position: 'bottom' } },
        scales: { y: { beginAtZero: true } }
    }
});

// Monthly Sales Chart
const monthlyCtx = document.getElementById('monthlySalesChart').getContext('2d');
new Chart(monthlyCtx, {
    type: 'bar',
    data: {
        labels: <?php echo json_encode(array_map(function($r){ return date('M Y', strtotime($r['month'].'-01')); }, $monthlySales)); ?>,
        datasets: [{
            label: 'Orders',
            data: <?php echo json_encode(array_map(function($r){ return (int)$r['order_count']; }, $monthlySales)); ?>,
            backgroundColor: '#1e88e5'
        }, {
            label: 'Gallons',
            data: <?php echo json_encode(array_map(function($r){ return (int)$r['total_gallons']; }, $monthlySales)); ?>,
            backgroundColor: '#0097a7'
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { position: 'bottom' } },
        scales: { y: { beginAtZero: true } }
    }
});

// Barangay Orders Chart
const brgyCtx = document.getElementById('barangayOrdersChart').getContext('2d');
new Chart(brgyCtx, {
    type: 'doughnut',
    data: {
        labels: <?php echo json_encode(array_map(function($r){ return $r['brgy']; }, $topBarangaysOrders)); ?>,
        datasets: [{
            data: <?php echo json_encode(array_map(function($r){ return (int)$r['order_count']; }, $topBarangaysOrders)); ?>,
            backgroundColor: ['#1e88e5','#0097a7','#7cb342','#f57f17','#e53935','#8e24aa','#00acc1','#43a047','#fb8c00','#3949ab']
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { position: 'right' } }
    }
});

// Order Type Chart
const typeCtx = document.getElementById('orderTypeChart').getContext('2d');
new Chart(typeCtx, {
    type: 'pie',
    data: {
        labels: <?php echo json_encode(array_map(function($r){ return ucfirst($r['order_type']); }, $orderTypeDist)); ?>,
        datasets: [{
            data: <?php echo json_encode(array_map(function($r){ return (int)$r['count']; }, $orderTypeDist)); ?>,
            backgroundColor: ['#1e88e5','#0097a7']
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { position: 'bottom' } }
    }
});

// Container Type Chart
const containerCtx = document.getElementById('containerTypeChart').getContext('2d');
new Chart(containerCtx, {
    type: 'doughnut',
    data: {
        labels: <?php echo json_encode(array_map(function($r){ return ucfirst($r['container_type']); }, $containerTypeDist)); ?>,
        datasets: [{
            data: <?php echo json_encode(array_map(function($r){ return (int)$r['count']; }, $containerTypeDist)); ?>,
            backgroundColor: ['#7cb342','#f57f17']
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { position: 'bottom' } }
    }
});

// Status Chart
const statusCtx = document.getElementById('statusChart').getContext('2d');
new Chart(statusCtx, {
    type: 'bar',
    data: {
        labels: <?php echo json_encode(array_map(function($r){ return $r['order_status']; }, $statusDist)); ?>,
        datasets: [{
            label: 'Orders',
            data: <?php echo json_encode(array_map(function($r){ return (int)$r['count']; }, $statusDist)); ?>,
            backgroundColor: ['#43a047','#1e88e5','#fb8c00','#e53935']
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { display: false } },
        scales: { y: { beginAtZero: true } }
    }
});
</script>

</body>
</html>
