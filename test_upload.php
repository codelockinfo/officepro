<?php
/**
 * Test File Upload
 */

session_start();

echo "<h1>Upload Test</h1>";

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['test_file'])) {
    echo "<h2>Upload Attempt</h2>";
    echo "<pre>";
    print_r($_FILES['test_file']);
    echo "</pre>";
    
    $uploadDir = 'uploads/profiles/';
    
    echo "<h3>Directory Info:</h3>";
    echo "Directory: " . $uploadDir . "<br>";
    echo "Exists: " . (file_exists($uploadDir) ? 'YES' : 'NO') . "<br>";
    echo "Is Dir: " . (is_dir($uploadDir) ? 'YES' : 'NO') . "<br>";
    echo "Writable: " . (is_writable($uploadDir) ? 'YES' : 'NO') . "<br>";
    echo "Absolute Path: " . realpath($uploadDir) . "<br>";
    
    if ($_FILES['test_file']['error'] === UPLOAD_ERR_OK) {
        $filename = 'test_' . time() . '.jpg';
        $filepath = $uploadDir . $filename;
        
        echo "<h3>Attempting to save to: $filepath</h3>";
        
        if (move_uploaded_file($_FILES['test_file']['tmp_name'], $filepath)) {
            echo "<p style='color: green; font-weight: bold;'>✓ SUCCESS! File uploaded to: $filepath</p>";
            echo "<img src='$filepath' style='max-width: 200px;'>";
        } else {
            echo "<p style='color: red; font-weight: bold;'>✗ FAILED to upload file</p>";
            echo "Error: " . $_FILES['test_file']['error'];
        }
    } else {
        echo "<p style='color: red;'>Upload error code: " . $_FILES['test_file']['error'] . "</p>";
    }
} else {
    ?>
    <form method="POST" enctype="multipart/form-data">
        <label>Choose an image:</label><br>
        <input type="file" name="test_file" accept="image/*" required><br><br>
        <button type="submit">Test Upload</button>
    </form>
    
    <hr>
    
    <h3>Current Files in uploads/profiles:</h3>
    <ul>
    <?php
    $files = glob('uploads/profiles/*');
    if ($files) {
        foreach ($files as $file) {
            echo "<li>$file (" . filesize($file) . " bytes)</li>";
        }
    } else {
        echo "<li>No files found</li>";
    }
    ?>
    </ul>
    <?php
}
?>


