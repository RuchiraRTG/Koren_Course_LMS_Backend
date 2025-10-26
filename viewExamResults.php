<?php
// View Exam Results - Simple viewer for exam_results table
require_once 'config/database.php';
require_once 'includes/functions.php';

startSecureSession();
$conn = getDBConnection();

// Get student ID from session or query parameter
$studentId = isset($_GET['student_id']) ? intval($_GET['student_id']) : ($_SESSION['user_id'] ?? null);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Exam Results Viewer</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 20px;
            min-height: 100vh;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
        }
        h1 {
            color: #333;
            margin-bottom: 10px;
            text-align: center;
        }
        .subtitle {
            text-align: center;
            color: #666;
            margin-bottom: 30px;
        }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        .stat-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            border-radius: 10px;
            text-align: center;
        }
        .stat-card h3 {
            font-size: 14px;
            margin-bottom: 10px;
            opacity: 0.9;
        }
        .stat-card .value {
            font-size: 32px;
            font-weight: bold;
        }
        .stat-card .unit {
            font-size: 14px;
            opacity: 0.8;
            margin-top: 5px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        thead {
            background: #667eea;
            color: white;
        }
        th, td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        th {
            font-weight: 600;
            text-transform: uppercase;
            font-size: 12px;
        }
        tbody tr:hover {
            background-color: #f5f5f5;
        }
        .badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
        }
        .badge-submitted {
            background: #10b981;
            color: white;
        }
        .badge-pending {
            background: #f59e0b;
            color: white;
        }
        .badge-in-progress {
            background: #3b82f6;
            color: white;
        }
        .score-good {
            color: #10b981;
            font-weight: 600;
        }
        .score-medium {
            color: #f59e0b;
            font-weight: 600;
        }
        .score-poor {
            color: #ef4444;
            font-weight: 600;
        }
        .no-data {
            text-align: center;
            padding: 40px;
            color: #999;
        }
        .filter-section {
            margin-bottom: 20px;
            padding: 20px;
            background: #f9fafb;
            border-radius: 10px;
        }
        .filter-section label {
            margin-right: 10px;
            font-weight: 600;
        }
        .filter-section input, .filter-section select {
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 5px;
            margin-right: 15px;
        }
        .filter-section button {
            padding: 8px 20px;
            background: #667eea;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-weight: 600;
        }
        .filter-section button:hover {
            background: #5568d3;
        }
        .time-format {
            color: #666;
            font-size: 13px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>📊 Exam Results Dashboard</h1>
        <p class="subtitle">View and analyze student exam performance</p>

        <?php if ($studentId): ?>
            <?php
            // Get statistics
            $statsSql = "SELECT 
                            COUNT(*) as total_exams,
                            AVG(percentage) as avg_percentage,
                            MAX(percentage) as best_score,
                            MIN(percentage) as worst_score,
                            AVG(time_taken) as avg_time
                         FROM exam_results 
                         WHERE student_id = ?";
            $statsStmt = $conn->prepare($statsSql);
            $statsStmt->bind_param('i', $studentId);
            $statsStmt->execute();
            $stats = $statsStmt->get_result()->fetch_assoc();
            $statsStmt->close();
            ?>

            <div class="stats-grid">
                <div class="stat-card">
                    <h3>Total Exams</h3>
                    <div class="value"><?php echo $stats['total_exams']; ?></div>
                </div>
                <div class="stat-card">
                    <h3>Average Score</h3>
                    <div class="value"><?php echo number_format($stats['avg_percentage'], 1); ?>%</div>
                </div>
                <div class="stat-card">
                    <h3>Best Score</h3>
                    <div class="value"><?php echo number_format($stats['best_score'], 1); ?>%</div>
                </div>
                <div class="stat-card">
                    <h3>Average Time</h3>
                    <div class="value"><?php echo round($stats['avg_time'] / 60); ?></div>
                    <div class="unit">minutes</div>
                </div>
            </div>

            <?php
            // Get all exam results for this student
            $resultsSql = "SELECT 
                            er.*,
                            u.username,
                            u.email
                         FROM exam_results er
                         LEFT JOIN users u ON er.student_id = u.id
                         WHERE er.student_id = ?
                         ORDER BY er.submitted_at DESC";
            $resultsStmt = $conn->prepare($resultsSql);
            $resultsStmt->bind_param('i', $studentId);
            $resultsStmt->execute();
            $results = $resultsStmt->get_result();
            ?>

            <?php if ($results->num_rows > 0): ?>
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Exam Type</th>
                            <th>Score</th>
                            <th>Percentage</th>
                            <th>Time Taken</th>
                            <th>Status</th>
                            <th>Submitted At</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($row = $results->fetch_assoc()): ?>
                            <?php
                            $percentage = floatval($row['percentage']);
                            $scoreClass = $percentage >= 75 ? 'score-good' : ($percentage >= 50 ? 'score-medium' : 'score-poor');
                            $examType = $row['exam_id'] ? "Exam #{$row['exam_id']}" : "Mock Exam";
                            $timeTaken = round($row['time_taken'] / 60, 1);
                            ?>
                            <tr>
                                <td>#<?php echo $row['id']; ?></td>
                                <td><?php echo $examType; ?></td>
                                <td><?php echo number_format($row['score'], 1); ?> / <?php echo $row['total_marks']; ?></td>
                                <td class="<?php echo $scoreClass; ?>"><?php echo number_format($percentage, 1); ?>%</td>
                                <td><?php echo $timeTaken; ?> min</td>
                                <td><span class="badge badge-<?php echo $row['status']; ?>"><?php echo $row['status']; ?></span></td>
                                <td class="time-format"><?php echo date('M d, Y h:i A', strtotime($row['submitted_at'])); ?></td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="no-data">
                    <p>No exam results found for this student.</p>
                </div>
            <?php endif; ?>

            <?php $resultsStmt->close(); ?>

        <?php else: ?>
            <div class="filter-section">
                <form method="GET" action="">
                    <label>Student ID:</label>
                    <input type="number" name="student_id" placeholder="Enter student ID" required>
                    <button type="submit">View Results</button>
                </form>
            </div>
            
            <div class="no-data">
                <p>Please enter a student ID or login to view exam results.</p>
            </div>

            <?php
            // Show recent results from all students
            $recentSql = "SELECT 
                            er.*,
                            u.username,
                            u.email
                         FROM exam_results er
                         LEFT JOIN users u ON er.student_id = u.id
                         ORDER BY er.submitted_at DESC
                         LIMIT 10";
            $recentResults = $conn->query($recentSql);
            ?>

            <?php if ($recentResults->num_rows > 0): ?>
                <h2 style="margin: 30px 0 20px 0; color: #333;">Recent Exam Submissions (All Students)</h2>
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Student</th>
                            <th>Score</th>
                            <th>Percentage</th>
                            <th>Status</th>
                            <th>Submitted At</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($row = $recentResults->fetch_assoc()): ?>
                            <?php
                            $percentage = floatval($row['percentage']);
                            $scoreClass = $percentage >= 75 ? 'score-good' : ($percentage >= 50 ? 'score-medium' : 'score-poor');
                            ?>
                            <tr>
                                <td>#<?php echo $row['id']; ?></td>
                                <td><?php echo htmlspecialchars($row['username'] ?? 'Unknown'); ?></td>
                                <td><?php echo number_format($row['score'], 1); ?> / <?php echo $row['total_marks']; ?></td>
                                <td class="<?php echo $scoreClass; ?>"><?php echo number_format($percentage, 1); ?>%</td>
                                <td><span class="badge badge-<?php echo $row['status']; ?>"><?php echo $row['status']; ?></span></td>
                                <td class="time-format"><?php echo date('M d, Y h:i A', strtotime($row['submitted_at'])); ?></td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</body>
</html>
<?php
$conn->close();
?>
