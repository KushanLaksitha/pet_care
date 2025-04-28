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
$owner_id = $owner ? $owner['owner_id'] : null;
$stmt->close();

// If owner_id is null, redirect to complete profile
if (!$owner_id) {
    header("Location: profile.php?action=complete");
    exit();
}

// Check if bill_id is provided
if (!isset($_GET['bill_id']) || !is_numeric($_GET['bill_id'])) {
    // No bill ID provided, redirect to bills page
    header("Location: bills.php");
    exit();
}

$bill_id = $_GET['bill_id'];

// Get bill information
$stmt = $conn->prepare("
    SELECT * FROM billing 
    WHERE bill_id = ? AND owner_id = ? 
    AND payment_status IN ('pending', 'partial')
");
$stmt->bind_param("ii", $bill_id, $owner_id);
$stmt->execute();
$bill_result = $stmt->get_result();
$bill = $bill_result->fetch_assoc();
$stmt->close();

// If bill not found or not eligible for payment, redirect to bills page
if (!$bill) {
    header("Location: bills.php?error=invalid_bill");
    exit();
}

// Handle payment form submission
$payment_success = false;
$payment_error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate payment amount
    $payment_amount = isset($_POST['payment_amount']) ? floatval($_POST['payment_amount']) : 0;
    $payment_method = isset($_POST['payment_method']) ? $_POST['payment_method'] : '';
    
    // Get remaining amount to be paid
    $remaining_amount = $bill['total_amount'];
    if ($bill['payment_status'] === 'partial') {
        // Calculate the remaining amount based on previous payments
        // This is a simplified version - in a real system, you would track payment history
        $stmt = $conn->prepare("
            SELECT SUM(amount) as paid_amount FROM payment_history 
            WHERE bill_id = ?
        ");
        $stmt->bind_param("i", $bill_id);
        $stmt->execute();
        $payment_result = $stmt->get_result();
        $payment_data = $payment_result->fetch_assoc();
        $stmt->close();
        
        $paid_amount = $payment_data['paid_amount'] ?: 0;
        $remaining_amount = $bill['total_amount'] - $paid_amount;
    }
    
    // Validate payment amount
    if ($payment_amount <= 0) {
        $payment_error = 'Payment amount must be greater than zero.';
    } elseif ($payment_amount > $remaining_amount) {
        $payment_error = 'Payment amount cannot exceed the remaining balance.';
    } elseif (empty($payment_method)) {
        $payment_error = 'Please select a payment method.';
    } else {
        // Process payment (in a real system, this would integrate with a payment gateway)
        
        // For demonstration purposes, we'll just update the bill status
        $new_status = ($payment_amount >= $remaining_amount) ? 'paid' : 'partial';
        
        // Begin transaction
        $conn->begin_transaction();
        
        try {
            // Update bill status
            $stmt = $conn->prepare("
                UPDATE billing 
                SET payment_status = ?, payment_method = ? 
                WHERE bill_id = ?
            ");
            $stmt->bind_param("ssi", $new_status, $payment_method, $bill_id);
            $stmt->execute();
            $stmt->close();
            
            // Record payment in payment_history (we'll assume this table exists)
            // In a real system, you would create this table to track payment history
            $stmt = $conn->prepare("
                INSERT INTO payment_history (bill_id, owner_id, amount, payment_method, payment_date) 
                VALUES (?, ?, ?, ?, CURRENT_DATE())
            ");
            $stmt->bind_param("iids", $bill_id, $owner_id, $payment_amount, $payment_method);
            $stmt->execute();
            $stmt->close();
            
            // Commit transaction
            $conn->commit();
            
            $payment_success = true;
        } catch (Exception $e) {
            // Rollback transaction on error
            $conn->rollback();
            $payment_error = 'An error occurred while processing your payment. Please try again.';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pet Care Center - Make Payment</title>
    <link rel="stylesheet" href="css/styles.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.5/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-SgOJa3DmI69IUzQ2PVdRZhwQ+dy64/BUtbMJw1MZ8t5HZApcHrRKUc4W0kG879m7" crossorigin="anonymous">
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
                    <li><a href="bills.php" class="active">Billing</a></li>
                    <li><a href="profile.php">My Profile</a></li>
                    <li><a href="auth/logout.php">Logout</a></li>
                </ul>
            </nav>
        </div>
    </header>

    <main class="container">
        <section class="page-header">
            <h2>Make Payment</h2>
            <p>Complete your payment for Bill #<?php echo $bill_id; ?></p>
        </section>

        <?php if ($payment_success): ?>
            <!-- Payment Success Message -->
            <div class="alert alert-success" role="alert">
                <h4 class="alert-heading">Payment Successful!</h4>
                <p>Your payment has been processed successfully.</p>
                <hr>
                <p class="mb-0">Thank you for your payment. You can view your updated bill status in your billing history.</p>
                <div class="mt-3">
                    <a href="bills.php?bill_id=<?php echo $bill_id; ?>" class="btn btn-primary">View Bill Details</a>
                    <a href="bills.php" class="btn btn-secondary">Return to Bills</a>
                </div>
            </div>
        <?php else: ?>
            <!-- Payment Form -->
            <?php if (!empty($payment_error)): ?>
                <div class="alert alert-danger" role="alert">
                    <?php echo $payment_error; ?>
                </div>
            <?php endif; ?>

            <div class="row">
                <div class="col-md-8">
                    <div class="card mb-4">
                        <div class="card-header">
                            <h4>Bill Summary</h4>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <p><strong>Bill #:</strong> <?php echo $bill['bill_id']; ?></p>
                                    <p><strong>Bill Date:</strong> <?php echo date('M d, Y', strtotime($bill['bill_date'])); ?></p>
                                </div>
                                <div class="col-md-6">
                                    <p><strong>Total Amount:</strong> Rs. <?php echo number_format($bill['total_amount'], 2); ?></p>
                                    <p><strong>Status:</strong> 
                                        <span class="badge bg-<?php echo ($bill['payment_status'] == 'partial') ? 'info' : 'warning'; ?>">
                                            <?php echo ucfirst($bill['payment_status']); ?>
                                        </span>
                                    </p>
                                </div>
                            </div>

                            <?php if ($bill['payment_status'] === 'partial'): ?>
                                <?php
                                // Calculate remaining amount (in a real system, this would be more robust)
                                $stmt = $conn->prepare("
                                    SELECT SUM(amount) as paid_amount FROM payment_history 
                                    WHERE bill_id = ?
                                ");
                                $stmt->bind_param("i", $bill_id);
                                $stmt->execute();
                                $payment_result = $stmt->get_result();
                                $payment_data = $payment_result->fetch_assoc();
                                $stmt->close();
                                
                                $paid_amount = $payment_data['paid_amount'] ?: 0;
                                $remaining_amount = $bill['total_amount'] - $paid_amount;
                                ?>
                                <div class="row mt-3">
                                    <div class="col-md-6">
                                        <p><strong>Paid Amount:</strong> Rs. <?php echo number_format($paid_amount, 2); ?></p>
                                    </div>
                                    <div class="col-md-6">
                                        <p><strong>Remaining Amount:</strong> Rs. <?php echo number_format($remaining_amount, 2); ?></p>
                                    </div>
                                </div>
                            <?php else: ?>
                                <?php $remaining_amount = $bill['total_amount']; ?>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="card">
                        <div class="card-header">
                            <h4>Payment Details</h4>
                        </div>
                        <div class="card-body">
                            <form action="payment.php?bill_id=<?php echo $bill_id; ?>" method="POST">
                                <div class="mb-3">
                                    <label for="payment_amount" class="form-label">Payment Amount (Rs.)</label>
                                    <input type="number" class="form-control" id="payment_amount" name="payment_amount" step="0.01" min="0.01" max="<?php echo $remaining_amount; ?>" value="<?php echo $remaining_amount; ?>" required>
                                    <div class="form-text">Maximum amount: Rs. <?php echo number_format($remaining_amount, 2); ?></div>
                                </div>

                                <div class="mb-3">
                                    <label for="payment_method" class="form-label">Payment Method</label>
                                    <select class="form-select" id="payment_method" name="payment_method" required>
                                        <option value="">Select payment method</option>
                                        <option value="cash">Cash</option>
                                        <option value="card">Credit/Debit Card</option>
                                        <option value="online">Online Payment</option>
                                        <option value="bank_transfer">Bank Transfer</option>
                                    </select>
                                </div>

                                <div class="payment-method-details" id="card-details" style="display: none;">
                                    <div class="mb-3">
                                        <label for="card_number" class="form-label">Card Number</label>
                                        <input type="text" class="form-control" id="card_number" placeholder="1234 5678 9012 3456">
                                    </div>
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label for="expiry_date" class="form-label">Expiry Date</label>
                                            <input type="text" class="form-control" id="expiry_date" placeholder="MM/YY">
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label for="cvv" class="form-label">CVV</label>
                                            <input type="text" class="form-control" id="cvv" placeholder="123">
                                        </div>
                                    </div>
                                </div>

                                <div class="payment-method-details" id="bank-details" style="display: none;">
                                    <div class="mb-3">
                                        <label class="form-label">Bank Account Details</label>
                                        <div class="card card-body bg-light">
                                            <p><strong>Account Name:</strong> Pet Care Center</p>
                                            <p><strong>Bank:</strong> Bank of Sri Lanka</p>
                                            <p><strong>Account Number:</strong> 123456789</p>
                                            <p><strong>Branch:</strong> Ambilipitiya</p>
                                            <p class="mb-0"><strong>Reference:</strong> Bill #<?php echo $bill_id; ?></p>
                                        </div>
                                    </div>
                                    <div class="mb-3">
                                        <label for="transfer_reference" class="form-label">Transfer Reference</label>
                                        <input type="text" class="form-control" id="transfer_reference" placeholder="Enter your transfer reference">
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <label for="payment_notes" class="form-label">Notes (Optional)</label>
                                    <textarea class="form-control" id="payment_notes" name="payment_notes" rows="3"></textarea>
                                </div>

                                <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                                    <a href="bills.php?bill_id=<?php echo $bill_id; ?>" class="btn btn-secondary me-md-2">Cancel</a>
                                    <button type="submit" class="btn btn-primary">Complete Payment</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <div class="col-md-4">
                    <div class="card mb-4">
                        <div class="card-header">
                            <h4>Payment Instructions</h4>
                        </div>
                        <div class="card-body">
                            <h5>Cash Payment</h5>
                            <p>Visit our center to make a cash payment. Please bring the exact amount if possible.</p>
                            
                            <h5>Card Payment</h5>
                            <p>We accept all major credit and debit cards. Your card information is securely processed.</p>
                            
                            <h5>Online Payment</h5>
                            <p>Pay securely online through our payment gateway. You will be redirected to a secure page.</p>
                            
                            <h5>Bank Transfer</h5>
                            <p>Make a transfer to our bank account and provide the reference number when completed.</p>
                            
                            <div class="alert alert-info mt-3" role="alert">
                                <strong>Need Help?</strong> If you have any questions about your payment, please contact us at +94 123 456 789.
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </main>

    <footer>
        <div class="container">
            <p>&copy; <?php echo date('Y'); ?> Pet Care Center - Ambilipitiya, Sri Lanka</p>
            <p>Contact: +94 123 456 789 | info@petcare.lk</p>
        </div>
    </footer>

    <script src="js/script.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.5/dist/js/bootstrap.bundle.min.js" integrity="sha384-k6d4wzSIapyDyv1kpU366/PK5hCdSbCRGRCMv+eplOQJWyd1fbcAu9OCUj5zNLiq" crossorigin="anonymous"></script>
    <script>
        // Show/hide payment method details based on selection
        document.getElementById('payment_method').addEventListener('change', function() {
            // Hide all payment method details first
            document.querySelectorAll('.payment-method-details').forEach(function(el) {
                el.style.display = 'none';
            });
            
            // Show specific payment method details
            if (this.value === 'card') {
                document.getElementById('card-details').style.display = 'block';
            } else if (this.value === 'bank_transfer') {
                document.getElementById('bank-details').style.display = 'block';
            }
        });
    </script>
</body>
</html>