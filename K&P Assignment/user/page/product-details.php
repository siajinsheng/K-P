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
                                <input type="radio" name="size" value="<?= $size->size ?>" required>
                                <span><?= $size->size ?></span>
                            </label>
                            <?php endforeach; ?>
                        </div>
                        
                        <div class="size-stock">
                            <?php foreach ($sizes as $size): ?>
                            <div class="stock-info" data-size="<?= $size->size ?>">
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
                </div>
            </div>
        </div>
        
        <div class="product-details-accordion">
            <button class="accordion-button active" data-tab="details">
                Product Details
                <i class="fas fa-chevron-down"></i>
            </button>
            <div class="accordion-content" id="details-content">
                <p><?= htmlspecialchars($product->product_description) ?></p>
                <ul>
                    <li><strong>Material:</strong> High-quality fabric designed for comfort and durability</li>
                    <li><strong>Style:</strong> Modern design suitable for casual and semi-formal occasions</li>
                    <li><strong>Features:</strong> Breathable material, comfortable fit</li>
                </ul>
            </div>
            
            <button class="accordion-button" data-tab="care">
                Care Instructions
                <i class="fas fa-chevron-down"></i>
            </button>
            <div class="accordion-content" id="care-content">
                <ul>
                    <li>Machine wash cold with like colors</li>
                    <li>Do not bleach</li>
                    <li>Tumble dry low</li>
                    <li>Cool iron if needed</li>
                    <li>Do not dry clean</li>
                </ul>
            </div>
            
            <button class="accordion-button" data-tab="shipping">
                Shipping & Returns
                <i class="fas fa-chevron-down"></i>
            </button>
            <div class="accordion-content" id="shipping-content">
                <p><strong>Shipping Policy:</strong></p>
                <ul>
                    <li>Free standard shipping on orders over RM100</li>
                    <li>Kuala Lumpur: RM20</li>
                    <li>Others City: RM40</li>
                </ul>
                <p><strong>Return Policy:</strong></p>
                <ul>
                    <li>Returns accepted within 7 days of delivery</li>
                    <li>Item must be unworn, unwashed, and with original tags</li>
                    <li>Return shipping fee is the responsibility of the customer unless the item is defective</li>
                </ul>
            </div>
        </div>
    </div>
    
    <!-- Size guide modal -->
    <div id="sizeGuideModal" class="modal">
        <div class="modal-content">
            <span class="close-modal">&times;</span>
            <h2>Size Guide</h2>
            <table class="size-chart">
                <thead>
                    <tr>
                        <th>Size</th>
                        <th>Chest (cm)</th>
                        <th>Waist (cm)</th>
                        <th>Hip (cm)</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>S</td>
                        <td>86-91</td>
                        <td>71-76</td>
                        <td>86-91</td>
                    </tr>
                    <tr>
                        <td>M</td>
                        <td>91-97</td>
                        <td>76-81</td>
                        <td>91-97</td>
                    </tr>
                    <tr>
                        <td>L</td>
                        <td>97-102</td>
                        <td>81-86</td>
                        <td>97-102</td>
                    </tr>
                    <tr>
                        <td>XL</td>
                        <td>102-107</td>
                        <td>86-91</td>
                        <td>102-107</td>
                    </tr>
                    <tr>
                        <td>XXL</td>
                        <td>107-112</td>
                        <td>91-97</td>
                        <td>107-112</td>
                    </tr>
                </tbody>
            </table>
            <p class="size-note">* This is a general guide only. Actual sizes may vary slightly.</p>
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
                    
                    const size = this.value;
                    const stockInfo = document.querySelector(`.stock-info[data-size="${size}"]`);
                    if (stockInfo) {
                        stockInfo.style.display = 'block';
                    }
                });
            });
            
            // Quantity controls
            const quantityInput = document.getElementById('quantity');
            const decreaseBtn = document.getElementById('decrease-quantity');
            const increaseBtn = document.getElementById('increase-quantity');
            
            decreaseBtn.addEventListener('click', function() {
                let value = parseInt(quantityInput.value);
                if (value > 1) {
                    quantityInput.value = value - 1;
                }
            });
            
            increaseBtn.addEventListener('click', function() {
                let value = parseInt(quantityInput.value);
                let max = parseInt(quantityInput.getAttribute('max'));
                if (value < max) {
                    quantityInput.value = value + 1;
                }
            });
            
            // Add to cart
            const addToCartBtn = document.getElementById('add-to-cart-btn');
            const sizeError = document.getElementById('size-error');
            const quantityError = document.getElementById('quantity-error');
            
            addToCartBtn.addEventListener('click', function() {
                // Clear error messages
                sizeError.textContent = '';
                quantityError.textContent = '';
                
                // Validate size selection
                const selectedSize = document.querySelector('input[name="size"]:checked');
                if (!selectedSize) {
                    sizeError.textContent = 'Please select a size';
                    return;
                }
                
                // Validate quantity
                const quantity = parseInt(quantityInput.value);
                if (isNaN(quantity) || quantity < 1) {
                    quantityError.textContent = 'Please enter a valid quantity';
                    return;
                }
                
                // Show loading state
                const originalText = addToCartBtn.innerHTML;
                addToCartBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Adding...';
                addToCartBtn.disabled = true;
                
                // Send AJAX request to add to cart
                const formData = new FormData();
                formData.append('product_id', '<?= $product_id ?>');
                formData.append('size', selectedSize.value);
                formData.append('quantity', quantity);
                
                fetch('add-to-cart.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: new URLSearchParams(formData).toString(),
                    credentials: 'same-origin'
                })
                .then(response => response.json())
                .then(data => {
                    setTimeout(() => {
                        addToCartBtn.disabled = false;
                        
                        if (data.success) {
                            addToCartBtn.innerHTML = '<i class="fas fa-check"></i> Added to Bag';
                            
                            // Create or update "in cart" message
                            let inCartMessage = document.querySelector('.in-cart-message');
                            if (!inCartMessage) {
                                inCartMessage = document.createElement('div');
                                inCartMessage.className = 'in-cart-message';
                                document.querySelector('.cart-actions').appendChild(inCartMessage);
                            }
                            
                            inCartMessage.innerHTML = `<i class="fas fa-check"></i> This item is in your shopping bag (Quantity: ${data.totalQuantity || quantity})`;
                            
                            // Reset button text after 2 seconds
                            setTimeout(() => {
                                addToCartBtn.innerHTML = originalText;
                            }, 2000);
                        } else {
                            addToCartBtn.innerHTML = '<i class="fas fa-times"></i> Error';
                            
                            if (data.message && data.field === 'size') {
                                sizeError.textContent = data.message;
                            } else if (data.message && data.field === 'quantity') {
                                quantityError.textContent = data.message;
                            } else if (data.message && data.message.includes('log in')) {
                                window.location.href = 'login.php?redirect=' + encodeURIComponent(window.location.href);
                            }
                            
                            // Reset button text after 2 seconds
                            setTimeout(() => {
                                addToCartBtn.innerHTML = originalText;
                            }, 2000);
                        }
                    }, 800);
                })
                .catch(error => {
                    console.error('Error:', error);
                    addToCartBtn.disabled = false;
                    addToCartBtn.innerHTML = '<i class="fas fa-times"></i> Error';
                    
                    // Reset button text after 2 seconds
                    setTimeout(() => {
                        addToCartBtn.innerHTML = originalText;
                    }, 2000);
                });
            });
            
            // Accordion functionality
            const accordionButtons = document.querySelectorAll('.accordion-button');
            
            accordionButtons.forEach(button => {
                button.addEventListener('click', function() {
                    this.classList.toggle('active');
                    
                    const content = document.getElementById(this.getAttribute('data-tab') + '-content');
                    if (content.style.maxHeight) {
                        content.style.maxHeight = null;
                    } else {
                        content.style.maxHeight = content.scrollHeight + 'px';
                    }
                });
            });
            
            // Initialize the first accordion as open
            const firstAccordion = document.querySelector('.accordion-button.active');
            if (firstAccordion) {
                const content = document.getElementById(firstAccordion.getAttribute('data-tab') + '-content');
                content.style.maxHeight = content.scrollHeight + 'px';
            }
            
            // Size guide modal
            const sizeGuideBtn = document.getElementById('sizeGuideBtn');
            const sizeGuideModal = document.getElementById('sizeGuideModal');
            const closeModal = document.querySelector('.close-modal');
            
            sizeGuideBtn.addEventListener('click', function() {
                sizeGuideModal.style.display = 'block';
            });
            
            closeModal.addEventListener('click', function() {
                sizeGuideModal.style.display = 'none';
            });
            
            window.addEventListener('click', function(event) {
                if (event.target === sizeGuideModal) {
                    sizeGuideModal.style.display = 'none';
                }
            });
        });
    </script>
</body>
</html>