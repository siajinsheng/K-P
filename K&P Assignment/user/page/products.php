<?php
require_once '../../_base.php';
// Set page variables
$page_title = "Products";
$gender = req('gender', 'Man'); // Default to Man if no gender specified

// Get category filter if any
$category_id = req('category', '');

// Get sorting parameter (if any)
$sort = req('sort', ''); // Default to no specific sorting

// Build query to get products based on filters
$sql = "SELECT p.product_id, p.product_name, p.product_price, p.product_pic1, 
               c.category_name, c.category_id 
        FROM product p 
        JOIN category c ON p.category_id = c.category_id
        WHERE p.product_status = 'Available'";

// Apply filters for gender and category
$params = [];
$conditions = [];

// Add gender condition
$conditions[] = "p.product_type = ?";
$params[] = $gender;

// Add category condition if specified
if ($category_id) {
    $conditions[] = "p.category_id = ?";
    $params[] = $category_id;
}

// Add conditions to SQL query
if (!empty($conditions)) {
    $sql .= " AND " . implode(" AND ", $conditions);
}

// Apply sorting
switch ($sort) {
    case 'price_asc':
        $sql .= " ORDER BY p.product_price ASC";
        break;
    case 'price_desc':
        $sql .= " ORDER BY p.product_price DESC";
        break;
    default:
        // Default sorting (e.g., by product name or ID)
        $sql .= " ORDER BY p.product_name ASC";
        break;
}

// Prepare and execute query
$stm = $_db->prepare($sql);
$stm->execute($params);
$products = $stm->fetchAll();

