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

// Get owner information
$stmt = $conn->prepare("SELECT * FROM owners WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$owner_result = $stmt->get_result();
$owner = $owner_result->fetch_assoc();
$owner_id = $owner ? $owner['owner_id'] : null;
$stmt->close();

// If no owner record found, redirect to create profile
if (!$owner_id) {
    header("Location: create_profile.php");
    exit();
}

// Check for success message
$success_message = '';
if (isset($_GET['success']) && $_GET['success'] == 1) {
    $success_message = 'Boarding booked successfully!';
}

// Process cancellation requests
if (isset($_POST['cancel_boarding']) && isset($_POST['boarding_id'])) {
    $boarding_id = $_POST['boarding_id'];
    
    // First check if the boarding belongs to this owner
    $stmt = $conn->prepare("
        SELECT b.*, p.name as pet_name
        FROM boarding b 
        JOIN pets p ON b.pet_id = p.pet_id 
        WHERE b.boarding_id = ? AND b.owner_id = ?
    ");
    $stmt->bind_param("ii", $boarding_id, $owner_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $boarding = $result->fetch_assoc();
        
        // Check if boarding is in a status that can be cancelled
        if ($boarding['status'] == 'booked') {
            // Update boarding status to cancelled
            $stmt = $conn->prepare("UPDATE boarding SET status = 'cancelled' WHERE boarding_id = ?");
            $stmt->bind_param("i", $boarding_id);
            
            if ($stmt->execute()) {
                $success_message = 'Boarding cancelled successfully!';
            } else {
                $error_message = 'Error cancelling boarding: ' . $conn->error;
            }
        } else {
            $error_message = 'This boarding cannot be cancelled as it is already ' . $boarding['status'];
        }
    } else {
        $error_message = 'Invalid boarding request';
    }
    $stmt->close();
}

// Get all boarding records for this owner
$stmt = $conn->prepare("
    SELECT b.*, p.name as pet_name, p.species_id, p.breed_id,
           s.species_name, br.breed_name
    FROM boarding b 
    JOIN pets p ON b.pet_id = p.pet_id 
    LEFT JOIN species s ON p.species_id = s.species_id
    LEFT JOIN breeds br ON p.breed_id = br.breed_id
    WHERE b.owner_id = ? 
    ORDER BY 
        CASE b.status 
            WHEN 'checked_in' THEN 1 
            WHEN 'booked' THEN 2 
            WHEN 'checked_out' THEN 3
            WHEN 'cancelled' THEN 4
        END,
        b.check_in_date DESC
");
$stmt->bind_param("i", $owner_id);
$stmt->execute();
$boarding_result = $stmt->get_result();
$boardings = [];
while ($boarding = $boarding_result->fetch_assoc()) {
    // Calculate number of days and total cost
    $check_in = new DateTime($boarding['check_in_date']);
    $check_out = new DateTime($boarding['check_out_date']);
    $interval = $check_in->diff($check_out);
    $boarding['days'] = $interval->days;
    $boarding['total_cost'] = $boarding['daily_rate'] * $boarding['days'];
    
    $boardings[] = $boarding;
}
$stmt->close();

// Separate boarding records into active, upcoming, past and cancelled
$active_boardings = [];
$upcoming_boardings = [];
$past_boardings = [];
$cancelled_boardings = [];

$today = new DateTime();
foreach ($boardings as $boarding) {
    $check_in = new DateTime($boarding['check_in_date']);
    $check_out = new DateTime($boarding['check_out_date']);
    
    if ($boarding['status'] == 'cancelled') {
        $cancelled_boardings[] = $boarding;
    } elseif ($boarding['status'] == 'checked_in') {
        $active_boardings[] = $boarding;
    } elseif ($boarding['status'] == 'checked_out' || $check_out < $today) {
        $past_boardings[] = $boarding;
    } elseif ($boarding['status'] == 'booked' && $check_in > $today) {
        $upcoming_boardings[] = $boarding;
    } else {
        // Any other case goes to active
        $active_boardings[] = $boarding;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pet Boarding - Pet Care Center</title>
    <link rel="stylesheet" href="css/styles.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.5/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-SgOJa3DmI69IUzQ2PVdRZhwQ+dy64/BUtbMJw1MZ8t5HZApcHrRKUc4W0kG879m7" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
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
                    <li><a href="boarding.php" class="active">Boarding</a></li>
                    <li><a href="bills.php">Billing</a></li>
                    <li><a href="profile.php">My Profile</a></li>
                    <li><a href="auth/logout.php">Logout</a></li>
                </ul>
            </nav>
        </div>
    </header>

    <main class="container">
        <section class="page-header">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h2>Pet Boarding</h2>
                    <p>Manage your pet boarding reservations</p>
                </div>
                <div>
                    <a href="book_boarding.php" class="btn btn-primary">
                        <i class="bi bi-plus-circle"></i> Book New Boarding
                    </a>
                </div>
            </div>
        </section>

        <?php if (!empty($success_message)): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?php echo htmlspecialchars($success_message); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <?php if (!empty($error_message)): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?php echo htmlspecialchars($error_message); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <?php if (count($boardings) === 0): ?>
            <div class="boarding-empty text-center py-5">
                <i class="bi bi-house-heart display-1 text-muted"></i>
                <h3 class="mt-3">No Boarding History</h3>
                <p>You haven't booked any boarding for your pets yet.</p>
                <a href="book_boarding.php" class="btn btn-primary mt-3">Book Your First Boarding</a>
            </div>
        <?php else: ?>
            <!-- Active Boardings -->
            <?php if (count($active_boardings) > 0): ?>
                <section class="boarding-section mb-4">
                    <h3>Active Boardings</h3>
                    <div class="table-responsive">
                        <table class="table table-hover table-striped">
                            <thead class="table-primary">
                                <tr>
                                    <th>Pet</th>
                                    <th>Check-in</th>
                                    <th>Check-out</th>
                                    <th>Days</th>
                                    <th>Daily Rate</th>
                                    <th>Total Cost</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($active_boardings as $boarding): ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo htmlspecialchars($boarding['pet_name']); ?></strong><br>
                                            <small><?php echo htmlspecialchars($boarding['species_name']); ?> 
                                            (<?php echo htmlspecialchars($boarding['breed_name'] ?? 'Unknown Breed'); ?>)</small>
                                        </td>
                                        <td><?php echo date('M d, Y', strtotime($boarding['check_in_date'])); ?></td>
                                        <td><?php echo date('M d, Y', strtotime($boarding['check_out_date'])); ?></td>
                                        <td><?php echo $boarding['days']; ?></td>
                                        <td>Rs. <?php echo number_format($boarding['daily_rate'], 2); ?></td>
                                        <td>Rs. <?php echo number_format($boarding['total_cost'], 2); ?></td>
                                        <td>
                                            <span class="badge bg-success"><?php echo ucfirst($boarding['status']); ?></span>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td colspan="7" class="bg-light">
                                            <strong>Special Instructions:</strong> 
                                            <?php echo !empty($boarding['special_instructions']) ? 
                                                  htmlspecialchars($boarding['special_instructions']) : 
                                                  'None provided'; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </section>
            <?php endif; ?>
            
            <!-- Upcoming Boardings -->
            <?php if (count($upcoming_boardings) > 0): ?>
                <section class="boarding-section mb-4">
                    <h3>Upcoming Boardings</h3>
                    <div class="table-responsive">
                        <table class="table table-hover table-striped">
                            <thead class="table-info">
                                <tr>
                                    <th>Pet</th>
                                    <th>Check-in</th>
                                    <th>Check-out</th>
                                    <th>Days</th>
                                    <th>Daily Rate</th>
                                    <th>Total Cost</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($upcoming_boardings as $boarding): ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo htmlspecialchars($boarding['pet_name']); ?></strong><br>
                                            <small><?php echo htmlspecialchars($boarding['species_name']); ?> 
                                            (<?php echo htmlspecialchars($boarding['breed_name'] ?? 'Unknown Breed'); ?>)</small>
                                        </td>
                                        <td><?php echo date('M d, Y', strtotime($boarding['check_in_date'])); ?></td>
                                        <td><?php echo date('M d, Y', strtotime($boarding['check_out_date'])); ?></td>
                                        <td><?php echo $boarding['days']; ?></td>
                                        <td>Rs. <?php echo number_format($boarding['daily_rate'], 2); ?></td>
                                        <td>Rs. <?php echo number_format($boarding['total_cost'], 2); ?></td>
                                        <td>
                                            <span class="badge bg-warning text-dark"><?php echo ucfirst($boarding['status']); ?></span>
                                        </td>
                                        <td>
                                            <form method="post" onsubmit="return confirm('Are you sure you want to cancel this boarding reservation?');">
                                                <input type="hidden" name="boarding_id" value="<?php echo $boarding['boarding_id']; ?>">
                                                <button type="submit" name="cancel_boarding" class="btn btn-sm btn-danger">
                                                    <i class="bi bi-x-circle"></i> Cancel
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td colspan="8" class="bg-light">
                                            <strong>Special Instructions:</strong> 
                                            <?php echo !empty($boarding['special_instructions']) ? 
                                                  htmlspecialchars($boarding['special_instructions']) : 
                                                  'None provided'; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </section>
            <?php endif; ?>
            
            <!-- Past Boardings -->
            <?php if (count($past_boardings) > 0): ?>
                <section class="boarding-section mb-4">
                    <h3>Past Boardings</h3>
                    <div class="table-responsive">
                        <table class="table table-hover table-striped">
                            <thead class="table-secondary">
                                <tr>
                                    <th>Pet</th>
                                    <th>Check-in</th>
                                    <th>Check-out</th>
                                    <th>Days</th>
                                    <th>Daily Rate</th>
                                    <th>Total Cost</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($past_boardings as $boarding): ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo htmlspecialchars($boarding['pet_name']); ?></strong><br>
                                            <small><?php echo htmlspecialchars($boarding['species_name']); ?> 
                                            (<?php echo htmlspecialchars($boarding['breed_name'] ?? 'Unknown Breed'); ?>)</small>
                                        </td>
                                        <td><?php echo date('M d, Y', strtotime($boarding['check_in_date'])); ?></td>
                                        <td><?php echo date('M d, Y', strtotime($boarding['check_out_date'])); ?></td>
                                        <td><?php echo $boarding['days']; ?></td>
                                        <td>Rs. <?php echo number_format($boarding['daily_rate'], 2); ?></td>
                                        <td>Rs. <?php echo number_format($boarding['total_cost'], 2); ?></td>
                                        <td>
                                            <span class="badge bg-secondary"><?php echo ucfirst($boarding['status']); ?></span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </section>
            <?php endif; ?>
            
            <!-- Cancelled Boardings -->
            <?php if (count($cancelled_boardings) > 0): ?>
                <section class="boarding-section mb-4">
                    <button class="btn btn-outline-secondary mb-2" type="button" data-bs-toggle="collapse" data-bs-target="#cancelledBoardings">
                        Show Cancelled Boardings <i class="bi bi-chevron-down"></i>
                    </button>
                    <div class="collapse" id="cancelledBoardings">
                        <div class="table-responsive">
                            <table class="table table-hover table-striped">
                                <thead class="table-danger">
                                    <tr>
                                        <th>Pet</th>
                                        <th>Check-in</th>
                                        <th>Check-out</th>
                                        <th>Days</th>
                                        <th>Daily Rate</th>
                                        <th>Total Cost</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($cancelled_boardings as $boarding): ?>
                                        <tr>
                                            <td>
                                                <strong><?php echo htmlspecialchars($boarding['pet_name']); ?></strong><br>
                                                <small><?php echo htmlspecialchars($boarding['species_name']); ?> 
                                                (<?php echo htmlspecialchars($boarding['breed_name'] ?? 'Unknown Breed'); ?>)</small>
                                            </td>
                                            <td><?php echo date('M d, Y', strtotime($boarding['check_in_date'])); ?></td>
                                            <td><?php echo date('M d, Y', strtotime($boarding['check_out_date'])); ?></td>
                                            <td><?php echo $boarding['days']; ?></td>
                                            <td>Rs. <?php echo number_format($boarding['daily_rate'], 2); ?></td>
                                            <td>Rs. <?php echo number_format($boarding['total_cost'], 2); ?></td>
                                            <td>
                                                <span class="badge bg-danger"><?php echo ucfirst($boarding['status']); ?></span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </section>
            <?php endif; ?>
        <?php endif; ?>

        <section class="boarding-info mt-5">
            <h3>Boarding Information</h3>
            <div class="row">
                <div class="col-md-6">
                    <div class="card mb-3">
                        <div class="card-header bg-primary text-white">
                            <i class="bi bi-info-circle"></i> Boarding Policies
                        </div>
                        <div class="card-body">
                            <ul class="list-unstyled">
                                <li><i class="bi bi-check-circle text-success"></i> Check-in time: 8:00 AM - 6:00 PM</li>
                                <li><i class="bi bi-check-circle text-success"></i> Check-out time: Before 12:00 PM</li>
                                <li><i class="bi bi-check-circle text-success"></i> All pets must have updated vaccinations</li>
                                <li><i class="bi bi-check-circle text-success"></i> Please bring your pet's food and medications</li>
                                <li><i class="bi bi-check-circle text-success"></i> Cancellations must be made 24 hours in advance</li>
                            </ul>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header bg-primary text-white">
                            <i class="bi bi-telephone"></i> Need Help?
                        </div>
                        <div class="card-body">
                            <p>If you have any questions about our boarding services or need assistance, please contact us:</p>
                            <ul class="list-unstyled">
                                <li><i class="bi bi-telephone-fill"></i> Phone: +94 123 456 789</li>
                                <li><i class="bi bi-envelope-fill"></i> Email: boarding@petcare.lk</li>
                                <li><i class="bi bi-geo-alt-fill"></i> Address: 123 Pet Care Lane, Ambilipitiya, Sri Lanka</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </main>

    <footer>
        <div class="container">
            <p>&copy; <?php echo date('Y'); ?> Pet Care Center - Ambilipitiya, Sri Lanka</p>
            <p>Contact: +94 123 456 789 | info@petcare.lk</p>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.5/dist/js/bootstrap.bundle.min.js" integrity="sha384-k6d4wzSIapyDyv1kpU366/PK5hCdSbCRGRCMv+eplOQJWyd1fbcAu9OCUj5zNLiq" crossorigin="anonymous"></script>
</body>
</html>