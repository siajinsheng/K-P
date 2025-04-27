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
$sql = "SELECT p.product_id, p.product_name, p.product_price, p.product_pic1, p.product_pic2, p.product_pic3, 
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

    <div class="video-container">
        <iframe 
            width="100%" 
            height="450" 
            src="https://www.youtube.com/embed/9Pbe4DyMQYw?si=Qan6a6KWCQ2XVuYe&autoplay=1&mute=1"
            title="YouTube video player" 
            frameborder="0" 
            allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" 
            allowfullscreen>
        </iframe>
    </div>

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
                                    <img src="../../img/<?= $product->product_pic1 ?>" alt="<?= $product->product_name ?>" class="primary-image">

                                    <!-- Add hover images only if they exist -->
                                    <?php if (!empty($product->product_pic2)): ?>
                                        <img src="../../img/<?= $product->product_pic2 ?>" alt="<?= $product->product_name ?>" class="hover-image hover-image-1">
                                    <?php endif; ?>

                                    <?php if (!empty($product->product_pic3)): ?>
                                        <img src="../../img/<?= $product->product_pic3 ?>" alt="<?= $product->product_name ?>" class="hover-image hover-image-2">
                                    <?php endif; ?>

                                    <!-- Add navigation dots if we have additional images -->
                                    <?php if (!empty($product->product_pic2) || !empty($product->product_pic3)): ?>
                                        <div class="image-dots">
                                            <span class="dot active" data-index="0"></span>
                                            <?php if (!empty($product->product_pic2)): ?>
                                                <span class="dot" data-index="1"></span>
                                            <?php endif; ?>
                                            <?php if (!empty($product->product_pic3)): ?>
                                                <span class="dot" data-index="2"></span>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div class="product-info">
                                    <h3 class="product-name"><?= $product->product_name ?></h3>
                                    <p class="product-price">RM <?= number_format($product->product_price, 2) ?></p>
                                </div>
                            </a>
                            <button class="add-to-cart-btn" data-product="<?= $product->product_id ?>" data-name="<?= $product->product_name ?>">
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

    <div id="size-selection-modal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Select Size</h3>
                <span class="close-modal">&times;</span>
            </div>
            <div class="modal-body">
                <p id="product-name"></p>
                <div class="size-options">
                    <div class="size-option">
                        <input type="radio" name="size" id="size-S" value="S">
                        <label for="size-S">S</label>
                    </div>
                    <div class="size-option">
                        <input type="radio" name="size" id="size-M" value="M">
                        <label for="size-M">M</label>
                    </div>
                    <div class="size-option">
                        <input type="radio" name="size" id="size-L" value="L" checked>
                        <label for="size-L">L</label>
                    </div>
                    <div class="size-option">
                        <input type="radio" name="size" id="size-XL" value="XL">
                        <label for="size-XL">XL</label>
                    </div>
                    <div class="size-option">
                        <input type="radio" name="size" id="size-XXL" value="XXL">
                        <label for="size-XXL">XXL</label>
                    </div>
                </div>
                <p class="size-error" style="color: red; display: none;">Please select a size</p>
            </div>
            <div class="modal-footer">
                <button id="add-to-cart-confirm" class="primary-btn">Add to Cart</button>
            </div>
        </div>
    </div>

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

        // Product image switching on hover
        document.querySelectorAll('.product-card').forEach(card => {
            let currentIndex = 0;
            const imageContainer = card.querySelector('.product-image');
            const dots = card.querySelectorAll('.dot');
            const images = [
                card.querySelector('.primary-image'),
                card.querySelector('.hover-image-1'),
                card.querySelector('.hover-image-2')
            ].filter(img => img); // Filter out null values

            // Update active image based on index
            function showImage(index) {
                if (index >= images.length) return;

                // Hide all images
                images.forEach(img => {
                    if (img) img.style.opacity = 0;
                });

                // Show selected image
                images[index].style.opacity = 1;

                // Update dots
                dots.forEach(dot => dot.classList.remove('active'));
                if (dots[index]) dots[index].classList.add('active');

                // Update current index
                currentIndex = index;
            }

            // Set initial state
            showImage(0);

            // Add click event to dots
            dots.forEach((dot, index) => {
                dot.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    showImage(index);
                });
            });

            // Auto rotate images on hover
            let hoverInterval;
            imageContainer.addEventListener('mouseenter', function() {
                if (images.length > 1) {
                    hoverInterval = setInterval(() => {
                        let nextIndex = (currentIndex + 1) % images.length;
                        showImage(nextIndex);
                    }, 1500); // Switch every 1.5 seconds
                }
            });

            imageContainer.addEventListener('mouseleave', function() {
                if (hoverInterval) {
                    clearInterval(hoverInterval);
                    showImage(0); // Reset to first image
                }
            });
        });

        // Size selection modal functionality
        const modal = document.getElementById('size-selection-modal');
        const addToCartButtons = document.querySelectorAll('.add-to-cart-btn');
        const closeModal = document.querySelector('.close-modal');
        const addToCartConfirm = document.getElementById('add-to-cart-confirm');
        const productNameElement = document.getElementById('product-name');
        const sizeError = document.querySelector('.size-error');

        let currentProductId = null;
        let currentProductName = null;

        // Open modal when clicking ADD button
        addToCartButtons.forEach(button => {
            button.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                currentProductId = this.getAttribute('data-product');
                currentProductName = this.getAttribute('data-name');
                productNameElement.textContent = currentProductName;

                // Reset size selection
                document.querySelector('#size-L').checked = true;
                sizeError.style.display = 'none';

                // Show the modal
                modal.style.display = 'block';
                document.body.style.overflow = 'hidden'; // Prevent scrolling
            });
        });

        // Close modal when clicking X
        closeModal.addEventListener('click', function() {
            modal.style.display = 'none';
            document.body.style.overflow = ''; // Enable scrolling
        });

        // Close modal when clicking outside
        window.addEventListener('click', function(event) {
            if (event.target === modal) {
                modal.style.display = 'none';
                document.body.style.overflow = ''; // Enable scrolling
            }
        });

        // Add to cart when size is selected and confirmed
        addToCartConfirm.addEventListener('click', function() {
            const selectedSize = document.querySelector('input[name="size"]:checked');

            if (!selectedSize) {
                sizeError.style.display = 'block';
                return;
            }

            const size = selectedSize.value;

            // Show loading state on button
            const originalText = this.textContent;
            this.textContent = 'Adding...';
            this.disabled = true;

            // First, get the quantity_id for the selected size
            fetch('get_quantity_id.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `product_id=${currentProductId}&size=${size}`,
                    credentials: 'same-origin'
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.quantity_id) {
                        // Now we have the quantity_id, add to cart
                        return fetch('add-to-cart.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/x-www-form-urlencoded',
                            },
                            body: `product_id=${currentProductId}&quantity_id=${data.quantity_id}&quantity=1`,
                            credentials: 'same-origin'
                        });
                    } else {
                        throw new Error(data.message || 'Could not find product with selected size');
                    }
                })
                .then(response => response.json())
                .then(data => {
                    // Reset button
                    this.textContent = originalText;
                    this.disabled = false;

                    // Close modal
                    modal.style.display = 'none';
                    document.body.style.overflow = '';

                    if (data.success) {
                        // Show success notification
                        showNotification(`Added "${currentProductName}" to your cart!`, 'success');

                        // Update cart count in header if exists
                        const cartCountElement = document.querySelector('.cart-count');
                        if (cartCountElement && data.cartTotalItems !== undefined) {
                            cartCountElement.textContent = data.cartTotalItems;
                            cartCountElement.style.display = 'flex';
                        }
                    } else {
                        // Handle authentication error separately
                        if (data.message && data.message.includes('log in')) {
                            showNotification('Please log in to add items to your cart', 'error');
                            setTimeout(() => {
                                window.location.href = 'login.php';
                            }, 2000);
                        } else {
                            // Show error notification
                            showNotification(data.message || 'Error adding to cart', 'error');
                        }
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    this.textContent = originalText;
                    this.disabled = false;
                    modal.style.display = 'none';
                    document.body.style.overflow = '';
                    showNotification('An error occurred. Please try again.', 'error');
                });
        });

        // Show notification function
        function showNotification(message, type = 'success') {
            // Check if notification container exists
            let notificationContainer = document.querySelector('.notification-container');

            if (!notificationContainer) {
                notificationContainer = document.createElement('div');
                notificationContainer.className = 'notification-container';
                document.body.appendChild(notificationContainer);
            }

            // Create notification element
            const notification = document.createElement('div');
            notification.className = `notification ${type}`;

            // Add icon based on type
            let icon = 'check-circle';
            if (type === 'error') {
                icon = 'times-circle';
            } else if (type === 'warning') {
                icon = 'exclamation-circle';
            } else if (type === 'info') {
                icon = 'info-circle';
            }

            notification.innerHTML = `
                <i class="fas fa-${icon}"></i>
                <span>${message}</span>
            `;

            notificationContainer.appendChild(notification);

            // Show notification with animation
            setTimeout(() => {
                notification.classList.add('show');
            }, 10);

            // Remove notification after few seconds
            setTimeout(() => {
                notification.classList.remove('show');
                setTimeout(() => {
                    notification.remove();
                }, 300);
            }, 3000);
        }
    </script>
</body>

</html>