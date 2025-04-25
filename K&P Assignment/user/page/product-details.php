<?php
require_once '../../_base.php';

// Start session
safe_session_start();

// Check if product ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    temp('error', 'Invalid product ID');
    redirect('products.php');
}

$product_id = $_GET['id'];

// Get product details
try {
    $stm = $_db->prepare("
        SELECT p.*, COUNT(DISTINCT r.review_id) as review_count, COALESCE(AVG(r.rating), 0) as avg_rating 
        FROM product p 
        LEFT JOIN review r ON p.product_id = r.product_id
        WHERE p.product_id = ?
        GROUP BY p.product_id
    ");
    $stm->execute([$product_id]);
    $product = $stm->fetch();
    
    if (!$product) {
        temp('error', 'Product not found');
        redirect('products.php');
    }
    
    // Get available sizes and stock
    $stm = $_db->prepare("
        SELECT size, product_stock 
        FROM quantity 
        WHERE product_id = ? AND product_stock > 0
        ORDER BY FIELD(size, 'XS', 'S', 'M', 'L', 'XL', 'XXL')
    ");
    $stm->execute([$product_id]);
    $sizes = $stm->fetchAll();
    
    // Get reviews
    $stm = $_db->prepare("
        SELECT r.*, u.user_name 
        FROM review r
        JOIN user u ON r.user_id = u.user_id
        WHERE r.product_id = ?
        ORDER BY r.created_at DESC
        LIMIT 5
    ");
    $stm->execute([$product_id]);
    $reviews = $stm->fetchAll();
    
} catch (PDOException $e) {
    error_log("Error fetching product details: " . $e->getMessage());
    temp('error', 'An error occurred while retrieving product information. Please try again.');
    redirect('products.php');
}

// Handle add to cart action
if (is_post() && isset($_POST['add_to_cart'])) {
    // Check if user is logged in
    if (!isset($_SESSION['user']) || empty($_SESSION['user']->user_id)) {
        temp('info', 'Please log in to add products to your shopping bag');
        redirect('login.php?redirect=' . urlencode($_SERVER['REQUEST_URI']));
    }
    
    $user_id = $_SESSION['user']->user_id;
    $size = $_POST['size'] ?? '';
    $quantity = isset($_POST['quantity']) ? intval($_POST['quantity']) : 1;
    
    // Validate inputs
    $errors = [];
    
    if (empty($size)) {
        $errors[] = 'Please select a size';
    }
    
    if ($quantity < 1) {
        $errors[] = 'Quantity must be at least 1';
    }
    
    if (empty($errors)) {
        try {
            // Check if product is available and has sufficient stock
            $stm = $_db->prepare("
                SELECT product_stock 
                FROM quantity 
                WHERE product_id = ? AND size = ?
            ");
            $stm->execute([$product_id, $size]);
            $available_stock = $stm->fetchColumn();
            
            if ($available_stock < $quantity) {
                temp('error', 'Not enough stock available. Currently available: ' . $available_stock);
                redirect($_SERVER['REQUEST_URI']);
            }
            
            // Check if product already exists in cart
            $stm = $_db->prepare("
                SELECT cart_id, quantity 
                FROM cart 
                WHERE user_id = ? AND product_id = ? AND size = ?
            ");
            $stm->execute([$user_id, $product_id, $size]);
            $existing_cart_item = $stm->fetch();
            
            if ($existing_cart_item) {
                // Update quantity
                $new_quantity = $existing_cart_item->quantity + $quantity;
                
                // Check if new quantity exceeds available stock
                if ($new_quantity > $available_stock) {
                    temp('error', 'Cannot add more items. Maximum available: ' . $available_stock);
                    redirect($_SERVER['REQUEST_URI']);
                }
                
                $stm = $_db->prepare("
                    UPDATE cart 
                    SET quantity = ?, added_time = NOW() 
                    WHERE cart_id = ?
                ");
                $stm->execute([$new_quantity, $existing_cart_item->cart_id]);
                
                temp('success', 'Item quantity updated in your shopping bag');
            } else {
                // Insert new cart item
                $stm = $_db->prepare("
                    INSERT INTO cart (user_id, product_id, quantity, size, added_time)
                    VALUES (?, ?, ?, ?, NOW())
                ");
                $stm->execute([$user_id, $product_id, $quantity, $size]);
                
                temp('success', 'Item added to your shopping bag');
            }
            
            redirect($_SERVER['REQUEST_URI']);
            
        } catch (PDOException $e) {
            error_log("Error adding to cart: " . $e->getMessage());
            temp('error', 'An error occurred while adding this item to your shopping bag. Please try again.');
            redirect($_SERVER['REQUEST_URI']);
        }
    } else {
        // Display errors
        foreach ($errors as $error) {
            temp('error', $error);
        }
        redirect($_SERVER['REQUEST_URI']);
    }
}

// Get any messages from session
$success_message = temp('success');
$error_message = temp('error');
$info_message = temp('info');
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>K&P - <?= htmlspecialchars($product->product_name ?? 'Product Details') ?></title>
    <link rel="stylesheet" href="../css/product-details.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <?php include('../header.php'); ?>

    <div class="container">
        <?php if ($success_message): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i> <?= $success_message ?>
            </div>
        <?php endif; ?>
        
        <?php if ($error_message): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-circle"></i> <?= $error_message ?>
            </div>
        <?php endif; ?>
        
        <?php if ($info_message): ?>
            <div class="alert alert-info">
                <i class="fas fa-info-circle"></i> <?= $info_message ?>
            </div>
        <?php endif; ?>

        <div class="breadcrumb">
            <a href="products.php">Products</a> 
            <span class="separator">/</span> 
            <a href="products.php?category=<?= urlencode($product->product_category ?? '') ?>"><?= htmlspecialchars($product->product_category ?? 'Category') ?></a>
            <span class="separator">/</span>
            <span class="current"><?= htmlspecialchars($product->product_name ?? 'Product') ?></span>
        </div>

        <div class="product-detail-container">
            <div class="product-gallery">
                <div class="main-image">
                    <img src="../../img/<?= $product->product_pic1 ?? 'default.jpg' ?>" alt="<?= htmlspecialchars($product->product_name ?? 'Product Image') ?>" id="main-product-image">
                </div>
                
                <div class="thumbnail-container">
                    <?php if ($product->product_pic1): ?>
                        <div class="thumbnail active" data-image="../../img/<?= $product->product_pic1 ?>">
                            <img src="../../img/<?= $product->product_pic1 ?>" alt="<?= htmlspecialchars($product->product_name) ?> - Image 1">
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($product->product_pic2): ?>
                        <div class="thumbnail" data-image="../../img/<?= $product->product_pic2 ?>">
                            <img src="../../img/<?= $product->product_pic2 ?>" alt="<?= htmlspecialchars($product->product_name) ?> - Image 2">
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($product->product_pic3): ?>
                        <div class="thumbnail" data-image="../../img/<?= $product->product_pic3 ?>">
                            <img src="../../img/<?= $product->product_pic3 ?>" alt="<?= htmlspecialchars($product->product_name) ?> - Image 3">
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="product-info">
                <h1 class="product-title"><?= htmlspecialchars($product->product_name ?? 'Product Name') ?></h1>
                
                <div class="product-category"><?= htmlspecialchars($product->product_category ?? 'Category') ?></div>
                
                <div class="product-price">RM <?= number_format($product->product_price ?? 0, 2) ?></div>
                
                <?php if (isset($product->review_count) && $product->review_count > 0): ?>
                    <div class="product-rating">
                        <?php 
                        $rating = round($product->avg_rating);
                        for ($i = 1; $i <= 5; $i++) {
                            if ($i <= $rating) {
                                echo '<i class="fas fa-star"></i>';
                            } else {
                                echo '<i class="far fa-star"></i>';
                            }
                        }
                        ?>
                        <span class="rating-count"><?= number_format($product->avg_rating, 1) ?> (<?= $product->review_count ?> reviews)</span>
                    </div>
                <?php endif; ?>
                
                <div class="product-description">
                    <?= nl2br(htmlspecialchars($product->product_description ?? 'No description available')) ?>
                </div>
                
                <form method="post" class="add-to-cart-form">
                    <div class="form-group">
                        <label for="size">Size</label>
                        <div class="size-options">
                            <?php if (!empty($sizes)): ?>
                                <?php foreach ($sizes as $size_option): ?>
                                    <div class="size-option">
                                        <input type="radio" name="size" id="size_<?= $size_option->size ?>" value="<?= $size_option->size ?>">
                                        <label for="size_<?= $size_option->size ?>"><?= $size_option->size ?></label>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <p class="out-of-stock-message">Currently out of stock</p>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="quantity">Quantity</label>
                        <div class="quantity-selector">
                            <button type="button" class="quantity-btn minus" data-input="quantity">
                                <i class="fas fa-minus"></i>
                            </button>
                            <input type="number" id="quantity" name="quantity" value="1" min="1" max="10" <?= empty($sizes) ? 'disabled' : '' ?>>
                            <button type="button" class="quantity-btn plus" data-input="quantity">
                                <i class="fas fa-plus"></i>
                            </button>
                        </div>
                    </div>
                    
                    <button type="submit" name="add_to_cart" class="add-to-cart-btn" <?= empty($sizes) ? 'disabled' : '' ?>>
                        <i class="fas fa-shopping-bag"></i> Add to Bag
                    </button>
                    
                    <div class="product-meta">
                        <p><strong>Product ID:</strong> <?= $product->product_id ?? 'N/A' ?></p>
                        <?php if (isset($product->created_at)): ?>
                        <p><strong>Added:</strong> <?= date('M d, Y', strtotime($product->created_at)) ?></p>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </div>
        
        <?php if (!empty($reviews)): ?>
            <div class="reviews-section">
                <h2>Customer Reviews</h2>
                
                <div class="reviews-container">
                    <?php foreach ($reviews as $review): ?>
                        <div class="review-card">
                            <div class="review-header">
                                <div class="review-rating">
                                    <?php 
                                    for ($i = 1; $i <= 5; $i++) {
                                        if ($i <= $review->rating) {
                                            echo '<i class="fas fa-star"></i>';
                                        } else {
                                            echo '<i class="far fa-star"></i>';
                                        }
                                    }
                                    ?>
                                </div>
                                <div class="review-author">
                                    <span class="author-name"><?= htmlspecialchars($review->user_name) ?></span>
                                    <span class="review-date"><?= date('M d, Y', strtotime($review->created_at)) ?></span>
                                </div>
                            </div>
                            <div class="review-content">
                                <?= nl2br(htmlspecialchars($review->review_text)) ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <?php if (isset($product->review_count) && $product->review_count > 5): ?>
                    <div class="view-all-reviews">
                        <a href="reviews.php?product_id=<?= $product->product_id ?>" class="view-all-btn">
                            View All <?= $product->review_count ?> Reviews
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
        
        <div class="related-products-section">
            <h2>You May Also Like</h2>
            
            <div class="related-products">
                <?php
                try {
                    if (isset($product->product_category)) {
                        $stm = $_db->prepare("
                            SELECT p.*, COUNT(DISTINCT r.review_id) as review_count, AVG(r.rating) as avg_rating 
                            FROM product p 
                            LEFT JOIN review r ON p.product_id = r.product_id
                            WHERE p.product_id != ? AND p.product_category = ? AND p.product_status = 'Available'
                            GROUP BY p.product_id
                            LIMIT 4
                        ");
                        $stm->execute([$product_id, $product->product_category]);
                        $related_products = $stm->fetchAll();
                        
                        foreach($related_products as $related): 
                    ?>
                        <div class="related-product-card">
                            <div class="product-image">
                                <a href="product-details.php?id=<?= $related->product_id ?>">
                                    <img src="../../img/<?= $related->product_pic1 ?>" alt="<?= htmlspecialchars($related->product_name) ?>">
                                </a>
                            </div>
                            <div class="product-info">
                                <h3 class="product-name">
                                    <a href="product-details.php?id=<?= $related->product_id ?>"><?= htmlspecialchars($related->product_name) ?></a>
                                </h3>
                                <div class="product-price">RM <?= number_format($related->product_price, 2) ?></div>
                            </div>
                        </div>
                    <?php
                        endforeach;
                    }
                } catch (PDOException $e) {
                    error_log("Error fetching related products: " . $e->getMessage());
                }
                ?>
            </div>
        </div>
    </div>
    
    <?php include('../footer.php'); ?>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Image gallery
        const thumbnails = document.querySelectorAll('.thumbnail');
        const mainImage = document.getElementById('main-product-image');
        
        thumbnails.forEach(thumbnail => {
            thumbnail.addEventListener('click', function() {
                // Remove active class from all thumbnails
                thumbnails.forEach(thumb => thumb.classList.remove('active'));
                
                // Add active class to clicked thumbnail
                this.classList.add('active');
                
                // Update main image
                const imageUrl = this.getAttribute('data-image');
                mainImage.src = imageUrl;
            });
        });
        
        // Quantity controls
        const quantityBtns = document.querySelectorAll('.quantity-btn');
        
        quantityBtns.forEach(btn => {
            btn.addEventListener('click', function() {
                const inputId = this.getAttribute('data-input');
                const input = document.getElementById(inputId);
                let value = parseInt(input.value);
                let min = parseInt(input.getAttribute('min') || 1);
                let max = parseInt(input.getAttribute('max') || 10);
                
                if (this.classList.contains('minus') && value > min) {
                    input.value = value - 1;
                } else if (this.classList.contains('plus') && value < max) {
                    input.value = value + 1;
                }
                
                // Trigger change event to update form
                const event = new Event('change', { bubbles: true });
                input.dispatchEvent(event);
            });
        });
        
        // Size selection validation
        const addToCartForm = document.querySelector('.add-to-cart-form');
        if (addToCartForm) {
            addToCartForm.addEventListener('submit', function(e) {
                const sizeSelected = document.querySelector('input[name="size"]:checked');
                if (!sizeSelected) {
                    e.preventDefault();
                    alert('Please select a size');
                    return false;
                }
                
                return true;
            });
        }
    });
    </script>
</body>
</html>