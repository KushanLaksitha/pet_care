<?php
// Start session
session_start();

// Include database connection
require_once 'includes/db_connect.php';

// Check if user is logged in
if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
    // User is not logged in, redirect to login page
    header("Location: auth/login.php");
    exit();
}

// Get user information
$user_id = $_SESSION['user_id'];
$stmt = $conn->prepare("SELECT * FROM users WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user_result = $stmt->get_result();
$user = $user_result->fetch_assoc();
$stmt->close();

// Get owner information
$stmt = $conn->prepare("SELECT * FROM owners WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$owner_result = $stmt->get_result();
$owner = $owner_result->fetch_assoc();
$stmt->close();

// Process form submission
$success_message = '';
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_profile'])) {
        // Get form data
        $full_name = trim($_POST['full_name']);
        $email = trim($_POST['email']);
        $phone = trim($_POST['phone']);
        $address = trim($_POST['address']);
        
        // Validate input
        if (empty($full_name) || empty($email)) {
            $error_message = "Full name and email are required fields.";
        } else {
            // Check if email is already used by another user
            $stmt = $conn->prepare("SELECT user_id FROM users WHERE email = ? AND user_id != ?");
            $stmt->bind_param("si", $email, $user_id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                $error_message = "Email is already in use by another account.";
            } else {
                // Begin transaction
                $conn->begin_transaction();
                
                try {
                    // Update user information
                    $stmt = $conn->prepare("UPDATE users SET full_name = ?, email = ?, phone = ?, address = ?, updated_at = CURRENT_TIMESTAMP WHERE user_id = ?");
                    $stmt->bind_param("ssssi", $full_name, $email, $phone, $address, $user_id);
                    $stmt->execute();
                    
                    // If owner record exists, update it; otherwise create one
                    if ($owner) {
                        $stmt = $conn->prepare("UPDATE owners SET owner_name = ?, contact_number = ?, email = ?, address = ? WHERE user_id = ?");
                        $stmt->bind_param("ssssi", $full_name, $phone, $email, $address, $user_id);
                        $stmt->execute();
                    } else {
                        $stmt = $conn->prepare("INSERT INTO owners (user_id, owner_name, contact_number, email, address) VALUES (?, ?, ?, ?, ?)");
                        $stmt->bind_param("issss", $user_id, $full_name, $phone, $email, $address);
                        $stmt->execute();
                    }
                    
                    // Commit transaction
                    $conn->commit();
                    
                    // Set success message
                    $success_message = "Profile updated successfully!";
                    
                    // Refresh user data
                    $stmt = $conn->prepare("SELECT * FROM users WHERE user_id = ?");
                    $stmt->bind_param("i", $user_id);
                    $stmt->execute();
                    $user_result = $stmt->get_result();
                    $user = $user_result->fetch_assoc();
                    
                    $stmt = $conn->prepare("SELECT * FROM owners WHERE user_id = ?");
                    $stmt->bind_param("i", $user_id);
                    $stmt->execute();
                    $owner_result = $stmt->get_result();
                    $owner = $owner_result->fetch_assoc();
                    
                } catch (Exception $e) {
                    // Rollback in case of error
                    $conn->rollback();
                    $error_message = "An error occurred while updating your profile: " . $e->getMessage();
                }
            }
        }
    } elseif (isset($_POST['change_password'])) {
        // Get password data
        $current_password = $_POST['current_password'];
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];
        
        // Validate passwords
        if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
            $error_message = "All password fields are required.";
        } elseif ($new_password !== $confirm_password) {
            $error_message = "New passwords do not match.";
        } elseif (strlen($new_password) < 8) {
            $error_message = "New password must be at least 8 characters long.";
        } else {
            // Verify current password
            if (password_verify($current_password, $user['password'])) {
                // Hash new password
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                
                // Update password
                $stmt = $conn->prepare("UPDATE users SET password = ?, updated_at = CURRENT_TIMESTAMP WHERE user_id = ?");
                $stmt->bind_param("si", $hashed_password, $user_id);
                
                if ($stmt->execute()) {
                    $success_message = "Password changed successfully!";
                } else {
                    $error_message = "An error occurred while changing your password.";
                }
            } else {
                $error_message = "Current password is incorrect.";
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - Pet Care Center</title>
    <link rel="stylesheet" href="css/styles.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.5/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-SgOJa3DmI69IUzQ2PVdRZhwQ+dy64/BUtbMJw1MZ8t5HZApcHrRKUc4W0kG879m7" crossorigin="anonymous">
    <style>
        .profile-section {
            background-color: #f8f9fa;
            border-radius: 10px;
            padding: 25px;
            margin-bottom: 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .profile-header {
            border-bottom: 2px solid #007bff;
            margin-bottom: 20px;
            padding-bottom: 10px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .password-section {
            background-color: #f8f9fa;
            border-radius: 10px;
            padding: 25px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .alert {
            margin-bottom: 20px;
        }
        
        .btn-update {
            background-color: #007bff;
            color: white;
            padding: 10px 20px;
        }
        
        .btn-update:hover {
            background-color: #0056b3;
            color: white;
        }
    </style>
</head>
<body>
    <header>
        <div class="container">
            <h1>Pet Care Center</h1>
            <nav>
                <ul>
                    <li><a href="index.php">Dashboard</a></li>
                    <li><a href="pets.php">My Pets</a></li>
                    <li><a href="appointments.php">Appointments</a></li>
                    <li><a href="boarding.php">Boarding</a></li>
                    <li><a href="bills.php">Billing</a></li>
                    <li><a href="services.php">Services</a></li>
                    <li><a href="profile.php" class="active">My Profile</a></li>
                    <li><a href="auth/logout.php">Logout</a></li>
                </ul>
            </nav>
        </div>
    </header>

    <main class="container py-4">
        <section class="page-header">
            <h2>My Profile</h2>
            <p>View and update your personal information.</p>
        </section>

        <?php if (!empty($success_message)): ?>
            <div class="alert alert-success"><?php echo $success_message; ?></div>
        <?php endif; ?>
        
        <?php if (!empty($error_message)): ?>
            <div class="alert alert-danger"><?php echo $error_message; ?></div>
        <?php endif; ?>

        <div class="row">
            <div class="col-md-8">
                <section class="profile-section">
                    <div class="profile-header">
                        <h3>Personal Information</h3>
                    </div>
                    
                    <form method="post" action="profile.php">
                        <div class="form-group">
                            <label for="username">Username:</label>
                            <input type="text" class="form-control" id="username" value="<?php echo htmlspecialchars($user['username']); ?>" readonly>
                            <small class="form-text text-muted">Username cannot be changed.</small>
                        </div>
                        
                        <div class="form-group">
                            <label for="full_name">Full Name:</label>
                            <input type="text" class="form-control" id="full_name" name="full_name" value="<?php echo htmlspecialchars($user['full_name']); ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="email">Email:</label>
                            <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="phone">Phone Number:</label>
                            <input type="tel" class="form-control" id="phone" name="phone" value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="address">Address:</label>
                            <textarea class="form-control" id="address" name="address" rows="3"><?php echo htmlspecialchars($user['address'] ?? ''); ?></textarea>
                        </div>
                        
                        <div class="form-group">
                            <label>Account Type:</label>
                            <input type="text" class="form-control" value="<?php echo ucfirst($user['role']); ?>" readonly>
                        </div>
                        
                        <div class="form-group">
                            <label>Member Since:</label>
                            <input type="text" class="form-control" value="<?php echo date('F d, Y', strtotime($user['created_at'])); ?>" readonly>
                        </div>
                        
                        <button type="submit" name="update_profile" class="btn btn-update">Update Profile</button>
                    </form>
                </section>
            </div>
            
            <div class="col-md-4">
                <section class="password-section">
                    <div class="profile-header">
                        <h3>Change Password</h3>
                    </div>
                    
                    <form method="post" action="profile.php">
                        <div class="form-group">
                            <label for="current_password">Current Password:</label>
                            <input type="password" class="form-control" id="current_password" name="current_password" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="new_password">New Password:</label>
                            <input type="password" class="form-control" id="new_password" name="new_password" required>
                            <small class="form-text text-muted">Password must be at least 8 characters long.</small>
                        </div>
                        
                        <div class="form-group">
                            <label for="confirm_password">Confirm New Password:</label>
                            <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                        </div>
                        
                        <button type="submit" name="change_password" class="btn btn-update">Change Password</button>
                    </form>
                </section>
                
                <div class="mt-4">
                    <div class="card">
                        <div class="card-body">
                            <h4>Account Help</h4>
                            <p>Need assistance with your account?</p>
                            <a href="contact.php" class="btn btn-outline-primary">Contact Support</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <footer>
        <div class="container">
            <p>&copy; <?php echo date('Y'); ?> Pet Care Center - Ambilipitiya, Sri Lanka</p>
            <p>Contact: +94 123 456 789 | info@petcare.lk</p>
        </div>
    </footer>

    <script src="js/script.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.5/dist/js/bootstrap.bundle.min.js" integrity="sha384-k6d4wzSIapyDyv1kpU366/PK5hCdSbCRGRCMv+eplOQJWyd1fbcAu9OCUj5zNLiq" crossorigin="anonymous"></script>
</body>
</html>