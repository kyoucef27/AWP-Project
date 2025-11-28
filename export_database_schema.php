<?php
session_start();
require_once 'includes/config.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: auth/login.php");
    exit();
}

$pdo = getDBConnection();
$format = $_GET['format'] ?? 'sql';
$databaseName = $pdo->query("SELECT DATABASE()")->fetchColumn();

function getAllTables($pdo) {
    $stmt = $pdo->query("SHOW TABLES");
    return $stmt->fetchAll(PDO::FETCH_COLUMN);
}

function getCreateTableStatement($pdo, $tableName) {
    $stmt = $pdo->query("SHOW CREATE TABLE `$tableName`");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    return $result['Create Table'];
}

function getForeignKeys($pdo, $tableName) {
    $sql = "
        SELECT 
            COLUMN_NAME,
            REFERENCED_TABLE_NAME,
            REFERENCED_COLUMN_NAME,
            CONSTRAINT_NAME,
            UPDATE_RULE,
            DELETE_RULE
        FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE 
        WHERE TABLE_SCHEMA = DATABASE() 
        AND TABLE_NAME = ? 
        AND REFERENCED_TABLE_NAME IS NOT NULL
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$tableName]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getTableStructure($pdo, $tableName) {
    $stmt = $pdo->query("DESCRIBE `$tableName`");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

if ($format === 'sql') {
    // Generate SQL Schema Export
    header('Content-Type: text/plain');
    header('Content-Disposition: attachment; filename="' . $databaseName . '_schema.sql"');
    
    $tables = getAllTables($pdo);
    
    echo "-- =============================================\n";
    echo "-- PAW Project Database Schema Export\n";
    echo "-- Database: $databaseName\n";
    echo "-- Generated: " . date('Y-m-d H:i:s') . "\n";
    echo "-- =============================================\n\n";
    
    echo "-- Drop existing tables (in reverse dependency order)\n";
    echo "SET FOREIGN_KEY_CHECKS = 0;\n\n";
    
    foreach ($tables as $table) {
        echo "DROP TABLE IF EXISTS `$table`;\n";
    }
    
    echo "\nSET FOREIGN_KEY_CHECKS = 1;\n\n";
    
    echo "-- =============================================\n";
    echo "-- CREATE TABLES\n";
    echo "-- =============================================\n\n";
    
    foreach ($tables as $table) {
        echo "-- Table: $table\n";
        echo "-- =============================================\n";
        $createStatement = getCreateTableStatement($pdo, $table);
        echo $createStatement . ";\n\n";
    }
    
    echo "-- =============================================\n";
    echo "-- FOREIGN KEY RELATIONSHIPS SUMMARY\n";
    echo "-- =============================================\n";
    
    foreach ($tables as $table) {
        $foreignKeys = getForeignKeys($pdo, $table);
        if (!empty($foreignKeys)) {
            echo "-- Table: $table\n";
            foreach ($foreignKeys as $fk) {
                echo "-- {$fk['COLUMN_NAME']} -> {$fk['REFERENCED_TABLE_NAME']}.{$fk['REFERENCED_COLUMN_NAME']}\n";
                echo "-- Constraint: {$fk['CONSTRAINT_NAME']}\n";
                echo "-- On Update: {$fk['UPDATE_RULE']}, On Delete: {$fk['DELETE_RULE']}\n";
            }
            echo "\n";
        }
    }
    
    echo "-- =============================================\n";
    echo "-- END OF SCHEMA EXPORT\n";
    echo "-- =============================================\n";
    
} elseif ($format === 'json') {
    // Generate JSON Schema Export
    header('Content-Type: application/json');
    header('Content-Disposition: attachment; filename="' . $databaseName . '_schema.json"');
    
    $tables = getAllTables($pdo);
    $schema = [
        'database' => $databaseName,
        'generated' => date('Y-m-d H:i:s'),
        'tables' => []
    ];
    
    foreach ($tables as $table) {
        $columns = getTableStructure($pdo, $table);
        $foreignKeys = getForeignKeys($pdo, $table);
        
        $tableSchema = [
            'name' => $table,
            'columns' => [],
            'primary_keys' => [],
            'foreign_keys' => [],
            'indexes' => []
        ];
        
        foreach ($columns as $column) {
            $tableSchema['columns'][] = [
                'name' => $column['Field'],
                'type' => $column['Type'],
                'null' => $column['Null'] === 'YES',
                'key' => $column['Key'],
                'default' => $column['Default'],
                'extra' => $column['Extra']
            ];
            
            if ($column['Key'] === 'PRI') {
                $tableSchema['primary_keys'][] = $column['Field'];
            }
        }
        
        foreach ($foreignKeys as $fk) {
            $tableSchema['foreign_keys'][] = [
                'column' => $fk['COLUMN_NAME'],
                'references_table' => $fk['REFERENCED_TABLE_NAME'],
                'references_column' => $fk['REFERENCED_COLUMN_NAME'],
                'constraint_name' => $fk['CONSTRAINT_NAME'],
                'update_rule' => $fk['UPDATE_RULE'],
                'delete_rule' => $fk['DELETE_RULE']
            ];
        }
        
        $schema['tables'][] = $tableSchema;
    }
    
    echo json_encode($schema, JSON_PRETTY_PRINT);
    
} elseif ($format === 'markdown') {
    // Generate Markdown Documentation
    header('Content-Type: text/plain');
    header('Content-Disposition: attachment; filename="' . $databaseName . '_schema.md"');
    
    $tables = getAllTables($pdo);
    
    echo "# PAW Project Database Schema Documentation\n\n";
    echo "**Database:** $databaseName  \n";
    echo "**Generated:** " . date('Y-m-d H:i:s') . "\n\n";
    
    echo "## Database Overview\n\n";
    echo "This database contains " . count($tables) . " tables supporting the PAW (Programmation Avancée Web) attendance management system.\n\n";
    
    echo "### Table List\n\n";
    foreach ($tables as $table) {
        echo "- [`$table`](#$table)\n";
    }
    echo "\n";
    
    echo "## Table Structures\n\n";
    
    foreach ($tables as $table) {
        $columns = getTableStructure($pdo, $table);
        $foreignKeys = getForeignKeys($pdo, $table);
        
        echo "### $table\n\n";
        
        echo "| Column | Type | Null | Key | Default | Extra |\n";
        echo "|--------|------|------|-----|---------|-------|\n";
        
        foreach ($columns as $column) {
            $null = $column['Null'] === 'YES' ? 'YES' : 'NO';
            $key = $column['Key'];
            if ($key === 'PRI') $key = '🔑 PRI';
            elseif ($key === 'UNI') $key = '🔒 UNI';
            elseif ($key === 'MUL') $key = '🔗 IDX';
            
            echo "| **{$column['Field']}** | `{$column['Type']}` | $null | $key | `{$column['Default']}` | {$column['Extra']} |\n";
        }
        
        echo "\n";
        
        if (!empty($foreignKeys)) {
            echo "#### Foreign Key Relationships\n\n";
            foreach ($foreignKeys as $fk) {
                echo "- `{$fk['COLUMN_NAME']}` → [`{$fk['REFERENCED_TABLE_NAME']}`](#{$fk['REFERENCED_TABLE_NAME']}).`{$fk['REFERENCED_COLUMN_NAME']}`\n";
            }
            echo "\n";
        }
    }
    
    echo "## Entity Relationship Summary\n\n";
    echo "```\n";
    foreach ($tables as $table) {
        $foreignKeys = getForeignKeys($pdo, $table);
        if (!empty($foreignKeys)) {
            foreach ($foreignKeys as $fk) {
                echo "{$fk['REFERENCED_TABLE_NAME']} ||--o{ $table : has\n";
            }
        }
    }
    echo "```\n\n";
    
    echo "---\n";
    echo "*Generated by PAW Project Database Design Extractor*\n";
}
?>