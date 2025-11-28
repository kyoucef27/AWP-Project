<?php
session_start();
require_once 'includes/config.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: auth/login.php");
    exit();
}

$pdo = getDBConnection();

// Function to get all tables
function getAllTables($pdo) {
    $stmt = $pdo->query("SHOW TABLES");
    return $stmt->fetchAll(PDO::FETCH_COLUMN);
}

// Function to get table structure
function getTableStructure($pdo, $tableName) {
    $stmt = $pdo->query("DESCRIBE `$tableName`");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Function to get foreign key constraints
function getForeignKeys($pdo, $tableName) {
    $sql = "
        SELECT 
            COLUMN_NAME,
            REFERENCED_TABLE_NAME,
            REFERENCED_COLUMN_NAME,
            CONSTRAINT_NAME
        FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE 
        WHERE TABLE_SCHEMA = DATABASE() 
        AND TABLE_NAME = ? 
        AND REFERENCED_TABLE_NAME IS NOT NULL
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$tableName]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Function to get table indexes
function getTableIndexes($pdo, $tableName) {
    $stmt = $pdo->query("SHOW INDEX FROM `$tableName`");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Function to get table row count
function getTableRowCount($pdo, $tableName) {
    try {
        $stmt = $pdo->query("SELECT COUNT(*) FROM `$tableName`");
        return $stmt->fetchColumn();
    } catch (Exception $e) {
        return 0;
    }
}

// Get all database information
$tables = getAllTables($pdo);
$databaseName = $pdo->query("SELECT DATABASE()")->fetchColumn();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Database Design Extractor - PAW Project</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/mermaid/10.6.1/mermaid.min.js"></script>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: Arial, sans-serif;
            background: white;
            color: #333;
            line-height: 1.4;
            padding: 20px;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
        }

        .header {
            border-bottom: 2px solid #333;
            padding-bottom: 20px;
            margin-bottom: 30px;
        }

        .header h1 {
            font-size: 24px;
            margin-bottom: 10px;
        }

        .nav-tabs {
            border-bottom: 1px solid #333;
            margin-bottom: 30px;
        }

        .nav-tab {
            display: inline-block;
            padding: 10px 20px;
            background: none;
            border: none;
            cursor: pointer;
            font-size: 14px;
            border-bottom: 2px solid transparent;
        }

        .nav-tab.active {
            border-bottom: 2px solid #333;
            font-weight: bold;
        }

        .tab-content {
            display: none;
        }

        .tab-content.active {
            display: block;
        }

        .section {
            margin-bottom: 40px;
        }

        .section h2 {
            font-size: 18px;
            margin-bottom: 15px;
            border-bottom: 1px solid #ddd;
            padding-bottom: 5px;
        }

        .stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-item {
            text-align: center;
            border: 1px solid #ddd;
            padding: 15px;
        }

        .stat-value {
            font-size: 24px;
            font-weight: bold;
            margin-bottom: 5px;
        }

        .table-list {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
        }

        .table-item {
            border: 1px solid #333;
            padding: 15px;
        }

        .table-header {
            font-weight: bold;
            font-size: 16px;
            margin-bottom: 10px;
            border-bottom: 1px solid #ddd;
            padding-bottom: 5px;
        }

        .table-content table {
            width: 100%;
            border-collapse: collapse;
            font-size: 12px;
        }

        .table-content th,
        .table-content td {
            border: 1px solid #ddd;
            padding: 5px;
            text-align: left;
        }

        .table-content th {
            background: #f5f5f5;
            font-weight: bold;
        }

        .primary-key {
            background: #f0f0f0;
            font-weight: bold;
        }

        .export-buttons {
            margin-bottom: 20px;
        }

        .btn {
            display: inline-block;
            padding: 8px 16px;
            border: 1px solid #333;
            background: white;
            color: #333;
            text-decoration: none;
            margin-right: 10px;
            cursor: pointer;
        }

        .btn:hover {
            background: #f0f0f0;
        }

        .er-diagram {
            border: 1px solid #ddd;
            padding: 20px;
            text-align: center;
            min-height: 400px;
            background: #fafafa;
        }

        .relationship-list {
            border: 1px solid #ddd;
            padding: 15px;
        }

        .relationship-item {
            padding: 5px 0;
            border-bottom: 1px solid #eee;
            font-family: monospace;
            font-size: 13px;
        }

        .relationship-item:last-child {
            border-bottom: none;
        }

        @media (max-width: 768px) {
            .table-list {
                grid-template-columns: 1fr;
            }
            
            .stats {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        .loading {
            text-align: center;
            padding: 40px;
        }

        #mermaid-diagram {
            overflow: auto;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Database Design Extractor</h1>
            <p>PAW Project - Database Schema Documentation</p>
            <p><strong>Database:</strong> <?php echo htmlspecialchars($databaseName); ?></p>
        </div>

        <div class="export-buttons">
            <a href="export_database_schema.php?format=sql" class="btn">Export SQL Schema</a>
            <a href="export_database_schema.php?format=json" class="btn">Export JSON</a>
            <a href="export_database_schema.php?format=markdown" class="btn">Export Markdown</a>
            <button class="btn" onclick="window.print()">Print</button>
        </div>

        <div class="nav-tabs">
            <button class="nav-tab active" onclick="showTab('overview')">Overview</button>
            <button class="nav-tab" onclick="showTab('er-diagram')">ER Diagram</button>
            <button class="nav-tab" onclick="showTab('tables')">Tables</button>
            <button class="nav-tab" onclick="showTab('constraints')">Constraints</button>
        </div>

        <!-- Overview Tab -->
        <div id="overview" class="tab-content active">
            <div class="section">
                <h2>Database Statistics</h2>
                <div class="stats">
                    <div class="stat-item">
                        <div class="stat-value"><?php echo count($tables); ?></div>
                        <div>Total Tables</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-value" id="total-columns">-</div>
                        <div>Total Columns</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-value" id="total-relationships">-</div>
                        <div>Relationships</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-value" id="total-records">-</div>
                        <div>Total Records</div>
                    </div>
                </div>
            </div>

            <div class="section">
                <h2>Table Overview</h2>
                <div class="table-list">
                    <?php foreach ($tables as $table): 
                        $columns = getTableStructure($pdo, $table);
                        $rowCount = getTableRowCount($pdo, $table);
                    ?>
                    <div class="table-item">
                        <div class="table-header"><?php echo htmlspecialchars($table); ?></div>
                        <p><?php echo count($columns); ?> columns • <?php echo $rowCount; ?> rows</p>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- ER Diagram Tab -->
        <div id="er-diagram" class="tab-content">
            <h2>Entity Relationship Diagram</h2>
            <div id="mermaid-diagram"></div>
        </div>

        <!-- Tables Tab -->
        <div id="tables" class="tab-content">
            <div class="table-list">
                <?php foreach ($tables as $table): 
                    $columns = getTableStructure($pdo, $table);
                    $foreignKeys = getForeignKeys($pdo, $table);
                    $rowCount = getTableRowCount($pdo, $table);
                ?>
                <div class="table-item">
                    <div class="table-header"><?php echo htmlspecialchars($table); ?> (<?php echo $rowCount; ?> records)</div>
                    <div class="table-content">
                        <table>
                            <thead>
                                <tr>
                                    <th>Column</th>
                                    <th>Type</th>
                                    <th>Null</th>
                                    <th>Key</th>
                                    <th>Default</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($columns as $column): 
                                    $isPrimary = $column['Key'] === 'PRI';
                                    $isForeign = false;
                                    foreach ($foreignKeys as $fk) {
                                        if ($fk['COLUMN_NAME'] === $column['Field']) {
                                            $isForeign = true;
                                            break;
                                        }
                                    }
                                    $rowClass = $isPrimary ? 'primary-key' : '';
                                ?>
                                <tr class="<?php echo $rowClass; ?>">
                                    <td><strong><?php echo htmlspecialchars($column['Field']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($column['Type']); ?></td>
                                    <td><?php echo $column['Null'] === 'YES' ? 'YES' : 'NO'; ?></td>
                                    <td>
                                        <?php 
                                        $keys = [];
                                        if ($isPrimary) $keys[] = 'PK';
                                        if ($isForeign) $keys[] = 'FK';
                                        if ($column['Key'] === 'UNI') $keys[] = 'UK';
                                        if ($column['Key'] === 'MUL') $keys[] = 'IDX';
                                        echo implode(', ', $keys);
                                        ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($column['Default'] ?? 'NULL'); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Constraints Tab -->
        <div id="constraints" class="tab-content">
            <h3>Foreign Key Relationships</h3>
            <table>
                <thead>
                    <tr>
                        <th>Table</th>
                        <th>Column</th>
                        <th>References</th>
                        <th>Constraint Name</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    // Build relationships array
                    $allRelationships = [];
                    foreach ($tables as $table): 
                        $foreignKeys = getForeignKeys($pdo, $table);
                        foreach ($foreignKeys as $fk):
                            $allRelationships[] = [
                                'table' => $table,
                                'column' => $fk['COLUMN_NAME'],
                                'references_table' => $fk['REFERENCED_TABLE_NAME'],
                                'references_column' => $fk['REFERENCED_COLUMN_NAME'],
                                'constraint' => $fk['CONSTRAINT_NAME']
                            ];
                    ?>
                    <tr>
                        <td><?php echo htmlspecialchars($table); ?></td>
                        <td><?php echo htmlspecialchars($fk['COLUMN_NAME']); ?></td>
                        <td><?php echo htmlspecialchars($fk['REFERENCED_TABLE_NAME'] . '.' . $fk['REFERENCED_COLUMN_NAME']); ?></td>
                        <td><?php echo htmlspecialchars($fk['CONSTRAINT_NAME']); ?></td>
                    </tr>
                    <?php 
                        endforeach;
                    endforeach; 
                    ?>
                </tbody>
            </table>

            <h3>Primary Keys</h3>
            <table>
                <thead>
                    <tr>
                        <th>Table</th>
                        <th>Primary Key Columns</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($tables as $table): 
                        $columns = getTableStructure($pdo, $table);
                        $primaryKeys = array_filter($columns, function($col) { return $col['Key'] === 'PRI'; });
                        if (!empty($primaryKeys)):
                    ?>
                    <tr>
                        <td><?php echo htmlspecialchars($table); ?></td>
                        <td><?php echo implode(', ', array_map(function($pk) { return htmlspecialchars($pk['Field']); }, $primaryKeys)); ?></td>
                    </tr>
                    <?php 
                        endif;
                    endforeach; 
                    ?>
                </tbody>
            </table>
        </div>
    </div>

    <script>
        // Tab switching functionality
        function showTab(tabName) {
            // Hide all tab contents
            $('.tab-content').removeClass('active');
            $('.nav-tab').removeClass('active');
            
            // Show selected tab
            $('#' + tabName).addClass('active');
            $('button[onclick*="' + tabName + '"]').addClass('active');

            // Generate ER diagram if viewing that tab
            if (tabName === 'er-diagram') {
                generateERDiagram();
            }
        }

        // Generate ER Diagram using Mermaid
        function generateERDiagram() {
            const relationships = <?php echo json_encode($allRelationships); ?>;
            
            let mermaidCode = 'erDiagram\n';
            
            // Add tables with their primary key columns only
            <?php foreach ($tables as $table): 
                $columns = getTableStructure($pdo, $table);
            ?>
            mermaidCode += '    <?php echo $table; ?> {\n';
            <?php foreach ($columns as $column): 
                if ($column['Key'] === 'PRI' || $column['Key'] === 'UNI'):
                    $type = preg_replace('/\([^)]*\)/', '', $column['Type']);
                    $keyInfo = $column['Key'] === 'PRI' ? ' PK' : ' UK';
            ?>
            mermaidCode += '        <?php echo $type . $keyInfo; ?> <?php echo $column['Field']; ?>\n';
            <?php 
                endif;
            endforeach; ?>
            mermaidCode += '    }\n\n';
            <?php endforeach; ?>

            // Add relationships
            relationships.forEach(rel => {
                mermaidCode += rel.references_table + ' ||--o{ ' + rel.table + ' : "' + rel.column + '"\n';
            });

            console.log('Generated Mermaid Code:', mermaidCode);

            // Clear and render the diagram
            const diagramDiv = document.getElementById('mermaid-diagram');
            diagramDiv.innerHTML = '<p>Generating diagram...</p>';
            
            try {
                // Use async/await for better error handling
                mermaid.render('er-diagram-svg', mermaidCode)
                    .then(function(result) {
                        diagramDiv.innerHTML = result.svg;
                    })
                    .catch(function(error) {
                        console.error('Mermaid error:', error);
                        diagramDiv.innerHTML = '<p>Error: ' + error.message + '</p><details><summary>Debug Info</summary><pre>' + mermaidCode + '</pre></details>';
                    });
            } catch (error) {
                console.error('Mermaid initialization error:', error);
                diagramDiv.innerHTML = '<p>Mermaid library error: ' + error.message + '</p>';
            }
        }

        // Calculate statistics
        $(document).ready(function() {
            let totalColumns = 0;
            let totalRecords = 0;
            
            <?php 
            $totalColumns = 0;
            $totalRecords = 0;
            $totalRelationships = count($allRelationships);
            foreach ($tables as $table) {
                $columns = getTableStructure($pdo, $table);
                $rowCount = getTableRowCount($pdo, $table);
                $totalColumns += count($columns);
                $totalRecords += $rowCount;
            }
            ?>
            
            $('#total-columns').text('<?php echo $totalColumns; ?>');
            $('#total-relationships').text('<?php echo $totalRelationships; ?>');
            $('#total-records').text('<?php echo number_format($totalRecords); ?>');
        });

        // Export functions
        function exportToSQL() {
            window.location.href = 'export_database_schema.php?format=sql';
        }

        function exportToPDF() {
            window.print();
        }

        // Initialize Mermaid when document is ready
        $(document).ready(function() {
            if (typeof mermaid !== 'undefined') {
                mermaid.initialize({
                    startOnLoad: false,
                    theme: 'default',
                    securityLevel: 'loose'
                });
            } else {
                console.error('Mermaid library not loaded');
            }
        });
    </script>
</body>
</html>