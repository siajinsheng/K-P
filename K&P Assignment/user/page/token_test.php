<?php
$_title = 'K&P - Token Test';
require_once '../../_base.php';

// Display variables for debugging
echo "<h1>Token Verification Test</h1>";

// Get token from URL or form
$token = isset($_GET['token']) ? $_GET['token'] : '';

// Form to test a token manually
echo "<form method='get' style='margin-bottom:20px;'>";
echo "<label>Test a token: </label>";
echo "<input type='text' name='token' value='" . htmlspecialchars($token) . "' style='width:400px;margin:0 10px;'>";
echo "<button type='submit'>Test</button>";
echo "</form>";

if (!empty($token)) {
    echo "<h2>Testing token: " . htmlspecialchars($token) . "</h2>";
    
    try {
        // Check database connection
        echo "<p>Database connection: ";
        try {
            $test = $_db->query("SELECT 1");
            echo "<span style='color:green'>OK</span></p>";
        } catch (Exception $e) {
            echo "<span style='color:red'>Failed - " . $e->getMessage() . "</span></p>";
            exit;
        }
        
        // Try to find the user with this token
        $stm = $_db->prepare("SELECT * FROM user WHERE activation_token = ?");
        $stm->execute([$token]);
        $user = $stm->fetch();
        
        if ($user) {
            echo "<div style='background:#e6f7e6;padding:15px;border:1px solid #28a745;margin:20px 0;'>";
            echo "<h3 style='color:#28a745'>User found! Details:</h3>";
            echo "<p><strong>User ID:</strong> {$user->user_id}</p>";
            echo "<p><strong>Name:</strong> {$user->user_name}</p>";
            echo "<p><strong>Email:</strong> {$user->user_Email}</p>";
            echo "<p><strong>Status:</strong> {$user->status}</p>";
            
            if ($user->activation_expiry) {
                $expiry = new DateTime($user->activation_expiry);
                $now = new DateTime();
                echo "<p><strong>Token Expiry:</strong> {$user->activation_expiry} ";
                if ($now > $expiry) {
                    echo "<span style='color:red'>(Expired)</span>";
                } else {
                    echo "<span style='color:green'>(Valid)</span>";
                }
                echo "</p>";
            } else {
                echo "<p><strong>Token Expiry:</strong> Not set</p>";
            }
            
            echo "</div>";
            
            // Add activation button
            echo "<form method='post'>";
            echo "<input type='hidden' name='user_id' value='{$user->user_id}'>";
            echo "<button type='submit' name='activate' style='background:#28a745;color:white;padding:10px 20px;border:none;border-radius:5px;cursor:pointer;'>Manually Activate This Account</button>";
            echo "</form>";
        } else {
            echo "<div style='background:#f8d7da;padding:15px;border:1px solid #dc3545;margin:20px 0;'>";
            echo "<h3 style='color:#dc3545'>No user found with this token</h3>";
            
            // Try to find any token that might be similar
            $stm = $_db->prepare("SELECT user_id, user_name, activation_token FROM user WHERE activation_token LIKE ?");
            $stm->execute(['%' . substr($token, 0, 10) . '%']);
            $similar = $stm->fetchAll();
            
            if (count($similar) > 0) {
                echo "<h4>Found " . count($similar) . " similar tokens:</h4>";
                echo "<table border='1' cellpadding='5' style='border-collapse:collapse;width:100%;'>";
                echo "<tr><th>User ID</th><th>Name</th><th>Token</th></tr>";
                foreach ($similar as $s) {
                    echo "<tr>";
                    echo "<td>{$s->user_id}</td>";
                    echo "<td>{$s->user_name}</td>";
                    echo "<td>{$s->activation_token}</td>";
                    echo "</tr>";
                }
                echo "</table>";
            } else {
                echo "<p>No similar tokens found.</p>";
            }
            
            echo "</div>";
        }
        
    } catch (Exception $e) {
        echo "<div style='background:#f8d7da;padding:15px;border:1px solid #dc3545;margin:20px 0;'>";
        echo "<h3 style='color:#dc3545'>Error:</h3>";
        echo "<p>" . $e->getMessage() . "</p>";
        echo "</div>";
    }
}

// Handle manual activation
if (isset($_POST['activate']) && isset($_POST['user_id'])) {
    try {
        $user_id = $_POST['user_id'];
        
        $stm = $_db->prepare("
            UPDATE user
            SET status = 'Active', activation_token = NULL, activation_expiry = NULL
            WHERE user_id = ?
        ");
        $stm->execute([$user_id]);
        
        echo "<div style='background:#e6f7e6;padding:15px;border:1px solid #28a745;margin:20px 0;'>";
        echo "<h3 style='color:#28a745'>Account successfully activated!</h3>";
        echo "<p>User ID: {$user_id} has been set to Active status.</p>";
        echo "</div>";
    } catch (Exception $e) {
        echo "<div style='background:#f8d7da;padding:15px;border:1px solid #dc3545;margin:20px 0;'>";
        echo "<h3 style='color:#dc3545'>Activation Error:</h3>";
        echo "<p>" . $e->getMessage() . "</p>";
        echo "</div>";
    }
}

// Display all pending users
echo "<h2>Current Pending Users</h2>";

try {
    $stm = $_db->query("SELECT user_id, user_name, user_Email, activation_token, activation_expiry FROM user WHERE status = 'Pending'");
    $pending = $stm->fetchAll();
    
    if (count($pending) > 0) {
        echo "<table border='1' cellpadding='5' style='border-collapse:collapse;width:100%;'>";
        echo "<tr><th>User ID</th><th>Name</th><th>Email</th><th>Token</th><th>Expiry</th><th>Actions</th></tr>";
        
        foreach ($pending as $p) {
            echo "<tr>";
            echo "<td>{$p->user_id}</td>";
            echo "<td>{$p->user_name}</td>";
            echo "<td>{$p->user_Email}</td>";
            echo "<td style='max-width:200px;overflow:hidden;text-overflow:ellipsis;'>{$p->activation_token}</td>";
            echo "<td>{$p->activation_expiry}</td>";
            echo "<td>";
            echo "<form method='post' style='display:inline;'>";
            echo "<input type='hidden' name='user_id' value='{$p->user_id}'>";
            echo "<button type='submit' name='activate' style='background:#28a745;color:white;padding:5px 10px;border:none;border-radius:3px;cursor:pointer;'>Activate</button>";
            echo "</form>";
            echo "</td>";
            echo "</tr>";
        }
        
        echo "</table>";
    } else {
        echo "<p>No pending users found.</p>";
    }
} catch (Exception $e) {
    echo "<p>Error fetching pending users: " . $e->getMessage() . "</p>";
}
?>