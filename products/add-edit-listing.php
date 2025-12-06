<?php
session_start();
require '../backend/db_connect.php';

// Redirect to login if not authenticated
if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$errors = [];
$success = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = filter_var($_POST['name'], FILTER_SANITIZE_STRING);
    $description = filter_var($_POST['description'], FILTER_SANITIZE_STRING);
    $status = $_POST['status'];
    $price = filter_var($_POST['price'], FILTER_VALIDATE_FLOAT);
    $category = $_POST['category'];

    // Validate inputs
    if (!$name) {
        $errors[] = 'Product name is required.';
    }
    if (!$description) {
        $errors[] = 'Product description is required.';
    }
    if (!in_array($status, ['used', 'new'])) {
        $errors[] = 'Invalid status selected.';
    }
    if (!$price || $price <= 0) {
        $errors[] = 'Valid price is required.';
    }
    if (!$category) {
        $errors[] = 'Please select a category.';
    }

    // Handle product image upload
    $product_image_path = '';
    if (isset($_FILES['product_image']) && $_FILES['product_image']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['product_image'];
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
        $max_size = 20 * 1024 * 1024; // 20MB
        if (!in_array($file['type'], $allowed_types)) {
            $errors[] = 'Only JPEG, PNG, or GIF images are allowed.';
        } elseif ($file['size'] > $max_size) {
            $errors[] = 'Image size must not exceed 20MB.';
        } else {
            $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
            $filename = 'product_' . $user_id . '_' . time() . '.' . $ext;
            $upload_dir = '../assets/images/products/';
            $upload_path = $upload_dir . $filename;
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            if (move_uploaded_file($file['tmp_name'], $upload_path)) {
                $product_image_path = 'assets/images/products/' . $filename;
            } else {
                $errors[] = 'Failed to upload image.';
            }
        }
    } else {
        $errors[] = 'Product image is required.';
    }

    // Insert product into database with approved=0
    if (empty($errors)) {
        $stmt = $conn->prepare("INSERT INTO products (user_id, name, image, status, price, details, category, approved) VALUES (?, ?, ?, ?, ?, ?, ?, 0)");
        $stmt->bind_param("isssdss", $user_id, $name, $product_image_path, $status, $price, $description, $category);
        if ($stmt->execute()) {
            $success = 'Product submitted successfully! Waiting for admin approval.';
            header("refresh:2;url=../index.php");
        } else {
            $errors[] = 'Error submitting product.';
        }
        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
  <title>Add Product | University Marketplace</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <style>
    /* === Reset & Base (match index.php dark professional) === */
    * { margin:0; padding:0; box-sizing:border-box; font-family:'Montserrat', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; -webkit-font-smoothing:antialiased; -moz-osx-font-smoothing:grayscale; }
    html,body { height:100%; }
    body { background:#0b1220; color:#e6eef8; line-height:1.6; -webkit-font-smoothing:antialiased; }

    /* === Header === */
    .header {
      background: linear-gradient(90deg,#1f2937,#1f2937);
      color: #e6eef8;
      padding: 1rem 1.25rem;
      display:flex;
      justify-content:space-between;
      align-items:center;
      position:sticky;
      top:0;
      z-index:1000;
      box-shadow: 0 6px 18px rgba(2,6,23,0.6);
      border-bottom: 1px solid rgba(255,255,255,0.03);
    }
    .logo { font-size:1.55rem; font-weight:700; color:#facc15; letter-spacing:0.4px; }

    .nav { display:flex; align-items:center; }
    .nav-menu { display:flex; list-style:none; align-items:center; gap:0.6rem; margin-left:0.5rem; }
    .nav-menu li { }
    .nav-menu li a {
      color:#e6eef8;
      text-decoration:none;
      padding:0.45rem 0.9rem;
      font-weight:500;
      border-radius:8px;
      transition: all 0.18s ease;
      display:inline-block;
    }
    .nav-menu li a:hover { background: rgba(255,255,255,0.03); color:#facc15; transform:translateY(-2px); }

    .btn-small {
      padding:0.45rem 0.8rem;
      font-size:0.85rem;
      border-radius:8px;
      border:none;
      background: linear-gradient(135deg,#3b82f6,#60a5fa);
      color:#07263b;
      font-weight:600;
    }
    .btn-small:hover { transform:translateY(-2px); }

    /* Mobile hamburger (kept but hidden on desktop) */
    .nav-toggle { display:none; }
    .nav-toggle-label { display:none; cursor:pointer; width:34px; height:22px; position:relative; }
    .nav-toggle-label span, .nav-toggle-label span::before, .nav-toggle-label span::after {
      background:#e6eef8; height:3px; width:100%; position:absolute; left:0; transition:all .25s ease;
    }
    .nav-toggle-label span { top:50%; transform:translateY(-50%); }
    .nav-toggle-label span::before { content:''; top:-8px; position:absolute; left:0; }
    .nav-toggle-label span::after  { content:''; top:8px; position:absolute; left:0; }

    .nav-toggle:checked ~ .nav-menu { display:flex; flex-direction:column; gap:0.5rem; padding:1rem; position:absolute; left:0; right:0; top:56px; background:#0b1220; box-shadow:0 10px 30px rgba(2,6,23,0.7); }

    /* === Form Container === */
    .form-container {
      max-width:720px;
      margin:2.25rem auto;
      padding:1.5rem;
      background: linear-gradient(180deg, #1f2937, rgba(11,17,32,0.9));
      border:1px solid rgba(255,255,255,0.03);
      border-radius:12px;
      box-shadow: 0 10px 30px rgba(2,6,23,0.7);
    }

    .form-container h2 {
      font-size:1.6rem;
      color:#facc15;
      margin-bottom:1.25rem;
      text-align:center;
      font-weight:700;
    }

    .form-group { margin-bottom:1rem; text-align:left; }
    .form-group label {
      display:block;
      font-weight:600;
      color:#cfe7ff;
      margin-bottom:0.4rem;
      font-size:0.95rem;
    }

    .form-group input,
    .form-group select,
    .form-group textarea {
      width:100%;
      padding:0.85rem;
      border-radius:10px;
      border:1px solid rgba(255,255,255,0.04);
      background:#0f1724;
      color:#e6eef8;
      font-size:0.95rem;
      transition:box-shadow .18s ease, border-color .18s ease;
      resize:vertical;
    }

    .form-group textarea { min-height:120px; }

    .form-group input:focus,
    .form-group select:focus,
    .form-group textarea:focus {
      outline:none;
      border-color:#3b82f6;
      box-shadow:0 6px 22px rgba(59,130,246,0.08);
    }

    .form-group input[type="file"] {
      padding:0.5rem;
      background:linear-gradient(180deg, rgba(255,255,255,0.01), rgba(255,255,255,0.02));
    }

    /* Buttons */
    .btn {
      display:inline-block;
      padding:0.85rem 1.6rem;
      border:none;
      border-radius:10px;
      font-weight:700;
      font-size:0.98rem;
      cursor:pointer;
      transition: transform .16s ease, box-shadow .16s ease;
      width:100%;
    }

    .btn-primary {
      background: linear-gradient(135deg,#e8b72e,#e8b72e);
      color:#041026;
      box-shadow: 0 6px 18px rgba(59,130,246,0.15);
    }
    .btn-primary:hover { transform:translateY(-3px); box-shadow: 0 10px 30px rgba(59,130,246,0.18); }

    .btn-secondary {
      background: linear-gradient(135deg,#374151,#4b5563);
      color:#e6eef8;
      margin-top:0.6rem;
    }
    .btn-secondary:hover { transform:translateY(-2px); }

    /* Messages */
    .error, .success {
      margin:1rem 0;
      padding:0.9rem;
      border-radius:8px;
      font-size:0.95rem;
      text-align:center;
    }
    .error { background: linear-gradient(180deg,#2b0b0b,#4a0f0f); color:#ffc8c8; border:1px solid #7f1d1d; }
    .success { background: linear-gradient(180deg,#072b1f,#0b412e); color:#bff7d1; border:1px solid #135e3f; }

    /* Footer */
    .footer {
      background: linear-gradient(180deg,#1f2937,#1f2937);
      color:#cfe7ff;
      padding:2rem 1.25rem;
      width:100%;
      margin-top:2rem;
      border-top:1px solid rgba(255,255,255,0.02);
    }
    .footer-container {
      display:flex;
      justify-content:space-between;
      flex-wrap:wrap;
      gap:1rem;
      max-width:1200px;
      margin:0 auto;
    }
    .footer-section { flex:1; min-width:220px; }
    .footer-section h3 { font-size:1.1rem; margin-bottom:0.8rem; color:#facc15; display:flex; gap:0.5rem; align-items:center; }
    .footer-section p, .footer-link { color:#cfe7ff; font-size:0.95rem; margin-bottom:0.5rem; }
    .footer-link:hover { color:#7dd3fc; text-decoration:underline; }
    .footer-bottom { text-align:center; margin-top:1rem; color:#94a3b8; font-size:0.9rem; }

    /* Scrollbar refinement */
    .form-group textarea::-webkit-scrollbar,
    .form-container::-webkit-scrollbar {
      width:8px;
    }
    .form-group textarea::-webkit-scrollbar-thumb,
    .form-container::-webkit-scrollbar-thumb {
      background:#1f6feb; border-radius:8px;
    }

    /* Responsive */
    @media (max-width: 992px) {
      .form-container { margin:1.5rem 1rem; padding:1.2rem; }
      .nav-menu { gap:0.45rem; }
    }
    @media (max-width: 768px) {
      .nav-toggle-label { display:block; }
      .nav-menu { display:none; position:relative; }
      .nav-toggle:checked ~ .nav-menu { display:flex; flex-direction:column; gap:0.6rem; padding:0.8rem; }
      .form-container { margin:1rem; padding:1rem; border-radius:10px; }
      .form-container h2 { font-size:1.35rem; }
    }
    @media (max-width: 480px) {
      .logo { font-size:1.35rem; }
      .form-group input, .form-group textarea, .form-group select { font-size:0.95rem; padding:0.7rem; }
      .btn { padding:0.75rem; font-size:0.95rem; }
    }
  </style>
</head>
<body>
  <header class="header">
    <div class="logo">UniMarket</div>
    <nav class="nav">
      <input type="checkbox" id="nav-toggle" class="nav-toggle">
      <label for="nav-toggle" class="nav-toggle-label">
        <span></span>
      </label>
      <ul class="nav-menu">
        <li><a href="../index.php">Home</a></li>
        <li><a href="add-edit-listing.php">Sell</a></li>
        <?php
        $stmt = $conn->prepare("SELECT is_admin FROM users WHERE id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if ($user['is_admin']): ?>
          <li><a href="../auth/admin.php">Admin Dashboard</a></li>
        <?php endif; ?>
        <li><a href="../auth/profile.php">Profile</a></li>
        <li><a href="../auth/logout.php" class="btn btn-secondary btn-small">Logout</a></li>
      </ul>
    </nav>
  </header>

  <main class="form-container">
    <h2>Add Product for Sale</h2>
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
    <form method="post" enctype="multipart/form-data">
      <div class="form-group">
        <label for="product_image">Product Image (JPEG, PNG, GIF, max 20MB)</label>
        <input type="file" id="product_image" name="product_image" accept="image/jpeg,image/png,image/gif" required>
      </div>
      <div class="form-group">
        <label for="name">Product Name</label>
        <input type="text" id="name" name="name" value="<?php echo isset($_POST['name']) ? htmlspecialchars($_POST['name']) : ''; ?>" required>
      </div>
      <div class="form-group">
        <label for="description">Description</label>
        <textarea id="description" name="description" required><?php echo isset($_POST['description']) ? htmlspecialchars($_POST['description']) : ''; ?></textarea>
      </div>
      <div class="form-group">
        <label for="status">Status</label>
        <select id="status" name="status" required>
          <option value="used" <?php echo isset($_POST['status']) && $_POST['status'] === 'used' ? 'selected' : ''; ?>>Used</option>
          <option value="new" <?php echo isset($_POST['status']) && $_POST['status'] === 'new' ? 'selected' : ''; ?>>New</option>
        </select>
      </div>
      <div class="form-group">
        <label for="category">Category</label>
        <select id="category" name="category" required>
          <option value="">-- Select Category --</option>
          <option value="Electronics" <?php echo isset($_POST['category']) && $_POST['category'] === 'Electronics' ? 'selected' : ''; ?>>Electronics</option>
          <option value="Furniture" <?php echo isset($_POST['category']) && $_POST['category'] === 'Furniture' ? 'selected' : ''; ?>>Furniture</option>
          <option value="Table" <?php echo isset($_POST['category']) && $_POST['category'] === 'Table' ? 'selected' : ''; ?>>Table</option>
          <option value="Chair" <?php echo isset($_POST['category']) && $_POST['category'] === 'Chair' ? 'selected' : ''; ?>>Chair</option>
          <option value="Clothes" <?php echo isset($_POST['category']) && $_POST['category'] === 'Clothes' ? 'selected' : ''; ?>>Clothes</option>
          <option value="Books" <?php echo isset($_POST['category']) && $_POST['category'] === 'Books' ? 'selected' : ''; ?>>Books</option>
          <option value="Vehicles" <?php echo isset($_POST['category']) && $_POST['category'] === 'Vehicles' ? 'selected' : ''; ?>>Vehicles</option>
          <option value="Others" <?php echo isset($_POST['category']) && $_POST['category'] === 'Others' ? 'selected' : ''; ?>>Others</option>
        </select>
      </div>
      <div class="form-group">
        <label for="price">Price ($)</label>
        <input type="number" id="price" name="price" step="0.01" value="<?php echo isset($_POST['price']) ? htmlspecialchars($_POST['price']) : ''; ?>" required>
      </div>
      <button type="submit" class="btn btn-primary">Submit Product</button>
      <a href="../index.php" class="btn btn-secondary">Cancel</a>
    </form>
  </main>

  <footer class="footer">
    <div class="footer-container">
      <div class="footer-section">
        <h3><i class="fas fa-store"></i> About UniMarket</h3>
        <p>UniMarket is a trusted marketplace for university students and staff to buy, sell, and trade goods in a secure and friendly environment.</p>
      </div>
      <div class="footer-section">
        <h3><i class="fas fa-link"></i> Quick Links</h3>
        <p><a href="../index.php" class="footer-link">Home</a></p>
        <p><a href="add-edit-listing.php" class="footer-link">Sell</a></p>
        <p><a href="../auth/profile.php" class="footer-link">Profile</a></p>
      </div>
      <div class="footer-section">
        <h3><i class="fas fa-envelope"></i> Contact Us</h3>
        <p>Email: support@unimarket.com</p>
        <p>Phone: +1 (555) 123-4567</p>
      </div>
    </div>
    <div class="footer-bottom">
      &copy; <?php echo date("Y"); ?> UniMarket. All rights reserved.
    </div>
  </footer>
</body>
</html>