// Get categories for filter menu
$cat_stm = $_db->prepare("SELECT * FROM category");
$cat_stm->execute();
$categories = $cat_stm->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>K&P - <?= $page_title ?></title>
    <link rel="stylesheet" href="../css/products.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <?php include('../header.php'); ?>

    <div class="gender-nav">
        <div class="container">
            <ul>
                <li class="<?= $gender === 'Man' ? 'active' : '' ?>">
                    <a href="?gender=Man">Men</a>
                </li>
                <li class="<?= $gender === 'Women' ? 'active' : '' ?>">
                    <a href="?gender=Women">Women</a>
                </li>
            </ul>
        </div>
    </div>

    <div class="container">
        <div class="page-header">
            <h1><?= $gender === 'Man' ? 'Men' : 'Women' ?></h1>
            
            <div class="filter-bar">
                <div class="filter-toggle">
                    <button id="filter-button">
                        <i class="fas fa-sliders-h"></i> Filter & Sort
                    </button>
                </div>
                
                <div class="sort-dropdown">
                    <select id="sort-select" class="sort-select">
                        <option value="">Sort by</option>
                        <option value="price_asc" <?= $sort === 'price_asc' ? 'selected' : '' ?>>Price: Low to High</option>
                        <option value="price_desc" <?= $sort === 'price_desc' ? 'selected' : '' ?>>Price: High to Low</option>
                    </select>
                </div>
            </div>
        </div>
        
        <div class="product-container">
            <div class="product-sidebar" id="product-sidebar">
                <div class="sidebar-header">
                    <h2>Filter</h2>
                    <button id="close-filter" class="close-filter">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                
                <div class="filter-section">
                    <h3>Category</h3>
                    <ul class="category-list">
                        <li><a href="?gender=<?= $gender ?>&sort=<?= $sort ?>" class="<?= $category_id === '' ? 'active' : '' ?>">All Products</a></li>
                        <?php foreach ($categories as $category): ?>
                        <li>
                            <a href="?gender=<?= $gender ?>&category=<?= $category->category_id ?>&sort=<?= $sort ?>" 
                               class="<?= $category_id === $category->category_id ? 'active' : '' ?>">
                                <?= $category->category_name ?>
                            </a>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                
                <div class="filter-section">
                    <h3>Sort By</h3>
                    <ul class="sort-list">
                        <li>
                            <a href="?gender=<?= $gender ?>&category=<?= $category_id ?>" class="<?= $sort === '' ? 'active' : '' ?>">
                                Default
                            </a>
                        </li>
                        <li>
                            <a href="?gender=<?= $gender ?>&category=<?= $category_id ?>&sort=price_asc" 
                               class="<?= $sort === 'price_asc' ? 'active' : '' ?>">
                                Price: Low to High
                            </a>
                        </li>
                        <li>
                            <a href="?gender=<?= $gender ?>&category=<?= $category_id ?>&sort=price_desc" 
                               class="<?= $sort === 'price_desc' ? 'active' : '' ?>">
                                Price: High to Low
                            </a>
                        </li>
                    </ul>
                </div>
            </div>
            
            <div class="products-grid">
                <?php if (count($products) > 0): ?>
                    <?php foreach ($products as $product): ?>
                        <div class="product-card">
                            <a href="product-details.php?id=<?= $product->product_id ?>" class="product-link">
                                <div class="product-image">
                                    <img src="../../img/<?= $product->product_pic1 ?>" alt="<?= $product->product_name ?>">
                                </div>
                                <div class="product-info">
                                    <h3 class="product-name"><?= $product->product_name ?></h3>
                                    <p class="product-price">RM <?= number_format($product->product_price, 2) ?></p>
                                </div>
                            </a>
                            <button class="add-to-cart" data-product="<?= $product->product_id ?>">
                                <span class="add-to-cart-text">ADD</span>
                                <i class="fas fa-shopping-bag"></i>
                            </button>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="no-products">
                        <p>No products found in this category.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <div class="overlay" id="overlay"></div>
    
    <?php include('../footer.php'); ?>

    <script>
        // Mobile filter toggle
        const filterButton = document.getElementById('filter-button');
        const sidebar = document.getElementById('product-sidebar');
        const overlay = document.getElementById('overlay');
        const closeFilter = document.getElementById('close-filter');
        
        filterButton.addEventListener('click', function() {
            sidebar.classList.add('open');
            overlay.classList.add('active');
            document.body.style.overflow = 'hidden';
        });
        
        closeFilter.addEventListener('click', function() {
            sidebar.classList.remove('open');
            overlay.classList.remove('active');
            document.body.style.overflow = '';
        });
        
        overlay.addEventListener('click', function() {
            sidebar.classList.remove('open');
            overlay.classList.remove('active');
            document.body.style.overflow = '';
        });
        
        // Sort dropdown functionality
        const sortSelect = document.getElementById('sort-select');
        if (sortSelect) {
            sortSelect.addEventListener('change', function() {
                // Get current URL parameters
                const urlParams = new URLSearchParams(window.location.search);
                
                // Update sort parameter
                if (this.value) {
                    urlParams.set('sort', this.value);
                } else {
                    urlParams.delete('sort');
                }
                
                // Redirect to the new URL
                window.location.href = `${window.location.pathname}?${urlParams.toString()}`;
            });
        }
        
        // Add to cart functionality
        const addToCartButtons = document.querySelectorAll('.add-to-cart');
        addToCartButtons.forEach(button => {
            button.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                
                const productId = this.getAttribute('data-product');
                const originalText = this.innerHTML;
                
                // Show loading state
                this.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
                this.disabled = true;
                
                // AJAX request to add item to cart
                fetch('add-to-cart.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `product_id=${productId}&quantity=1`,
                    credentials: 'same-origin'
                })
                .then(response => {
                    if (!response.ok) {
                        throw new Error(`Server responded with status: ${response.status}`);
                    }
                    return response.json();
                })
                .then(data => {
                    setTimeout(() => {
                        button.disabled = false;
                        
                        if (data.success) {
                            // Show success state
                            button.innerHTML = '<i class="fas fa-check"></i>';
                            
                            // Update cart count in header if exists
                            const cartCountElement = document.querySelector('.cart-count');
                            if (cartCountElement && data.cartTotalItems !== undefined) {
                                cartCountElement.textContent = data.cartTotalItems;
                            }
                            
                            // Reset button text after 2 seconds
                            setTimeout(() => {
                                button.innerHTML = originalText;
                            }, 2000);
                        } else {
                            // Show error state
                            button.innerHTML = '<i class="fas fa-times"></i>';
                            
                            // Handle authentication error separately
                            if (data.message && data.message.includes('log in')) {
                                window.location.href = 'login.php';
                            }
                            
                            // Reset button text after 2 seconds
                            setTimeout(() => {
                                button.innerHTML = originalText;
                            }, 2000);
                        }
                    }, 500);
                })
                .catch(error => {
                    console.error('Error:', error);
                    button.disabled = false;
                    button.innerHTML = '<i class="fas fa-times"></i>';
                    
                    // Reset button text after 2 seconds
                    setTimeout(() => {
                        button.innerHTML = originalText;
                    }, 2000);
                });
            });
        });
    </script>
</body>
</html>