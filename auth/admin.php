<?php
session_start();
require '../backend/db_connect.php';

// Redirect if not logged in or not admin
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$stmt = $conn->prepare("SELECT is_admin FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$user['is_admin']) {
    header("Location: ../index.php");
    exit();
}

$errors = [];
$success = '';
$base_url = 'http://localhost/university_marketplace/'; // change according to your setup

// Approve product
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['approve'])) {
    $product_id = filter_var($_POST['product_id'], FILTER_VALIDATE_INT);
    if ($product_id) {
        $stmt = $conn->prepare("UPDATE products SET approved = 1 WHERE id = ?");
        $stmt->bind_param("i", $product_id);
        if ($stmt->execute()) {
            $success = 'Product approved successfully!';
            header("refresh:1;url=admin.php");
            exit();
        } else {
            $errors[] = 'Error approving product.';
        }
        $stmt->close();
    } else {
        $errors[] = 'Invalid product ID.';
    }
}

// Delete product
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete'])) {
    $product_id = filter_var($_POST['delete'], FILTER_VALIDATE_INT);
    if ($product_id) {
        $stmt = $conn->prepare("SELECT image FROM products WHERE id = ?");
        $stmt->bind_param("i", $product_id);
        $stmt->execute();
        $product = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        $stmt = $conn->prepare("DELETE FROM products WHERE id = ?");
        $stmt->bind_param("i", $product_id);
        if ($stmt->execute()) {
            // Delete image file
            if (!empty($product['image'])) {
                $image_path = __DIR__ . '/../' . $product['image'];
                if (file_exists($image_path)) {
                    unlink($image_path);
                }
            }
            $success = 'Product deleted successfully!';
            header("refresh:1;url=admin.php");
            exit();
        } else {
            $errors[] = 'Error deleting product.';
        }
        $stmt->close();
    } else {
        $errors[] = 'Invalid product ID.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admin Dashboard | UniMarket</title>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;700&display=swap">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
/* === Reset & Base (match index.php) === */
* { margin:0; padding:0; box-sizing:border-box; font-family:'Montserrat',sans-serif; }
body { background:#121212; color:#e0e0e0; line-height:1.6; }
a { text-decoration:none; }
button { cursor:pointer; }

/* === Header (same structure & vibe as index.php) === */
.header {
  background:#1f2937;
  color:#e0e0e0;
  padding:1rem 2rem;
  display:flex;
  justify-content:space-between;
  align-items:center;
  position:sticky;
  top:0;
  z-index:1000;
  box-shadow:0 4px 10px rgba(0,0,0,0.5);
}
.logo { font-size:2rem; font-weight:700; color:#facc15; }
.nav-menu { display:flex; list-style:none; align-items:center; gap:0.75rem; }
.nav-menu li a {
  color:#e0e0e0;
  font-weight:500;
  padding:0.5rem 1rem;
  border-radius:6px;
  transition:0.3s;
}
.nav-menu li a:hover { color:#facc15; background:rgba(255,255,255,0.06); }

/* Shared buttons (match index.php palette) */
.btn { padding:0.5rem 1.25rem; border:none; border-radius:6px; font-weight:500; font-size:0.95rem; transition:0.3s; box-shadow:0 3px 6px rgba(0,0,0,0.3); }
.btn-primary { background:#facc15; color:#000000; }
.btn-primary:hover { background:#facc24; transform:translateY(-2px); }
.btn-secondary { background:#374151; color:#fff; }
.btn-secondary:hover { background:#1f2937; transform:translateY(-2px); }
.btn-danger { background:#ef4444; color:#fff; }
.btn-danger:hover { background:#b91c1c; transform:translateY(-2px); }
.btn-edit { background:#facc15; color:#000000; }
.btn-edit:hover { background:#c4a93b; transform:translateY(-2px); }

/* === Admin Container (dark card shell) === */
.admin-container {
  max-width:1200px;
  margin:2rem auto;
  padding:2rem;
  background:#0f172a;
  border:1px solid #374151;
  border-radius:12px;
  box-shadow:0 6px 20px rgba(0,0,0,0.5);
}
.admin-container h2 {
  text-align:center;
  font-size:2rem;
  color:#facc15;
  margin-bottom:1.5rem;
}

/* Messages (dark-friendly) */
.error, .success {
  margin:1rem 0;
  padding:1rem;
  border-radius:8px;
  text-align:center;
  font-size:1rem;
}
.error { background:#991b1b; color:#fcd34d; border:1px solid #f87171; }
.success { background:#065f46; color:#d1fae5; border:1px solid #34d399; }

/* === Product Grid (match cards from index.php) === */
.product-list {
  display:grid;
  grid-template-columns:repeat(auto-fill, minmax(260px, 1fr));
  gap:1.5rem;
}
.product-item {
  background:#1e293b;
  padding:1rem;
  border-radius:10px;
  border:1px solid #374151;
  box-shadow:0 6px 15px rgba(0,0,0,0.5);
  display:flex;
  flex-direction:column;
  justify-content:space-between;
  transition:transform 0.3s ease, box-shadow 0.3s ease;
}
.product-item:hover { transform:translateY(-5px); box-shadow:0 10px 22px rgba(0,0,0,0.6); }

.product-image img {
  width:100%;
  height:160px;
  object-fit:cover;
  border-radius:6px;
  border:1px solid #374151;
  margin-bottom:0.75rem;
}

.product-details p {
  margin:0.35rem 0;
  font-size:0.95rem;
  color:#cbd5e1;
  overflow:hidden;
  text-overflow:ellipsis;
}
.product-details p strong { color:#e5e7eb; }

.product-actions {
  display:flex;
  justify-content:center;
  gap:0.5rem;
  margin-top:1rem;
  flex-wrap:wrap;
}

/* === Footer (match index.php) === */
.footer {
  background:#1f2937;
  color:#cbd5e1;
  padding:2rem 2rem;
  width:100%;
  border-top:1px solid #374151;
  margin-top:2rem;
}
.footer-container {
  display:flex;
  justify-content:space-between;
  flex-wrap:wrap;
  gap:2rem;
  max-width:1200px;
  margin:0 auto;
}
.footer-section { flex:1; min-width:220px; }
.footer-section h3 {
  font-size:1.2rem;
  margin-bottom:1rem;
  color:#facc15;
  display:flex;
  align-items:center;
  gap:0.5rem;
}
.footer-section p { font-size:0.95rem; color:#cbd5e1; margin-bottom:0.5rem; }
.footer-link { color:#93c5fd; text-decoration:none; font-size:0.95rem; }
.footer-link:hover { color:#facc15; text-decoration:underline; }
.footer-bottom {
  text-align:center;
  margin-top:1.5rem;
  padding-top:1rem;
  border-top:1px solid #374151;
  font-size:0.9rem;
  color:#94a3b8;
}

/* === Responsive (clean & simple, no HTML changes needed) === */
@media (max-width:1024px){
  .admin-container { padding:1.5rem; }
}
@media (max-width:768px){
  .nav-menu { flex-wrap:wrap; justify-content:center; gap:0.5rem; }
  .admin-container { margin:1.25rem auto; padding:1rem; }
  .product-list { grid-template-columns:1fr; }
  .product-image img { height:140px; }
}
@media (max-width:480px){
  .logo { font-size:1.6rem; }
  .btn { padding:0.45rem 1rem; font-size:0.9rem; }
  .product-image img { height:120px; }
}
</style>
</head>
<body>
<header class="header">
  <div class="logo">UniMarket Admin</div>
  <nav>
    <ul class="nav-menu">
      <li><a href="../index.php">Home</a></li>
      <li><a href="admin.php">Dashboard</a></li>
      <li><a href="logout.php" class="btn btn-secondary">Logout</a></li>
    </ul>
  </nav>
</header>
<main class="admin-container">
<h2>Admin Dashboard - Product Management</h2>

<?php if ($errors): ?>
<div class="error">
  <?php foreach ($errors as $error): ?>
    <p><?php echo htmlspecialchars($error); ?></p>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<?php if ($success): ?>
<div class="success"><?php echo htmlspecialchars($success); ?></div>
<?php endif; ?>

<div class="product-list">
<?php
$stmt = $conn->prepare("SELECT * FROM products ORDER BY created_at DESC");
$stmt->execute();
$products = $stmt->get_result();

if ($products->num_rows > 0) {
  while ($product = $products->fetch_assoc()) {
      $image_path = !empty($product['image']) && file_exists(__DIR__ . '/../' . $product['image'])
                    ? $base_url . $product['image'] . '?t=' . time()
                    : 'https://via.placeholder.com/180x140';

      echo '<div class="product-item">
        <div class="product-image">
          <img src="' . $image_path . '" alt="Product Image">
        </div>
        <div class="product-details">
          <p><strong>Name:</strong> ' . htmlspecialchars($product['name']) . '</p>
          <p><strong>Status:</strong> ' . ($product['approved'] ? 'Approved' : 'Pending') . '</p>
          <p><strong>Price:</strong> $' . htmlspecialchars($product['price']) . '</p>
          <p><strong>Details:</strong> ' . htmlspecialchars($product['details']) . '</p>
        </div>
        <div class="product-actions">';

      if ($product['approved']) {
          // Approved: Edit + Delete
          echo '<form method="get" action="edit_product.php" style="margin:0;">
                  <input type="hidden" name="id" value="' . $product['id'] . '">
                  <button type="submit" class="btn btn-edit">Edit</button>
                </form>';
      } else {
          // Unapproved: Approve + Delete
          echo '<form method="post" style="margin:0;">
                  <input type="hidden" name="product_id" value="' . $product['id'] . '">
                  <button type="submit" name="approve" class="btn btn-primary">Approve</button>
                </form>';
      }
      echo '<form method="post" style="margin:0;">
              <button type="submit" name="delete" value="' . $product['id'] . '" class="btn btn-danger">Delete</button>
            </form>';

      echo '</div></div>';
  }
} else {
    echo '<p>No products found.</p>';
}
$stmt->close();
?>
</div>

</main>

<footer class="footer">
<div class="footer-container">
  <div class="footer-section">
    <h3><i class="fas fa-envelope"></i> Contact Us</h3>
    <p>Email: support@unimarket.edu</p>
    <p>Phone: +8801745-706878</p>
    <a href="mailto:support@unimarket.edu" class="footer-link">Send a message</a>
  </div>
  <div class="footer-section">
    <h3><i class="fas fa-file-alt"></i> Terms & Conditions</h3>
    <p>By using UniMarket, you agree to our safe trading policies.</p>
    <a href="../support/terms-privacy.php" class="footer-link">Read Full Terms</a>
  </div>
  <div class="footer-section">
    <h3><i class="fas fa-map-marker-alt"></i> Location</h3>
    <p>Shahid Shahidul Islam Hall</p>
    <a href="https://www.google.com/maps/place/Shahid+Shahidul+Islam+Hall/@24.3667606,88.6233247,644m/data=!3m2!1e3!4b1!4m6!3m5!1s0x39fbefd1b268fda9:0x623d5891478eb5af!8m2!3d24.3667557!4d88.6258996!16s%2Fg%2F1q69kh20k?entry=ttu&g_ep=EgoyMDI1MDkwNy4wIKXMDSoASAFQAw%3D%3D" target="_blank" class="footer-link">View on Map</a>
  </div>
</div>
<div class="footer-bottom">&copy; 2025 UniMarket. All rights reserved.</div>
</footer>
</body>
</html>
