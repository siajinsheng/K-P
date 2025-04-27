<?php
require_once '../../_base.php';
// Get product ID from URL
$product_id = req('id');

// If no product ID provided, redirect to products page
if (empty($product_id)) {
    redirect('products.php');
}

// Get product details
$stm = $_db->prepare("
    SELECT p.*, c.category_name 
    FROM product p 
    JOIN category c ON p.category_id = c.category_id
    WHERE p.product_id = ?
");
$stm->execute([$product_id]);
$product = $stm->fetch();

// If product not found or not available, redirect to products page
if (!$product || $product->product_status !== 'Available') {
    temp('error', 'Product not found or no longer available.');
    redirect('products.php');
}

// Get product sizes and stock with quantity_id
$stm = $_db->prepare("
    SELECT quantity_id, size, product_stock 
    FROM quantity 
    WHERE product_id = ? AND product_stock > 0
    ORDER BY FIELD(size, 'S', 'M', 'L', 'XL', 'XXL')
");
$stm->execute([$product_id]);
$sizes = $stm->fetchAll();

// Check if product is in user's cart (if logged in)
$in_cart = false;
$cart_quantity = 0;
if (isset($_SESSION['user'])) {
    $stm = $_db->prepare("
        SELECT c.quantity, q.size FROM cart c
        JOIN quantity q ON c.quantity_id = q.quantity_id 
        WHERE c.user_id = ? AND c.product_id = ?
    ");
    $stm->execute([$_SESSION['user']->user_id, $product_id]);
    $cart_items = $stm->fetchAll();
    
    if ($cart_items) {
        $in_cart = true;
        foreach ($cart_items as $item) {
            $cart_quantity += $item->quantity;
        }
    }
}

// Define additional product details based on category
$fabricDetails = '';
$careInstructions = '';
$fitInformation = '';

// Set category-specific details
if (strpos($product->category_id, 'CAT1001') !== false) {
    // Short T-shirt
    $fabricDetails = 'Made with premium 100% cotton jersey fabric. Breathable, soft to the touch, and perfect for everyday wear.';
    $careInstructions = 'Machine wash cold with similar colors. Tumble dry low. Do not bleach.';
    $fitInformation = 'Regular fit. Model is 180cm/5\'11" tall and wears size M.';
} elseif (strpos($product->category_id, 'CAT1002') !== false) {
    // Long T-shirt
    $fabricDetails = 'Crafted from a premium blend of cotton (95%) and elastane (5%) for enhanced comfort and durability.';
    $careInstructions = 'Machine wash cold with similar colors. Lay flat to dry for best results. Do not bleach.';
    $fitInformation = 'Relaxed fit with slightly longer sleeves. Model is 175cm/5\'9" tall and wears size M.';
} elseif (strpos($product->category_id, 'CAT1003') !== false) {
    // Jeans
    $fabricDetails = 'Premium denim with just the right amount of stretch. 98% cotton, 2% elastane for comfort and flexibility.';
    $careInstructions = 'Machine wash cold inside out. Line dry to preserve color and shape. Wash separately before first wear.';
    $fitInformation = $product->product_type === 'Man' ? 
        'Regular waist, straight through hip and thigh. Model is 185cm/6\'1" tall and wears size 32.' : 
        'High waist, relaxed through hip and thigh. Model is 172cm/5\'8" tall and wears size 28.';
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>K&P - <?= htmlspecialchars($product->product_name) ?></title>
    <link rel="stylesheet" href="../css/product-details.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        /* Additional styles for enhanced product description */
        .product-description {
            margin-bottom: 30px;
        }
        
        .product-description p {
            margin-bottom: 15px;
            line-height: 1.6;
        }
        
        .product-specs {
            margin-top: 20px;
            margin-bottom: 30px;
            border-top: 1px solid #eee;
            padding-top: 20px;
        }
        
        .spec-group {
            margin-bottom: 15px;
        }
        
        .spec-title {
            font-weight: 500;
            margin-bottom: 5px;
            font-size: 14px;
            color: #333;
        }
        
        .spec-info {
            font-size: 13px;
            color: #666;
            line-height: 1.5;
        }
        
        .product-highlights {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 15px;
        }
        
        .highlight-tag {
            display: inline-block;
            padding: 4px 12px;
            background-color: #f5f5f5;
            border-radius: 20px;
            font-size: 12px;
            color: #555;
        }
    </style>
</head>
<body>
    <?php include('../header.php'); ?>

    <div class="container">
        <div class="breadcrumb">
            <a href="../../index.php">Home</a>
            <span class="separator">/</span>
            <a href="products.php?gender=<?= urlencode($product->product_type) ?>">
                <?= $product->product_type === 'Man' ? 'Men' : 'Women' ?>
            </a>
            <span class="separator">/</span>
            <a href="products.php?gender=<?= urlencode($product->product_type) ?>&category=<?= urlencode($product->category_id) ?>">
                <?= htmlspecialchars($product->category_name) ?>
            </a>
            <span class="separator">/</span>
            <span class="current"><?= htmlspecialchars($product->product_name) ?></span>
        </div>

        <div class="product-details">
            <div class="product-gallery">
                <div class="product-images">
                    <div class="main-image">
                        <img id="main-product-image" src="../../img/<?= $product->product_pic1 ?>" alt="<?= htmlspecialchars($product->product_name) ?>">
                    </div>
                    <?php if ($product->product_pic2): ?>
                    <div class="additional-image">
                        <img src="../../img/<?= $product->product_pic2 ?>" alt="<?= htmlspecialchars($product->product_name) ?> - View 2" class="thumbnail" data-image="../../img/<?= $product->product_pic2 ?>">
                    </div>
                    <?php endif; ?>
                    <?php if ($product->product_pic3): ?>
                    <div class="additional-image">
                        <img src="../../img/<?= $product->product_pic3 ?>" alt="<?= htmlspecialchars($product->product_name) ?> - View 3" class="thumbnail" data-image="../../img/<?= $product->product_pic3 ?>">
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="product-info">
                <h1 class="product-title"><?= htmlspecialchars($product->product_name) ?></h1>
                
                <div class="product-price">
                    <span class="price">RM <?= number_format($product->product_price, 2) ?></span>
                </div>
                
                <div class="product-description">
                    <p><?= htmlspecialchars($product->product_description) ?></p>
                    
                    <div class="product-highlights">
                        <?php if (strpos($product->product_description, 'comfortable') !== false || strpos($product->product_description, 'Comfortable') !== false): ?>
                        <span class="highlight-tag"><i class="fas fa-check-circle"></i> Comfort</span>
                        <?php endif; ?>
                        
                        <?php if (strpos($product->category_name, 'T-shirt') !== false): ?>
                        <span class="highlight-tag"><i class="fas fa-tshirt"></i> Casual</span>
                        <?php endif; ?>
                        
                        <?php if ($product->product_type === 'Unisex'): ?>
                        <span class="highlight-tag"><i class="fas fa-users"></i> Unisex</span>
                        <?php endif; ?>
                        
                        <?php if (strpos(strtolower($product->product_description), 'cotton') !== false): ?>
                        <span class="highlight-tag"><i class="fas fa-leaf"></i> Cotton</span>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="product-specs">
                    <div class="spec-group">
                        <div class="spec-title">Fabric Details</div>
                        <div class="spec-info"><?= $fabricDetails ?></div>
                    </div>
                    
                    <div class="spec-group">
                        <div class="spec-title">Care Instructions</div>
                        <div class="spec-info"><?= $careInstructions ?></div>
                    </div>
                    
                    <div class="spec-group">
                        <div class="spec-title">Fit Information</div>
                        <div class="spec-info"><?= $fitInformation ?></div>
                    </div>
                </div>
                
                <form id="add-to-cart-form" class="product-actions">
                    <div class="size-selection">
                        <div class="selection-header">
                            <span class="size-label">Size</span>
                            <button type="button" class="size-guide-button" id="sizeGuideBtn">Size Guide</button>
                        </div>
                        
                        <div class="size-options">
                            <?php foreach ($sizes as $size): ?>
                            <label class="size-option">
                                <input type="radio" name="quantity_id" value="<?= $size->quantity_id ?>" required>
                                <span><?= $size->size ?></span>
                            </label>
                            <?php endforeach; ?>
                        </div>
                        
                        <div class="size-stock">
                            <?php foreach ($sizes as $size): ?>
                            <div class="stock-info" data-quantity-id="<?= $size->quantity_id ?>">
                                <span class="stock-count"><?= $size->product_stock ?></span> items available
                            </div>
                            <?php endforeach; ?>
                        </div>
                        
                        <div id="size-error" class="error-message"></div>
                    </div>
                    
                    <div class="quantity-selection">
                        <span class="quantity-label">Quantity</span>
                        <div class="quantity-controls">
                            <button type="button" class="quantity-btn" id="decrease-quantity">
                                <i class="fas fa-minus"></i>
                            </button>
                            <input type="number" id="quantity" name="quantity" value="1" min="1" max="10">
                            <button type="button" class="quantity-btn" id="increase-quantity">
                                <i class="fas fa-plus"></i>
                            </button>
                        </div>
                        <div id="quantity-error" class="error-message"></div>
                    </div>
                    
                    <input type="hidden" name="product_id" value="<?= $product->product_id ?>">
                    
                    <div class="cart-actions">
                        <button type="button" id="add-to-cart-btn" class="add-to-cart-btn">
                            <i class="fas fa-shopping-bag"></i> Add to Shopping Bag
                        </button>
                        
                        <?php if ($in_cart): ?>
                        <div class="in-cart-message">
                            <i class="fas fa-check"></i> This item is in your shopping bag (Quantity: <?= $cart_quantity ?>)
                        </div>
                        <?php endif; ?>
                    </div>
                </form>
                
                <div class="product-features">
                    <div class="feature">
                        <i class="fas fa-truck"></i>
                        <div class="feature-text">
                            <span class="feature-title">Free shipping</span>
                            <span class="feature-desc">on orders over RM100</span>
                        </div>
                    </div>
                    
                    <div class="feature">
                        <i class="fas fa-box"></i>
                        <div class="feature-text">
                            <span class="feature-title">Free returns</span>
                            <span class="feature-desc">within 7 days</span>
                        </div>
                    </div>
                    
                    <div class="feature">
                        <i class="fas fa-shield-alt"></i>
                        <div class="feature-text">
                            <span class="feature-title">Secure checkout</span>
                            <span class="feature-desc">safe payment methods</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Size guide modal -->
        <div id="sizeGuideModal" class="modal">
            <!-- Size guide modal content remains the same -->
        </div>
    </div>
    
    <?php include('../footer.php'); ?>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Thumbnail gallery functionality
            const thumbnails = document.querySelectorAll('.thumbnail');
            const mainImage = document.getElementById('main-product-image');
            
            thumbnails.forEach(thumbnail => {
                thumbnail.addEventListener('click', function() {
                    mainImage.src = this.getAttribute('data-image');
                });
            });
            
            // Size selection
            const sizeInputs = document.querySelectorAll('.size-option input');
            const stockInfos = document.querySelectorAll('.stock-info');
            
            sizeInputs.forEach(input => {
                input.addEventListener('change', function() {
                    // Update active state
                    document.querySelectorAll('.size-option span').forEach(span => {
                        span.classList.remove('active');
                    });
                    this.nextElementSibling.classList.add('active');
                    
                    // Show stock info for selected size
                    stockInfos.forEach(info => {
                        info.style.display = 'none';
                    });
                    
                    const quantityId = this.value;
                    const stockInfo = document.querySelector(`.stock-info[data-quantity-id="${quantityId}"]`);
                    if (stockInfo) {
                        stockInfo.style.display = 'block';
                        
                        // Update max quantity based on available stock
                        const stockCount = parseInt(stockInfo.querySelector('.stock-count').textContent);
                        document.getElementById('quantity').setAttribute('max', Math.min(10, stockCount));
                        
                        // Adjust quantity if it exceeds new max
                        const quantityInput = document.getElementById('quantity');
                        if (parseInt(quantityInput.value) > stockCount) {
                            quantityInput.value = stockCount;
                        }
                    }
                });
            });
            
            // Rest of the JavaScript remains the same...
        });
    </script>
</body>
</html>