<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/remixicon@3.0.0/fonts/remixicon.css" rel="stylesheet">
    <link rel="stylesheet" href="/user/css/footer.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://kit.fontawesome.com/d317456e1b.js" crossorigin="anonymous"></script>
</head>
<footer class="footer">
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
                    <h4>Shop</h4>
                    <ul>
                        <li><a href="/user/page/products.php?gender=Man">Men's Collection</a></li>
                        <li><a href="/user/page/products.php?gender=Women">Women's Collection</a></li>
                        <li><a href="/user/page/products.php">New Arrivals</a></li>
                        <li><a href="/user/page/products.php">Best Sellers</a></li>
                    </ul>
                </div>

                <div class="footer-column">
                    <h4>Customer Service</h4>
                    <ul>
                        <li><a href="/user/page/contact.php">Contact Us</a></li>
                        <li><a href="/user/page/faq.php">FAQ</a></li>
                        <li><a href="/user/page/return_policy.php">Return Policy</a></li>
                        <li><a href="/user/page/shipping_policy.php">Shipping Policy</a></li>
                    </ul>
                </div>

                <div class="footer-column">
                    <h4>Account</h4>
                    <ul>
                        <li><a href="/user/page/login.php">Sign In</a></li>
                        <li><a href="/user/page/register.php">Register</a></li>
                        <li><a href="/user/page/orders.php">Order History</a></li>
                        <li><a href="/user/page/profile.php">My Profile</a></li>
                    </ul>
                </div>

                <div class="footer-column">
                    <h4>About Us</h4>
                    <ul>
                        <li><a href="/user/page/about.php">Our Story</a></li>
                        <li><a href="/user/page/sustainability.php">Sustainability</a></li>
                        <li><a href="/user/page/careers.php">Careers</a></li>
                        <li><a href="/user/page/privacy_policy.php">Privacy Policy</a></li>
                    </ul>
                </div>
            </div>

            <div class="footer-social">
                <h4>Follow Us</h4>
                <div class="social-icons">
                    <a href="https://www.instagram.com/js_0419_"><i class="fab fa-instagram"></i></a>
                    <a href="https://www.facebook.com/lee.jinkhai.7"><i class="fab fa-facebook"></i></a>
                    <a href="https://twitter.com"><i class="fab fa-twitter"></i></a>
                    <a href="https://youtube.com"><i class="fab fa-youtube"></i></a>
                </div>
            </div>

            <div class="footer-newsletter">
                <h4>Subscribe to our Newsletter</h4>
                <form action="/user/page/subscribe_newsletter.php" method="post">
                    <div class="newsletter-input">
                        <input type="email" name="email" placeholder="Your email address" required>
                        <button type="submit"><i class="fas fa-paper-plane"></i></button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="footer-bottom">
        <p>© <?= date('Y') ?> K&P Fashion. All rights reserved. The content of this site is copyright-protected and is the property of K&P.</p>
        <p>K&P business concept is to offer fashion and quality at the best price in a sustainable way.</p>
    </div>

    <script src="https://kit.fontawesome.com/d317456e1b.js" crossorigin="anonymous"></script>
</footer>