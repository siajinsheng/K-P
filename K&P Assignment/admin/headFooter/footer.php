</div> <!-- End of Main Content Wrapper -->

<footer class="admin-footer">
    <div class="footer-container">
        <div class="footer-left">
            <h3>Visit Our Store</h3>
            <div class="map-container">
                <iframe
                src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3983.786880529544!2d101.61542180000001!3d3.1508396000000043!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x31cc4ed533e1825f%3A0x7c8be57d07706be!2s1%20Utama%20Shopping%20Centre%20(New%20Wing)%2C%20Bandar%20Utama%2C%2047800%20Petaling%20Jaya%2C%20Selangor!5e0!3m2!1sen!2smy!4v1745455844950!5m2!1sen!2smy"
                width="100%"
                    height="250"
                    style="border:0;"
                    allowfullscreen=""
                    loading="lazy"
                    referrerpolicy="no-referrer-when-downgrade">
                </iframe>
            </div>
            <div class="store-info">
                <div class="store-detail">
                    <i class="fas fa-map-marker-alt"></i>
                    <p>Jalan Genting Kelang, Setapak, 53300 Kuala Lumpur, Malaysia</p>
                </div>
                <div class="store-detail">
                    <i class="fas fa-phone"></i>
                    <p>+60 3-4145 0123</p>
                </div>
                <div class="store-detail">
                    <i class="fas fa-clock"></i>
                    <p>Mon-Sat: 10:00 AM - 9:00 PM</p>
                </div>
            </div>
        </div>

        <div class="footer-right">
            <div class="footer-links">
                <div class="footer-column">
                    <h4>Quick Links</h4>
                    <ul>
                        <li><a href="../home/home.php">Dashboard</a></li>
                        <li><a href="../product/product.php">Products</a></li>
                        <li><a href="../category/category.php">Categories</a></li>
                        <li><a href="../discount/discount.php">Discounts</a></li>
                    </ul>
                </div>

                <div class="footer-column">
                    <h4>Management</h4>
                    <ul>
                        <li><a href="../order/orders.php">Orders</a></li>
                        <li><a href="../payment/payment.php">Payments</a></li>
                        <li><a href="../customer/customers.php">Customers</a></li>
                        <?php if (isset($_SESSION['user']) && $_SESSION['user']->role === 'admin'): ?>
                        <li><a href="../staff/staff.php">Staff</a></li>
                        <?php endif; ?>
                    </ul>
                </div>

                <div class="footer-column">
                    <h4>Resources</h4>
                    <ul>
                        <li><a href="../home/reports.php">Sales Reports</a></li>
                        <li><a href="../help/documentation.php">Documentation</a></li>
                        <li><a href="../help/system_guide.php">System Guide</a></li>
                        <li><a href="../help/faq.php">FAQ</a></li>
                    </ul>
                </div>

                <div class="footer-column">
                    <h4>Account</h4>
                    <ul>
                        <li><a href="../profile/profile.php">My Profile</a></li>
                        <li><a href="../profile/security.php">Security</a></li>
                        <li><a href="../loginOut/logout.php">Log Out</a></li>
                        <li>
                            <div class="user-info">
                                <span class="user-name"><?= htmlspecialchars($_SESSION['user']->user_name ?? 'Admin') ?></span>
                                <span class="current-time"><?= date('Y-m-d H:i:s') ?></span>
                            </div>
                        </li>
                    </ul>
                </div>
            </div>

            <div class="footer-social">
                <h4>Follow K&P Fashion</h4>
                <div class="social-icons">
                    <a href="https://www.instagram.com/js_0419_"><i class="fab fa-instagram"></i></a>
                    <a href="https://www.facebook.com/lee.jinkhai.7"><i class="fab fa-facebook"></i></a>
                    <a href="https://twitter.com"><i class="fab fa-twitter"></i></a>
                    <a href="https://youtube.com"><i class="fab fa-youtube"></i></a>
                </div>
            </div>

            <div class="footer-newsletter">
                <h4>Admin Notifications</h4>
                <div class="newsletter-input">
                    <input type="email" placeholder="Your work email" disabled>
                    <button type="button" disabled><i class="fas fa-bell"></i></button>
                </div>
                <p class="notification-text">Admin notifications are managed in system settings</p>
            </div>
        </div>
    </div>

    <div class="footer-bottom">
        <p>© <?= date('Y') ?> K&P Fashion Admin Portal. All rights reserved.</p>
        <p>Current User: <?= htmlspecialchars($_SESSION['user']->user_name ?? 'Admin') ?> | Last Login: <?= date('Y-m-d H:i:s') ?></p>
    </div>

    <script src="/admin/headFooter/header.js"></script>
</footer>
</body>
</html>