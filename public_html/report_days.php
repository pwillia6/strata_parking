<?php
// Database Configuration
$host     = '127.0.0.1';
$dbname   = 'parking';
$username = 'root';
$password = '';

// Initialize variables
$chartData = [];
$startDateInput = isset($_GET['start_date']) ? $_GET['start_date'] : '';
$endDateInput   = isset($_GET['end_date'])   ? $_GET['end_date']   : '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Build the query
    $sql = "SELECT phototime FROM parking_records WHERE phototime IS NOT NULL";
    $params = [];

    // Apply Date Filtering if provided
    if ($startDateInput && $endDateInput) {
        // The user selects dates in Sydney time, but our DB is in UTC.
        // We need to convert the filter boundaries to UTC for the query.
        $startDt = new DateTime($startDateInput . ' 00:00:00', new DateTimeZone('Australia/Sydney'));
        $startDt->setTimezone(new DateTimeZone('UTC'));
        
        $endDt = new DateTime($endDateInput . ' 23:59:59', new DateTimeZone('Australia/Sydney'));
        $endDt->setTimezone(new DateTimeZone('UTC'));

        $sql .= " AND phototime >= ? AND phototime <= ?";
        $params[] = $startDt->format('Y-m-d H:i:s');
        $params[] = $endDt->format('Y-m-d H:i:s');
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Grouping records by ISO week
    $weeklyData = [];
    
    foreach ($rows as $row) {
        // 1. Read the time as UTC
        $dt = new DateTime($row['phototime'], new DateTimeZone('UTC'));
        
        // 2. Convert to Sydney Time
        $dt->setTimezone(new DateTimeZone('Australia/Sydney'));

        // 3. Extract ISO Year and ISO Week ('o' is ISO year, 'W' is ISO week starting Monday)
        $weekKey = $dt->format('o-\WW'); 
        
        // 4. Extract the exact date (to count unique days, not total photos)
        $dayString = $dt->format('Y-m-d');

        if (!isset($weeklyData[$weekKey])) {
            $weeklyData[$weekKey] = [];
        }
        
        // Use the date string as an array key to naturally deduplicate multiple photos on the same day
        $weeklyData[$weekKey][$dayString] = true;
    }

    // Format data for Chart.js
    foreach ($weeklyData as $weekKey => $uniqueDays) {
        // Extract Year and Week to find the date of the Monday of that week
        $year = (int) substr($weekKey, 0, 4);
        $week = (int) substr($weekKey, 6, 2);
        
        $monday = new DateTime();
        $monday->setISODate($year, $week); // This perfectly aligns with our Monday-Sunday week

        $chartData[] = [
            'x' => $monday->format('Y-m-d'), // X-Axis: Date of the Monday
            'y' => count($uniqueDays)        // Y-Axis: Number of unique days (1-7)
        ];
    }

    // Sort the dataset chronologically for the linear graph
    usort($chartData, function($a, $b) {
        return strcmp($a['x'], $b['x']);
    });

} catch (PDOException $e) {
    die("Database Connection failed: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Parking Photos Weekly Report</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chartjs-adapter-date-fns/dist/chartjs-adapter-date-fns.bundle.min.js"></script>
    <?php include 'tailwind-theme.php'; ?>
</head>
<body class="bg-slate-100">
    <div class="max-w-4xl mx-auto p-5">

    <h2>Days per Week with Parking Photos</h2>

    <div class="filter-form flex items-center flex-wrap gap-3 bg-white p-4 rounded-lg shadow-sm mb-5">
        <form method="GET" action="" class="flex items-center flex-wrap gap-3">
            <div class="flex items-center gap-1.5">
                <label for="start_date" class="font-bold">Start Date:</label>
                <input type="date" id="start_date" name="start_date" value="<?php echo htmlspecialchars($startDateInput); ?>">
            </div>

            <div class="flex items-center gap-1.5">
                <label for="end_date" class="font-bold">End Date:</label>
                <input type="date" id="end_date" name="end_date" value="<?php echo htmlspecialchars($endDateInput); ?>">
            </div>

            <button type="submit">Filter</button>
            <a href="?" class="text-red-500 hover:text-red-600">Clear</a>
        </form>
    </div>

    <div class="bg-white p-4 rounded-lg shadow-sm">
        <canvas id="parkingChart"></canvas>
    </div>

    <script>
        // Data injected from PHP
        const rawData = <?php echo json_encode($chartData); ?>;

        const ctx = document.getElementById('parkingChart').getContext('2d');
        const parkingChart = new Chart(ctx, {
            type: 'line',
            data: {
                datasets: [{
                    label: 'Distinct Days with Photos per Week',
                    data: rawData,
                    borderColor: 'rgba(75, 192, 192, 1)',
                    backgroundColor: 'rgba(75, 192, 192, 0.2)',
                    borderWidth: 2,
                    pointRadius: 4,
                    pointBackgroundColor: 'rgba(75, 192, 192, 1)',
                    tension: 0.1 // Slight curve to the line
                }]
            },
            options: {
                responsive: true,
                scales: {
                    x: {
                        type: 'time',
                        time: {
                            unit: 'week',
                            displayFormats: {
                                week: 'MMM d, yyyy'
                            },
                            tooltipFormat: 'MMM d, yyyy'
                        },
                        title: {
                            display: true,
                            text: 'Week of (Monday)'
                        }
                    },
                    y: {
                        beginAtZero: true,
                        max: 7, // A week can only have a maximum of 7 days
                        ticks: {
                            stepSize: 1
                        },
                        title: {
                            display: true,
                            text: 'Number of Days'
                        }
                    }
                }
            }
        });
    </script>
    </div>
</body>
</html>
