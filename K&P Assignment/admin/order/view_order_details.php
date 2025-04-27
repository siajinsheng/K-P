<?php
$_title = 'Order Details';
require '../../_base.php';
auth('admin', 'staff');
require '../headFooter/header.php';

// Get order ID from URL
$orderId = req('id');

if (empty($orderId)) {
    temp('error', 'Order ID is required');
    redirect('orders.php');
}

// Fetch order details
$orderQuery = "SELECT o.*, 
                     u.user_name, u.user_Email, u.user_phone,
                     p.payment_id, p.payment_method, p.payment_status, p.payment_date, 
                     p.total_amount, p.tax, p.discount,
                     d.delivery_id, d.delivery_fee, d.delivery_status, d.estimated_date, d.delivered_date
              FROM orders o
              JOIN user u ON o.user_id = u.user_id
              JOIN payment p ON o.order_id = p.order_id
              JOIN delivery d ON o.delivery_id = d.delivery_id
              WHERE o.order_id = ?";

$orderStmt = $_db->prepare($orderQuery);
$orderStmt->execute([$orderId]);
$order = $orderStmt->fetch();

if (!$order) {
    temp('error', 'Order not found');
    redirect('orders.php');
}

// Fetch order items
$itemsQuery = "SELECT od.*, p.product_name, p.product_pic1
               FROM order_details od
               JOIN product p ON od.product_id = p.product_id
               WHERE od.order_id = ?";

$itemsStmt = $_db->prepare($itemsQuery);
$itemsStmt->execute([$orderId]);
$orderItems = $itemsStmt->fetchAll();

// Calculate order summary
$subtotal = 0;
$totalItems = 0;
foreach ($orderItems as $item) {
    $subtotal += $item->unit_price * $item->quantity;
    $totalItems += $item->quantity;
}

// Fetch delivery address
$addressQuery = "SELECT a.*
                FROM address a
                JOIN delivery d ON a.address_id = d.address_id
                WHERE d.delivery_id = ?";

$addressStmt = $_db->prepare($addressQuery);
$addressStmt->execute([$order->delivery_id]);
$address = $addressStmt->fetch();

// Determine which status options to show based on current status
$nextStatus = '';
$nextStatusLabel = '';

switch ($order->orders_status) {
    case 'Pending':
        $nextStatus = 'Processing';
        $nextStatusLabel = 'Process Order';
        break;
    case 'Processing':
        $nextStatus = 'Shipped';
        $nextStatusLabel = 'Mark as Shipped';
        break;
    case 'Shipped':
        $nextStatus = 'Delivered';
        $nextStatusLabel = 'Mark as Delivered';
        break;
}

// Format status for display
function getStatusClass($status) {
    switch ($status) {
        case 'Pending':
            return 'bg-yellow-100 text-yellow-800';
        case 'Processing':
            return 'bg-blue-100 text-blue-800';
        case 'Shipped':
            return 'bg-indigo-100 text-indigo-800';
        case 'Delivered':
            return 'bg-green-100 text-green-800';
        case 'Cancelled':
            return 'bg-red-100 text-red-800';
        default:
            return 'bg-gray-100 text-gray-800';
    }
}

function getPaymentStatusClass($status) {
    switch ($status) {
        case 'Completed':
            return 'bg-green-100 text-green-800';
        case 'Pending':
            return 'bg-yellow-100 text-yellow-800';
        case 'Failed':
            return 'bg-red-100 text-red-800';
        case 'Refunded':
            return 'bg-purple-100 text-purple-800';
        default:
            return 'bg-gray-100 text-gray-800';
    }
}

function getDeliveryStatusClass($status) {
    switch ($status) {
        case 'Processing':
            return 'bg-blue-100 text-blue-800';
        case 'Out for Delivery':
            return 'bg-indigo-100 text-indigo-800';
        case 'Delivered':
            return 'bg-green-100 text-green-800';
        case 'Failed':
            return 'bg-red-100 text-red-800';
        default:
            return 'bg-gray-100 text-gray-800';
    }
}

