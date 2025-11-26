<?php
echo "<h2>File Access Test</h2>";

$test_file = '../uploads/justifications/just_160_3209_1764192708.jpg';

echo "<p><strong>Testing file:</strong> $test_file</p>";

if (file_exists($test_file)) {
    echo "<p style='color: green;'>✓ File exists</p>";
    echo "<p><strong>File size:</strong> " . filesize($test_file) . " bytes</p>";
    echo "<p><strong>File permissions:</strong> " . substr(sprintf('%o', fileperms($test_file)), -4) . "</p>";
    echo "<p><strong>File is readable:</strong> " . (is_readable($test_file) ? 'Yes' : 'No') . "</p>";
    
    // Try to read a small portion
    $handle = fopen($test_file, 'r');
    if ($handle) {
        echo "<p style='color: green;'>✓ File can be opened for reading</p>";
        fclose($handle);
    } else {
        echo "<p style='color: red;'>✗ Cannot open file for reading</p>";
    }
} else {
    echo "<p style='color: red;'>✗ File does not exist</p>";
}

// Check directory permissions
$dir = '../uploads/justifications/';
echo "<h3>Directory Check</h3>";
echo "<p><strong>Directory:</strong> $dir</p>";

if (is_dir($dir)) {
    echo "<p style='color: green;'>✓ Directory exists</p>";
    echo "<p><strong>Directory permissions:</strong> " . substr(sprintf('%o', fileperms($dir)), -4) . "</p>";
    echo "<p><strong>Directory is readable:</strong> " . (is_readable($dir) ? 'Yes' : 'No') . "</p>";
    echo "<p><strong>Directory is writable:</strong> " . (is_writable($dir) ? 'Yes' : 'No') . "</p>";
    
    // List files in directory
    echo "<h4>Files in directory:</h4>";
    $files = scandir($dir);
    echo "<ul>";
    foreach ($files as $file) {
        if ($file != '.' && $file != '..') {
            echo "<li>$file</li>";
        }
    }
    echo "</ul>";
} else {
    echo "<p style='color: red;'>✗ Directory does not exist</p>";
}

// Test web access
echo "<h3>Web Access Test</h3>";
echo "<p>Try accessing the file directly:</p>";
echo "<p><a href='../uploads/justifications/just_160_3209_1764192708.jpg' target='_blank'>Direct link to file</a></p>";

echo "<h3>Secure Download Test</h3>";
echo "<p><a href='download_document.php?file=just_160_3209_1764192708.jpg' target='_blank'>Secure download link</a></p>";

?>

<style>
body { font-family: Arial, sans-serif; max-width: 800px; margin: 20px auto; padding: 20px; }
</style>