<?php
session_start();
require '../backend/db_connect.php';

// Redirect to login if not authenticated
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$errors = [];
$success = '';

// Initialize variables safely
$name = '';
$email = '';
$profile_picture = 'https://via.placeholder.com/150';
$base_url = '/'; // adjust if your project folder name is different

// Fetch current user data including is_admin, created_at (as joined_date), and order count
$stmt = $conn->prepare("SELECT email, name, profile_picture, is_admin, created_at FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

if ($user) {
    $email = $user['email'];
    $name = $user['name'];
    $is_admin = $user['is_admin'];
    $joined_date = $user['created_at'] ? date('F d, Y', strtotime($user['created_at'])) : 'N/A';

    // Fetch number of orders
    $stmt = $conn->prepare("SELECT COUNT(*) as order_count FROM orders WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $order_result = $stmt->get_result();
    $order_count = $order_result->fetch_assoc()['order_count'] ?? 0;
    $stmt->close();

    if (!empty($user['profile_picture'])) {
        $profile_picture = $base_url . ltrim($user['profile_picture'], '/') . '?t=' . time();
    }
}

// Handle profile picture upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] === UPLOAD_ERR_OK) {
    $file = $_FILES['profile_picture'];
    $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
    $max_size = 20 * 1024 * 1024; // 20MB

    if (!in_array($file['type'], $allowed_types)) {
        $errors[] = 'Only JPEG, PNG, or GIF images are allowed.';
    } elseif ($file['size'] > $max_size) {
        $errors[] = 'Image size must not exceed 20MB.';
    } else {
        $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename = 'user_' . $user_id . '_' . time() . '.' . $ext;

        $upload_dir = __DIR__ . '/../assets/images/profiles/';
        if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);

        $upload_path = $upload_dir . $filename;
        if (move_uploaded_file($file['tmp_name'], $upload_path)) {
            $profile_picture_path = 'assets/images/profiles/' . $filename;

            $stmt = $conn->prepare("UPDATE users SET profile_picture = ? WHERE id = ?");
            $stmt->bind_param("si", $profile_picture_path, $user_id);
            if ($stmt->execute()) {
                $success = 'Profile picture updated successfully!';
                $profile_picture = $base_url . $profile_picture_path . '?t=' . time();
                $_SESSION['profile_picture'] = $profile_picture_path;

                // Delete old picture
                if (!empty($user['profile_picture']) && file_exists(__DIR__ . '/../' . $user['profile_picture'])) {
                    unlink(__DIR__ . '/../' . $user['profile_picture']);
                }

                header("refresh:1;url=profile.php");
            } else {
                $errors[] = 'Failed to update profile picture in database.';
            }
            $stmt->close();
        } else {
            $errors[] = 'Failed to upload image.';
        }
    }
}

// Handle profile info update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_FILES['profile_picture'])) {
    $new_email = filter_var($_POST['email'], FILTER_SANITIZE_EMAIL);
    $new_name = filter_var($_POST['name'], FILTER_SANITIZE_STRING);
    $new_password = $_POST['password'];

    if (!$new_email || !filter_var($new_email, FILTER_VALIDATE_EMAIL) || !str_ends_with($new_email, '.edu')) {
        $errors[] = 'Please use a valid .edu email address.';
    }
    if (!$new_name) $errors[] = 'Full name is required.';
    if ($new_password && strlen($new_password) < 6) $errors[] = 'Password must be at least 6 characters.';

    if (empty($errors) && $new_email !== $email) {
        $stmt = $conn->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
        $stmt->bind_param("si", $new_email, $user_id);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) $errors[] = 'Email already registered.';
        $stmt->close();
    }

    if (empty($errors)) {
        if ($new_password) {
            $hashed_password = password_hash($new_password, PASSWORD_BCRYPT);
            $stmt = $conn->prepare("UPDATE users SET email = ?, name = ?, password = ? WHERE id = ?");
            $stmt->bind_param("sssi", $new_email, $new_name, $hashed_password, $user_id);
        } else {
            $stmt = $conn->prepare("UPDATE users SET email = ?, name = ? WHERE id = ?");
            $stmt->bind_param("ssi", $new_email, $new_name, $user_id);
        }
        if ($stmt->execute()) {
            $success = 'Profile updated successfully!';
            $_SESSION['user_email'] = $new_email;
            $_SESSION['user_name'] = $new_name;
            $email = $new_email;
            $name = $new_name;
            header("refresh:1;url=profile.php");
        } else {
            $errors[] = 'Error updating profile.';
        }
        $stmt->close();
    }
}

