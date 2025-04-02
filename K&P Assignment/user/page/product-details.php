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

// Get product sizes and stock
$stm = $_db->prepare("
    SELECT size, product_stock 
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
        SELECT quantity FROM cart 
        WHERE user_id = ? AND product_id = ?
    ");
    $stm->execute([$_SESSION['user']->user_id, $product_id]);
    $cart_item = $stm->fetch();
    
    if ($cart_item) {
        $in_cart = true;
        $cart_quantity = $cart_item->quantity;
    }
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
</head>
<body>
    <?php include('../header.php'); ?>

    <div class="container">
        <div class="breadcrumb">
            <a href="index.php">Home</a> &gt;
            <a href="products.php?gender=<?= urlencode($product->product_type) ?>">
                <?= $product->product_type === 'Man' ? 'Men' : 'Women' ?>
            </a> &gt;
            <a href="products.php?gender=<?= urlencode($product->product_type) ?>&category=<?= urlencode($product->category_id) ?>">
                <?= htmlspecialchars($product->category_name) ?>
            </a> &gt;
            <span><?= htmlspecialchars($product->product_name) ?></span>
        </div>

        <div class="product-details">
            <div class="product-gallery">
                <div class="main-image">
                    <img id="main-product-image" src="../../img/<?= $product->product_pic1 ?>" alt="<?= htmlspecialchars($product->product_name) ?>">
                </div>
                <div class="thumbnail-gallery">
                    <div class="thumbnail active" data-image="../../img/<?= $product->product_pic1 ?>">
                        <img src="../../img/<?= $product->product_pic1 ?>" alt="Thumbnail 1">
                    </div>
                    <?php if ($product->product_pic2): ?>
                    <div class="thumbnail" data-image="../../img/<?= $product->product_pic2 ?>">
                        <img src="../../img/<?= $product->product_pic2 ?>" alt="Thumbnail 2">
                    </div>
                    <?php endif; ?>
                    <?php if ($product->product_pic3): ?>
                    <div class="thumbnail" data-image="../../img/<?= $product->product_pic3 ?>">
                        <img src="../../img/<?= $product->product_pic3 ?>" alt="Thumbnail 3">
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="product-info">
                <h1 class="product-title"><?= htmlspecialchars($product->product_name) ?></h1>
                
                <div class="product-meta">
                    <span class="product-id">Product ID: <?= $product->product_id ?></span>
                    <span class="product-category">Category: <?= htmlspecialchars($product->category_name) ?></span>
                    <span class="product-type">Gender: <?= $product->product_type === 'Man' ? 'Men' : 'Women' ?></span>
                </div>
                
                <div class="product-price">
                    <span class="price">RM <?= number_format($product->product_price, 2) ?></span>
                </div>
                
                <div class="product-description">
                    <h3>Description</h3>
                    <p><?= htmlspecialchars($product->product_description) ?></p>
                </div>
                
                <form id="add-to-cart-form" class="product-actions">
                    <div class="size-selection">
                        <h3>Size</h3>
                        <div class="size-options">
                            <?php foreach ($sizes as $size): ?>
                            <label class="size-option">
                                <input type="radio" name="size" value="<?= $size->size ?>" required>
                                <span><?= $size->size ?></span>
                                <small>(<?= $size->product_stock ?> available)</small>
                            </label>
                            <?php endforeach; ?>
                        </div>
                        <div id="size-error" class="error-message"></div>
                    </div>
                    
                    <div class="quantity-selection">
                        <h3>Quantity</h3>
                        <div class="quantity-controls">
                            <button type="button" class="quantity-btn minus" id="decrease-quantity">
                                <i class="fas fa-minus"></i>
                            </button>
                            <input type="number" id="quantity" name="quantity" value="1" min="1" max="10">
                            <button type="button" class="quantity-btn plus" id="increase-quantity">
                                <i class="fas fa-plus"></i>
                            </button>
                        </div>
                        <div id="quantity-error" class="error-message"></div>
                    </div>
                    
                    <input type="hidden" name="product_id" value="<?= $product->product_id ?>">
                    
                    <div class="cart-actions">
                        <button type="button" id="add-to-cart-btn" class="add-to-cart-btn">
                            <i class="fas fa-shopping-cart"></i> Add to Cart
                        </button>
                        
                        <?php if ($in_cart): ?>
                        <div class="in-cart-message">
                            <i class="fas fa-check-circle"></i> This item is in your cart (Quantity: <?= $cart_quantity ?>)
                        </div>
                        <?php endif; ?>
                    </div>
                </form>
                
                <div class="product-features">
                    <div class="feature">
                        <i class="fas fa-shipping-fast"></i>
                        <span>Free shipping on orders over RM100</span>
                    </div>
                    <div class="feature">
                        <i class="fas fa-undo"></i>
                        <span>Easy 30-day returns</span>
                    </div>
                    <div class="feature">
                        <i class="fas fa-shield-alt"></i>
                        <span>100% secure checkout</span>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="product-tabs">
            <div class="tabs-nav">
                <button class="tab-btn active" data-tab="details">Product Details</button>
                <button class="tab-btn" data-tab="care">Care Instructions</button>
                <button class="tab-btn" data-tab="shipping">Shipping & Returns</button>
            </div>
            
            <div class="tab-content active" id="details-tab">
                <h3>Product Details</h3>
                <p>
                    <?= htmlspecialchars($product->product_description) ?>
                </p>
                <ul>
                    <li><strong>Material:</strong> High-quality fabric designed for comfort and durability</li>
                    <li><strong>Style:</strong> Modern design suitable for casual and semi-formal occasions</li>
                    <li><strong>Features:</strong> Breathable material, comfortable fit</li>
                </ul>
            </div>
            
            <div class="tab-content" id="care-tab">
                <h3>Care Instructions</h3>
                <ul>
                    <li>Machine wash cold with like colors</li>
                    <li>Do not bleach</li>
                    <li>Tumble dry low</li>
                    <li>Cool iron if needed</li>
                    <li>Do not dry clean</li>
                </ul>
            </div>
            
            <div class="tab-content" id="shipping-tab">
                <h3>Shipping & Returns</h3>
                <p><strong>Shipping Policy:</strong></p>
                <ul>
                    <li>Free standard shipping on orders over RM100</li>
                    <li>Standard shipping (3-5 business days): RM10</li>
                    <li>Express shipping (1-2 business days): RM20</li>
                </ul>
                <p><strong>Return Policy:</strong></p>
                <ul>
                    <li>Returns accepted within 30 days of delivery</li>
                    <li>Item must be unworn, unwashed, and with original tags</li>
                    <li>Return shipping fee is the responsibility of the customer unless the item is defective</li>
                </ul>
            </div>
        </div>
    </div>
    
    <?php include('../footer.php'); ?>

    <script src="../js/product-details.js"></script>
</body>
</html>