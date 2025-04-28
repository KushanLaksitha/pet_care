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

// Handle filters
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : '';

// Build the query with filters
$query = "SELECT b.*, a.appointment_id, a.appointment_date 
          FROM billing b 
          LEFT JOIN appointments a ON b.appointment_id = a.appointment_id 
          WHERE b.owner_id = ?";

$params = array($owner_id);
$types = "i";

if (!empty($status_filter)) {
    $query .= " AND b.payment_status = ?";
    $params[] = $status_filter;
    $types .= "s";
}

if (!empty($date_from)) {
    $query .= " AND b.bill_date >= ?";
    $params[] = $date_from;
    $types .= "s";
}

if (!empty($date_to)) {
    $query .= " AND b.bill_date <= ?";
    $params[] = $date_to;
    $types .= "s";
}

$query .= " ORDER BY b.bill_date DESC";

// Prepare and execute the query
$stmt = $conn->prepare($query);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$bills_result = $stmt->get_result();
$bills = [];
while ($bill = $bills_result->fetch_assoc()) {
    $bills[] = $bill;
}
$stmt->close();

// Check if bill details are requested
$bill_details = [];
if (isset($_GET['bill_id']) && is_numeric($_GET['bill_id'])) {
    $bill_id = $_GET['bill_id'];
    
    // Get bill header information
    $stmt = $conn->prepare("
        SELECT b.*, o.owner_name 
        FROM billing b
        JOIN owners o ON b.owner_id = o.owner_id
        WHERE b.bill_id = ? AND b.owner_id = ?
    ");
    $stmt->bind_param("ii", $bill_id, $owner_id);
    $stmt->execute();
    $current_bill = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    if ($current_bill) {
        // Get bill details
        $stmt = $conn->prepare("
            SELECT bd.*, s.service_name, i.item_name
            FROM bill_details bd
            LEFT JOIN services s ON bd.service_id = s.service_id
            LEFT JOIN inventory_items i ON bd.item_id = i.item_id
            WHERE bd.bill_id = ?
        ");
        $stmt->bind_param("i", $bill_id);
        $stmt->execute();
        $details_result = $stmt->get_result();
        while ($detail = $details_result->fetch_assoc()) {
            $bill_details[] = $detail;
        }
        $stmt->close();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pet Care Center - Billing</title>
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
            <h2>Billing History</h2>
            <p>View and manage your billing information</p>
        </section>

        <?php if (isset($current_bill) && $current_bill): ?>
            <!-- Bill Details View -->
            <section class="bill-details-section">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h3>Bill #<?php echo $current_bill['bill_id']; ?></h3>
                    <a href="bills.php" class="btn btn-secondary">Back to Bills</a>
                </div>
                
                <div class="card mb-4">
                    <div class="card-header">
                        <h4>Bill Information</h4>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <p><strong>Bill Date:</strong> <?php echo date('M d, Y', strtotime($current_bill['bill_date'])); ?></p>
                                <p><strong>Customer:</strong> <?php echo htmlspecialchars($current_bill['owner_name']); ?></p>
                                <p><strong>Total Amount:</strong> Rs. <?php echo number_format($current_bill['total_amount'], 2); ?></p>
                            </div>
                            <div class="col-md-6">
                                <p><strong>Payment Status:</strong> 
                                    <span class="badge bg-<?php 
                                        echo ($current_bill['payment_status'] == 'paid') ? 'success' : 
                                            (($current_bill['payment_status'] == 'pending') ? 'warning' : 
                                            (($current_bill['payment_status'] == 'partial') ? 'info' : 'danger')); 
                                    ?>">
                                        <?php echo ucfirst($current_bill['payment_status']); ?>
                                    </span>
                                </p>
                                <p><strong>Payment Method:</strong> <?php echo ucfirst($current_bill['payment_method']); ?></p>
                                <?php if ($current_bill['appointment_id']): ?>
                                    <p><strong>Appointment:</strong> <a href="appointments.php?id=<?php echo $current_bill['appointment_id']; ?>">View Details</a></p>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <?php if (!empty($current_bill['notes'])): ?>
                            <div class="row mt-3">
                                <div class="col-12">
                                    <p><strong>Notes:</strong></p>
                                    <p><?php echo nl2br(htmlspecialchars($current_bill['notes'])); ?></p>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="card">
                    <div class="card-header">
                        <h4>Bill Details</h4>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Description</th>
                                        <th>Type</th>
                                        <th>Quantity</th>
                                        <th>Unit Price</th>
                                        <th>Subtotal</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (count($bill_details) > 0): ?>
                                        <?php foreach ($bill_details as $detail): ?>
                                            <tr>
                                                <td>
                                                    <?php 
                                                        if ($detail['service_id']) {
                                                            echo htmlspecialchars($detail['service_name']);
                                                        } elseif ($detail['item_id']) {
                                                            echo htmlspecialchars($detail['item_name']);
                                                        } else {
                                                            echo htmlspecialchars($detail['description']);
                                                        }
                                                    ?>
                                                </td>
                                                <td>
                                                    <?php 
                                                        if ($detail['service_id']) {
                                                            echo 'Service';
                                                        } elseif ($detail['item_id']) {
                                                            echo 'Item';
                                                        } else {
                                                            echo 'Other';
                                                        }
                                                    ?>
                                                </td>
                                                <td><?php echo $detail['quantity']; ?></td>
                                                <td>Rs. <?php echo number_format($detail['unit_price'], 2); ?></td>
                                                <td>Rs. <?php echo number_format($detail['subtotal'], 2); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="5" class="text-center">No details available for this bill.</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                                <tfoot>
                                    <tr>
                                        <th colspan="4" class="text-end">Total</th>
                                        <th>Rs. <?php echo number_format($current_bill['total_amount'], 2); ?></th>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>
                </div>
                
                <?php if ($current_bill['payment_status'] == 'pending' || $current_bill['payment_status'] == 'partial'): ?>
                    <div class="mt-4 text-center">
                        <a href="payment.php?bill_id=<?php echo $current_bill['bill_id']; ?>" class="btn btn-primary">Make Payment</a>
                    </div>
                <?php endif; ?>
            </section>
            
        <?php else: ?>
            <!-- Bills List View -->
            <section class="bills-filter-section">
                <div class="card mb-4">
                    <div class="card-header">
                        <h4>Filter Bills</h4>
                    </div>
                    <div class="card-body">
                        <form action="bills.php" method="GET" class="row g-3">
                            <div class="col-md-3">
                                <label for="status" class="form-label">Payment Status</label>
                                <select name="status" id="status" class="form-select">
                                    <option value="">All</option>
                                    <option value="pending" <?php if($status_filter == 'pending') echo 'selected'; ?>>Pending</option>
                                    <option value="paid" <?php if($status_filter == 'paid') echo 'selected'; ?>>Paid</option>
                                    <option value="partial" <?php if($status_filter == 'partial') echo 'selected'; ?>>Partial</option>
                                    <option value="cancelled" <?php if($status_filter == 'cancelled') echo 'selected'; ?>>Cancelled</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label for="date_from" class="form-label">From Date</label>
                                <input type="date" name="date_from" id="date_from" class="form-control" value="<?php echo $date_from; ?>">
                            </div>
                            <div class="col-md-3">
                                <label for="date_to" class="form-label">To Date</label>
                                <input type="date" name="date_to" id="date_to" class="form-control" value="<?php echo $date_to; ?>">
                            </div>
                            <div class="col-md-3 d-flex align-items-end">
                                <button type="submit" class="btn btn-primary me-2">Filter</button>
                                <a href="bills.php" class="btn btn-secondary">Reset</a>
                            </div>
                        </form>
                    </div>
                </div>
            </section>

            <section class="bills-list-section">
                <div class="card">
                    <div class="card-header">
                        <h4>Your Bills</h4>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Bill #</th>
                                        <th>Date</th>
                                        <th>Amount</th>
                                        <th>Status</th>
                                        <th>Payment Method</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (count($bills) > 0): ?>
                                        <?php foreach ($bills as $bill): ?>
                                            <tr>
                                                <td><?php echo $bill['bill_id']; ?></td>
                                                <td><?php echo date('M d, Y', strtotime($bill['bill_date'])); ?></td>
                                                <td>Rs. <?php echo number_format($bill['total_amount'], 2); ?></td>
                                                <td>
                                                    <span class="badge bg-<?php 
                                                        echo ($bill['payment_status'] == 'paid') ? 'success' : 
                                                            (($bill['payment_status'] == 'pending') ? 'warning' : 
                                                            (($bill['payment_status'] == 'partial') ? 'info' : 'danger')); 
                                                    ?>">
                                                        <?php echo ucfirst($bill['payment_status']); ?>
                                                    </span>
                                                </td>
                                                <td><?php echo ucfirst($bill['payment_method']); ?></td>
                                                <td>
                                                    <a href="bills.php?bill_id=<?php echo $bill['bill_id']; ?>" class="btn btn-sm btn-info">View</a>
                                                    <?php if ($bill['payment_status'] == 'pending' || $bill['payment_status'] == 'partial'): ?>
                                                        <a href="payment.php?bill_id=<?php echo $bill['bill_id']; ?>" class="btn btn-sm btn-primary">Pay</a>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="6" class="text-center">No bills found.</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </section>
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
</body>
</html>