<?php
$_title = 'K&P - Link Test';
require_once '../../_base.php';

// Generate a test token
$test_token = bin2hex(random_bytes(32));

// Test different URL formats
$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https://" : "http://";
$server = $_SERVER['HTTP_HOST'];

// Variations of the URL
$urls = [
    "Standard URL" => $protocol . $server . "/K&P%20Assignment/user/page/verify_email.php?token=" . $test_token,
    "URL with encoded space" => $protocol . $server . "/K&P%20Assignment/user/page/verify_email.php?token=" . $test_token,
    "URL with normal space" => $protocol . $server . "/K&P Assignment/user/page/verify_email.php?token=" . $test_token,
    "URL without Assignment" => $protocol . $server . "/K&P/user/page/verify_email.php?token=" . $test_token,
    "URL with different spaces" => $protocol . $server . "/K%26P%20Assignment/user/page/verify_email.php?token=" . $test_token,
    "URL with PHP directory" => __DIR__ . "/verify_email.php?token=" . $test_token,
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?= $_title ?></title>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; padding: 20px; }
        h1 { color: #4a6fa5; }
        table { border-collapse: collapse; width: 100%; margin-bottom: 20px; }
        th, td { border: 1px solid #ddd; padding: 8px; }
        th { background-color: #f2f2f2; text-align: left; }
        .test-link { margin-bottom: 10px; }
        .test-link a { display: inline-block; margin-right: 10px; }
    </style>
</head>
<body>
    <h1>URL Path Testing Tool</h1>
    
    <h2>Test Links</h2>
    <p>Click each link to test if the path is correct:</p>
    
    <?php foreach ($urls as $description => $url): ?>
        <div class="test-link">
            <strong><?= $description ?>:</strong><br>
            <a href="<?= $url ?>" target="_blank">Test this link</a>
            <code><?= htmlspecialchars($url) ?></code>
        </div>
    <?php endforeach; ?>
    
    <h2>Server Information</h2>
    <table>
        <tr>
            <th>Variable</th>
            <th>Value</th>
        </tr>
        <tr>
            <td>SERVER_NAME</td>
            <td><?= $_SERVER['SERVER_NAME'] ?></td>
        </tr>
        <tr>
            <td>HTTP_HOST</td>
            <td><?= $_SERVER['HTTP_HOST'] ?></td>
        </tr>
        <tr>
            <td>DOCUMENT_ROOT</td>
            <td><?= $_SERVER['DOCUMENT_ROOT'] ?></td>
        </tr>
        <tr>
            <td>SCRIPT_NAME</td>
            <td><?= $_SERVER['SCRIPT_NAME'] ?></td>
        </tr>
        <tr>
            <td>PHP_SELF</td>
            <td><?= $_SERVER['PHP_SELF'] ?></td>
        </tr>
        <tr>
            <td>REQUEST_URI</td>
            <td><?= $_SERVER['REQUEST_URI'] ?></td>
        </tr>
        <tr>
            <td>__FILE__</td>
            <td><?= __FILE__ ?></td>
        </tr>
        <tr>
            <td>__DIR__</td>
            <td><?= __DIR__ ?></td>
        </tr>
    </table>
    
    <h2>File Check</h2>
    <?php
    $verification_file = __DIR__ . '/verify_email.php';
    if (file_exists($verification_file)) {
        echo "<p style='color: green;'>The verification file exists at: $verification_file</p>";
    } else {
        echo "<p style='color: red;'>The verification file does NOT exist at: $verification_file</p>";
    }
    ?>
</body>
</html>