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

// Get pet ID from GET parameter if available
$pet_id = isset($_GET['pet_id']) ? intval($_GET['pet_id']) : 0;

// If pet_id is provided, verify it belongs to this owner
if ($pet_id > 0) {
    $stmt = $conn->prepare("SELECT * FROM pets WHERE pet_id = ? AND owner_id = ?");
    $stmt->bind_param("ii", $pet_id, $owner_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        // Pet not found or doesn't belong to this owner
        header("Location: pets.php");
        exit();
    }
    
    $selected_pet = $result->fetch_assoc();
    $stmt->close();
}

// Get all pets for this owner
$stmt = $conn->prepare("SELECT * FROM pets WHERE owner_id = ? ORDER BY name");
$stmt->bind_param("i", $owner_id);
$stmt->execute();
$pets_result = $stmt->get_result();
$pets = [];
while ($pet = $pets_result->fetch_assoc()) {
    $pets[] = $pet;
}
$stmt->close();

// Get available services
$stmt = $conn->prepare("SELECT * FROM services WHERE status = 'active' ORDER BY service_name");
$stmt->execute();
$services_result = $stmt->get_result();
$services = [];
while ($service = $services_result->fetch_assoc()) {
    $services[] = $service;
}
$stmt->close();

// Process form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Validate input
    $pet_id = isset($_POST['pet_id']) ? intval($_POST['pet_id']) : 0;
    $service_id = isset($_POST['service_id']) ? intval($_POST['service_id']) : 0;
    $appointment_date = isset($_POST['appointment_date']) ? $_POST['appointment_date'] : '';
    $appointment_time = isset($_POST['appointment_time']) ? $_POST['appointment_time'] : '';
    $notes = isset($_POST['notes']) ? trim($_POST['notes']) : '';
    
    // Check if pet belongs to the owner
    if ($pet_id <= 0) {
        $errors[] = "Please select a pet.";
    } else {
        $stmt = $conn->prepare("SELECT * FROM pets WHERE pet_id = ? AND owner_id = ?");
        $stmt->bind_param("ii", $pet_id, $owner_id);
        $stmt->execute();
        if ($stmt->get_result()->num_rows === 0) {
            $errors[] = "Invalid pet selection.";
        }
        $stmt->close();
    }
    
    // Check if service exists and is active
    if ($service_id <= 0) {
        $errors[] = "Please select a service.";
    } else {
        $stmt = $conn->prepare("SELECT * FROM services WHERE service_id = ? AND status = 'active'");
        $stmt->bind_param("i", $service_id);
        $stmt->execute();
        if ($stmt->get_result()->num_rows === 0) {
            $errors[] = "Invalid service selection.";
        }
        $stmt->close();
    }
    
    // Validate date (must be today or in the future)
    if (empty($appointment_date)) {
        $errors[] = "Please select an appointment date.";
    } else {
        $today = new DateTime();
        $today->setTime(0, 0, 0); // Set to beginning of day
        $appointmentDateTime = new DateTime($appointment_date);
        $appointmentDateTime->setTime(0, 0, 0);
        
        if ($appointmentDateTime < $today) {
            $errors[] = "Appointment date cannot be in the past.";
        }
    }
    
    // Validate time (must be a valid time format)
    if (empty($appointment_time)) {
        $errors[] = "Please select an appointment time.";
    } elseif (!preg_match('/^([0-1][0-9]|2[0-3]):[0-5][0-9]$/', $appointment_time)) {
        $errors[] = "Invalid time format. Please use HH:MM format.";
    }
    
    // Check business hours (assuming 8 AM to 6 PM)
    $time_parts = explode(':', $appointment_time);
    $hour = intval($time_parts[0]);
    $minute = intval($time_parts[1]);
    
    if ($hour < 8 || ($hour == 18 && $minute > 0) || $hour > 18) {
        $errors[] = "Appointments are only available between 8:00 AM and 6:00 PM.";
    }
    
    // If no errors, insert the appointment
    if (empty($errors)) {
        $status = 'scheduled'; // Default status
        
        // Begin transaction
        $conn->begin_transaction();
        
        try {
            // Insert appointment
            $stmt = $conn->prepare("
                INSERT INTO appointments (pet_id, service_id, appointment_date, appointment_time, status, notes) 
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->bind_param("iissss", $pet_id, $service_id, $appointment_date, $appointment_time, $status, $notes);
            $stmt->execute();
            $appointment_id = $conn->insert_id;
            $stmt->close();
            
            // Commit transaction
            $conn->commit();
            
            $success_message = "Appointment booked successfully!";
            
            // Redirect after short delay
            header("refresh:2;url=view_appointment.php?id=" . $appointment_id);
            
        } catch (Exception $e) {
            // Rollback on error
            $conn->rollback();
            $errors[] = "Database error: " . $e->getMessage();
        }
    }
}