// Fetch order history
$stmt = $conn->prepare("SELECT o.order_date, o.id, o.quantity, o.total_amount, o.payment_method, o.phone_number, p.name as product_name FROM orders o LEFT JOIN products p ON o.product_id = p.id WHERE o.user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$orders = $stmt->get_result();
$stmt->close();

// Handle order deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_order']) && isset($_POST['order_id'])) {
    $order_id = (int)$_POST['order_id'];
    $stmt = $conn->prepare("DELETE FROM orders WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ii", $order_id, $user_id);
    if ($stmt->execute()) {
        $success = 'Order deleted successfully!';
        header("refresh:1;url=profile.php");
    } else {
        $errors[] = 'Failed to delete order.';
    }
    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile | UniMarket</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* Reset and Base */
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        body { background: #000; color: #e0e0e0; line-height: 1.6; }
        a { text-decoration: none; }
        button { cursor: pointer; }

        /* Main Layout */
        .app-container { display: flex; height: 100vh; background: #000; }
        .sidebar { width: 280px; background: #0a0a0a; padding: 2rem 1rem; display: flex; flex-direction: column; }
        .sidebar-top { flex: 1; }
        .search-container { width: 100%; padding: 0.75rem 1rem 0.75rem 3rem; border: none; border-radius: 8px; position: relative; margin-bottom: 2rem; background-color: #eac808ff; color: #000; }
        .search-container input { width: 100%; padding: 0.75rem 1rem 0.75rem 3rem; border: none; border-radius: 8px; background: #eac808ff; color: #130d0dff; }
        .search-container .fas.fa-search { position: absolute; left: 1rem; top: 50%; transform: translateY(-50%); color: #888; }
        .user-nav { display: flex; align-items: center; margin-bottom: 2rem; padding: 0.5rem; background: #1a1a1a; border-radius: 8px; }
        .user-nav img { width: 40px; height: 40px; border-radius: 50%; margin-right: 1rem; }
        .user-nav .username { color: #e0e0e0; font-weight: 500; }
        .main-content { flex: 1; padding: 2rem; background: #0d0d0d; overflow-y: auto; }

        /* Profile Card */
        .profile-card { background: #1a1a1a; border-radius: 12px; padding: 2rem; margin-bottom: 2rem; display: grid; grid-template-columns: 100px 1fr; gap: 2rem; align-items: start; }
        .avatar { width: 100px; height: 100px; border-radius: 50%; object-fit: cover; border: 3px solid #ffd700; }
        .profile-details { display: grid; grid-template-columns: 1fr 1fr; gap: 2rem; }
        .profile-name { grid-column: 1 / -1; display: flex; align-items: center; margin-bottom: 1rem; }
        .profile-name h1 { color: #fff; font-size: 1.8rem; margin: 0; }
        .premium-badge { background: #ffd700; color: #000; padding: 0.25rem 0.75rem; border-radius: 20px; font-size: 0.8rem; font-weight: bold; margin-left: 1rem; }
        .detail-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; }
        .detail-item { }
        .detail-label { color: #888; font-size: 0.85rem; margin-bottom: 0.25rem; }
        .detail-value { color: #e0e0e0; font-weight: 500; }
        .full-width { grid-column: 1 / -1; }
        .availability { color: #4ade80; }
        .tags { display: flex; gap: 0.5rem; flex-wrap: wrap; }
        .tag { background: #333; color: #e0e0e0; padding: 0.25rem 0.5rem; border-radius: 4px; font-size: 0.8rem; }

        /* Social Media */
        .social-section { background: #1a1a1a; border-radius: 12px; padding: 1.5rem; margin-bottom: 2rem; }
        .social-section h3 { color: #fff; margin-bottom: 1rem; }
        .social-icons { display: flex; gap: 1rem; }
        .social-icon { width: 48px; height: 48px; border-radius: 50%; display: flex; align-items: center; justify-content: center; background: #333; color: #fff; font-size: 1.2rem; transition: background 0.3s; }
        .social-icon:hover { background: #555; }

        /* Orders Table */
        .orders-section { background: #1a1a1a; border-radius: 12px; padding: 1.5rem; margin-bottom: 2rem; box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1); }
        .orders-section h3 { color: #fff; margin-bottom: 1.5rem; font-size: 1.5rem; border-bottom: 2px solid #ffd700; padding-bottom: 0.5rem; }
        .table-container { overflow-x: auto; }
        table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            background: #222;
            border-radius: 8px;
            overflow: hidden;
        }
        th, td {
            padding: 1rem;
            text-align: left;
            border-bottom: 1px solid #333;
        }
        th {
            background: #1e1e1e;
            color: #ffd700;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            position: sticky;
            top: 0;
            z-index: 1;
        }
        tr:hover {
            background: #2a2a2a;
            transition: background 0.3s ease;
        }
        td {
            color: #e0e0e0;
            font-weight: 400;
        }
        .delete-btn {
            background: #991b1b;
            color: #fcd34d;
            border: none;
            padding: 0.5rem 1rem;
            border-radius: 4px;
            font-size: 0.9rem;
            cursor: pointer;
            transition: background 0.3s ease, transform 0.2s ease;
        }
        .delete-btn:hover {
            background: #7f1d1d;
            transform: scale(1.05);
        }

        /* Forms */
        .form-section { background: #1a1a1a; border-radius: 12px; padding: 1.5rem; margin-bottom: 2rem; }
        .form-section h3 { color: #fff; margin-bottom: 1rem; }
        .form-group { margin-bottom: 1rem; }
        .form-group label { display: block; color: #888; margin-bottom: 0.5rem; font-weight: 500; }
        .form-group input { width: 100%; padding: 0.75rem; border: 1px solid #333; border-radius: 6px; background: #222; color: #e0e0e0; }
        .form-group input:focus { outline: none; border-color: #ffd700; }
        .btn { padding: 0.75rem 1.5rem; background: #ffd700; color: #000; border: none; border-radius: 6px; font-weight: 500; cursor: pointer; transition: background 0.3s; width: 100%; margin-bottom: 0.5rem; }
        .btn:hover { background: #e6c200; }
        .back-btn { background: #333 !important; color: #e0e0e0 !important; width: auto !important; display: inline-block; }

        /* Messages */
        .messages { margin-bottom: 1rem; }
        .error { background: #991b1b; color: #fcd34d; padding: 1rem; border-radius: 6px; }
        .success { background: #065f46; color: #d1fae5; padding: 1rem; border-radius: 6px; }

        /* Responsive */
        @media (max-width: 768px) {
            .app-container { flex-direction: column; height: auto; }
            .sidebar { width: 100%; height: auto; }
            .profile-card { grid-template-columns: 1fr; text-align: center; }
            .detail-grid { grid-template-columns: 1fr; }
            .profile-details { gap: 1rem; }
            .main-content { padding: 1rem; }
            table { font-size: 0.9rem; }
            th, td { padding: 0.75rem; }
        }
    </style>
</head>
<body>
    <div class="app-container">
        <!-- Sidebar -->
        <aside class="sidebar">
            <div class="sidebar-top">
                <div class="search-container">
                    <a href="../index.php" class="back-home">← Back to Home</a>
                </div>
                <nav class="user-nav">
                    <img src="<?php echo $profile_picture; ?>" alt="User Avatar" class="user-avatar">
                    <span class="username"><?php echo htmlspecialchars($name ?? ''); ?></span>
                    <i class="fas fa-chevron-down" style="margin-left: auto; color: #888;"></i>
                </nav>
            </div>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <!-- Profile Card -->
            <section class="profile-card">
                <img src="<?php echo $profile_picture; ?>" alt="Profile Picture" class="avatar">
                <div class="profile-details">
                    <div class="profile-name">
                        <h1><?php echo htmlspecialchars($name ?? ''); ?></h1>
                        <?php if ($is_admin == 1): ?>
                            <span class="premium-badge">Admin</span>
                        <?php endif; ?>
                    </div>
                    <div class="detail-grid">
                       
                        <div class="detail-item">
                             <div class="detail-label">Email</div>
                            <div class="detail-value"><?php echo htmlspecialchars($email ?? ''); ?></div>
                            </div>
                      
                        <div class="detail-item">
                            <div class="detail-label">Joined Date</div>
                            <div class="detail-value"><?php echo $joined_date; ?></div>
                        </div>
                        <div class="detail-item full-width">
                            <div class="detail-label">Number of Orders</div>
                            <div class="detail-value"><?php echo $order_count; ?></div>
                        </div>
                     
                        <div class="detail-item full-width">
                            <div class="detail-label">Tags</div>
                            <div class="detail-value">
                                <div class="tags">
                                    <span class="tag">#Buyer</span>
                                    <span class="tag">#Seller</span>
                                    <span class="tag">#UniMarketer</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Social Media -->
            <section class="social-section">
                <h3>Social Media</h3>
                <div class="social-icons">
                    <a href="#" class="social-icon" title="YouTube"><i class="fab fa-youtube"></i></a>
                    <a href="#" class="social-icon" title="Instagram"><i class="fab fa-instagram"></i></a>
                </div>
            </section>

            <!-- Orders -->
            <section class="orders-section">
                <h3>My Orders</h3>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>Order Date</th>
                                <th>Order ID</th>
                                <th>Product Name</th>
                                <th>Quantity</th>
                                <th>Total Amount</th>
                                <th>Payment Method</th>
                                <th>Phone Number</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($order = $orders->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo date('F d, Y', strtotime($order['order_date'])); ?></td>
                                    <td>#<?php echo $order['id']; ?></td>
                                    <td><?php echo htmlspecialchars($order['product_name'] ?? 'N/A'); ?></td>
                                    <td><?php echo $order['quantity']; ?></td>
                                    <td>$<?php echo number_format($order['total_amount'], 2); ?></td>
                                    <td><?php echo htmlspecialchars($order['payment_method'] ?? 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars($order['phone_number'] ?? 'N/A'); ?></td>
                                    <td>
                                        <form method="post" style="display:inline;">
                                            <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
                                            <button type="submit" name="delete_order" class="delete-btn">Delete</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </section>

            <!-- Profile Picture Upload Form -->
            <section class="form-section">
                <h3>Update Profile Picture</h3>
                <div class="messages">
                    <?php if (!empty($errors)): ?>
                        <div class="error">
                            <?php foreach ($errors as $error): ?>
                                <p><?php echo htmlspecialchars($error); ?></p>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                    <?php if ($success): ?>
                        <div class="success"><?php echo htmlspecialchars($success); ?></div>
                    <?php endif; ?>
                </div>
                <form method="post" enctype="multipart/form-data">
                    <div class="form-group">
                        <label for="profile_picture">Profile Picture (JPEG, PNG, GIF, max 20MB)</label>
                        <input type="file" id="profile_picture" name="profile_picture" accept="image/jpeg,image/png,image/gif">
                    </div>
                    <button type="submit" class="btn">Upload Picture</button>
                </form>
            </section>

            <!-- Profile Info Update Form -->
            <section class="form-section">
                <h3>Update Profile Info</h3>
                <form method="post">
                    <div class="form-group">
                        <label for="email">University Email (*.edu)</label>
                        <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($email ?? ''); ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="name">Full Name</label>
                        <input type="text" id="name" name="name" value="<?php echo htmlspecialchars($name ?? ''); ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="password">New Password (leave blank to keep current)</label>
                        <input type="password" id="password" name="password" placeholder="Enter new password">
                    </div>
                    <button type="submit" class="btn">Update Profile</button>
                </form>
                <a href="../index.php" class="back-btn">Back to Home</a>
            </section>
        </main>
    </div>
</body>
</html>