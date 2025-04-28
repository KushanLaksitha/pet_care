<?php
// Initialize the session
session_start();

// Check if the user is already logged in, if yes then redirect to index page
if(isset($_SESSION["user_id"]) && !empty($_SESSION["user_id"])) {
    header("location: ../index.php");
    exit;
}

// Include database connection
require_once "../includes/db_connect.php";

// Define variables and initialize with empty values
$username = $password = "";
$username_err = $password_err = $login_err = "";

// Processing form data when form is submitted
if($_SERVER["REQUEST_METHOD"] == "POST") {
    
    // Check if username is empty
    if(empty(trim($_POST["username"]))) {
        $username_err = "Please enter username.";
    } else {
        $username = sanitize($conn, $_POST["username"]);
    }
    
    // Check if password is empty
    if(empty(trim($_POST["password"]))) {
        $password_err = "Please enter your password.";
    } else {
        $password = trim($_POST["password"]);
    }
    
    // Validate credentials
    if(empty($username_err) && empty($password_err)) {
        // Prepare a select statement
        $sql = "SELECT user_id, username, password, role, full_name FROM users WHERE username = ?";
        
        if($stmt = $conn->prepare($sql)) {
            // Bind variables to the prepared statement as parameters
            $stmt->bind_param("s", $param_username);
            
            // Set parameters
            $param_username = $username;
            
            // Attempt to execute the prepared statement
            if($stmt->execute()) {
                // Store result
                $stmt->store_result();
                
                // Check if username exists, if yes then verify password
                if($stmt->num_rows == 1) {
                    // Bind result variables
                    $stmt->bind_result($id, $username, $hashed_password, $role, $full_name);
                    
                    if($stmt->fetch()) {
                        if(password_verify($password, $hashed_password)) {
                            // Password is correct, so start a new session
                            session_start();
                            
                            // Store data in session variables
                            $_SESSION["user_id"] = $id;
                            $_SESSION["username"] = $username;
                            $_SESSION["role"] = $role;
                            $_SESSION["full_name"] = $full_name;
                            
                            // Redirect user to index page
                            header("location: ../index.php");
                            exit;
                        } else {
                            // Password is not valid
                            $login_err = "Invalid username or password.";
                        }
                    }
                } else {
                    // Username doesn't exist
                    $login_err = "Invalid username or password.";
                }
            } else {
                echo "Oops! Something went wrong. Please try again later.";
            }

            // Close statement
            $stmt->close();
        }
    }
    
    // Close connection
    $conn->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Pet Care Ambilipitiya</title>
    <link rel="stylesheet" href="../css/styles.css">
</head>
<body>
    <div class="container">
        <div class="auth-container">
            <h2>Pet Care Center Login</h2>
            
            <?php 
            if(!empty($login_err)){
                echo '<div class="error-message">' . $login_err . '</div>';
            }        
            ?>

            <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
                <div class="form-group">
                    <label for="username">Username</label>
                    <input type="text" name="username" id="username" value="<?php echo $username; ?>" 
                        class="<?php echo (!empty($username_err)) ? 'error' : ''; ?>">
                    <?php if(!empty($username_err)): ?>
                        <div class="error-message"><?php echo $username_err; ?></div>
                    <?php endif; ?>
                </div>
                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" name="password" id="password" 
                        class="<?php echo (!empty($password_err)) ? 'error' : ''; ?>">
                    <?php if(!empty($password_err)): ?>
                        <div class="error-message"><?php echo $password_err; ?></div>
                    <?php endif; ?>
                </div>
                <div class="form-group">
                    <button type="submit" class="btn-primary">Login</button>
                </div>
                <div class="auth-links">
                    <p>Don't have an account? <a href="register.php">Sign up now</a></p>
                </div>
            </form>
        </div>
    </div>
</body>
</html>