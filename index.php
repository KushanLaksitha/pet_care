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

// Get user's pets
$pets = [];
if ($owner_id) {
    $stmt = $conn->prepare("
        SELECT p.*, s.species_name, b.breed_name 
        FROM pets p 
        LEFT JOIN species s ON p.species_id = s.species_id 
        LEFT JOIN breeds b ON p.breed_id = b.breed_id 
        WHERE p.owner_id = ?
    ");
    $stmt->bind_param("i", $owner_id);
    $stmt->execute();
    $pets_result = $stmt->get_result();
    while ($pet = $pets_result->fetch_assoc()) {
        $pets[] = $pet;
    }
    $stmt->close();

    // Get upcoming appointments
    $stmt = $conn->prepare("
        SELECT a.*, p.name as pet_name, s.service_name 
        FROM appointments a 
        JOIN pets p ON a.pet_id = p.pet_id 
        JOIN services s ON a.service_id = s.service_id 
        WHERE p.owner_id = ? AND a.appointment_date >= CURDATE() 
        AND a.status IN ('scheduled', 'confirmed') 
        ORDER BY a.appointment_date, a.appointment_time
        LIMIT 5
    ");
    $stmt->bind_param("i", $owner_id);
    $stmt->execute();
    $appointments_result = $stmt->get_result();
    $upcoming_appointments = [];
    while ($appointment = $appointments_result->fetch_assoc()) {
        $upcoming_appointments[] = $appointment;
    }
    $stmt->close();

    // Get active boardings
    $stmt = $conn->prepare("
        SELECT b.*, p.name as pet_name 
        FROM boarding b 
        JOIN pets p ON b.pet_id = p.pet_id 
        WHERE b.owner_id = ? AND b.status IN ('booked', 'checked_in') 
        AND b.check_out_date >= CURDATE() 
        ORDER BY b.check_in_date
        LIMIT 5
    ");
    $stmt->bind_param("i", $owner_id);
    $stmt->execute();
    $boarding_result = $stmt->get_result();
    $active_boardings = [];
    while ($boarding = $boarding_result->fetch_assoc()) {
        $active_boardings[] = $boarding;
    }
    $stmt->close();

    // Get recent bills
    $stmt = $conn->prepare("
        SELECT * FROM billing 
        WHERE owner_id = ? 
        ORDER BY bill_date DESC
        LIMIT 5
    ");
    $stmt->bind_param("i", $owner_id);
    $stmt->execute();
    $bills_result = $stmt->get_result();
    $recent_bills = [];
    while ($bill = $bills_result->fetch_assoc()) {
        $recent_bills[] = $bill;
    }
    $stmt->close();
}

// Get services list
$stmt = $conn->prepare("SELECT * FROM services WHERE status = 'active'");
$stmt->execute();
$services_result = $stmt->get_result();
$services = [];
while ($service = $services_result->fetch_assoc()) {
    $services[] = $service;
}
$stmt->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pet Care Center - Customer Dashboard</title>
    <link rel="stylesheet" href="css/styles.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.5/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-SgOJa3DmI69IUzQ2PVdRZhwQ+dy64/BUtbMJw1MZ8t5HZApcHrRKUc4W0kG879m7" crossorigin="anonymous">
</head>
<body>
    <header>
        <div class="container">
            <h1>Pet Care Center</h1>
            <nav>
                <ul>
                    <li><a href="index.php" class="active">Dashboard</a></li>
                    <li><a href="pets.php">My Pets</a></li>
                    <li><a href="appointments.php">Appointments</a></li>
                    <li><a href="boarding.php">Boarding</a></li>
                    <li><a href="bills.php">Billing</a></li>
                    <li><a href="profile.php">My Profile</a></li>
                    <li><a href="auth/logout.php">Logout</a></li>
                </ul>
            </nav>
        </div>
    </header>

    <main class="container">
        <section class="welcome-section">
            <h2>Welcome, <?php echo htmlspecialchars($user['full_name']); ?>!</h2>
            <p>Here's an overview of your pet care information.</p>
        </section>

        <div class="dashboard-grid">
            <!-- My Pets Section -->
            <section class="dashboard-card">
                <h3>My Pets</h3>
                <?php if(count($pets) > 0): ?>
                    <ul class="dashboard-list">
                        <?php foreach($pets as $pet): ?>
                            <li>
                                <strong><?php echo htmlspecialchars($pet['name']); ?></strong> - 
                                <?php echo htmlspecialchars($pet['species_name']); ?> 
                                (<?php echo htmlspecialchars($pet['breed_name'] ?? 'Unknown Breed'); ?>)
                            </li>
                        <?php endforeach; ?>
                    </ul>
                    <a href="pets.php" class="view-all-link">View All Pets</a>
                <?php else: ?>
                    <p>You haven't registered any pets yet.</p>
                    <a href="add_pet.php" class="btn-primary">Register a Pet</a>
                <?php endif; ?>
            </section>

            <!-- Upcoming Appointments Section -->
            <section class="dashboard-card">
                <h3>Upcoming Appointments</h3>
                <?php if(isset($upcoming_appointments) && count($upcoming_appointments) > 0): ?>
                    <ul class="dashboard-list">
                        <?php foreach($upcoming_appointments as $appointment): ?>
                            <li>
                                <div class="appointment-date">
                                    <?php 
                                        echo date('M d, Y', strtotime($appointment['appointment_date']));
                                        echo ' at ';
                                        echo date('h:i A', strtotime($appointment['appointment_time']));
                                    ?>
                                </div>
                                <div class="appointment-details">
                                    <strong><?php echo htmlspecialchars($appointment['pet_name']); ?></strong> - 
                                    <?php echo htmlspecialchars($appointment['service_name']); ?> 
                                    <span class="appointment-status <?php echo $appointment['status']; ?>">
                                        <?php echo ucfirst($appointment['status']); ?>
                                    </span>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                    <a href="appointments.php" class="view-all-link">View All Appointments</a>
                <?php else: ?>
                    <p>No upcoming appointments.</p>
                    <a href="book_appointment.php" class="btn-primary">Book an Appointment</a>
                <?php endif; ?>
            </section>

            <!-- Active Boardings Section -->
            <section class="dashboard-card">
                <h3>Current Boardings</h3>
                <?php if(isset($active_boardings) && count($active_boardings) > 0): ?>
                    <ul class="dashboard-list">
                        <?php foreach($active_boardings as $boarding): ?>
                            <li>
                                <div class="boarding-date">
                                    <?php 
                                        echo date('M d', strtotime($boarding['check_in_date']));
                                        echo ' - ';
                                        echo date('M d, Y', strtotime($boarding['check_out_date']));
                                    ?>
                                </div>
                                <div class="boarding-details">
                                    <strong><?php echo htmlspecialchars($boarding['pet_name']); ?></strong>
                                    <span class="boarding-status <?php echo $boarding['status']; ?>">
                                        <?php echo ucfirst($boarding['status']); ?>
                                    </span>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                    <a href="boarding.php" class="view-all-link">View All Boardings</a>
                <?php else: ?>
                    <p>No active boarding requests.</p>
                    <a href="book_boarding.php" class="btn-primary">Book Boarding</a>
                <?php endif; ?>
            </section>

            <!-- Recent Bills Section -->
            <section class="dashboard-card">
                <h3>Recent Bills</h3>
                <?php if(isset($recent_bills) && count($recent_bills) > 0): ?>
                    <ul class="dashboard-list">
                        <?php foreach($recent_bills as $bill): ?>
                            <li>
                                <div class="bill-date">
                                    <?php echo date('M d, Y', strtotime($bill['bill_date'])); ?>
                                </div>
                                <div class="bill-details">
                                    <strong>Rs. <?php echo number_format($bill['total_amount'], 2); ?></strong>
                                    <span class="bill-status <?php echo $bill['payment_status']; ?>">
                                        <?php echo ucfirst($bill['payment_status']); ?>
                                    </span>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                    <a href="bills.php" class="view-all-link">View All Bills</a>
                <?php else: ?>
                    <p>No billing history found.</p>
                <?php endif; ?>
            </section>

            <!-- Our Services Section -->
            <section class="dashboard-card services-card">
                <h3>Our Services</h3>
                <div class="services-grid">
                    <?php foreach($services as $index => $service): ?>
                        <?php if($index < 6): ?>
                            <div class="service-item">
                                <h4><?php echo htmlspecialchars($service['service_name']); ?></h4>
                                <p class="service-price">Rs. <?php echo number_format($service['price'], 2); ?></p>
                                <p class="service-duration"><?php echo $service['duration']; ?> min</p>
                            </div>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>
                <a href="services.php" class="view-all-link">View All Services</a>
            </section>

            <!-- Quick Actions Section -->
            <section class="dashboard-card quick-actions">
                <h3>Quick Actions</h3>
                <div class="action-buttons">
                    <a href="book_appointment.php" class="btn-action">Book Appointment</a>
                    <a href="book_boarding.php" class="btn-action">Book Boarding</a>
                    <a href="add_pet.php" class="btn-action">Register New Pet</a>
                </div>
            </section>
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