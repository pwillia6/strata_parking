<?php
// Database Configuration
$host     = '127.0.0.1';
$dbname   = 'parking';
$username = 'root';
$password = '';

// Initialize variables
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
        // Convert Sydney filter dates to UTC for database querying
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

    // Grouping records by ISO week and counting conditions
    $weeklyData = [];
    
    foreach ($rows as $row) {
        $dt = new DateTime($row['phototime'], new DateTimeZone('UTC'));
        $dt->setTimezone(new DateTimeZone('Australia/Sydney'));

        // Extract ISO Year and ISO Week 
        $weekKey = $dt->format('o-\WW'); 
        
        if (!isset($weeklyData[$weekKey])) {
            $weeklyData[$weekKey] = [
                'in_hours'  => 0, // Green
                'out_hours' => 0  // Red
            ];
        }

        // Determine Day of Week (1 = Monday, 7 = Sunday) and Time
        $dayOfWeek = (int)$dt->format('N');
        $timeStr   = $dt->format('H:i:s');

        // Check conditions: Monday-Friday AND between 08:00:00 and 14:00:00
        $isWeekday = ($dayOfWeek >= 1 && $dayOfWeek <= 5);
        $isInTimeRange = ($timeStr >= '08:00:00' && $timeStr <= '14:00:00');

        if ($isWeekday && $isInTimeRange) {
            $weeklyData[$weekKey]['in_hours']++;
        } else {
            $weeklyData[$weekKey]['out_hours']++;
        }
    }

    // Format data into two separate datasets for Chart.js
    $chartDataGreen = [];
    $chartDataRed   = [];

    foreach ($weeklyData as $weekKey => $counts) {
        $year = (int) substr($weekKey, 0, 4);
        $week = (int) substr($weekKey, 6, 2);
        
        $monday = new DateTime();
        $monday->setISODate($year, $week); 
        $dateLabel = $monday->format('Y-m-d');

        $chartDataGreen[] = ['x' => $dateLabel, 'y' => $counts['in_hours']];
        $chartDataRed[]   = ['x' => $dateLabel, 'y' => $counts['out_hours']];
    }

    // Sort chronologically 
    $sortFunc = function($a, $b) { return strcmp($a['x'], $b['x']); };
    usort($chartDataGreen, $sortFunc);
    usort($chartDataRed, $sortFunc);

} catch (PDOException $e) {
    die("Database Connection failed: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Parking Photos: In-Hours vs Out-of-Hours</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chartjs-adapter-date-fns/dist/chartjs-adapter-date-fns.bundle.min.js"></script>
    <?php include 'tailwind-theme.php'; ?>
</head>
<body class="bg-slate-100">
    <div class="max-w-4xl mx-auto p-5">

    <h2>Weekly Parking Photos (08:00-14:00 Mon-Fri)</h2>

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
        const dataGreen = <?php echo json_encode($chartDataGreen); ?>;
        const dataRed   = <?php echo json_encode($chartDataRed); ?>;

        const ctx = document.getElementById('parkingChart').getContext('2d');
        const parkingChart = new Chart(ctx, {
            type: 'bar', // Changed from line to bar
            data: {
                datasets: [
                    {
                        label: 'In Hours (08:00 - 14:00, Mon-Fri)',
                        data: dataGreen,
                        backgroundColor: 'rgba(76, 175, 80, 0.8)', // Green
                        borderColor: 'rgba(76, 175, 80, 1)',
                        borderWidth: 1
                    },
                    {
                        label: 'Out of Hours (Weekends or outside 08:00 - 14:00)',
                        data: dataRed,
                        backgroundColor: 'rgba(244, 67, 54, 0.8)', // Red
                        borderColor: 'rgba(244, 67, 54, 1)',
                        borderWidth: 1
                    }
                ]
            },
            options: {
                responsive: true,
                scales: {
                    x: {
                        type: 'time',
                        stacked: true, // Enables stacking on the X-axis
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
                        stacked: true, // Enables stacking on the Y-axis
                        beginAtZero: true,
                        title: {
                            display: true,
                            text: 'Total Number of Photos'
                        }
                    }
                }
            }
        });
    </script>
    </div>
</body>
</html>
