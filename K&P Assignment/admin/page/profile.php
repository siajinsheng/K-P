<?php
require_once '../../_base.php'; // Include your base file with all functions
require 'header.php';
auth('admin', 'staff');      // Only allow users with Admin or Staff roles
$user = $_SESSION['user'];   // User object updated in the auth() function
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link type="text/css" rel="stylesheet" href="../css/cusStaff.css" />
    <title>Profile</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f4f4f4;
            margin: 0;
            padding: 0;
        }
        .profile-container {
            max-width: 800px;
            margin: 50px auto;
            background: #fff;
            padding: 20px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
            border-radius: 8px;
        }
        .profile-photo{
            max-width:100%;
        }
        h1 {
            text-align: center;
            color: #333;
            margin-bottom: 20px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        th, td {
            padding: 10px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        th {
            background-color: #f2f2f2;
        }
        .error {
            color: red;
            font-size: 0.9em;
        }
        .button {
            display: block;
            width: 100%;
            padding: 10px;
            background-color: #4CAF50;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
            text-align: center;
            text-decoration: none;
            margin-top: 20px;
        }
        .button:hover {
            background-color: #45a049;
        }
        img {
            display: block;
            margin-top: 10px;   
        }
    </style>
</head>
<body>
    <div class="profile-container">
        <h1>Profile</h1>
        <table>
            <tr>
                <th>Profile Picture</th>
                <td>
                    <?php if (!empty($user->user_profile_pic)): ?>
                        <img src="../pic/<?php echo htmlspecialchars($user->user_profile_pic); ?>" alt="Profile Picture">
                    <?php else: ?>
                        No Picture Available
                    <?php endif; ?>
                </td>
            </tr>
            <tr>
                <th>ID</th>
                <td><?php echo htmlspecialchars($user->user_id); ?></td>
            </tr>
            <tr>
                <th>Name</th>
                <td><?php echo htmlspecialchars($user->user_name); ?></td>
            </tr>
            <tr>
                <th>Email</th>
                <td><?php echo htmlspecialchars($user->user_Email); ?></td>
            </tr>
            <tr>
                <th>Phone</th>
                <td><?php echo htmlspecialchars($user->user_phone); ?></td>
            </tr>
            <tr>
                <th>Password</th>
                <td><?php echo htmlspecialchars($user->user_password); ?></td>
            </tr>
            <tr>
                <th>Status</th>
                <td><?php echo htmlspecialchars($user->status); ?></td>
            </tr>
            <tr>
                <th>Role</th>
                <td><?php echo htmlspecialchars($user->role); ?></td>
            </tr>
            <tr>
                <th>Last Update</th>
                <td><?php echo htmlspecialchars($user->user_update_time); ?></td>
            </tr>
        </table>
        <a href="updateProfile.php" class="button">Update Profile</a>
    </div>
</body>
</html>
<?php
include('footer.php');
?>