// Format date function
function formatDate($date) {
    if (empty($date)) return 'N/A';
    return date('F j, Y, g:i a', strtotime($date));
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $_title ?> - <?= htmlspecialchars($orderId) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../css/orders.css">
</head>
<body class="bg-gray-50">
    <div class="container mx-auto px-4 py-8 max-w-6xl">
        <!-- Header with breadcrumbs and actions -->
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-6">
            <div class="mb-4 md:mb-0">
                <div class="flex items-center text-sm text-gray-500 mb-2">
                    <a href="orders.php" class="hover:text-indigo-700">Orders</a>
                    <svg class="h-4 w-4 mx-2" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                    </svg>
                    <span>Order Details</span>
                    <svg class="h-4 w-4 mx-2" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                    </svg>
                    <span class="font-medium"><?= htmlspecialchars($orderId) ?></span>
                </div>
                <h1 class="text-2xl font-bold text-indigo-900">
                    Order #<?= htmlspecialchars($orderId) ?>
                </h1>
                <p class="text-gray-600">Placed on <?= formatDate($order->order_date) ?></p>
            </div>
            <div class="flex flex-wrap gap-2">
                <a href="orders.php" class="flex items-center px-4 py-2 bg-white border border-gray-300 rounded-md text-sm text-gray-700 hover:bg-gray-50 transition-colors">
                    <i class="fas fa-arrow-left mr-2"></i> Back to Orders
                </a>
                <?php if ($order->orders_status !== 'Cancelled' && $order->orders_status !== 'Delivered'): ?>
                    <?php if (!empty($nextStatus)): ?>
                        <button onclick="updateOrderStatus('<?= $orderId ?>', '<?= $nextStatus ?>')" class="flex items-center px-4 py-2 bg-indigo-600 text-white rounded-md text-sm hover:bg-indigo-700 transition-colors">
                            <?php if ($nextStatus === 'Processing'): ?>
                                <i class="fas fa-cog mr-2"></i>
                            <?php elseif ($nextStatus === 'Shipped'): ?>
                                <i class="fas fa-truck mr-2"></i>
                            <?php elseif ($nextStatus === 'Delivered'): ?>
                                <i class="fas fa-check-circle mr-2"></i>
                            <?php endif; ?>
                            <?= $nextStatusLabel ?>
                        </button>
                    <?php endif; ?>
                    
                    <?php if ($order->orders_status !== 'Cancelled'): ?>
                        <button onclick="updateOrderStatus('<?= $orderId ?>', 'Cancelled')" class="flex items-center px-4 py-2 bg-red-600 text-white rounded-md text-sm hover:bg-red-700 transition-colors">
                            <i class="fas fa-times-circle mr-2"></i> Cancel Order
                        </button>
                    <?php endif; ?>
                <?php endif; ?>
                
                <button onclick="printOrderDetails()" class="flex items-center px-4 py-2 bg-white border border-gray-300 rounded-md text-sm text-gray-700 hover:bg-gray-50 transition-colors">
                    <i class="fas fa-print mr-2"></i> Print
                </button>
            </div>
        </div>
        
        <!-- Order Status Timeline -->
        <div class="bg-white rounded-lg shadow-sm mb-6 p-6">
            <h2 class="text-lg font-semibold mb-4 text-gray-800">Order Status</h2>
            <div class="flex flex-col md:flex-row justify-between relative">
                <!-- Timeline connector -->
                <div class="hidden md:block absolute top-1/2 left-0 right-0 h-1 bg-gray-200 -translate-y-1/2 z-0"></div>
                
                <!-- Status points -->
                <div class="flex items-start md:items-center mb-4 md:mb-0 relative z-10 flex-1 <?= $order->orders_status === 'Pending' || $order->orders_status === 'Processing' || $order->orders_status === 'Shipped' || $order->orders_status === 'Delivered' ? 'text-indigo-600' : 'text-gray-400' ?>">
                    <div class="flex flex-col md:items-center">
                        <div class="flex items-center justify-center w-8 h-8 rounded-full <?= $order->orders_status === 'Pending' || $order->orders_status === 'Processing' || $order->orders_status === 'Shipped' || $order->orders_status === 'Delivered' ? 'bg-indigo-100 text-indigo-600' : 'bg-gray-100 text-gray-400' ?> mb-2">
                            <i class="fas fa-clipboard-check"></i>
                        </div>
                        <span class="md:text-center font-medium">Pending</span>
                    </div>
                </div>
                
                <div class="flex items-start md:items-center mb-4 md:mb-0 relative z-10 flex-1 <?= $order->orders_status === 'Processing' || $order->orders_status === 'Shipped' || $order->orders_status === 'Delivered' ? 'text-indigo-600' : 'text-gray-400' ?>">
                    <div class="flex flex-col md:items-center">
                        <div class="flex items-center justify-center w-8 h-8 rounded-full <?= $order->orders_status === 'Processing' || $order->orders_status === 'Shipped' || $order->orders_status === 'Delivered' ? 'bg-indigo-100 text-indigo-600' : 'bg-gray-100 text-gray-400' ?> mb-2">
                            <i class="fas fa-cog"></i>
                        </div>
                        <span class="md:text-center font-medium">Processing</span>
                    </div>
                </div>
                
                <div class="flex items-start md:items-center mb-4 md:mb-0 relative z-10 flex-1 <?= $order->orders_status === 'Shipped' || $order->orders_status === 'Delivered' ? 'text-indigo-600' : 'text-gray-400' ?>">
                    <div class="flex flex-col md:items-center">
                        <div class="flex items-center justify-center w-8 h-8 rounded-full <?= $order->orders_status === 'Shipped' || $order->orders_status === 'Delivered' ? 'bg-indigo-100 text-indigo-600' : 'bg-gray-100 text-gray-400' ?> mb-2">
                            <i class="fas fa-shipping-fast"></i>
                        </div>
                        <span class="md:text-center font-medium">Shipped</span>
                    </div>
                </div>
                
                <div class="flex items-start md:items-center mb-4 md:mb-0 relative z-10 flex-1 <?= $order->orders_status === 'Delivered' ? 'text-indigo-600' : 'text-gray-400' ?>">
                    <div class="flex flex-col md:items-center">
                        <div class="flex items-center justify-center w-8 h-8 rounded-full <?= $order->orders_status === 'Delivered' ? 'bg-indigo-100 text-indigo-600' : 'bg-gray-100 text-gray-400' ?> mb-2">
                            <i class="fas fa-check"></i>
                        </div>
                        <span class="md:text-center font-medium">Delivered</span>
                    </div>
                </div>

                <?php if ($order->orders_status === 'Cancelled'): ?>
                <div class="flex items-start md:items-center relative z-10 flex-1 text-red-600">
                    <div class="flex flex-col md:items-center">
                        <div class="flex items-center justify-center w-8 h-8 rounded-full bg-red-100 text-red-600 mb-2">
                            <i class="fas fa-times"></i>
                        </div>
                        <span class="md:text-center font-medium">Cancelled</span>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Main content - two columns on desktop, single column on mobile -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Left column - Order Items and Summary -->
            <div class="lg:col-span-2">
                <!-- Order Items -->
                <div class="bg-white rounded-lg shadow-sm mb-6 overflow-hidden">
                    <div class="px-6 py-4 bg-gray-50 border-b border-gray-100">
                        <h2 class="text-lg font-semibold text-gray-800">Order Items (<?= count($orderItems) ?>)</h2>
                    </div>
                    
                    <div class="divide-y divide-gray-100">
                        <?php foreach ($orderItems as $item): ?>
                            <div class="p-6 flex flex-col md:flex-row">
                                <div class="flex-shrink-0 w-full md:w-20 h-20 mb-4 md:mb-0 md:mr-6">
                                    <img src="../../img/<?= htmlspecialchars($item->product_pic1) ?>" alt="<?= htmlspecialchars($item->product_name) ?>" class="w-full h-full object-cover rounded">
                                </div>
                                <div class="flex-grow">
                                    <div class="flex flex-col md:flex-row md:justify-between">
                                        <div>
                                            <h3 class="font-medium text-gray-800"><?= htmlspecialchars($item->product_name) ?></h3>
                                            <p class="text-sm text-gray-600 mt-1">Product ID: <?= htmlspecialchars($item->product_id) ?></p>
                                            <?php if (isset($item->size) && !empty($item->size)): ?>
                                                <p class="text-sm text-gray-600">Size: <?= htmlspecialchars($item->size) ?></p>
                                            <?php endif; ?>
                                        </div>
                                        <div class="mt-2 md:mt-0 md:text-right">
                                            <p class="text-sm text-gray-600">
                                                <?= $item->quantity ?> × RM <?= number_format($item->unit_price, 2) ?>
                                            </p>
                                            <p class="font-medium text-gray-800 mt-1">
                                                RM <?= number_format($item->quantity * $item->unit_price, 2) ?>
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                
                <!-- Price Summary -->
                <div class="bg-white rounded-lg shadow-sm mb-6 overflow-hidden">
                    <div class="px-6 py-4 bg-gray-50 border-b border-gray-100">
                        <h2 class="text-lg font-semibold text-gray-800">Order Summary</h2>
                    </div>
                    <div class="p-6">
                        <div class="divide-y divide-gray-100">
                            <div class="py-3 flex justify-between">
                                <span class="text-gray-600">Subtotal</span>
                                <span class="font-medium">RM <?= number_format($subtotal, 2) ?></span>
                            </div>
                            <div class="py-3 flex justify-between">
                                <span class="text-gray-600">Shipping Fee</span>
                                <span class="font-medium">RM <?= number_format($order->delivery_fee, 2) ?></span>
                            </div>
                            <div class="py-3 flex justify-between">
                                <span class="text-gray-600">Tax</span>
                                <span class="font-medium">RM <?= number_format($order->tax, 2) ?></span>
                            </div>
                            <?php if (!empty($order->discount) && $order->discount > 0): ?>
                                <div class="py-3 flex justify-between text-green-600">
                                    <span>Discount</span>
                                    <span class="font-medium">- RM <?= number_format($order->discount, 2) ?></span>
                                </div>
                            <?php endif; ?>
                            <div class="py-3 flex justify-between">
                                <span class="text-lg font-semibold text-gray-800">Total</span>
                                <span class="text-lg font-bold text-indigo-700">RM <?= number_format($order->total_amount, 2) ?></span>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Payment Details -->
                <div class="bg-white rounded-lg shadow-sm mb-6 overflow-hidden">
                    <div class="px-6 py-4 bg-gray-50 border-b border-gray-100">
                        <h2 class="text-lg font-semibold text-gray-800">Payment Information</h2>
                    </div>
                    <div class="p-6">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <p class="text-sm text-gray-600 mb-1">Payment ID</p>
                                <p class="font-medium"><?= htmlspecialchars($order->payment_id) ?></p>
                            </div>
                            <div>
                                <p class="text-sm text-gray-600 mb-1">Payment Method</p>
                                <p class="font-medium">
                                    <?php if ($order->payment_method === 'Credit Card'): ?>
                                        <i class="far fa-credit-card mr-2 text-gray-600"></i>
                                    <?php elseif ($order->payment_method === 'PayPal'): ?>
                                        <i class="fab fa-paypal mr-2 text-blue-500"></i>
                                    <?php elseif ($order->payment_method === 'Bank Transfer'): ?>
                                        <i class="fas fa-university mr-2 text-gray-600"></i>
                                    <?php elseif ($order->payment_method === 'Cash on Delivery'): ?>
                                        <i class="fas fa-money-bill-wave mr-2 text-green-600"></i>
                                    <?php endif; ?>
                                    <?= htmlspecialchars($order->payment_method) ?>
                                </p>
                            </div>
                            <div>
                                <p class="text-sm text-gray-600 mb-1">Payment Date</p>
                                <p class="font-medium"><?= formatDate($order->payment_date) ?></p>
                            </div>
                            <div>
                                <p class="text-sm text-gray-600 mb-1">Payment Status</p>
                                <p class="inline-block px-2.5 py-0.5 rounded-full text-xs font-medium <?= getPaymentStatusClass($order->payment_status) ?>">
                                    <?= htmlspecialchars($order->payment_status) ?>
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Right column - Customer Info, Shipping Details, Status -->
            <div class="lg:col-span-1">
                <!-- Order Status -->
                <div class="bg-white rounded-lg shadow-sm mb-6 overflow-hidden">
                    <div class="px-6 py-4 bg-gray-50 border-b border-gray-100">
                        <h2 class="text-lg font-semibold text-gray-800">Order Status</h2>
                    </div>
                    <div class="p-6">
                        <div class="flex items-center justify-between mb-4">
                            <span class="text-gray-600">Current Status:</span>
                            <span class="inline-flex px-3 py-1 rounded-full text-sm font-medium <?= getStatusClass($order->orders_status) ?>">
                                <?= htmlspecialchars($order->orders_status) ?>
                            </span>
                        </div>
                        <?php if ($order->orders_status !== 'Cancelled' && $order->orders_status !== 'Delivered'): ?>
                            <form id="statusUpdateForm" class="mt-4">
                                <input type="hidden" name="orderId" value="<?= htmlspecialchars($orderId) ?>">
                                <label class="block text-sm font-medium text-gray-700 mb-2">Update Status</label>
                                <div class="flex space-x-2">
                                    <select name="newStatus" class="flex-grow rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50">
                                        <option value="">Select Status</option>
                                        <option value="Pending" <?= $order->orders_status === 'Pending' ? 'selected' : '' ?>>Pending</option>
                                        <option value="Processing" <?= $order->orders_status === 'Processing' ? 'selected' : '' ?>>Processing</option>
                                        <option value="Shipped" <?= $order->orders_status === 'Shipped' ? 'selected' : '' ?>>Shipped</option>
                                        <option value="Delivered" <?= $order->orders_status === 'Delivered' ? 'selected' : '' ?>>Delivered</option>
                                        <option value="Cancelled" <?= $order->orders_status === 'Cancelled' ? 'selected' : '' ?>>Cancelled</option>
                                    </select>
                                    <button type="button" onclick="updateStatusFromForm()" class="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700 transition-colors">
                                        Update
                                    </button>
                                </div>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Customer Information -->
                <div class="bg-white rounded-lg shadow-sm mb-6 overflow-hidden">
                    <div class="px-6 py-4 bg-gray-50 border-b border-gray-100">
                        <h2 class="text-lg font-semibold text-gray-800">Customer Information</h2>
                    </div>
                    <div class="p-6">
                        <div class="mb-6">
                            <h3 class="text-sm font-medium text-gray-600 mb-2">Contact Details</h3>
                            <p class="text-gray-800 font-medium"><?= htmlspecialchars($order->user_name) ?></p>
                            <div class="mt-2 space-y-1">
                                <p class="text-gray-600 flex items-center">
                                    <i class="fas fa-envelope text-gray-400 w-5"></i>
                                    <span><?= htmlspecialchars($order->user_Email) ?></span>
                                </p>
                                <p class="text-gray-600 flex items-center">
                                    <i class="fas fa-phone text-gray-400 w-5"></i>
                                    <span><?= htmlspecialchars($order->user_phone) ?></span>
                                </p>
                            </div>
                        </div>
                        
                        <div>
                            <h3 class="text-sm font-medium text-gray-600 mb-2">Customer ID</h3>
                            <p class="text-gray-800"><?= htmlspecialchars($order->user_id) ?></p>
                        </div>
                    </div>
                </div>
                
                <!-- Shipping Information -->
                <div class="bg-white rounded-lg shadow-sm mb-6 overflow-hidden">
                    <div class="px-6 py-4 bg-gray-50 border-b border-gray-100">
                        <h2 class="text-lg font-semibold text-gray-800">Shipping Information</h2>
                    </div>
                    <div class="p-6">
                        <?php if ($address): ?>
                            <div class="mb-6">
                                <h3 class="text-sm font-medium text-gray-600 mb-2">Shipping Address</h3>
                                <p class="font-medium"><?= htmlspecialchars($address->recipient_name) ?></p>
                                <p class="text-gray-600 mt-1"><?= htmlspecialchars($address->phone) ?></p>
                                <div class="mt-2 text-gray-600">
                                    <p><?= htmlspecialchars($address->address_line1) ?></p>
                                    <?php if (!empty($address->address_line2)): ?>
                                        <p><?= htmlspecialchars($address->address_line2) ?></p>
                                    <?php endif; ?>
                                    <p>
                                        <?= htmlspecialchars($address->city) ?>, 
                                        <?= htmlspecialchars($address->state) ?>, 
                                        <?= htmlspecialchars($address->post_code) ?>
                                    </p>
                                    <p><?= htmlspecialchars($address->country) ?></p>
                                </div>
                            </div>
                        <?php else: ?>
                            <p class="text-gray-600">No shipping address information available</p>
                        <?php endif; ?>
                        
                        <div>
                            <h3 class="text-sm font-medium text-gray-600 mb-2">Delivery Status</h3>
                            <p class="inline-block px-2.5 py-0.5 rounded-full text-xs font-medium <?= getDeliveryStatusClass($order->delivery_status) ?>">
                                <?= htmlspecialchars($order->delivery_status) ?>
                            </p>
                            
                            <div class="grid grid-cols-2 gap-4 mt-4">
                                <div>
                                    <p class="text-sm text-gray-600 mb-1">Estimated Delivery</p>
                                    <p class="font-medium">
                                        <?= !empty($order->estimated_date) ? date('M d, Y', strtotime($order->estimated_date)) : 'N/A' ?>
                                    </p>
                                </div>
                                <div>
                                    <p class="text-sm text-gray-600 mb-1">Delivered Date</p>
                                    <p class="font-medium">
                                        <?= !empty($order->delivered_date) ? date('M d, Y', strtotime($order->delivered_date)) : 'N/A' ?>
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Notes and Actions -->
                <div class="bg-white rounded-lg shadow-sm overflow-hidden">
                    <div class="px-6 py-4 bg-gray-50 border-b border-gray-100">
                        <h2 class="text-lg font-semibold text-gray-800">Actions</h2>
                    </div>
                    <div class="p-6">
                        <div class="space-y-3">
                            <button onclick="downloadInvoice('<?= $orderId ?>')" class="w-full flex items-center justify-center px-4 py-2 bg-white border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50 transition-colors">
                                <i class="fas fa-file-invoice mr-2"></i> Download Invoice
                            </button>
                            <button onclick="sendEmail('<?= $orderId ?>')" class="w-full flex items-center justify-center px-4 py-2 bg-white border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50 transition-colors">
                                <i class="fas fa-envelope mr-2"></i> Email Customer
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>

<?php require '../headFooter/footer.php'; ?>