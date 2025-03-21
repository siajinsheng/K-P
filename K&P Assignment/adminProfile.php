<?php
include('connectDatabase.php');
include('header(admin).php');

$errors = [];

$admin_id = $_SESSION['adminID'];
$query = "SELECT * FROM admin WHERE admin_id = '$admin_id'";
$result = mysqli_query($connection, $query);

if ($result) {
    $admin = mysqli_fetch_assoc($result);
} else {
    die("Failed to fetch admin details: " . mysqli_error($connection));
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link type="text/css" rel="stylesheet" href="CSS/appAdmin.css" />
    <title>Admin Profile</title>
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

        th,
        td {
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
            max-width: 100px;
            border-radius: 50%;
        }
    </style>
</head>

<body>
    <div class="profile-container">
        <h1>Admin Profile</h1>
        <table>
            <tr>
                <th>Profile Picture</th>
                <td>
                    <?php if ($admin['admin_profile_pic']) : ?>
                        <img src="<?php echo $admin['admin_profile_pic']; ?>" alt="Profile Picture">
                    <?php endif; ?>
                </td>
            </tr>
            <tr>
                <th>ID</th>
                <td><?php echo $admin['admin_id']; ?></td>
            </tr>
            <tr>
                <th>Name</th>
                <td><?php echo $admin['admin_name']; ?></td>
            </tr>
            <tr>
                <th>Email</th>
                <td><?php echo $admin['admin_email']; ?></td>
            </tr>
            <tr>
                <th>Contact</th>
                <td><?php echo $admin['admin_contact']; ?></td>
            </tr>
            <tr>
                <th>Password</th>
                <td><?php echo $admin['admin_password']; ?></td>
            </tr>
            <tr>
                <th>Status</th>
                <td><?php echo $admin['admin_status'] ? 'Active' : 'Inactive'; ?></td>
            </tr>
            <tr>
                <th>Role</th>
                <td><?php echo $admin['admin_role'] == 1 ? 'Admin' : 'User'; ?></td>
            </tr>
            <tr>
                <th>Last Update</th>
                <td><?php echo $admin['admin_update_time']; ?></td>
            </tr>
        </table>
        <a href="updateAdminProfile.php" class="button">Update Profile</a>
    </div>
</body>

</html>
<?php
include('footer(admin).php');
?>