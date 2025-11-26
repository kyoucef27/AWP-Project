<?php
session_start();

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../auth/unauthorized.php");
    exit();
}

require_once '../includes/config.php';

$format = $_GET['format'] ?? 'csv';

try {
    $pdo = getDBConnection();
    
    // Fetch all modules
    $stmt = $pdo->query("
        SELECT 
            module_code,
            module_name,
            description,
            credits,
            department,
            year_level,
            semester,
            is_active,
            created_at,
            updated_at
        FROM modules 
        ORDER BY department, year_level, semester, module_code
    ");
    $modules = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if ($format === 'csv') {
        // Set headers for CSV download
        $filename = 'modules_export_' . date('Y-m-d_H-i-s') . '.csv';
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        
        // Open output stream
        $output = fopen('php://output', 'w');
        
        // Write CSV headers
        fputcsv($output, [
            'Module Code',
            'Module Name', 
            'Description',
            'Credits',
            'Department',
            'Year Level',
            'Semester',
            'Status',
            'Created Date',
            'Updated Date'
        ]);
        
        // Write data rows
        foreach ($modules as $module) {
            fputcsv($output, [
                $module['module_code'],
                $module['module_name'],
                $module['description'],
                $module['credits'],
                $module['department'],
                $module['year_level'],
                $module['semester'],
                $module['is_active'] ? 'Active' : 'Inactive',
                $module['created_at'],
                $module['updated_at']
            ]);
        }
        
        fclose($output);
        
    } elseif ($format === 'excel') {
        // For Excel format, we'll create an HTML table that Excel can import
        $filename = 'modules_export_' . date('Y-m-d_H-i-s') . '.xls';
        header('Content-Type: application/vnd.ms-excel');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        
        echo '<?xml version="1.0"?>';
        echo '<ss:Workbook xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet">';
        echo '<ss:Worksheet ss:Name="Modules">';
        echo '<ss:Table>';
        
        // Headers
        echo '<ss:Row>';
        $headers = ['Module Code', 'Module Name', 'Description', 'Credits', 'Department', 'Year Level', 'Semester', 'Status', 'Created Date', 'Updated Date'];
        foreach ($headers as $header) {
            echo '<ss:Cell><ss:Data ss:Type="String">' . htmlspecialchars($header) . '</ss:Data></ss:Cell>';
        }
        echo '</ss:Row>';
        
        // Data rows
        foreach ($modules as $module) {
            echo '<ss:Row>';
            echo '<ss:Cell><ss:Data ss:Type="String">' . htmlspecialchars($module['module_code']) . '</ss:Data></ss:Cell>';
            echo '<ss:Cell><ss:Data ss:Type="String">' . htmlspecialchars($module['module_name']) . '</ss:Data></ss:Cell>';
            echo '<ss:Cell><ss:Data ss:Type="String">' . htmlspecialchars($module['description']) . '</ss:Data></ss:Cell>';
            echo '<ss:Cell><ss:Data ss:Type="Number">' . htmlspecialchars($module['credits']) . '</ss:Data></ss:Cell>';
            echo '<ss:Cell><ss:Data ss:Type="String">' . htmlspecialchars($module['department']) . '</ss:Data></ss:Cell>';
            echo '<ss:Cell><ss:Data ss:Type="String">' . htmlspecialchars($module['year_level']) . '</ss:Data></ss:Cell>';
            echo '<ss:Cell><ss:Data ss:Type="String">' . htmlspecialchars($module['semester']) . '</ss:Data></ss:Cell>';
            echo '<ss:Cell><ss:Data ss:Type="String">' . ($module['is_active'] ? 'Active' : 'Inactive') . '</ss:Data></ss:Cell>';
            echo '<ss:Cell><ss:Data ss:Type="String">' . htmlspecialchars($module['created_at']) . '</ss:Data></ss:Cell>';
            echo '<ss:Cell><ss:Data ss:Type="String">' . htmlspecialchars($module['updated_at']) . '</ss:Data></ss:Cell>';
            echo '</ss:Row>';
        }
        
        echo '</ss:Table>';
        echo '</ss:Worksheet>';
        echo '</ss:Workbook>';
    }
    
} catch (PDOException $e) {
    header('Content-Type: text/html');
    echo "<p>Error exporting data: " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<p><a href='module_management/module_management.php'>Go back to Module Management</a></p>";
}
?>