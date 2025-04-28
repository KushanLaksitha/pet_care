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

$user_id = $_SESSION['user_id'];
$errors = [];
$success_message = '';

// Get owner information
$stmt = $conn->prepare("SELECT * FROM owners WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$owner_result = $stmt->get_result();
$owner = $owner_result->fetch_assoc();
$owner_id = $owner ? $owner['owner_id'] : null;
$stmt->close();

// If no owner record, redirect to create profile
if (!$owner_id) {
    header("Location: profile.php?action=create");
    exit();
}

// Handle appointment cancellation if requested
if (isset($_GET['cancel']) && isset($_GET['id'])) {
    $appointment_id = intval($_GET['id']);
    
    // Verify appointment belongs to this owner's pet
    $stmt = $conn->prepare("
        SELECT a.* FROM appointments a
        JOIN pets p ON a.pet_id = p.pet_id
        WHERE a.appointment_id = ? AND p.owner_id = ? AND a.status IN ('scheduled', 'confirmed')
    ");
    $stmt->bind_param("ii", $appointment_id, $owner_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        // Update appointment status to cancelled
        $stmt = $conn->prepare("UPDATE appointments SET status = 'cancelled', updated_at = CURRENT_TIMESTAMP WHERE appointment_id = ?");
        $stmt->bind_param("i", $appointment_id);
        
        if ($stmt->execute()) {
            $success_message = "Appointment #$appointment_id has been cancelled successfully.";
        } else {
            $errors[] = "Failed to cancel appointment. Please try again.";
        }
    } else {
        $errors[] = "Invalid appointment or the appointment cannot be cancelled.";
    }
    $stmt->close();
}

// Get filter parameters
$filter_status = isset($_GET['status']) ? $_GET['status'] : '';
$filter_pet = isset($_GET['pet_id']) ? intval($_GET['pet_id']) : 0;
$filter_service = isset($_GET['service_id']) ? intval($_GET['service_id']) : 0;
$filter_date_from = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$filter_date_to = isset($_GET['date_to']) ? $_GET['date_to'] : '';

// Base query
$query = "
    SELECT a.*, p.name as pet_name, p.species_id, s.service_name, s.price,
           sp.species_name, st.staff_name, st.position
    FROM appointments a
    JOIN pets p ON a.pet_id = p.pet_id
    JOIN services s ON a.service_id = s.service_id
    LEFT JOIN species sp ON p.species_id = sp.species_id
    LEFT JOIN staff st ON a.staff_id = st.staff_id
    WHERE p.owner_id = ?
";

// Add filters to query
$params = array($owner_id);
$types = "i";

if (!empty($filter_status)) {
    $query .= " AND a.status = ?";
    $params[] = $filter_status;
    $types .= "s";
}

if ($filter_pet > 0) {
    $query .= " AND p.pet_id = ?";
    $params[] = $filter_pet;
    $types .= "i";
}

if ($filter_service > 0) {
    $query .= " AND a.service_id = ?";
    $params[] = $filter_service;
    $types .= "i";
}

if (!empty($filter_date_from)) {
    $query .= " AND a.appointment_date >= ?";
    $params[] = $filter_date_from;
    $types .= "s";
}

if (!empty($filter_date_to)) {
    $query .= " AND a.appointment_date <= ?";
    $params[] = $filter_date_to;
    $types .= "s";
}

// Add order by
$query .= " ORDER BY a.appointment_date DESC, a.appointment_time DESC";

// Prepare and execute the query
$stmt = $conn->prepare($query);
if (count($params) > 1) {
    $stmt->bind_param($types, ...$params);
} else {
    $stmt->bind_param($types, $params[0]);
}
$stmt->execute();
$appointments_result = $stmt->get_result();
$appointments = [];
while ($appointment = $appointments_result->fetch_assoc()) {
    $appointments[] = $appointment;
}
$stmt->close();

// Get pets for filter dropdown
$stmt = $conn->prepare("SELECT pet_id, name FROM pets WHERE owner_id = ? ORDER BY name");
$stmt->bind_param("i", $owner_id);
$stmt->execute();
$pets_result = $stmt->get_result();
$pets = [];
while ($pet = $pets_result->fetch_assoc()) {
    $pets[] = $pet;
}
$stmt->close();

// Get services for filter dropdown
$stmt = $conn->prepare("SELECT service_id, service_name FROM services WHERE status = 'active' ORDER BY service_name");
$stmt->execute();
$services_result = $stmt->get_result();
$services = [];
while ($service = $services_result->fetch_assoc()) {
    $services[] = $service;
}
$stmt->close();

// Calculate counts for each status
$status_counts = [
    'all' => 0,
    'scheduled' => 0,
    'confirmed' => 0,
    'completed' => 0,
    'cancelled' => 0
];

$stmt = $conn->prepare("
    SELECT a.status, COUNT(*) as count
    FROM appointments a
    JOIN pets p ON a.pet_id = p.pet_id
    WHERE p.owner_id = ?
    GROUP BY a.status
");
$stmt->bind_param("i", $owner_id);
$stmt->execute();
$counts_result = $stmt->get_result();
while ($count = $counts_result->fetch_assoc()) {
    $status_counts[$count['status']] = $count['count'];
    $status_counts['all'] += $count['count'];
}
$stmt->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Appointments - Pet Care Center</title>
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
                    <li><a href="appointments.php" class="active">Appointments</a></li>
                    <li><a href="boarding.php">Boarding</a></li>
                    <li><a href="bills.php">Billing</a></li>
                    <li><a href="profile.php">My Profile</a></li>
                    <li><a href="auth/logout.php">Logout</a></li>
                </ul>
            </nav>
        </div>
    </header>

    <main class="container py-4">
        <div class="row mb-4">
            <div class="col-md-8">
                <h2>My Appointments</h2>
                <p class="text-muted">Manage your pet care appointments.</p>
            </div>
            <div class="col-md-4 text-md-end">
                <a href="book_appointment.php" class="btn btn-primary">
                    <i class="bi bi-plus-circle"></i> Book New Appointment
                </a>
            </div>
        </div>

        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger">
                <ul class="mb-0">
                    <?php foreach ($errors as $error): ?>
                        <li><?php echo htmlspecialchars($error); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <?php if (!empty($success_message)): ?>
            <div class="alert alert-success">
                <?php echo htmlspecialchars($success_message); ?>
            </div>
        <?php endif; ?>

        <div class="row mb-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header bg-light">
                        <ul class="nav nav-tabs card-header-tabs">
                            <li class="nav-item">
                                <a class="nav-link <?php echo empty($filter_status) ? 'active' : ''; ?>" href="appointments.php">
                                    All <span class="badge bg-secondary"><?php echo $status_counts['all']; ?></span>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link <?php echo $filter_status === 'scheduled' ? 'active' : ''; ?>" href="appointments.php?status=scheduled">
                                    Scheduled <span class="badge bg-primary"><?php echo $status_counts['scheduled']; ?></span>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link <?php echo $filter_status === 'confirmed' ? 'active' : ''; ?>" href="appointments.php?status=confirmed">
                                    Confirmed <span class="badge bg-info"><?php echo $status_counts['confirmed']; ?></span>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link <?php echo $filter_status === 'completed' ? 'active' : ''; ?>" href="appointments.php?status=completed">
                                    Completed <span class="badge bg-success"><?php echo $status_counts['completed']; ?></span>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link <?php echo $filter_status === 'cancelled' ? 'active' : ''; ?>" href="appointments.php?status=cancelled">
                                    Cancelled <span class="badge bg-danger"><?php echo $status_counts['cancelled']; ?></span>
                                </a>
                            </li>
                        </ul>
                    </div>
                    <div class="card-body">
                        <form class="row g-3" method="get" action="">
                            <?php if (!empty($filter_status)): ?>
                                <input type="hidden" name="status" value="<?php echo htmlspecialchars($filter_status); ?>">
                            <?php endif; ?>
                            
                            <div class="col-md-4">
                                <label for="pet_id" class="form-label">Filter by Pet</label>
                                <select class="form-select" id="pet_id" name="pet_id">
                                    <option value="">All Pets</option>
                                    <?php foreach ($pets as $pet): ?>
                                        <option value="<?php echo $pet['pet_id']; ?>" <?php echo ($filter_pet == $pet['pet_id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($pet['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="col-md-4">
                                <label for="service_id" class="form-label">Filter by Service</label>
                                <select class="form-select" id="service_id" name="service_id">
                                    <option value="">All Services</option>
                                    <?php foreach ($services as $service): ?>
                                        <option value="<?php echo $service['service_id']; ?>" <?php echo ($filter_service == $service['service_id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($service['service_name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="col-md-4">
                                <label for="date_from" class="form-label">From Date</label>
                                <input type="date" class="form-control" id="date_from" name="date_from" value="<?php echo htmlspecialchars($filter_date_from); ?>">
                            </div>
                            
                            <div class="col-md-4">
                                <label for="date_to" class="form-label">To Date</label>
                                <input type="date" class="form-control" id="date_to" name="date_to" value="<?php echo htmlspecialchars($filter_date_to); ?>">
                            </div>
                            
                            <div class="col-md-4 d-flex align-items-end">
                                <button type="submit" class="btn btn-primary me-2">Apply Filters</button>
                                <a href="appointments.php<?php echo !empty($filter_status) ? '?status=' . urlencode($filter_status) : ''; ?>" class="btn btn-outline-secondary">Clear Filters</a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <?php if (count($appointments) > 0): ?>
            <div class="table-responsive">
                <table class="table table-striped table-hover">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Date & Time</th>
                            <th>Pet</th>
                            <th>Service</th>
                            <th>Status</th>
                            <th>Price</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($appointments as $appointment): ?>
                            <?php 
                                // Set row class based on date
                                $row_class = '';
                                $today = date('Y-m-d');
                                
                                if ($appointment['appointment_date'] == $today) {
                                    $row_class = 'table-info';
                                } elseif ($appointment['appointment_date'] < $today && $appointment['status'] == 'scheduled') {
                                    $row_class = 'table-warning';
                                }
                                
                                // Set status class
                                $status_class = '';
                                switch ($appointment['status']) {
                                    case 'scheduled':
                                        $status_class = 'bg-primary';
                                        break;
                                    case 'confirmed':
                                        $status_class = 'bg-info';
                                        break;
                                    case 'completed':
                                        $status_class = 'bg-success';
                                        break;
                                    case 'cancelled':
                                        $status_class = 'bg-danger';
                                        break;
                                }
                            ?>
                            <tr class="<?php echo $row_class; ?>">
                                <td><?php echo $appointment['appointment_id']; ?></td>
                                <td>
                                    <?php echo date('M d, Y', strtotime($appointment['appointment_date'])); ?><br>
                                    <small><?php echo date('h:i A', strtotime($appointment['appointment_time'])); ?></small>
                                </td>
                                <td>
                                    <?php echo htmlspecialchars($appointment['pet_name']); ?><br>
                                    <small class="text-muted"><?php echo htmlspecialchars($appointment['species_name']); ?></small>
                                </td>
                                <td><?php echo htmlspecialchars($appointment['service_name']); ?></td>
                                <td>
                                    <span class="badge <?php echo $status_class; ?>">
                                        <?php echo ucfirst($appointment['status']); ?>
                                    </span>
                                </td>
                                <td>Rs. <?php echo number_format($appointment['price'], 2); ?></td>
                                <td>
                                    <a href="view_appointment.php?id=<?php echo $appointment['appointment_id']; ?>" class="btn btn-sm btn-outline-primary">View</a>
                                    
                                    <?php if ($appointment['status'] == 'scheduled' || $appointment['status'] == 'confirmed'): ?>
                                        <?php if (strtotime($appointment['appointment_date']) > strtotime('today')): ?>
                                            <button type="button" class="btn btn-sm btn-outline-danger" 
                                                    onclick="confirmCancel(<?php echo $appointment['appointment_id']; ?>)">
                                                Cancel
                                            </button>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="alert alert-info">
                <p class="mb-0">No appointments found matching your criteria.</p>
            </div>
        <?php endif; ?>

        <div class="mt-4 card">
            <div class="card-header">
                <h3>Appointment Information</h3>
            </div>
            <div class="card-body">
                <h4>Appointment Status Meanings</h4>
                <ul>
                    <li><strong>Scheduled</strong> - Your appointment has been booked but not yet confirmed by our staff.</li>
                    <li><strong>Confirmed</strong> - Your appointment has been confirmed by our staff.</li>
                    <li><strong>Completed</strong> - Your appointment has been completed.</li>
                    <li><strong>Cancelled</strong> - The appointment has been cancelled.</li>
                </ul>
                
                <h4>Cancellation Policy</h4>
                <p>Please note the following regarding appointment cancellations:</p>
                <ul>
                    <li>Appointments must be cancelled at least 24 hours before the scheduled time to avoid cancellation fees.</li>
                    <li>Late cancellations (less than 24 hours before) may result in a 50% service fee charge.</li>
                    <li>No-shows will be charged the full service fee.</li>
                    <li>For emergencies or special circumstances, please contact our clinic directly at +94 123 456 789.</li>
                </ul>
            </div>
        </div>
    </main>

    <footer>
        <div class="container">
            <p>&copy; <?php echo date('Y'); ?> Pet Care Center - Ambilipitiya, Sri Lanka</p>
            <p>Contact: +94 123 456 789 | info@petcare.lk</p>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.5/dist/js/bootstrap.bundle.min.js" integrity="sha384-k6d4wzSIapyDyv1kpU366/PK5hCdSbCRGRCMv+eplOQJWyd1fbcAu9OCUj5zNLiq" crossorigin="anonymous"></script>

    <script>
    function confirmCancel(appointmentId) {
        if (confirm("Are you sure you want to cancel this appointment? This action cannot be undone.")) {
            window.location.href = "appointments.php?cancel=true&id=" + appointmentId;
        }
    }
    </script>
</body>
</html>