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

// Get appointment ID from GET parameter
$appointment_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($appointment_id <= 0) {
    header("Location: appointments.php");
    exit();
}

// Get appointment details and verify it belongs to this owner's pet
$stmt = $conn->prepare("
    SELECT a.*, p.name as pet_name, p.species_id, p.breed_id, p.gender, 
           s.service_name, s.description as service_description, s.price, s.duration,
           st.staff_name, st.position
    FROM appointments a
    JOIN pets p ON a.pet_id = p.pet_id
    JOIN services s ON a.service_id = s.service_id
    LEFT JOIN staff st ON a.staff_id = st.staff_id
    WHERE a.appointment_id = ? AND p.owner_id = ?
");
$stmt->bind_param("ii", $appointment_id, $owner_id);
$stmt->execute();
$appointment_result = $stmt->get_result();

if ($appointment_result->num_rows === 0) {
    // Appointment not found or doesn't belong to this owner's pet
    header("Location: appointments.php");
    exit();
}

$appointment = $appointment_result->fetch_assoc();
$stmt->close();

// Get species and breed information
$species_name = "";
$breed_name = "";

if ($appointment['species_id']) {
    $stmt = $conn->prepare("SELECT species_name FROM species WHERE species_id = ?");
    $stmt->bind_param("i", $appointment['species_id']);
    $stmt->execute();
    $species_result = $stmt->get_result();
    if ($species_row = $species_result->fetch_assoc()) {
        $species_name = $species_row['species_name'];
    }
    $stmt->close();
}

if ($appointment['breed_id']) {
    $stmt = $conn->prepare("SELECT breed_name FROM breeds WHERE breed_id = ?");
    $stmt->bind_param("i", $appointment['breed_id']);
    $stmt->execute();
    $breed_result = $stmt->get_result();
    if ($breed_row = $breed_result->fetch_assoc()) {
        $breed_name = $breed_row['breed_name'];
    }
    $stmt->close();
}

// Check if there's a billing record for this appointment
$bill_details = null;
$stmt = $conn->prepare("
    SELECT b.*, bd.detail_id
    FROM billing b
    LEFT JOIN bill_details bd ON b.bill_id = bd.bill_id
    WHERE b.appointment_id = ? AND b.owner_id = ?
    LIMIT 1
");
$stmt->bind_param("ii", $appointment_id, $owner_id);
$stmt->execute();
$bill_result = $stmt->get_result();
if ($bill_result->num_rows > 0) {
    $bill_details = $bill_result->fetch_assoc();
}
$stmt->close();

// Process cancellation request
if (isset($_POST['cancel_appointment']) && $_POST['cancel_appointment'] == 'yes') {
    // Check if appointment can be cancelled (not completed or already cancelled)
    if ($appointment['status'] !== 'completed' && $appointment['status'] !== 'cancelled') {
        $stmt = $conn->prepare("UPDATE appointments SET status = 'cancelled', updated_at = CURRENT_TIMESTAMP WHERE appointment_id = ? AND pet_id IN (SELECT pet_id FROM pets WHERE owner_id = ?)");
        $stmt->bind_param("ii", $appointment_id, $owner_id);
        
        if ($stmt->execute()) {
            $success_message = "Appointment cancelled successfully.";
            
            // Refresh appointment data
            $appointment['status'] = 'cancelled';
        } else {
            $errors[] = "Failed to cancel appointment. Please try again.";
        }
        
        $stmt->close();
    } else {
        $errors[] = "This appointment cannot be cancelled.";
    }
}

// Format appointment date and time for display
$appointment_date = date('F j, Y', strtotime($appointment['appointment_date']));
$appointment_time = date('g:i A', strtotime($appointment['appointment_time']));
$appointment_status_class = "";

// Set status class for styling
switch($appointment['status']) {
    case 'scheduled':
        $appointment_status_class = "text-primary";
        break;
    case 'confirmed':
        $appointment_status_class = "text-info";
        break;
    case 'completed':
        $appointment_status_class = "text-success";
        break;
    case 'cancelled':
        $appointment_status_class = "text-danger";
        break;
    default:
        $appointment_status_class = "text-secondary";
}

// Check for medical records linked to this appointment
$medical_record = null;
$stmt = $conn->prepare("
    SELECT * FROM medical_records 
    WHERE pet_id = ? AND visit_date = ?
    ORDER BY created_at DESC
    LIMIT 1
");
$stmt->bind_param("is", $appointment['pet_id'], $appointment['appointment_date']);
$stmt->execute();
$medical_result = $stmt->get_result();
if ($medical_result->num_rows > 0) {
    $medical_record = $medical_result->fetch_assoc();
}
$stmt->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Appointment - Pet Care Center</title>
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
                <h2>Appointment Details</h2>
                <p class="text-muted">Review your appointment information below.</p>
            </div>
            <div class="col-md-4 text-md-end">
                <a href="appointments.php" class="btn btn-outline-secondary">
                    <i class="bi bi-arrow-left"></i> Back to Appointments
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

        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h3 class="mb-0">Appointment #<?php echo $appointment_id; ?></h3>
                <span class="badge <?php echo $appointment_status_class; ?> bg-opacity-10 border border-<?php echo str_replace('text-', '', $appointment_status_class); ?> text-<?php echo str_replace('text-', '', $appointment_status_class); ?> px-3 py-2">
                    <?php echo ucfirst($appointment['status']); ?>
                </span>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <h4>Appointment Information</h4>
                        <div class="mb-3">
                            <p><strong>Date:</strong> <?php echo $appointment_date; ?></p>
                            <p><strong>Time:</strong> <?php echo $appointment_time; ?></p>
                            <p><strong>Service:</strong> <?php echo htmlspecialchars($appointment['service_name']); ?></p>
                            <p><strong>Duration:</strong> <?php echo $appointment['duration']; ?> minutes</p>
                            <p><strong>Price:</strong> Rs. <?php echo number_format($appointment['price'], 2); ?></p>
                            <?php if (!empty($appointment['staff_name'])): ?>
                                <p><strong>Assigned Staff:</strong> <?php echo htmlspecialchars($appointment['staff_name']); ?> (<?php echo htmlspecialchars($appointment['position']); ?>)</p>
                            <?php else: ?>
                                <p><strong>Assigned Staff:</strong> <span class="text-muted">Not assigned yet</span></p>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <h4>Pet Information</h4>
                        <div class="mb-3">
                            <p><strong>Pet Name:</strong> <?php echo htmlspecialchars($appointment['pet_name']); ?></p>
                            <p><strong>Species:</strong> <?php echo htmlspecialchars($species_name); ?></p>
                            <?php if (!empty($breed_name)): ?>
                                <p><strong>Breed:</strong> <?php echo htmlspecialchars($breed_name); ?></p>
                            <?php endif; ?>
                            <p><strong>Gender:</strong> <?php echo ucfirst($appointment['gender']); ?></p>
                        </div>
                    </div>
                </div>

                <?php if (!empty($appointment['notes'])): ?>
                    <div class="mt-3">
                        <h4>Notes</h4>
                        <div class="card bg-light">
                            <div class="card-body">
                                <p><?php echo nl2br(htmlspecialchars($appointment['notes'])); ?></p>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if ($medical_record): ?>
                    <div class="mt-4">
                        <h4>Medical Record</h4>
                        <div class="card bg-light">
                            <div class="card-body">
                                <p><strong>Diagnosis:</strong> <?php echo nl2br(htmlspecialchars($medical_record['diagnosis'])); ?></p>
                                
                                <?php if (!empty($medical_record['treatment'])): ?>
                                    <p><strong>Treatment:</strong> <?php echo nl2br(htmlspecialchars($medical_record['treatment'])); ?></p>
                                <?php endif; ?>
                                
                                <?php if (!empty($medical_record['prescription'])): ?>
                                    <p><strong>Prescription:</strong> <?php echo nl2br(htmlspecialchars($medical_record['prescription'])); ?></p>
                                <?php endif; ?>
                                
                                <?php if (!empty($medical_record['follow_up_date'])): ?>
                                    <p><strong>Follow-up Date:</strong> <?php echo date('F j, Y', strtotime($medical_record['follow_up_date'])); ?></p>
                                <?php endif; ?>
                                
                                <?php if (!empty($medical_record['notes'])): ?>
                                    <p><strong>Additional Notes:</strong> <?php echo nl2br(htmlspecialchars($medical_record['notes'])); ?></p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
            <div class="card-footer">
                <div class="d-flex justify-content-between">
                    <div>
                        <?php if ($appointment['status'] === 'scheduled' || $appointment['status'] === 'confirmed'): ?>
                            <form method="post" action="" id="cancelForm" class="d-inline">
                                <input type="hidden" name="cancel_appointment" value="yes">
                                <button type="button" class="btn btn-outline-danger" onclick="confirmCancel()">
                                    Cancel Appointment
                                </button>
                            </form>
                        <?php endif; ?>
                    </div>
                    <div>
                        <?php if ($bill_details): ?>
                            <a href="view_bill.php?id=<?php echo $bill_details['bill_id']; ?>" class="btn btn-outline-primary">
                                View Bill
                            </a>
                        <?php endif; ?>
                        
                        <a href="book_appointment.php?pet_id=<?php echo $appointment['pet_id']; ?>" class="btn btn-primary">
                            Book Another Appointment
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <?php if ($appointment['status'] === 'scheduled'): ?>
            <div class="card mb-4">
                <div class="card-header">
                    <h3>Cancellation Policy</h3>
                </div>
                <div class="card-body">
                    <p>Please note the following regarding appointment cancellations:</p>
                    <ul>
                        <li>Appointments must be cancelled at least 24 hours before the scheduled time to avoid cancellation fees.</li>
                        <li>Late cancellations (less than 24 hours before) may result in a 50% service fee charge.</li>
                        <li>No-shows will be charged the full service fee.</li>
                        <li>For emergencies or special circumstances, please contact our clinic directly at +94 123 456 789.</li>
                    </ul>
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

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.5/dist/js/bootstrap.bundle.min.js" integrity="sha384-k6d4wzSIapyDyv1kpU366/PK5hCdSbCRGRCMv+eplOQJWyd1fbcAu9OCUj5zNLiq" crossorigin="anonymous"></script>

    <script>
    function confirmCancel() {
        if (confirm("Are you sure you want to cancel this appointment? This action cannot be undone.")) {
            document.getElementById('cancelForm').submit();
        }
    }
    </script>
</body>
</html>