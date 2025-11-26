<?php
require_once '../includes/config.php';

try {
    $pdo = getDBConnection();
    echo "<h2>Database Table Structures</h2>";
    
    $tables = ['users', 'students', 'teachers', 'groups', 'modules', 'enrollments', 'attendance', 'absence_justifications'];
    
    foreach ($tables as $table) {
        try {
            $stmt = $pdo->query("SHOW TABLES LIKE '$table'");
            if ($stmt->rowCount() > 0) {
                echo "<h3>Table: $table</h3>";
                
                $stmt = $pdo->query("DESCRIBE $table");
                $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                echo "<table border='1' cellpadding='8' style='border-collapse: collapse; margin-bottom: 20px;'>";
                echo "<tr style='background: #f0f0f0;'><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
                
                foreach ($columns as $column) {
                    echo "<tr>";
                    echo "<td><strong>" . $column['Field'] . "</strong></td>";
                    echo "<td>" . $column['Type'] . "</td>";
                    echo "<td>" . $column['Null'] . "</td>";
                    echo "<td>" . $column['Key'] . "</td>";
                    echo "<td>" . ($column['Default'] ?? 'NULL') . "</td>";
                    echo "<td>" . $column['Extra'] . "</td>";
                    echo "</tr>";
                }
                echo "</table>";
                
                // Show sample data
                $stmt = $pdo->query("SELECT COUNT(*) FROM $table");
                $count = $stmt->fetchColumn();
                echo "<p><em>Total records: $count</em></p>";
                
                if ($count > 0 && $count <= 10) {
                    echo "<h4>Sample Data:</h4>";
                    $stmt = $pdo->query("SELECT * FROM $table LIMIT 5");
                    $sample_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    
                    if (!empty($sample_data)) {
                        echo "<table border='1' cellpadding='5' style='border-collapse: collapse; font-size: 0.9em; margin-bottom: 20px;'>";
                        echo "<tr style='background: #f9f9f9;'>";
                        foreach (array_keys($sample_data[0]) as $header) {
                            echo "<th>$header</th>";
                        }
                        echo "</tr>";
                        
                        foreach ($sample_data as $row) {
                            echo "<tr>";
                            foreach ($row as $value) {
                                echo "<td>" . htmlspecialchars($value ?? 'NULL') . "</td>";
                            }
                            echo "</tr>";
                        }
                        echo "</table>";
                    }
                }
                
            } else {
                echo "<h3 style='color: red;'>Table: $table - NOT FOUND</h3>";
            }
            
            echo "<hr>";
            
        } catch (PDOException $e) {
            echo "<h3 style='color: red;'>Table: $table - ERROR</h3>";
            echo "<p>Error: " . $e->getMessage() . "</p>";
            echo "<hr>";
        }
    }
    
} catch (PDOException $e) {
    echo "<h3 style='color: red;'>Database Connection Error</h3>";
    echo "<p>Error: " . $e->getMessage() . "</p>";
}
?>

<style>
body {
    font-family: Arial, sans-serif;
    max-width: 1200px;
    margin: 20px auto;
    padding: 20px;
    line-height: 1.5;
}
h2, h3 {
    color: #333;
}
table {
    width: 100%;
    margin: 10px 0;
}
th {
    text-align: left;
    font-weight: bold;
}
td, th {
    padding: 8px;
    border: 1px solid #ddd;
}
</style>