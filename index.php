<?php
session_start();
require 'backend/db_connect.php';

$errors = [];
$success = '';

$base_url = '/university_marketplace/'; // adjust if needed

// Fetch cart count
if (isset($_SESSION['user_id'])) {
    $stmt = $conn->prepare("SELECT SUM(quantity) as total_items FROM cart WHERE user_id = ?");
    $stmt->bind_param("i", $_SESSION['user_id']);
    $stmt->execute();
    $cart_count_result = $stmt->get_result();
    $cart_count = $cart_count_result->fetch_assoc()['total_items'] ?? 0;
    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>UniMarket | University Marketplace</title>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;700&display=swap">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
/* === Reset & Base === */
* {margin:0;padding:0;box-sizing:border-box;font-family:'Montserrat',sans-serif;}
body {background:#121212;color:#e0e0e0;line-height:1.6;}
a {text-decoration:none;}
button {cursor:pointer;}
/* Header */
.header {background:#1f2937;color:#e0e0e0;padding:1rem 2rem;display:flex;justify-content:space-between;align-items:center;position:sticky;top:0;z-index:1000;box-shadow:0 4px 10px rgba(0,0,0,0.5);}
.logo {font-size:2rem;font-weight:700;color:#facc15;}
.nav-menu {display:flex;list-style:none;align-items:center;}
.nav-menu li {margin-left:1rem;}
.nav-menu li a {color:#e0e0e0;font-weight:500;padding:0.5rem 1rem;transition:0.3s;}
.nav-menu li a:hover {color:#000;background:#facc15;}
.btn {padding:0.5rem 1.25rem;border:none;border-radius:6px;font-weight:500;font-size:0.95rem;transition:0.3s;box-shadow:0 3px 6px rgba(0,0,0,0.3);}
.btn-primary {background:#facc15;color:#000000;}
.btn-primary:hover {background:#facd34;transform:translateY(-2px);}
.btn-secondary {background:#374151;color:#fff;}
.btn-secondary:hover {background:#1f2937;transform:translateY(-2px);}
.btn-cart {background:#f2b518;color:#000000;}
.btn-cart:hover {background:#000000;color:#cbd5e1;transform:translateY(-2px);}

/* Mini Cart */
#mini-cart {display:none;position:absolute;right:0;top:40px;width:320px;background:#1e293b;color:#e2e8f0;padding:1rem;border-radius:8px;box-shadow:0 4px 15px rgba(0,0,0,0.3);z-index:1000;}
#mini-cart-items {max-height:200px;overflow-y:auto;}
#mini-cart-items div {display:flex;align-items:center;margin-bottom:0.5rem;}
#mini-cart-items img {width:50px;height:40px;object-fit:cover;border-radius:4px;margin-right:0.5rem;}
#mini-cart-items p {margin:0;}
#mini-cart-items .name {font-size:0.9rem;}
#mini-cart-items .price {font-size:0.8rem;}
#mini-cart .go-to-cart {display:block;margin-top:0.5rem;text-align:center;padding:0.5rem;background:#facc15;color:#000000;border-radius:6px;text-decoration:none;font-weight:500;}
#mini-cart .go-to-cart:hover {background:#eab308;}

/* Hero */
.hero {text-align:center;padding:3rem 1rem;background:#1f2937;border-bottom:1px solid #374151;}
.hero h1 {font-size:2.5rem;color:#facc15;margin-bottom:1rem;}
.hero p {font-size:1.1rem;color:#cbd5e1;margin-bottom:2rem;}

/* Hero Slider */
.hero-slider-container {position:relative;width:100%;max-width:800px;height:500px;margin:2rem auto 0;overflow:hidden;border-radius:10px;box-shadow:0 6px 15px rgba(0,0,0,0.5);}
.hero-slider .slide {position:absolute;width:100%;height:100%;opacity:0;transition:opacity 1s ease-in-out;}
.hero-slider .slide img {width:100%;height:100%;object-fit:cover;display:block;border-radius:10px;}
.hero-slider .slide.active {opacity:1;}
.slider-arrow {position:absolute;top:50%;transform:translateY(-50%);background:rgba(30,30,30,0.5);color:#facc15;border:none;font-size:2rem;padding:0.2rem 0.6rem;cursor:pointer;border-radius:50%;z-index:10;transition:background 0.3s;}
.slider-arrow:hover {background:rgba(30,30,30,0.8);}
.slider-arrow.prev {left:10px;}
.slider-arrow.next {right:10px;}
.slider-dots {position:absolute;bottom:10px;width:100%;text-align:center;z-index:10;}
.slider-dots .dot {display:inline-block;width:10px;height:10px;margin:0 5px;background:rgba(250,204,21,0.5);border-radius:50%;cursor:pointer;transition:background 0.3s;}
.slider-dots .dot.active {background:#facc15;}

/* Search & Filter */
.search-bar,
.filter-bar {
  max-width:1200px;
  margin:2rem auto 0;
  padding:0 1rem;
  display:flex;
  gap:1rem;
  flex-wrap:wrap;
  justify-content:center;
}
.search-bar input {
  padding:0.7rem 1rem;
  border-radius:6px;
  border:1px solid #374151;
  background:#1e293b;
  color:#e0e0e0;
  width:680px;
  outline:none;
}
.filter-bar select {
  padding:0.7rem 1rem;
  border-radius:6px;
  border:1px solid #374151;
  background:#1e293b;
  color:#e0e0e0;
  width:220px;
  outline:none;
}
.search-bar button,
.filter-bar button {
  padding:0.7rem 1.2rem;
  border-radius:6px;
  background:#facc15;
  color:#000;
  border:none;
  cursor:pointer;
  transition:0.3s;
}
.search-bar button:hover,
.filter-bar button:hover {
  background:#eab308;
  color:#000;
}

/* Product Grid */
.product-list {max-width:1200px;margin:2rem auto;padding:0 1rem;display:grid;grid-template-columns:repeat(auto-fill,minmax(260px,1fr));gap:1.5rem;}
.product-item {background:#1e293b;padding:1.5rem;border-radius:10px;box-shadow:0 6px 15px rgba(0,0,0,0.5);display:flex;flex-direction:column;justify-content:space-between;min-height:400px;max-height:400px;transition:transform 0.3s ease;}
.product-item:hover {transform:translateY(-5px);}
.product-image img {width:100%;height:160px;object-fit:cover;border-radius:6px;border:1px solid #374151;margin-bottom:1rem;}
.product-details {flex-grow:1;overflow-y:auto;padding:0 0.25rem;margin-bottom:0.5rem;}
.product-details h3 {font-size:1.2rem;color:#facc15;margin-bottom:0.5rem;word-wrap:break-word;}
.product-details p, .product-details .details-text {font-size:0.95rem;color:#cbd5e1;margin-bottom:0.5rem;}
.product-details::-webkit-scrollbar {width:6px;}
.product-details::-webkit-scrollbar-track {background:#fff;}
.product-details::-webkit-scrollbar-thumb {background:#facc15;border-radius:6px;}
.product-actions {margin-top:1rem;padding:0 0.25rem;text-align:center;}
.add-to-cart-btn {width:100%;padding:0.5rem;border:none;border-radius:6px;background:#f2b518;color:#000;font-weight:500;transition:0.3s;}
.add-to-cart-btn:hover {background:#000;color:#cbd5e1;transform:translateY(-2px);}

/* Messages */
.error, .success {margin:1rem auto;padding:1rem;border-radius:8px;font-size:1rem;text-align:center;max-width:650px;}
.error {background:#991b1b;color:#fcd34d;border:1px solid #f87171;}
.success {background:#065f46;color:#d1fae5;border:1px solid #34d399;}

/* Footer */
.footer {background:#1f2937;color:#cbd5e1;padding:2rem 2rem;width:100%;border-top:1px solid #374151;}
.footer-container {display:flex;justify-content:space-between;flex-wrap:wrap;gap:2rem;max-width:1200px;margin:0 auto;}
.footer-section {flex:1;min-width:220px;}
.footer-section h3 {font-size:1.2rem;margin-bottom:1rem;color:#facc15;display:flex;align-items:center;gap:0.5rem;}
.footer-section p, .footer-link {font-size:0.95rem;color:#cbd5e1;margin-bottom:0.5rem;}
.footer-link:hover {color:#facc15;text-decoration:underline;}
.footer-bottom {text-align:center;margin-top:2rem;padding-top:1rem;border-top:1px solid #374151;font-size:0.9rem;color:#94a3b8;}

/* Responsive */
@media (max-width:768px){
    .nav-menu {flex-direction:column;background:#1f2937;position:absolute;top:64px;left:0;width:100%;display:none;}
    .nav-menu li {margin:0.5rem 0;}
    .product-list {grid-template-columns:1fr;}
    .product-item {min-height:350px;max-height:350px;}
    .product-image img {height:140px;}
    .product-details {max-height:120px;}
    .hero-slider-container {height:150px;}
    #mini-cart {width:250px;}
}
@media (max-width:480px){
    .hero h1{font-size:2rem;}
    .btn{padding:0.4rem 1rem;font-size:0.85rem;}
    .product-image img{height:120px;}
    .product-details{max-height:100px;}
    .hero-slider-container {height:120px;}
    #mini-cart {width:200px;}
}
</style>
</head>
<body>
<header class="header">
    <div class="logo">UniMarket</div>
    <nav class="nav">
        <ul class="nav-menu">
            <li><a href="index.php">Home</a></li>
            <?php if(isset($_SESSION['user_id'])): 
                $stmt = $conn->prepare("SELECT is_admin FROM users WHERE id=?");
                $stmt->bind_param("i", $_SESSION['user_id']); $stmt->execute(); $user = $stmt->get_result()->fetch_assoc(); $stmt->close();
                if($user['is_admin']): ?>
                    <li><a href="auth/admin.php">Admin Dashboard</a></li>
                <?php endif; ?>
                <li><a href="auth/profile.php">Profile</a></li>
                <li><a href="products/add-edit-listing.php">Sell</a></li>
                <li><a href="auth/logout.php">Logout</a></li>
                <li style="position:relative;">
                    <button id="cart-btn" class="btn btn-cart">
                        <i class="fas fa-shopping-cart"></i>
                        <?php if ($cart_count > 0): ?>
                            <span id="cart-count" style="background:#facc15;color:#000;border-radius:50%;padding:2px 6px;font-size:0.8rem;margin-left:5px;"><?php echo $cart_count; ?></span>
                        <?php endif; ?>
                    </button>
                    <div id="mini-cart">
                        <div id="mini-cart-items"></div>
                        <a href="cart.php" class="go-to-cart">Go to Cart</a>
                    </div>
                </li>
            <?php else: ?>
                <li><a href="auth/signup.php" class="btn btn-primary btn-small">Sign Up</a></li>
                <li><a href="auth/login.php" class="btn btn-secondary btn-small">Login</a></li>
            <?php endif; ?>
        </ul>
    </nav>
</header>

<main>
    <section class="hero">
        <h1>Welcome to UniMarket</h1>
        <p>Discover a professional marketplace for university trading.</p>
        <?php if(!isset($_SESSION['user_id'])): ?>
            <a href="auth/signup.php" class="btn btn-primary">Get Started</a>
        <?php endif; ?>

        <!-- Hero Slider -->
        <div class="hero-slider-container">
            <div class="hero-slider">
                <?php
                $stmt = $conn->prepare("SELECT image FROM products WHERE approved=1 ORDER BY created_at DESC LIMIT 5");
                $stmt->execute();
                $sliderImages = $stmt->get_result();
                if($sliderImages->num_rows > 0){
                    while($img = $sliderImages->fetch_assoc()){
                        $imgPath = file_exists($_SERVER['DOCUMENT_ROOT'].'/'.$img['image']) ? $img['image'].'?t='.time() : 'https://via.placeholder.com/800x200';
                        echo '<div class="slide"><img src="'.$imgPath.'" alt="Slider Image"></div>';
                    }
                } else {
                    echo '<div class="slide"><img src="https://via.placeholder.com/800x200" alt="Slider Image"></div>';
                }
                $stmt->close();
                ?>
            </div>
            <button class="slider-arrow prev">&#10094;</button>
            <button class="slider-arrow next">&#10095;</button>
            <div class="slider-dots"></div>
        </div>
    </section>

    <!-- Search Bar -->
    <div class="search-bar">
        <input type="text" id="search-input" placeholder="Search products by name or details...">
        <button id="search-btn">Search</button>
    </div>

    <!-- Filter Bar -->
    <div class="filter-bar">
        <select id="filter-category">
            <option value="">All Categories</option>
            <option value="Electronics">Electronics</option>
            <option value="Furniture">Furniture</option>
            <option value="Table">Table</option>
            <option value="Vehicles">Vehicles</option>
            <option value="Clothes">Clothes</option>
            <option value="Books">Books</option>
            <option value="Others">Others</option>
        </select>
        <select id="filter-status">
            <option value="">All Conditions</option>
            <option value="New">New</option>
            <option value="Used">Used</option>
        </select>
        <select id="filter-price">
            <option value="">All Prices</option>
            <option value="low">Price: Low to High</option>
            <option value="high">Price: High to Low</option>
        </select>
        <button id="filter-btn">Filter</button>
    </div>

    <section class="product-list">
        <?php
        $stmt = $conn->prepare("SELECT * FROM products WHERE approved=1 ORDER BY created_at DESC");
        $stmt->execute();
        $products = $stmt->get_result();
        if($products->num_rows > 0){
            while($product = $products->fetch_assoc()){
                $relative_path = htmlspecialchars($product['image']);
                $file_path = $_SERVER['DOCUMENT_ROOT'].'/'.$relative_path;
                $image_path = file_exists($file_path) ? $relative_path.'?t='.time() : 'https://via.placeholder.com/220';
                echo '<div class="product-item" 
                      data-name="'.htmlspecialchars(strtolower($product['name'])).'" 
                      data-details="'.htmlspecialchars(strtolower($product['details'])).'" 
                      data-status="'.htmlspecialchars($product['status']).'" 
                      data-price="'.htmlspecialchars($product['price']).'" 
                      data-category="'.htmlspecialchars($product['category']).'">
                      <div class="product-image"><img src="'.$image_path.'" alt="'.htmlspecialchars($product['name']).'"></div>
                      <div class="product-details">
                          <h3>'.htmlspecialchars($product['name']).'</h3>
                          <p><strong>Status:</strong> '.htmlspecialchars($product['status']).'</p>
                          <p><strong>Price:</strong> $'.htmlspecialchars($product['price']).'</p>
                          <div class="details-text">'.nl2br(htmlspecialchars($product['details'])).'</div>
                      </div>
                      <div class="product-actions">';
                if(isset($_SESSION['user_id'])){
                    echo '<form class="add-to-cart-form" style="width:100%;">';
                    echo '<input type="hidden" name="product_id" value="' . $product['id'] . '">';
                    echo '<input type="hidden" name="quantity" value="1">';
                    echo '<button type="submit" class="add-to-cart-btn">Add to Cart</button>';
                    echo '</form>';
                }
                echo '</div></div>';
            }
        } else {
            echo '<p style="text-align:center;">No products available.</p>';
        }
        $stmt->close();
        ?>
    </section>

    <?php
    if (!empty($errors)) {
        foreach ($errors as $error) {
            echo '<div class="error">'.$error . '</div>';
        }
    }
    if (!empty($success)) {
        echo '<div class="success">'.$success . '</div>';
    }
    ?>
</main>

<footer class="footer">
    <div class="footer-container">
        <div class="footer-section">
            <h3><i class="fas fa-envelope"></i> Contact Us</h3>
            <p>Email: support@unimarket.edu</p>
            <p>Phone: +8801745-706878</p>
            <a href="mailto:support@unimarket.edu" class="footer-link">Send us a message</a>
        </div>
        <div class="footer-section">
            <h3><i class="fas fa-file-alt"></i> Terms & Conditions</h3>
            <p>By using UniMarket, you agree to our policies on safe trading.</p>
            <a href="support/terms-privacy.php" class="footer-link">Read Full Terms</a>
        </div>
        <div class="footer-section">
            <h3><i class="fas fa-map-marker-alt"></i> Location</h3>
            <p>Shahid Shahidul Islam Hall</p>
            <a href="https://www.google.com/maps/place/Shahid+Shahidul+Islam+Hall/@24.3667606,88.6233247,644m/data=!3m2!1e3!4b1!4m6!3m5!1s0x39fbefd1b268fda9:0x623d5891478eb5af!8m2!3d24.3667557!4d88.6258996!16s%2Fg%2F1q69kh20k?entry=ttu&g_ep=EgoyMDI1MDkwNy4wIKXMDSoASAFQAw%3D%3D" target="_blank" class="footer-link">View on Map</a>
        </div>
    </div>
    <div class="footer-bottom">
        <p>&copy; 2025 UniMarket. All rights reserved.</p>
    </div>
</footer>

<script>
let slides = document.querySelectorAll('.slide');
let currentSlide = 0;
let slideInterval = setInterval(nextSlide, 3000);

function showSlide(n) {
    slides.forEach((s, i) => s.classList.remove('active'));
    slides[n].classList.add('active');
    updateDots(n);
}

function nextSlide() {
    currentSlide = (currentSlide + 1) % slides.length;
    showSlide(currentSlide);
}

function prevSlide() {
    currentSlide = (currentSlide - 1 + slides.length) % slides.length;
    showSlide(currentSlide);
}

document.querySelector('.slider-arrow.next').addEventListener('click', () => { nextSlide(); resetInterval(); });
document.querySelector('.slider-arrow.prev').addEventListener('click', () => { prevSlide(); resetInterval(); });

const dotsContainer = document.querySelector('.slider-dots');
slides.forEach((_, i) => {
    const dot = document.createElement('span');
    dot.classList.add('dot');
    dot.addEventListener('click', () => { currentSlide = i; showSlide(currentSlide); resetInterval(); });
    dotsContainer.appendChild(dot);
});

function updateDots(n) {
    document.querySelectorAll('.dot').forEach(d => d.classList.remove('active'));
    document.querySelectorAll('.dot')[n].classList.add('active');
}

function resetInterval() {
    clearInterval(slideInterval);
    slideInterval = setInterval(nextSlide, 3000);
}

showSlide(currentSlide);

// Fetch and display mini cart
function fetchCart() {
    fetch('cart_fetch.php')
    .then(res => {
        if (!res.ok) throw new Error('Network response was not ok');
        return res.json();
    })
    .then(data => {
        const container = document.getElementById('mini-cart-items');
        const miniCart = document.getElementById('mini-cart');
        const cartCount = document.getElementById('cart-count');
        container.innerHTML = '';
        if (data.items.length === 0) {
            miniCart.style.display = 'none';
            if (cartCount) {
                cartCount.style.display = 'none';
                cartCount.textContent = '0';
            }
            return;
        }
        miniCart.style.display = 'block';
        if (cartCount) {
            cartCount.style.display = 'inline';
            cartCount.textContent = data.items.reduce((sum, item) => sum + item.quantity, 0);
        }
        data.items.forEach(item => {
            const div = document.createElement('div');
            div.style.display = 'flex';
            div.style.alignItems = 'center';
            div.style.marginBottom = '0.5rem';
            div.innerHTML = `<img src="${item.image}" style="width:50px;height:40px;object-fit:cover;border-radius:4px;margin-right:0.5rem;">
                             <div style="flex:1;"><p style="margin:0;font-size:0.9rem;">${item.name}</p><p style="margin:0;font-size:0.8rem;">$${item.price} x ${item.quantity}</p></div>`;
            container.appendChild(div);
        });
    })
    .catch(error => console.error('Error fetching cart:', error));
}

// Add to Cart
document.querySelectorAll('.add-to-cart-form').forEach(form => {
    form.addEventListener('submit', function(e) {
        e.preventDefault();
        const formData = new FormData(form);
        fetch('cart_add.php', {
            method: 'POST',
            body: formData
        })
        .then(res => {
            if (!res.ok) throw new Error('Network response was not ok');
            return res.json();
        })
        .then(data => {
            if (data.success) {
                fetchCart(); // Update mini cart and cart count
                alert('Item added to cart successfully!');
            } else {
                alert(data.error);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('An error occurred while adding to cart: ' + error.message);
        });
    });
});

// Toggle mini cart on cart button click
document.getElementById('cart-btn').addEventListener('click', () => {
    const miniCart = document.getElementById('mini-cart');
    if (miniCart.style.display === 'block') {
        miniCart.style.display = 'none';
    } else {
        fetchCart(); // Refresh cart before showing
        miniCart.style.display = 'block';
    }
});

// Initial fetch of cart
fetchCart();

// Search & Filter
function filterProducts() {
    const searchVal = document.getElementById('search-input').value.toLowerCase();
    const categoryVal = document.getElementById('filter-category').value.toLowerCase();
    const statusVal = document.getElementById('filter-status').value.toLowerCase();
    const priceVal = document.getElementById('filter-price').value;

    document.querySelectorAll('.product-item').forEach(item => {
        const name = item.dataset.name.toLowerCase();
        const details = item.dataset.details.toLowerCase();
        const status = item.dataset.status.toLowerCase();
        const price = parseFloat(item.dataset.price);
        const category = item.dataset.category ? item.dataset.category.toLowerCase() : '';

        let show = true;

        if (searchVal && !(name.includes(searchVal) || details.includes(searchVal))) show = false;
        if (statusVal && status !== statusVal) show = false;
        if (categoryVal && category !== categoryVal) show = false;

        item.style.display = show ? 'flex' : 'none';
    });

    if (priceVal) {
        const container = document.querySelector('.product-list');
        const items = Array.from(container.querySelectorAll('.product-item'))
                           .filter(i => i.style.display !== 'none');
        items.sort((a, b) => {
            const pa = parseFloat(a.dataset.price);
            const pb = parseFloat(b.dataset.price);
            return priceVal === 'low' ? pa - pb : pb - pa;
        });
        items.forEach(i => container.appendChild(i));
    }
}

document.getElementById('search-btn').addEventListener('click', filterProducts);
document.getElementById('filter-btn').addEventListener('click', filterProducts);
document.getElementById('search-input').addEventListener('input', filterProducts);
</script>
</body>
</html>