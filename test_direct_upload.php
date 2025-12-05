<?php
/**
 * Direct Upload Test - Bypass all helpers
 */

session_start();

echo "<h1>Direct Upload Test</h1>";

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['photo'])) {
    echo "<h2>Upload Details:</h2>";
    echo "<pre>";
    print_r($_FILES['photo']);
    echo "</pre>";
    
    $file = $_FILES['photo'];
    
    if ($file['error'] === UPLOAD_ERR_OK) {
        $uploadDir = 'uploads/profiles/';
        $filename = 'test_direct_' . time() . '.jpg';
        $filepath = $uploadDir . $filename;
        
        echo "<h3>Attempting Upload:</h3>";
        echo "From: " . $file['tmp_name'] . "<br>";
        echo "To: " . $filepath . "<br>";
        echo "File exists in temp: " . (file_exists($file['tmp_name']) ? 'YES' : 'NO') . "<br>";
        
        if (move_uploaded_file($file['tmp_name'], $filepath)) {
            echo "<p style='color: green; font-weight: bold; font-size: 20px;'>✓ SUCCESS!</p>";
            echo "<p>File saved to: $filepath</p>";
            echo "<p>File size: " . filesize($filepath) . " bytes</p>";
            echo "<h3>Uploaded Image:</h3>";
            echo "<img src='$filepath?v=" . time() . "' style='max-width: 300px; border: 3px solid green;'>";
        } else {
            echo "<p style='color: red; font-weight: bold; font-size: 20px;'>✗ FAILED!</p>";
            echo "<p>move_uploaded_file returned false</p>";
            
            // Check permissions
            echo "<h3>Directory Permissions:</h3>";
            echo "Directory writable: " . (is_writable($uploadDir) ? 'YES' : 'NO') . "<br>";
            echo "Directory readable: " . (is_readable($uploadDir) ? 'YES' : 'NO') . "<br>";
            
            // Try to create a test file
            $testFile = $uploadDir . 'test.txt';
            if (file_put_contents($testFile, 'test')) {
                echo "Can write text files: YES<br>";
                unlink($testFile);
            } else {
                echo "Can write text files: NO<br>";
            }
        }
    } else {
        echo "<p style='color: red;'>Upload Error Code: " . $file['error'] . "</p>";
        $errors = [
            UPLOAD_ERR_INI_SIZE => 'File exceeds upload_max_filesize in php.ini',
            UPLOAD_ERR_FORM_SIZE => 'File exceeds MAX_FILE_SIZE in HTML form',
            UPLOAD_ERR_PARTIAL => 'File was only partially uploaded',
            UPLOAD_ERR_NO_FILE => 'No file was uploaded',
            UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder',
            UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
            UPLOAD_ERR_EXTENSION => 'File upload stopped by extension'
        ];
        echo "<p>" . ($errors[$file['error']] ?? 'Unknown error') . "</p>";
    }
    
    echo "<hr>";
    echo "<h3>Current files in uploads/profiles/:</h3>";
    $files = glob('uploads/profiles/*');
    foreach ($files as $f) {
        echo "<p>$f</p>";
    }
} else {
    ?>
    <form method="POST" enctype="multipart/form-data">
        <h3>Upload a Photo Directly:</h3>
        <input type="file" name="photo" accept="image/*" required><br><br>
        <button type="submit" style="padding: 10px 20px; background: #4da6ff; color: white; border: none; cursor: pointer;">Upload Now</button>
    </form>
    
    <hr>
    <h3>PHP Settings:</h3>
    <pre>
upload_max_filesize: <?php echo ini_get('upload_max_filesize'); ?>

post_max_size: <?php echo ini_get('post_max_size'); ?>

max_file_uploads: <?php echo ini_get('max_file_uploads'); ?>

file_uploads: <?php echo ini_get('file_uploads') ? 'Enabled' : 'Disabled'; ?>

temp directory: <?php echo sys_get_temp_dir(); ?>
</pre>
    <?php
}
?>

