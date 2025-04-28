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

// Check if pet_id is provided
if (!isset($_GET['pet_id']) || empty($_GET['pet_id'])) {
    header("Location: pets.php");
    exit();
}

$pet_id = $_GET['pet_id'];
$user_id = $_SESSION['user_id'];

// Get owner information
$stmt = $conn->prepare("SELECT * FROM owners WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$owner_result = $stmt->get_result();
$owner = $owner_result->fetch_assoc();
$owner_id = $owner ? $owner['owner_id'] : null;
$stmt->close();

// Verify pet belongs to this owner
$stmt = $conn->prepare("
    SELECT p.*, s.species_name, b.breed_name 
    FROM pets p 
    LEFT JOIN species s ON p.species_id = s.species_id 
    LEFT JOIN breeds b ON p.breed_id = b.breed_id 
    WHERE p.pet_id = ? AND p.owner_id = ?
");
$stmt->bind_param("ii", $pet_id, $owner_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    // Pet not found or doesn't belong to this owner
    header("Location: pets.php");
    exit();
}

$pet = $result->fetch_assoc();
$stmt->close();

// Get vaccination history
$stmt = $conn->prepare("
    SELECT v.*, s.staff_name 
    FROM vaccinations v 
    LEFT JOIN staff s ON v.administered_by = s.staff_id 
    WHERE v.pet_id = ? 
    ORDER BY v.vaccination_date DESC
");
$stmt->bind_param("i", $pet_id);
$stmt->execute();
$vaccinations_result = $stmt->get_result();
$vaccinations = [];
while ($vaccination = $vaccinations_result->fetch_assoc()) {
    $vaccinations[] = $vaccination;
}
$stmt->close();

// Get medical records
$stmt = $conn->prepare("
    SELECT mr.*, s.staff_name 
    FROM medical_records mr 
    LEFT JOIN staff s ON mr.staff_id = s.staff_id 
    WHERE mr.pet_id = ? 
    ORDER BY mr.visit_date DESC
");
$stmt->bind_param("i", $pet_id);
$stmt->execute();
$medical_records_result = $stmt->get_result();
$medical_records = [];
while ($record = $medical_records_result->fetch_assoc()) {
    $medical_records[] = $record;
}
$stmt->close();

// Get upcoming appointments
$stmt = $conn->prepare("
    SELECT a.*, sv.service_name, s.staff_name 
    FROM appointments a 
    LEFT JOIN services sv ON a.service_id = sv.service_id 
    LEFT JOIN staff s ON a.staff_id = s.staff_id 
    WHERE a.pet_id = ? AND a.status IN ('scheduled', 'confirmed') 
    ORDER BY a.appointment_date ASC, a.appointment_time ASC
");
$stmt->bind_param("i", $pet_id);
$stmt->execute();
$upcoming_appointments_result = $stmt->get_result();
$upcoming_appointments = [];
while ($appointment = $upcoming_appointments_result->fetch_assoc()) {
    $upcoming_appointments[] = $appointment;
}
$stmt->close();

// Get past appointments
$stmt = $conn->prepare("
    SELECT a.*, sv.service_name, s.staff_name 
    FROM appointments a 
    LEFT JOIN services sv ON a.service_id = sv.service_id 
    LEFT JOIN staff s ON a.staff_id = s.staff_id 
    WHERE a.pet_id = ? AND a.status IN ('completed', 'cancelled') 
    ORDER BY a.appointment_date DESC, a.appointment_time DESC
");
$stmt->bind_param("i", $pet_id);
$stmt->execute();
$past_appointments_result = $stmt->get_result();
$past_appointments = [];
while ($appointment = $past_appointments_result->fetch_assoc()) {
    $past_appointments[] = $appointment;
}
$stmt->close();

// Get boarding history
$stmt = $conn->prepare("
    SELECT * FROM boarding 
    WHERE pet_id = ? 
    ORDER BY check_in_date DESC
");
$stmt->bind_param("i", $pet_id);
$stmt->execute();
$boarding_result = $stmt->get_result();
$boarding_history = [];
while ($boarding = $boarding_result->fetch_assoc()) {
    $boarding_history[] = $boarding;
}
$stmt->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pet['name']); ?> - Pet Care Center</title>
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
                    <li><a href="pets.php" class="active">My Pets</a></li>
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
        <div class="row mb-4">
            <div class="col">
                <a href="pets.php" class="btn btn-secondary mb-3"><i class="fas fa-arrow-left"></i> Back to Pets</a>
                <h2><?php echo htmlspecialchars($pet['name']); ?>'s Profile</h2>
            </div>
            <div class="col-auto">
                <a href="edit_pet.php?pet_id=<?php echo $pet_id; ?>" class="btn btn-primary">Edit Pet</a>
                <a href="book_appointment.php?pet_id=<?php echo $pet_id; ?>" class="btn btn-success">Book Appointment</a>
            </div>
        </div>

        <div class="row">
            <div class="col-md-4">
                <div class="card mb-4">
                    <div class="card-header">
                        <h3>Basic Information</h3>
                    </div>
                    <div class="card-body">
                        <dl class="row">
                            <dt class="col-sm-5">Name:</dt>
                            <dd class="col-sm-7"><?php echo htmlspecialchars($pet['name']); ?></dd>
                            
                            <dt class="col-sm-5">Species:</dt>
                            <dd class="col-sm-7"><?php echo htmlspecialchars($pet['species_name']); ?></dd>
                            
                            <?php if ($pet['breed_name']): ?>
                                <dt class="col-sm-5">Breed:</dt>
                                <dd class="col-sm-7"><?php echo htmlspecialchars($pet['breed_name']); ?></dd>
                            <?php endif; ?>
                            
                            <dt class="col-sm-5">Gender:</dt>
                            <dd class="col-sm-7"><?php echo ucfirst(htmlspecialchars($pet['gender'])); ?></dd>
                            
                            <?php if ($pet['date_of_birth']): ?>
                                <dt class="col-sm-5">Date of Birth:</dt>
                                <dd class="col-sm-7"><?php echo date('M d, Y', strtotime($pet['date_of_birth'])); ?></dd>
                                
                                <dt class="col-sm-5">Age:</dt>
                                <dd class="col-sm-7">
                                <?php 
                                    $dob = new DateTime($pet['date_of_birth']);
                                    $now = new DateTime();
                                    $diff = $dob->diff($now);
                                    
                                    if ($diff->y > 0) {
                                        echo $diff->y . ' year' . ($diff->y > 1 ? 's' : '');
                                        if ($diff->m > 0) {
                                            echo ', ' . $diff->m . ' month' . ($diff->m > 1 ? 's' : '');
                                        }
                                    } elseif ($diff->m > 0) {
                                        echo $diff->m . ' month' . ($diff->m > 1 ? 's' : '');
                                        if ($diff->d > 0) {
                                            echo ', ' . $diff->d . ' day' . ($diff->d > 1 ? 's' : '');
                                        }
                                    } else {
                                        echo $diff->d . ' day' . ($diff->d > 1 ? 's' : '');
                                    }
                                ?>
                                </dd>
                            <?php endif; ?>
                            
                            <?php if ($pet['weight']): ?>
                                <dt class="col-sm-5">Weight:</dt>
                                <dd class="col-sm-7"><?php echo htmlspecialchars($pet['weight']); ?> kg</dd>
                            <?php endif; ?>
                            
                            <?php if ($pet['color']): ?>
                                <dt class="col-sm-5">Color:</dt>
                                <dd class="col-sm-7"><?php echo htmlspecialchars($pet['color']); ?></dd>
                            <?php endif; ?>
                            
                            <?php if ($pet['microchip_number']): ?>
                                <dt class="col-sm-5">Microchip:</dt>
                                <dd class="col-sm-7"><?php echo htmlspecialchars($pet['microchip_number']); ?></dd>
                            <?php endif; ?>
                            
                            <dt class="col-sm-5">Registered:</dt>
                            <dd class="col-sm-7"><?php echo date('M d, Y', strtotime($pet['created_at'])); ?></dd>
                        </dl>
                    </div>
                </div>

                <?php if ($pet['special_notes']): ?>
                <div class="card mb-4">
                    <div class="card-header">
                        <h3>Special Notes</h3>
                    </div>
                    <div class="card-body">
                        <p><?php echo nl2br(htmlspecialchars($pet['special_notes'])); ?></p>
                    </div>
                </div>
                <?php endif; ?>
            </div>

            <div class="col-md-8">
                <!-- Vaccination History -->
                <div class="card mb-4">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h3>Vaccination History</h3>
                    </div>
                    <div class="card-body">
                        <?php if (count($vaccinations) > 0): ?>
                            <div class="table-responsive">
                                <table class="table table-striped">
                                    <thead>
                                        <tr>
                                            <th>Vaccine</th>
                                            <th>Date</th>
                                            <th>Expiry</th>
                                            <th>Administered By</th>
                                            <th>Notes</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($vaccinations as $vaccination): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($vaccination['vaccine_name']); ?></td>
                                                <td><?php echo date('M d, Y', strtotime($vaccination['vaccination_date'])); ?></td>
                                                <td>
                                                    <?php 
                                                    if ($vaccination['expiry_date']) {
                                                        echo date('M d, Y', strtotime($vaccination['expiry_date']));
                                                        
                                                        // Check if vaccine is expired
                                                        $expiry = new DateTime($vaccination['expiry_date']);
                                                        $now = new DateTime();
                                                        if ($expiry < $now) {
                                                            echo ' <span class="badge bg-danger">Expired</span>';
                                                        } elseif ($expiry < $now->modify('+30 days')) {
                                                            echo ' <span class="badge bg-warning">Expiring Soon</span>';
                                                        }
                                                    } else {
                                                        echo 'N/A';
                                                    }
                                                    ?>
                                                </td>
                                                <td><?php echo $vaccination['staff_name'] ? htmlspecialchars($vaccination['staff_name']) : 'N/A'; ?></td>
                                                <td><?php echo $vaccination['notes'] ? htmlspecialchars($vaccination['notes']) : ''; ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <p class="text-muted">No vaccination records found.</p>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Medical Records -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h3>Medical Records</h3>
                    </div>
                    <div class="card-body">
                        <?php if (count($medical_records) > 0): ?>
                            <div class="accordion" id="medicalRecordsAccordion">
                                <?php foreach ($medical_records as $index => $record): ?>
                                    <div class="accordion-item">
                                        <h2 class="accordion-header" id="heading<?php echo $index; ?>">
                                            <button class="accordion-button <?php echo ($index > 0) ? 'collapsed' : ''; ?>" type="button" data-bs-toggle="collapse" data-bs-target="#collapse<?php echo $index; ?>" aria-expanded="<?php echo ($index === 0) ? 'true' : 'false'; ?>" aria-controls="collapse<?php echo $index; ?>">
                                                <strong><?php echo date('M d, Y', strtotime($record['visit_date'])); ?></strong>: <?php echo substr(strip_tags($record['diagnosis']), 0, 50); ?>...
                                            </button>
                                        </h2>
                                        <div id="collapse<?php echo $index; ?>" class="accordion-collapse collapse <?php echo ($index === 0) ? 'show' : ''; ?>" aria-labelledby="heading<?php echo $index; ?>" data-bs-parent="#medicalRecordsAccordion">
                                            <div class="accordion-body">
                                                <dl class="row">
                                                    <dt class="col-sm-3">Visit Date:</dt>
                                                    <dd class="col-sm-9"><?php echo date('M d, Y', strtotime($record['visit_date'])); ?></dd>
                                                    
                                                    <dt class="col-sm-3">Veterinarian:</dt>
                                                    <dd class="col-sm-9"><?php echo $record['staff_name'] ? htmlspecialchars($record['staff_name']) : 'N/A'; ?></dd>
                                                    
                                                    <dt class="col-sm-3">Diagnosis:</dt>
                                                    <dd class="col-sm-9"><?php echo nl2br(htmlspecialchars($record['diagnosis'])); ?></dd>
                                                    
                                                    <?php if ($record['treatment']): ?>
                                                        <dt class="col-sm-3">Treatment:</dt>
                                                        <dd class="col-sm-9"><?php echo nl2br(htmlspecialchars($record['treatment'])); ?></dd>
                                                    <?php endif; ?>
                                                    
                                                    <?php if ($record['prescription']): ?>
                                                        <dt class="col-sm-3">Prescription:</dt>
                                                        <dd class="col-sm-9"><?php echo nl2br(htmlspecialchars($record['prescription'])); ?></dd>
                                                    <?php endif; ?>
                                                    
                                                    <?php if ($record['follow_up_date']): ?>
                                                        <dt class="col-sm-3">Follow-up:</dt>
                                                        <dd class="col-sm-9">
                                                            <?php echo date('M d, Y', strtotime($record['follow_up_date'])); ?>
                                                            <?php 
                                                            $follow_up = new DateTime($record['follow_up_date']);
                                                            $now = new DateTime();
                                                            if ($follow_up > $now) {
                                                                echo ' <span class="badge bg-primary">Upcoming</span>';
                                                            }
                                                            ?>
                                                        </dd>
                                                    <?php endif; ?>
                                                    
                                                    <?php if ($record['notes']): ?>
                                                        <dt class="col-sm-3">Notes:</dt>
                                                        <dd class="col-sm-9"><?php echo nl2br(htmlspecialchars($record['notes'])); ?></dd>
                                                    <?php endif; ?>
                                                </dl>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <p class="text-muted">No medical records found.</p>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Upcoming Appointments -->
                <div class="card mb-4">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h3>Upcoming Appointments</h3>
                        <a href="book_appointment.php?pet_id=<?php echo $pet_id; ?>" class="btn btn-sm btn-primary">Book New</a>
                    </div>
                    <div class="card-body">
                        <?php if (count($upcoming_appointments) > 0): ?>
                            <div class="table-responsive">
                                <table class="table table-striped">
                                    <thead>
                                        <tr>
                                            <th>Date & Time</th>
                                            <th>Service</th>
                                            <th>Staff</th>
                                            <th>Status</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($upcoming_appointments as $appointment): ?>
                                            <tr>
                                                <td>
                                                    <?php echo date('M d, Y', strtotime($appointment['appointment_date'])); ?>
                                                    <br>
                                                    <?php echo date('h:i A', strtotime($appointment['appointment_time'])); ?>
                                                </td>
                                                <td><?php echo htmlspecialchars($appointment['service_name']); ?></td>
                                                <td><?php echo $appointment['staff_name'] ? htmlspecialchars($appointment['staff_name']) : 'Not assigned'; ?></td>
                                                <td>
                                                    <?php 
                                                    switch ($appointment['status']) {
                                                        case 'scheduled':
                                                            echo '<span class="badge bg-info">Scheduled</span>';
                                                            break;
                                                        case 'confirmed':
                                                            echo '<span class="badge bg-success">Confirmed</span>';
                                                            break;
                                                    }
                                                    ?>
                                                </td>
                                                <td>
                                                    <a href="view_appointment.php?id=<?php echo $appointment['appointment_id']; ?>" class="btn btn-sm btn-info">View</a>
                                                    <a href="appointments.php?action=cancel&id=<?php echo $appointment['appointment_id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure you want to cancel this appointment?')">Cancel</a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <p class="text-muted">No upcoming appointments.</p>
                            <a href="book_appointment.php?pet_id=<?php echo $pet_id; ?>" class="btn btn-primary">Book an Appointment</a>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Past Appointments -->
                <?php if (count($past_appointments) > 0): ?>
                <div class="card mb-4">
                    <div class="card-header">
                        <h3>Past Appointments</h3>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Date & Time</th>
                                        <th>Service</th>
                                        <th>Staff</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($past_appointments as $appointment): ?>
                                        <tr>
                                            <td>
                                                <?php echo date('M d, Y', strtotime($appointment['appointment_date'])); ?>
                                                <br>
                                                <?php echo date('h:i A', strtotime($appointment['appointment_time'])); ?>
                                            </td>
                                            <td><?php echo htmlspecialchars($appointment['service_name']); ?></td>
                                            <td><?php echo $appointment['staff_name'] ? htmlspecialchars($appointment['staff_name']) : 'Not assigned'; ?></td>
                                            <td>
                                                <?php 
                                                switch ($appointment['status']) {
                                                    case 'completed':
                                                        echo '<span class="badge bg-success">Completed</span>';
                                                        break;
                                                    case 'cancelled':
                                                        echo '<span class="badge bg-danger">Cancelled</span>';
                                                        break;
                                                }
                                                ?>
                                            </td>
                                            <td>
                                                <a href="view_appointment.php?id=<?php echo $appointment['appointment_id']; ?>" class="btn btn-sm btn-info">View</a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Boarding History -->
                <?php if (count($boarding_history) > 0): ?>
                <div class="card mb-4">
                    <div class="card-header">
                        <h3>Boarding History</h3>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Check-in</th>
                                        <th>Check-out</th>
                                        <th>Cage/Kennel</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($boarding_history as $boarding): ?>
                                        <tr>
                                            <td><?php echo date('M d, Y', strtotime($boarding['check_in_date'])); ?></td>
                                            <td><?php echo date('M d, Y', strtotime($boarding['check_out_date'])); ?></td>
                                            <td><?php echo htmlspecialchars($boarding['cage_number']); ?></td>
                                            <td>
                                                <?php 
                                                switch ($boarding['status']) {
                                                    case 'booked':
                                                        echo '<span class="badge bg-info">Booked</span>';
                                                        break;
                                                    case 'checked_in':
                                                        echo '<span class="badge bg-primary">Checked In</span>';
                                                        break;
                                                    case 'checked_out':
                                                        echo '<span class="badge bg-success">Completed</span>';
                                                        break;
                                                    case 'cancelled':
                                                        echo '<span class="badge bg-danger">Cancelled</span>';
                                                        break;
                                                }
                                                ?>
                                            </td>
                                            <td>
                                                <a href="view_boarding.php?id=<?php echo $boarding['boarding_id']; ?>" class="btn btn-sm btn-info">View</a>
                                                <?php if ($boarding['status'] == 'booked'): ?>
                                                    <a href="boarding.php?action=cancel&id=<?php echo $boarding['boarding_id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure you want to cancel this boarding?')">Cancel</a>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
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
</body>
</html>