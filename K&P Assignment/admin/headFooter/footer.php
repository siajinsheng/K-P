</div> <!-- End of Main Content Wrapper -->
    
    <footer class="bg-white border-t border-gray-200 mt-12">
        <div class="container mx-auto px-4 py-8">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                <!-- Logo and About -->
                <div>
                    <div class="flex items-center mb-4">
                        <img src="../pic/logo.jpg" alt="K&P Fashion Logo" class="h-10 mr-3">
                        <h3 class="text-lg font-bold text-gray-800">K&P Fashion</h3>
                    </div>
                    <p class="text-gray-600 text-sm mb-4">
                        K&P Fashion offers trendy and high-quality clothing for all styles and occasions,
                        providing excellent customer service and a seamless shopping experience.
                    </p>
                    <div class="flex space-x-4">
                        <a href="#" class="text-gray-500 hover:text-indigo-600 transition-colors">
                            <i class="fab fa-facebook-f"></i>
                        </a>
                        <a href="#" class="text-gray-500 hover:text-indigo-600 transition-colors">
                            <i class="fab fa-instagram"></i>
                        </a>
                        <a href="#" class="text-gray-500 hover:text-indigo-600 transition-colors">
                            <i class="fab fa-twitter"></i>
                        </a>
                        <a href="#" class="text-gray-500 hover:text-indigo-600 transition-colors">
                            <i class="fab fa-linkedin-in"></i>
                        </a>
                    </div>
                </div>
                
                <!-- Quick Links -->
                <div>
                    <h3 class="text-lg font-bold text-gray-800 mb-4">Quick Links</h3>
                    <ul class="space-y-2">
                        <li><a href="../home/home.php" class="text-gray-600 hover:text-indigo-600 text-sm">Dashboard</a></li>
                        <li><a href="../product/product.php" class="text-gray-600 hover:text-indigo-600 text-sm">Products</a></li>
                        <li><a href="../order/orders.php" class="text-gray-600 hover:text-indigo-600 text-sm">Orders</a></li>
                        <li><a href="../product/reports.php" class="text-gray-600 hover:text-indigo-600 text-sm">Reports</a></li>
                    </ul>
                </div>
                
                <!-- Contact Info -->
                <div>
                    <h3 class="text-lg font-bold text-gray-800 mb-4">Contact Information</h3>
                    <ul class="space-y-2">
                        <li class="flex items-start">
                            <i class="fas fa-map-marker-alt text-indigo-600 mt-1 mr-3"></i>
                            <span class="text-gray-600 text-sm">123 Fashion Street, Kuala Lumpur, Malaysia</span>
                        </li>
                        <li class="flex items-center">
                            <i class="fas fa-phone-alt text-indigo-600 mr-3"></i>
                            <span class="text-gray-600 text-sm">+60 12-345-6789</span>
                        </li>
                        <li class="flex items-center">
                            <i class="fas fa-envelope text-indigo-600 mr-3"></i>
                            <span class="text-gray-600 text-sm">support@kpfashion.com</span>
                        </li>
                    </ul>
                </div>
            </div>
            
            <div class="border-t border-gray-200 mt-8 pt-6 flex flex-col md:flex-row justify-between items-center">
                <p class="text-gray-500 text-sm mb-4 md:mb-0">&copy; <?= date('Y') ?> K&P Fashion. All rights reserved.</p>
                <div class="flex space-x-4">
                    <a href="#" class="text-gray-500 hover:text-indigo-600 text-sm">Privacy Policy</a>
                    <a href="#" class="text-gray-500 hover:text-indigo-600 text-sm">Terms of Service</a>
                    <a href="#" class="text-gray-500 hover:text-indigo-600 text-sm">Help Center</a>
                </div>
            </div>
        </div>
    </footer>

    <script>
    // Toggle dropdown menu
    function toggleDropdown(event) {
        event.stopPropagation();
        document.querySelector('.profile-dropdown').classList.toggle('show');
    }

    // Close dropdown when clicking outside
    window.addEventListener('click', function() {
        document.querySelector('.profile-dropdown').classList.remove('show');
    });
    
    // Mobile menu functionality
    document.getElementById('mobile-menu-button').addEventListener('click', function() {
        document.getElementById('mobile-nav').classList.add('show');
    });
    
    document.getElementById('close-mobile-menu').addEventListener('click', function() {
        document.getElementById('mobile-nav').classList.remove('show');
    });
    
    // Add scroll effect to header
    window.addEventListener('scroll', function() {
        const header = document.querySelector('.navbar');
        if (window.scrollY > 10) {
            header.classList.add('scrolled');
        } else {
            header.classList.remove('scrolled');
        }
    });
    
    // Initialize header state on page load
    if (window.scrollY > 10) {
        document.querySelector('.navbar').classList.add('scrolled');
    }
    </script>
</body>
</html>