// Calculate the earliest possible date (today)
$min_date = date('Y-m-d');

// Calculate the furthest bookable date (e.g., 3 months from now)
$max_date = date('Y-m-d', strtotime('+3 months'));
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Book Appointment - Pet Care Center</title>
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
            <div class="col">
                <h2>Book an Appointment</h2>
                <p class="text-muted">Schedule a visit for your pet at our care center.</p>
            </div>
        </div>

        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger">
                <h4>Please correct the following errors:</h4>
                <ul>
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
            <div class="card-body">
                <form method="post" action="">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <div class="form-group mb-3">
                                <label for="pet_id" class="form-label">Select Pet <span class="text-danger">*</span></label>
                                <select class="form-select" id="pet_id" name="pet_id" required>
                                    <option value="">-- Select a Pet --</option>
                                    <?php foreach ($pets as $pet): ?>
                                        <option value="<?php echo $pet['pet_id']; ?>" <?php echo ($pet_id == $pet['pet_id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($pet['name']); ?> 
                                            (<?php echo htmlspecialchars($pet['species_id'] == 1 ? 'Dog' : ($pet['species_id'] == 2 ? 'Cat' : 'Other')); ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <?php if (empty($pets)): ?>
                                    <div class="text-danger mt-2">
                                        You don't have any registered pets. 
                                        <a href="add_pet.php">Register a pet first</a> before booking an appointment.
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="form-group mb-3">
                                <label for="service_id" class="form-label">Select Service <span class="text-danger">*</span></label>
                                <select class="form-select" id="service_id" name="service_id" required>
                                    <option value="">-- Select a Service --</option>
                                    <?php foreach ($services as $service): ?>
                                        <option value="<?php echo $service['service_id']; ?>" data-price="<?php echo $service['price']; ?>" data-duration="<?php echo $service['duration']; ?>">
                                            <?php echo htmlspecialchars($service['service_name']); ?> 
                                            (Rs. <?php echo number_format($service['price'], 2); ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <div id="service-details" class="mt-2" style="display: none;">
                                    <div class="card bg-light">
                                        <div class="card-body">
                                            <h5 class="service-name"></h5>
                                            <p><strong>Price:</strong> Rs. <span class="service-price"></span></p>
                                            <p><strong>Duration:</strong> <span class="service-duration"></span> minutes</p>
                                            <p class="service-description"></p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="form-group mb-3">
                                <label for="appointment_date" class="form-label">Appointment Date <span class="text-danger">*</span></label>
                                <input type="date" class="form-control" id="appointment_date" name="appointment_date" 
                                       min="<?php echo $min_date; ?>" max="<?php echo $max_date; ?>" required>
                                <small class="text-muted">Appointments can be booked up to 3 months in advance.</small>
                            </div>
                            
                            <div class="form-group mb-3">
                                <label for="appointment_time" class="form-label">Appointment Time <span class="text-danger">*</span></label>
                                <select class="form-select" id="appointment_time" name="appointment_time" required>
                                    <option value="">-- Select a Time --</option>
                                    <?php
                                    // Generate time slots from 8:00 AM to 6:00 PM in 30-minute intervals
                                    $start = 8 * 60; // 8:00 AM in minutes
                                    $end = 18 * 60; // 6:00 PM in minutes
                                    $interval = 30; // 30 minutes
                                    
                                    for ($time = $start; $time < $end; $time += $interval) {
                                        $hour = floor($time / 60);
                                        $minute = $time % 60;
                                        
                                        $formattedTime = sprintf("%02d:%02d", $hour, $minute);
                                        $displayTime = date("g:i A", strtotime($formattedTime));
                                        
                                        echo "<option value=\"$formattedTime\">$displayTime</option>";
                                    }
                                    ?>
                                </select>
                                <small class="text-muted">Appointments available from 8:00 AM to 6:00 PM daily.</small>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-group mb-3">
                        <label for="notes" class="form-label">Special Notes or Requirements</label>
                        <textarea class="form-control" id="notes" name="notes" rows="3" placeholder="Any special requirements or information the veterinarian should know about your pet for this visit."><?php echo isset($_POST['notes']) ? htmlspecialchars($_POST['notes']) : ''; ?></textarea>
                    </div>
                    
                    <div class="mt-4">
                        <button type="submit" class="btn btn-primary" <?php echo empty($pets) ? 'disabled' : ''; ?>>Book Appointment</button>
                        <a href="<?php echo isset($_GET['pet_id']) ? 'pet_detail.php?pet_id=' . intval($_GET['pet_id']) : 'appointments.php'; ?>" class="btn btn-secondary">Cancel</a>
                    </div>
                </form>
            </div>
        </div>

        <div class="card mb-4">
            <div class="card-header">
                <h3>Appointment Policies</h3>
            </div>
            <div class="card-body">
                <ul>
                    <li>Please arrive 10-15 minutes before your scheduled appointment time.</li>
                    <li>If you need to cancel, please do so at least 24 hours in advance.</li>
                    <li>For urgent cases, please call our clinic directly at +94 123 456 789.</li>
                    <li>Payment is required at the time of service.</li>
                    <li>Please bring any previous medical records for your pet if this is your first visit.</li>
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
    document.addEventListener('DOMContentLoaded', function() {
        // Handle service selection to show details
        const serviceSelect = document.getElementById('service_id');
        const serviceDetails = document.getElementById('service-details');
        const serviceName = document.querySelector('.service-name');
        const servicePrice = document.querySelector('.service-price');
        const serviceDuration = document.querySelector('.service-duration');
        
        serviceSelect.addEventListener('change', function() {
            if (this.value) {
                const selectedOption = this.options[this.selectedIndex];
                const price = selectedOption.getAttribute('data-price');
                const duration = selectedOption.getAttribute('data-duration');
                
                serviceName.textContent = selectedOption.textContent.split('(')[0].trim();
                servicePrice.textContent = parseFloat(price).toLocaleString('en-LK', {minimumFractionDigits: 2});
                serviceDuration.textContent = duration;
                
                serviceDetails.style.display = 'block';
            } else {
                serviceDetails.style.display = 'none';
            }
        });
        
        // Trigger change event if a service is already selected
        if (serviceSelect.value) {
            serviceSelect.dispatchEvent(new Event('change'));
        }
        
        // Validate date selection to disable weekends if needed
        // Uncomment if you want to disable weekends
        /*
        const dateInput = document.getElementById('appointment_date');
        dateInput.addEventListener('input', function() {
            const selectedDate = new Date(this.value);
            const dayOfWeek = selectedDate.getUTCDay();
            
            // 0 is Sunday, 6 is Saturday
            if (dayOfWeek === 0 || dayOfWeek === 6) {
                alert('Appointments are not available on weekends. Please select a weekday.');
                this.value = '';
            }
        });
        */
    });
    </script>
</body>
</html>