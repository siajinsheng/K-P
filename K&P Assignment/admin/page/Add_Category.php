<?php
$_title = 'Add Category';
require '../../_base.php';
auth(0, 1);

// Function to generate a new category ID
function generateCategoryId() {
    global $_db;
    
    // Get the highest existing category_id
    $stmt = $_db->query("SELECT category_id FROM category ORDER BY category_id DESC LIMIT 1");
    $highestId = $stmt->fetchColumn();
    
    if (!$highestId) {
        // If no categories exist yet, start with CAT001
        return 'CAT001';
    }
    
    // Extract the numeric part and increment it
    if (preg_match('/CAT(\d+)/', $highestId, $matches)) {
        $nextNum = intval($matches[1]) + 1;
        return 'CAT' . str_pad($nextNum, 3, '0', STR_PAD_LEFT);
    } else {
        // Fallback if the format is unexpected
        return 'CAT001';
    }
}

$errors = [];
$success = false;
$popup_message = '';

if (is_post()) {
    // Get the category name from the form
    $category_name = post('category_name');
    
    // Validate the category name
    if (empty($category_name)) {
        $errors[] = 'Category name is required';
        $popup_message = 'Error: Category name is required';
    } else {
        // Check if the category name already exists
        $check_stmt = $_db->prepare("SELECT COUNT(*) FROM category WHERE category_name = ?");
        $check_stmt->execute([$category_name]);
        if ($check_stmt->fetchColumn() > 0) {
            $errors[] = 'Category with this name already exists';
            $popup_message = 'Error: Category with this name already exists';
        } else {
            try {
                // Generate a new category ID
                $category_id = generateCategoryId();
                
                // Insert the new category
                $stmt = $_db->prepare("INSERT INTO category (category_id, category_name) VALUES (?, ?)");
                $result = $stmt->execute([$category_id, $category_name]);
                
                if ($result) {
                    $success = true;
                    $popup_message = "Success: Category '$category_name' has been successfully added with ID: $category_id";
                    temp('info', "Category '$category_name' has been successfully added with ID: $category_id");
                    // We'll use JavaScript to show popup before redirect
                } else {
                    $errors[] = 'Failed to add category';
                    $popup_message = 'Error: Failed to add category';
                }
            } catch (PDOException $e) {
                $errors[] = 'Database error: ' . $e->getMessage();
                $popup_message = 'Error: Database error occurred';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $_title ?></title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css" rel="stylesheet">
    <style>
        .btn-black {
            background-color: black;
            color: white;
            transition: all 0.3s ease;
        }

        .btn-black:hover {
            background-color: #333;
        }

        input, select, textarea {
            border-color: #000;
            transition: all 0.3s ease;
        }

        input:focus, select:focus, textarea:focus {
            outline: none;
            border-color: #000;
            box-shadow: 0 0 0 3px rgba(0, 0, 0, 0.3);
        }
        
        /* Custom modal styles */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
        }
        
        .modal-content {
            background-color: white;
            margin: 15% auto;
            padding: 20px;
            border-radius: 8px;
            max-width: 500px;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
            position: relative;
        }
        
        .close-modal {
            position: absolute;
            right: 15px;
            top: 10px;
            font-size: 24px;
            cursor: pointer;
        }
    </style>
</head>
<body class="bg-gray-100">
    <!-- Modal for popup messages -->
    <div id="messageModal" class="modal">
        <div class="modal-content">
            <span class="close-modal">&times;</span>
            <div id="modalIcon" class="text-center text-4xl mb-2"></div>
            <h2 id="modalTitle" class="text-xl font-bold text-center mb-2"></h2>
            <p id="modalMessage" class="text-center"></p>
            <div class="text-center mt-4">
                <button id="modalButton" class="btn-black hover:bg-gray-800 text-white font-bold py-2 px-4 rounded">
                    OK
                </button>
            </div>
        </div>
    </div>

    <div class="container mx-auto px-4 py-8">
        <h1 class="text-3xl font-bold text-center mb-6">Add New Category</h1>
        
        <?php if (!empty($errors)): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                <strong class="font-bold">Error!</strong>
                <ul class="list-disc pl-5">
                    <?php foreach ($errors as $error): ?>
                        <li><?= htmlspecialchars($error) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
                <strong class="font-bold">Success!</strong>
                <p>Category has been added successfully.</p>
            </div>
        <?php endif; ?>
        
        <div class="bg-white shadow-md rounded-lg p-6 max-w-md mx-auto">
            <form action="Add_Category.php" method="post" class="space-y-4">
                <div>
                    <label for="category_name" class="block text-sm font-medium text-gray-700">Category Name</label>
                    <input type="text" id="category_name" name="category_name" required
                           class="mt-1 block w-full rounded-md border-gray-300 border-2 p-2 shadow-sm focus:border-black focus:ring focus:ring-black focus:ring-opacity-50">
                    <p class="mt-1 text-sm text-gray-500">Category ID will be automatically generated</p>
                </div>
                
                <div class="flex justify-between pt-4">
                    <a href="product.php" class="bg-gray-300 hover:bg-gray-400 text-black font-bold py-2 px-4 rounded transition">
                        Cancel
                    </a>
                    <button type="submit" class="btn-black hover:bg-gray-800 text-white font-bold py-2 px-4 rounded">
                        Add Category
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Modal functionality
        const modal = document.getElementById('messageModal');
        const modalTitle = document.getElementById('modalTitle');
        const modalMessage = document.getElementById('modalMessage');
        const modalIcon = document.getElementById('modalIcon');
        const modalButton = document.getElementById('modalButton');
        const closeBtn = document.querySelector('.close-modal');
        
        // Close modal when clicking the X or the OK button
        closeBtn.onclick = function() {
            closeModal();
        }
        
        modalButton.onclick = function() {
            closeModal();
        }
        
        // Close modal when clicking outside of it
        window.onclick = function(event) {
            if (event.target == modal) {
                closeModal();
            }
        }
        
        function closeModal() {
            modal.style.display = "none";
            <?php if ($success): ?>
            // Redirect after showing success message
            window.location.href = 'product.php';
            <?php endif; ?>
        }
        
        function showModal(isSuccess, message) {
            modalTitle.textContent = isSuccess ? "Success" : "Error";
            modalMessage.textContent = message;
            modalIcon.innerHTML = isSuccess 
                ? '<i class="fas fa-check-circle text-green-500"></i>' 
                : '<i class="fas fa-exclamation-circle text-red-500"></i>';
            modal.style.display = "block";
        }
        
        <?php if (!empty($popup_message)): ?>
        // Show modal with the message when page loads
        document.addEventListener('DOMContentLoaded', function() {
            showModal(<?= $success ? 'true' : 'false' ?>, <?= json_encode($popup_message) ?>);
        });
        <?php endif; ?>
    </script>
</body>
</html>