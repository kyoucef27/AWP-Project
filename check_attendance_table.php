<?php
require_once 'includes/config.php';

$pdo = getDBConnection();

echo "<h1>📋 Attendance Table Structure Check</h1>";

// Check attendance table structure
echo "<h2>Attendance Table Columns:</h2>";
$stmt = $pdo->query("DESCRIBE attendance");
$columns = $stmt->fetchAll();

echo "<table style='border-collapse: collapse; margin: 20px 0;'>";
echo "<tr style='background: #f2f2f2;'><th style='border: 1px solid #ddd; padding: 8px;'>Column</th><th style='border: 1px solid #ddd; padding: 8px;'>Type</th><th style='border: 1px solid #ddd; padding: 8px;'>Null</th><th style='border: 1px solid #ddd; padding: 8px;'>Key</th><th style='border: 1px solid #ddd; padding: 8px;'>Default</th></tr>";
foreach ($columns as $column) {
    echo "<tr>";
    echo "<td style='border: 1px solid #ddd; padding: 8px;'>{$column['Field']}</td>";
    echo "<td style='border: 1px solid #ddd; padding: 8px;'>{$column['Type']}</td>";
    echo "<td style='border: 1px solid #ddd; padding: 8px;'>{$column['Null']}</td>";
    echo "<td style='border: 1px solid #ddd; padding: 8px;'>{$column['Key']}</td>";
    echo "<td style='border: 1px solid #ddd; padding: 8px;'>{$column['Default']}</td>";
    echo "</tr>";
}
echo "</table>";

// Check for sample data
echo "<h2>Sample Attendance Records:</h2>";
$stmt = $pdo->query("SELECT * FROM attendance LIMIT 5");
$records = $stmt->fetchAll();

if (empty($records)) {
    echo "<p>No attendance records found yet.</p>";
} else {
    echo "<table style='border-collapse: collapse; margin: 20px 0;'>";
    echo "<tr style='background: #f2f2f2;'>";
    foreach (array_keys($records[0]) as $header) {
        echo "<th style='border: 1px solid #ddd; padding: 8px;'>$header</th>";
    }
    echo "</tr>";
    
    foreach ($records as $record) {
        echo "<tr>";
        foreach ($record as $value) {
            echo "<td style='border: 1px solid #ddd; padding: 8px;'>$value</td>";
        }
        echo "</tr>";
    }
    echo "</table>";
}

// Test insert query
echo "<h2>🧪 Test Insert Query:</h2>";
try {
    $stmt = $pdo->prepare("
        INSERT INTO attendance (enrollment_id, attendance_date, status, recorded_by, session_id)
        VALUES (?, ?, ?, ?, ?)
    ");
    echo "<p>✅ Query preparation successful - all columns exist</p>";
} catch (PDOException $e) {
    echo "<p style='color: red;'>❌ Query preparation failed: " . $e->getMessage() . "</p>";
}

?>

<style>
body { font-family: Arial, sans-serif; margin: 20px; }
h1, h2 { color: #333; }
</style>