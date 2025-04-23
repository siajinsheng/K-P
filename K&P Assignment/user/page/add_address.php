<?php
require_once '../../_base.php';

// Ensure user is authenticated
safe_session_start();
if (!isset($_SESSION['user']) || empty($_SESSION['user']->user_id)) {
    temp('info', 'Please log in to manage addresses');
    redirect('login.php?redirect=' . urlencode($_SERVER['REQUEST_URI']));
}

$user_id = $_SESSION['user']->user_id;
$error_messages = [];

// Handle form submission
if (is_post()) {
    $address_name = trim($_POST['address_name'] ?? '');
    $recipient_name = trim($_POST['recipient_name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $address_line1 = trim($_POST['address_line1'] ?? '');
    $address_line2 = trim($_POST['address_line2'] ?? '');
    $city = trim($_POST['city'] ?? '');
    $state = trim($_POST['state'] ?? '');
    $postal_code = trim($_POST['postal_code'] ?? '');
    $country = trim($_POST['country'] ?? '');
    $is_default = isset($_POST['is_default']) ? 1 : 0;
    
    // Validate required fields
    if (empty($address_name)) {
        $error_messages[] = "Address label is required.";
    }
    
    if (empty($recipient_name)) {
        $error_messages[] = "Recipient name is required.";
    }
    
    if (empty($phone)) {
        $error_messages[] = "Phone number is required.";
    } else {
        $validated_phone = validate_malaysian_phone($phone);
        if ($validated_phone === false) {
            $error_messages[] = "Invalid phone number format. Please use a valid Malaysian phone number.";
        } else {
            $phone = $validated_phone;
        }
    }
    
    if (empty($address_line1)) {
        $error_messages[] = "Street address is required.";
    }
    
    if (empty($city)) {
        $error_messages[] = "City is required.";
    }
    
    if (empty($state)) {
        $error_messages[] = "State is required.";
    }
    
    if (empty($postal_code)) {
        $error_messages[] = "Postal code is required.";
    } elseif (!preg_match('/^[0-9]{5}$/', $postal_code)) {
        $error_messages[] = "Invalid postal code format. Please use a 5-digit postal code.";
    }
    
    if (empty($country)) {
        $error_messages[] = "Country is required.";
    }
    
    // If no errors, save the address
    if (empty($error_messages)) {
        try {
            $_db->beginTransaction();
            
            // Generate unique address ID
            $address_id = 'ADDR_' . date('YmdHis') . '_' . substr(md5(uniqid()), 0, 8);
            
            // If this is default address, clear default status from other addresses
            if ($is_default) {
                $stm = $_db->prepare("
                    UPDATE address
                    SET is_default = 0
                    WHERE user_id = ?
                ");
                $stm->execute([$user_id]);
            }
            
            // If this is the first address, make it default regardless of selection
            $stm = $_db->prepare("SELECT COUNT(*) FROM address WHERE user_id = ?");
            $stm->execute([$user_id]);
            $address_count = $stm->fetchColumn();
            
            if ($address_count === 0) {
                $is_default = 1;
            }
            
            // Insert new address
            $stm = $_db->prepare("
                INSERT INTO address (
                    address_id, user_id, address_name, recipient_name, phone,
                    address_line1, address_line2, city, state, post_code, country, is_default
                ) VALUES (
                    ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?
                )
            ");
            
            $stm->execute([
                $address_id, $user_id, $address_name, $recipient_name, $phone,
                $address_line1, $address_line2, $city, $state, $postal_code, $country, $is_default
            ]);
            
            $_db->commit();
            
            // Set success message and redirect
            temp('success', 'Address has been added successfully.');
            redirect('profile.php#addresses');
            
        } catch (PDOException $e) {
            if ($_db->inTransaction()) {
                $_db->rollBack();
            }
            
            error_log("Error adding address: " . $e->getMessage());
            $error_messages[] = "An error occurred while saving your address. Please try again.";
        }
    }
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>K&P - Add New Address</title>
    <link rel="stylesheet" href="../css/profile.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .address-form-container {
            max-width: 800px;
            margin: 50px auto;
            padding: 30px;
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 2px 15px rgba(0,0,0,0.1);
        }
        
        .form-header {
            display: flex;
            align-items: center;
            margin-bottom: 30px;
        }
        
        .form-header .back-link {
            margin-right: 15px;
            color: var(--primary-color);
            font-size: 1.5rem;
        }
        
        .form-header h1 {
            margin: 0;
            font-size: 2rem;
            color: var(--primary-color);
        }
        
        .address-form {
            display: grid;
            grid-gap: 20px;
        }
        
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            grid-gap: 20px;
        }
        
        .form-group {
            margin-bottom: 0;
        }
        
        .full-width {
            grid-column: 1 / -1;
        }
        
        .checkbox-group {
            display: flex;
            align-items: center;
            margin-top: 15px;
        }
        
        .checkbox-group input[type="checkbox"] {
            margin-right: 10px;
        }
        
        .form-actions {
            display: flex;
            justify-content: space-between;
            margin-top: 30px;
        }
    </style>
</head>
<body>
    <?php include('../header.php'); ?>

    <div class="address-form-container">
        <div class="form-header">
            <a href="profile.php#addresses" class="back-link">
                <i class="fas fa-arrow-left"></i>
            </a>
            <h1>Add New Address</h1>
        </div>
        
        <?php if (!empty($error_messages)): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-circle"></i> Please correct the following errors:
                <ul>
                    <?php foreach ($error_messages as $msg): ?>
                        <li><?= $msg ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
        
        <form method="post" class="address-form">
            <div class="form-row">
                <div class="form-group">
                    <label for="address_name">Address Label</label>
                    <input type="text" id="address_name" name="address_name" value="<?= $_POST['address_name'] ?? '' ?>" placeholder="e.g., Home, Office, etc." required>
                </div>
                
                <div class="form-group">
                    <label for="recipient_name">Recipient Name</label>
                    <input type="text" id="recipient_name" name="recipient_name" value="<?= $_POST['recipient_name'] ?? '' ?>" required>
                </div>
            </div>
            
            <div class="form-group">
                <label for="phone">Recipient Phone Number</label>
                <input type="tel" id="phone" name="phone" value="<?= $_POST['phone'] ?? '' ?>" placeholder="e.g., 60123456789" required>
                <p class="form-hint">Enter Malaysian phone number with country code (e.g., 60123456789)</p>
            </div>
            
            <div class="form-group">
                <label for="address_line1">Address Line 1</label>
                <input type="text" id="address_line1" name="address_line1" value="<?= $_POST['address_line1'] ?? '' ?>" placeholder="Street address, P.O. box" required>
            </div>
            
            <div class="form-group">
                <label for="address_line2">Address Line 2 (Optional)</label>
                <input type="text" id="address_line2" name="address_line2" value="<?= $_POST['address_line2'] ?? '' ?>" placeholder="Apartment, suite, unit, building, floor, etc.">
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label for="city">City</label>
                    <input type="text" id="city" name="city" value="<?= $_POST['city'] ?? '' ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="state">State</label>
                    <select id="state" name="state" required>
                        <option value="">Select State</option>
                        <option value="Johor" <?= (isset($_POST['state']) && $_POST['state'] === 'Johor') ? 'selected' : '' ?>>Johor</option>
                        <option value="Kedah" <?= (isset($_POST['state']) && $_POST['state'] === 'Kedah') ? 'selected' : '' ?>>Kedah</option>
                        <option value="Kelantan" <?= (isset($_POST['state']) && $_POST['state'] === 'Kelantan') ? 'selected' : '' ?>>Kelantan</option>
                        <option value="Kuala Lumpur" <?= (isset($_POST['state']) && $_POST['state'] === 'Kuala Lumpur') ? 'selected' : '' ?>>Kuala Lumpur</option>
                        <option value="Labuan" <?= (isset($_POST['state']) && $_POST['state'] === 'Labuan') ? 'selected' : '' ?>>Labuan</option>
                        <option value="Melaka" <?= (isset($_POST['state']) && $_POST['state'] === 'Melaka') ? 'selected' : '' ?>>Melaka</option>
                        <option value="Negeri Sembilan" <?= (isset($_POST['state']) && $_POST['state'] === 'Negeri Sembilan') ? 'selected' : '' ?>>Negeri Sembilan</option>
                        <option value="Pahang" <?= (isset($_POST['state']) && $_POST['state'] === 'Pahang') ? 'selected' : '' ?>>Pahang</option>
                        <option value="Penang" <?= (isset($_POST['state']) && $_POST['state'] === 'Penang') ? 'selected' : '' ?>>Penang</option>
                        <option value="Perak" <?= (isset($_POST['state']) && $_POST['state'] === 'Perak') ? 'selected' : '' ?>>Perak</option>
                        <option value="Perlis" <?= (isset($_POST['state']) && $_POST['state'] === 'Perlis') ? 'selected' : '' ?>>Perlis</option>
                        <option value="Putrajaya" <?= (isset($_POST['state']) && $_POST['state'] === 'Putrajaya') ? 'selected' : '' ?>>Putrajaya</option>
                        <option value="Sabah" <?= (isset($_POST['state']) && $_POST['state'] === 'Sabah') ? 'selected' : '' ?>>Sabah</option>
                        <option value="Sarawak" <?= (isset($_POST['state']) && $_POST['state'] === 'Sarawak') ? 'selected' : '' ?>>Sarawak</option>
                        <option value="Selangor" <?= (isset($_POST['state']) && $_POST['state'] === 'Selangor') ? 'selected' : '' ?>>Selangor</option>
                        <option value="Terengganu" <?= (isset($_POST['state']) && $_POST['state'] === 'Terengganu') ? 'selected' : '' ?>>Terengganu</option>
                    </select>
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label for="postal_code">Postal Code</label>
                    <input type="text" id="postal_code" name="postal_code" value="<?= $_POST['postal_code'] ?? '' ?>" placeholder="5-digit postal code" required>
                </div>
                
                <div class="form-group">
                    <label for="country">Country</label>
                    <select id="country" name="country" required>
                        <option value="Malaysia" selected>Malaysia</option>
                    </select>
                </div>
            </div>
            
            <div class="checkbox-group">
                <input type="checkbox" id="is_default" name="is_default" <?= isset($_POST['is_default']) ? 'checked' : '' ?>>
                <label for="is_default">Set as default address</label>
            </div>
            
            <div class="form-actions">
                <a href="profile.php#addresses" class="btn outline-btn">Cancel</a>
                <button type="submit" class="btn primary-btn">Save Address</button>
            </div>
        </form>
    </div>
    
    <?php include('../footer.php'); ?>
</body>
</html>