<?php
session_start();
require '../backend/db_connect.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$product_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if (!$product_id) {
    header("Location: admin.php");
    exit();
}

$stmt = $conn->prepare("SELECT * FROM products WHERE id = ?");
$stmt->bind_param("i", $product_id);
$stmt->execute();
$product = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$product) die("Product not found.");

$errors = [];
$success = '';
$base_url = 'http://localhost/university_marketplace/';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name     = trim($_POST['name']);
    $price    = floatval($_POST['price']);
    $details  = trim($_POST['details']);
    $category = trim($_POST['category']);

    if (empty($name))     $errors[] = "Product name is required.";
    if ($price <= 0)      $errors[] = "Price must be greater than 0.";
    if (empty($details))  $errors[] = "Product details are required.";
    if (empty($category)) $errors[] = "Category is required.";

    $image_path = $product['image'];
    if (!empty($_FILES['image']['name']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = __DIR__ . '/../assets/images/products/';
        if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);

        $file_name   = time() . '_' . basename($_FILES['image']['name']);
        $target_file = $upload_dir . $file_name;

        if (move_uploaded_file($_FILES['image']['tmp_name'], $target_file)) {
            if (!empty($product['image']) && file_exists(__DIR__ . '/../' . $product['image'])) {
                unlink(__DIR__ . '/../' . $product['image']);
            }
            $image_path = 'assets/images/products/' . $file_name;
        } else {
            $errors[] = "Failed to upload image.";
        }
    }

    if (!$errors) {
        $stmt = $conn->prepare(
            "UPDATE products SET name=?, price=?, details=?, category=?, image=? WHERE id=?"
        );
        $stmt->bind_param("sdsssi", $name, $price, $details, $category, $image_path, $product_id);
        if ($stmt->execute()) {
            $success = "Product updated successfully!";
            header("refresh:1;url=admin.php");
        } else $errors[] = "Failed to update product.";
        $stmt->close();
    }
}

$image_preview = (!empty($product['image']) && file_exists(__DIR__ . '/../' . $product['image']))
    ? $base_url . $product['image'] . '?t=' . time()
    : 'https://via.placeholder.com/180x140';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Edit Product | UniMarket</title>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;700&display=swap">
<style>
body {
  font-family: 'Montserrat', sans-serif;
  background:#121212;
  color:#e0e0e0;
  margin:0;
  padding:2rem;
}
.container {
  max-width:650px;
  margin:2rem auto;
  background:#1e1e1e;
  padding:2rem;
  border-radius:14px;
  box-shadow:0 6px 20px rgba(0,0,0,0.6);
}
h2 {
  text-align:center;
  color:#facc15;
  margin-bottom:1.5rem;
  font-weight:700;
}
form {display:flex;flex-direction:column;gap:1rem;}
label {font-weight:500;color:#bdbdbd;}
input[type="text"], input[type="number"], textarea, select {
  padding:0.9rem 1rem;
  border:1px solid #333;
  border-radius:10px;
  width:100%;
  font-size:1rem;
  background:#222;
  color:#e0e0e0;
  transition:border 0.3s, box-shadow 0.3s;
}
textarea {resize:vertical;}
input:focus, textarea:focus, select:focus {
  outline:none;
  border:1px solid #facc15;
  box-shadow:0 0 6px rgba(250,204,21,0.6);
}
/* nicer dropdown arrow */
select {
  appearance:none;
  background-image:
    linear-gradient(45deg, transparent 50%, #facc15 50%),
    linear-gradient(135deg, #facc15 50%, transparent 50%);
  background-position: calc(100% - 1.2rem) center,
                       calc(100% - 0.8rem) center;
  background-size: 0.5rem 0.5rem;
  background-repeat: no-repeat;
}
input[type="file"] {padding:0.4rem;color:#e0e0e0;}
img.preview {
  width:180px;height:140px;object-fit:cover;
  border-radius:8px;margin-top:0.5rem;
  border:1px solid #333;box-shadow:0 3px 8px rgba(0,0,0,0.5);
}
.btn {
  padding:0.8rem 1.5rem;
  border:none;
  border-radius:8px;
  cursor:pointer;
  font-weight:600;
  transition:background 0.3s;
  width:fit-content;
}
.btn-save {background:#facc15;color:#000;}
.btn-save:hover {background:#eab308;}
.btn-back {
  background:#424242;color:#fff;text-decoration:none;
  display:inline-block;margin-top:1rem;
}
.btn-back:hover {background:#616161;}
.error,.success {
  padding:0.9rem;border-radius:8px;text-align:center;
  font-weight:500;margin-bottom:1rem;
}
.error  {background:rgba(244,67,54,0.1);border:1px solid #f44336;color:#ef5350;}
.success{background:rgba(76,175,80,0.1);border:1px solid #4caf50;color:#66bb6a;}
</style>
</head>
<body>
<div class="container">
<h2>Edit Product</h2>

<?php if($errors): ?>
<div class="error"><?php foreach($errors as $e) echo "<p>".htmlspecialchars($e)."</p>"; ?></div>
<?php endif; ?>

<?php if($success): ?>
<div class="success"><?php echo htmlspecialchars($success); ?></div>
<?php endif; ?>

<form method="post" enctype="multipart/form-data">
  <label>Product Name</label>
  <input type="text" name="name" value="<?php echo htmlspecialchars($product['name']); ?>" required>

  <label>Price ($)</label>
  <input type="number" name="price" value="<?php echo htmlspecialchars($product['price']); ?>" step="0.01" required>

  <label>Details</label>
  <textarea name="details" rows="4" required><?php echo htmlspecialchars($product['details']); ?></textarea>

  <!-- Updated Category dropdown -->
  <label>Category</label>
  <select name="category" required>
    <option value="">Select Category</option>
    <?php
      $cats = ["Electronics","Furniture","Table","Chair","Vehicles","Clothes","Books","Others"];
      foreach($cats as $c){
        $sel = ($product['category']===$c) ? 'selected' : '';
        echo "<option value='".htmlspecialchars($c)."' $sel>$c</option>";
      }
    ?>
  </select>

  <label>Product Image</label>
  <input type="file" name="image" accept="image/*">
  <img src="<?php echo $image_preview; ?>" class="preview" alt="Current Image">

  <button type="submit" class="btn btn-save">Save Changes</button>
</form>
<a href="admin.php" class="btn btn-back">Back to Dashboard</a>
</div>
</body>
</html>
