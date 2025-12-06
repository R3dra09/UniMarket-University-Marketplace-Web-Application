<?php
session_start();
require '../backend/db_connect.php';
$errors = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = filter_var($_POST['email'], FILTER_SANITIZE_EMAIL);
    $name = filter_var($_POST['name'], FILTER_SANITIZE_STRING);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm-password'];
    $terms = isset($_POST['terms']);

    // Server-side validation
    if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL) || !str_ends_with($email, '.edu')) {
        $errors[] = 'Please use a valid .edu email address.';
    }
    if (!$name) {
        $errors[] = 'Full name is required.';
    }
    if (!$password || strlen($password) < 6) {
        $errors[] = 'Password must be at least 6 characters.';
    }
    if ($password !== $confirm_password) {
        $errors[] = 'Passwords do not match.';
    }
    if (!$terms) {
        $errors[] = 'You must agree to the Terms & Privacy.';
    }

    // Check if email exists
    if (empty($errors)) {
        $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) {
            $errors[] = 'Email already registered.';
        }
        $stmt->close();
    }

    // Insert user
    if (empty($errors)) {
        $hashed_password = password_hash($password, PASSWORD_BCRYPT);
        $stmt = $conn->prepare("INSERT INTO users (email, name, password) VALUES (?, ?, ?)");
        $stmt->bind_param("sss", $email, $name, $hashed_password);
        if ($stmt->execute()) {
            $success = 'Signup successful! Redirecting to login...';
            header("refresh:2;url=login.php");
        } else {
            $errors[] = 'Error creating account. Please try again.';
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
  <title>Sign Up | University Marketplace</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <style>
   /* General Reset and Base Styles */
* {
  margin: 0;
  padding: 0;
  box-sizing: border-box;
  font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
  -webkit-font-smoothing: antialiased;
  -moz-osx-font-smoothing: grayscale;
}

body {
  background-color: #0f172a; /* Dark background */
  color: #e5e7eb; /* Light gray text */
  line-height: 1.6;
  overscroll-behavior: none;
}

/* Header Styling */
.header {
  background: #000; /* Black header */
  color: white;
  padding: 1rem 1.5rem;
  display: flex;
  justify-content: space-between;
  align-items: center;
  box-shadow: 0 4px 6px rgba(0, 0, 0, 0.6);
  position: sticky;
  top: 0;
  z-index: 1000;
}

.logo {
  font-size: 1.6rem;
  font-weight: 700;
  letter-spacing: 0.5px;
  color: #e8b72e; /* Gold logo */
}

/* Navigation */
.nav {
  display: flex;
  align-items: center;
}

.nav-menu {
  display: flex;
  list-style: none;
  align-items: center;
}

.nav-menu li a {
  color: white;
  text-decoration: none;
  padding: 0.5rem 1rem;
  font-weight: 500;
  transition: color 0.3s ease, transform 0.2s ease;
}

.nav-menu li a:hover {
  color: #e8b72e;
  transform: translateY(-2px);
}

/* Hamburger Menu */
.nav-toggle {
  display: none;
}

.nav-toggle-label {
  display: none;
  cursor: pointer;
  width: 30px;
  height: 20px;
  position: relative;
}

.nav-toggle-label span,
.nav-toggle-label span::before,
.nav-toggle-label span::after {
  background: white;
  height: 3px;
  width: 100%;
  position: absolute;
  left: 0;
  transition: all 0.3s ease;
}

.nav-toggle-label span {
  top: 50%;
}

.nav-toggle-label span::before {
  content: '';
  top: -8px;
}

.nav-toggle-label span::after {
  content: '';
  top: 8px;
}

.nav-toggle:checked ~ .nav-menu {
  display: flex;
}

.nav-toggle:checked + .nav-toggle-label span {
  background: transparent;
}

.nav-toggle:checked + .nav-toggle-label span::before {
  transform: rotate(45deg);
  top: 0;
}

.nav-toggle:checked + .nav-toggle-label span::after {
  transform: rotate(-45deg);
  top: 0;
}

/* Auth Container */
.auth-container {
  display: flex;
  justify-content: center;
  align-items: center;
  min-height: calc(100vh - 180px);
  padding: 1.5rem;
  gap: 1.5rem;
  background: linear-gradient(135deg, rgba(232,183,46,0.1), rgba(0,0,0,0.3));
}

.auth-hero {
  flex: 1;
  text-align: center;
  max-width: 400px;
}

.auth-hero h1 {
  font-size: 2rem;
  color: #e8b72e;
  margin-bottom: 0.8rem;
}

.auth-hero p {
  font-size: 1rem;
  color: #cbd5e1;
}

.auth-card {
  background: #111827; /* Dark card background */
  padding: 2rem;
  border-radius: 12px;
  box-shadow: 0 6px 20px rgba(0, 0, 0, 0.6);
  max-width: 400px;
  width: 100%;
  text-align: center;
  flex: 1;
}

.auth-card h2 {
  font-size: 1.7rem;
  color: #e8b72e;
  margin-bottom: 1rem;
}

/* Form Styling */
.auth-form .form-group {
  margin-bottom: 1rem;
  text-align: left;
}

.auth-form label {
  display: block;
  font-weight: 500;
  color: #e5e7eb;
  margin-bottom: 0.3rem;
  font-size: 0.9rem;
}

.auth-form input {
  width: 100%;
  padding: 0.8rem;
  border: 1px solid #374151;
  border-radius: 8px;
  font-size: 0.95rem;
  background: #1f2937;
  color: #f9fafb;
  transition: border-color 0.3s ease, box-shadow 0.3s ease;
}

.auth-form input:focus {
  outline: none;
  border-color: #e8b72e;
  box-shadow: 0 0 0 3px rgba(232,183,46,0.2);
}

.forgot-password {
  display: block;
  margin-bottom: 1rem;
  font-size: 0.9rem;
  color: #9ca3af;
}

.checkbox-group {
  margin: 1rem 0;
  font-size: 0.9rem;
  color: #9ca3af;
  display: flex;
  align-items: center;
  gap: 0.5rem;
  position: relative;
}

.checkbox-group input[type="checkbox"] {
  width: 16px;
  height: 16px;
  margin: 0;
  cursor: pointer;
  accent-color: #e8b72e;
}

.checkbox-group label {
  cursor: pointer;
  font-size: 0.9rem;
  line-height: 1.2;
}

/* Button Styling */
.btn {
  display: inline-block;
  padding: 0.9rem 2rem;
  border: none;
  border-radius: 8px;
  font-weight: 500;
  font-size: 1rem;
  cursor: pointer;
  transition: background-color 0.3s ease, transform 0.2s ease;
  touch-action: manipulation;
  width: 100%;
}

.btn-primary {
  background-color: #e8b72e;
  color: #000;
}

.btn-primary:hover {
  background-color: #d4a51f;
  transform: translateY(-2px);
}

.btn-secondary {
  background-color: #374151;
  color: #e5e7eb;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 0.5rem;
}

.btn-secondary:hover {
  background-color: #4b5563;
  transform: translateY(-2px);
}

/* Link Styling */
.link {
  color: #e8b72e;
  text-decoration: none;
  font-weight: 500;
}

.link:hover {
  text-decoration: underline;
}

/* Error and Success Messages */
.error, .success {
  margin: 1rem 0;
  padding: 0.8rem;
  border-radius: 8px;
  font-size: 0.9rem;
  text-align: center;
}

.error {
  background-color: #fee2e2;
  color: #dc2626;
}

.success {
  background-color: rgba(232,183,46,0.1);
  color: #e8b72e;
  border: 1px solid #e8b72e;
}

/* Footer Styling */
.footer {
  background: #000;
  color: #e2e8f0;
  padding: 2rem 1.5rem;
  width: 100%;
}

.footer-container {
  display: flex;
  justify-content: space-between;
  max-width: 1200px;
  margin: 0 auto;
  flex-wrap: wrap;
  gap: 1.5rem;
}

.footer-section {
  flex: 1;
  min-width: 200px;
}

.footer-section h3 {
  font-size: 1.2rem;
  margin-bottom: 1rem;
  display: flex;
  align-items: center;
  gap: 0.5rem;
  color: #e8b72e;
}

.footer-section i {
  font-size: 1.3rem;
}

.footer-section p {
  font-size: 0.9rem;
  margin-bottom: 0.5rem;
  color: #cbd5e1;
}

.footer-link {
  color: #e8b72e;
  text-decoration: none;
  font-size: 0.9rem;
  transition: color 0.3s ease;
}

.footer-link:hover {
  color: #facc15;
  text-decoration: underline;
}

.footer-bottom {
  text-align: center;
  margin-top: 1.5rem;
  padding-top: 1rem;
  border-top: 1px solid #334155;
  font-size: 0.85rem;
  color: #94a3b8;
}

/* Responsive Design */
@media (max-width: 768px) {
  .header {
    padding: 1rem;
  }
  .logo {
    font-size: 1.4rem;
  }
  .nav-menu {
    display: none;
    flex-direction: column;
    position: absolute;
    top: 60px;
    left: 0;
    right: 0;
    background: #000;
    padding: 1rem;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.6);
  }
  .nav-menu li {
    margin: 0.5rem 0;
  }
  .nav-toggle-label {
    display: block;
  }
  .nav-toggle:checked ~ .nav-menu {
    display: flex;
  }
  .auth-container {
    flex-direction: column;
    padding: 1rem;
  }
  .auth-hero {
    display: none;
  }
  .auth-card {
    padding: 1.5rem;
    margin: 0 auto;
  }
  .auth-card h2 {
    font-size: 1.5rem;
  }
  .auth-form input {
    font-size: 0.9rem;
    padding: 0.7rem;
  }
  .btn {
    padding: 0.8rem;
    font-size: 0.95rem;
  }
  .checkbox-group input[type="checkbox"] {
    width: 14px;
    height: 14px;
  }
  .footer-container {
    flex-direction: column;
    align-items: center;
    text-align: center;
  }
  .footer-section {
    min-width: 100%;
  }
}

@media (max-width: 480px) {
  .auth-card {
    max-width: 100%;
  }
  .auth-form label {
    font-size: 0.85rem;
  }
  .footer-section h3 {
    font-size: 1.1rem;
  }
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
        <li><a href="../products/browse.php">Browse</a></li>
        <li><a href="<?php echo isset($_SESSION['user_id']) ? '../products/add-edit-listing.php' : 'login.php'; ?>" onclick="<?php if (!isset($_SESSION['user_id'])) echo 'alert(\"Please log in to sell items!\")'; ?>">Sell</a></li>
        <li><a href="profile.html">Profile</a></li>
        <li><a href="../transactions/messages.php">Messages</a></li>
        <?php if (!isset($_SESSION['user_id'])): ?>
          <li>
            <a href="signup.php" class="btn btn-primary btn-small">Sign Up</a>
            <a href="login.php" class="btn btn-secondary btn-small">Login</a>
          </li>
        <?php endif; ?>
      </ul>
    </nav>
  </header>

  <main class="auth-container">
    <div class="auth-hero">
      <h1>Join the RUET Marketplace</h1>
      <p>Buy and sell textbooks, electronics, and more with fellow students!</p>
    </div>
    <div class="auth-card">
      <h2>Create Your Account</h2>
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
      <form method="post" class="auth-form">
        <div class="form-group">
          <label for="email">University Email (*.edu)</label>
          <input type="email" id="email" name="email" placeholder="you@university.edu" value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>" required>
        </div>
        <div class="form-group">
          <label for="name">Full Name</label>
          <input type="text" id="name" name="name" placeholder="John Doe" value="<?php echo isset($_POST['name']) ? htmlspecialchars($_POST['name']) : ''; ?>" required>
        </div>
        <div class="form-group">
          <label for="password">Password</label>
          <input type="password" id="password" name="password" placeholder="Create a strong password" required>
        </div>
        <div class="form-group">
          <label for="confirm-password">Confirm Password</label>
          <input type="password" id="confirm-password" name="confirm-password" placeholder="Re-enter password" required>
        </div>
        <div class="checkbox-group">
          <input type="checkbox" id="terms" name="terms" <?php echo isset($_POST['terms']) ? 'checked' : ''; ?> required>
          <label for="terms">I agree to the <a href="../support/terms-privacy.php" class="link">Terms & Privacy</a></label>
        </div>
        <button type="submit" class="btn btn-primary">Sign Up</button>
      </form>
      <p>Already have an account? <a href="login.php" class="link">Login</a></p>
    </div>
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
        <p>By using UniMarket, you agree to our policies on safe trading and data privacy.</p>
        <a href="../support/terms-privacy.php" class="footer-link">Read Full Terms</a>
      </div>
      <div class="footer-section">
        <h3><i class="fas fa-map-marker-alt"></i> Location</h3>
        <p>Shahid Shahidul Islam Hall</p>
        <a href="https://www.google.com/maps/place/Shahid+Shahidul+Islam+Hall/@24.3667606,88.6233247,644m/data=!3m2!1e3!4b1!4m6!3m5!1s0x39fbefd1b268fda9:0x623d5891478eb5af!8m2!3d24.3667557!4d88.6258996!16s%2Fg%2F1q69kh20k?entry=ttu&g_ep=EgoyMDI1MDkwNy4wIKXMDSoASAFQAw%3D%3D" target="_blank" class="footer-link">View on Google Maps</a>
      </div>
    </div>
    <div class="footer-bottom">
      <p>&copy; 2025 UniMarket. All rights reserved.</p>
    </div>
  </footer>
</body>
</html>