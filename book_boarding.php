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
}

// Get boarding rates
$stmt = $conn->prepare("
    SELECT * FROM services 
    WHERE service_name LIKE 'Boarding%' 
    AND status = 'active'
");
$stmt->execute();
$boarding_rates_result = $stmt->get_result();
$boarding_rates = [];
while ($rate = $boarding_rates_result->fetch_assoc()) {
    $boarding_rates[] = $rate;
}
$stmt->close();

// Process form submission
$message = '';
$error = '';
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate inputs
    $pet_id = $_POST['pet_id'] ?? 0;
    $check_in_date = $_POST['check_in_date'] ?? '';
    $check_out_date = $_POST['check_out_date'] ?? '';
    $special_instructions = $_POST['special_instructions'] ?? '';
    $daily_rate = $_POST['daily_rate'] ?? 0;
    
    // Basic validation
    if (empty($pet_id) || empty($check_in_date) || empty($check_out_date) || empty($daily_rate)) {
        $error = "All required fields must be filled out";
    } else {
        // Check if dates are valid
        $today = date('Y-m-d');
        if ($check_in_date < $today) {
            $error = "Check-in date cannot be in the past";
        } elseif ($check_out_date <= $check_in_date) {
            $error = "Check-out date must be after check-in date";
        } else {
            // Insert boarding record
            $stmt = $conn->prepare("
                INSERT INTO boarding 
                (pet_id, check_in_date, check_out_date, special_instructions, 
                status, daily_rate, owner_id) 
                VALUES (?, ?, ?, ?, 'booked', ?, ?)
            ");
            $stmt->bind_param("isssdi", $pet_id, $check_in_date, $check_out_date, 
                              $special_instructions, $daily_rate, $owner_id);
            
            if ($stmt->execute()) {
                $boarding_id = $conn->insert_id;
                $success = true;
                $message = "Boarding booked successfully!";
                
                // Calculate total days and amount
                $check_in = new DateTime($check_in_date);
                $check_out = new DateTime($check_out_date);
                $interval = $check_in->diff($check_out);
                $days = $interval->days;
                $total_amount = $daily_rate * $days;
                
                // Create a billing record
                $bill_date = date('Y-m-d');
                $stmt = $conn->prepare("
                    INSERT INTO billing 
                    (owner_id, bill_date, total_amount, payment_status, notes) 
                    VALUES (?, ?, ?, 'pending', ?)
                ");
                $notes = "Boarding for " . $days . " days from " . $check_in_date . " to " . $check_out_date;
                $stmt->bind_param("isds", $owner_id, $bill_date, $total_amount, $notes);
                $stmt->execute();
                $bill_id = $conn->insert_id;
                
                // Create bill detail
                $description = "Pet Boarding Service";
                $stmt = $conn->prepare("
                    INSERT INTO bill_details 
                    (bill_id, description, quantity, unit_price, subtotal) 
                    VALUES (?, ?, ?, ?, ?)
                ");
                $stmt->bind_param("isidd", $bill_id, $description, $days, $daily_rate, $total_amount);
                $stmt->execute();
                
                // Redirect to avoid form resubmission
                header("Location: boarding.php?success=1");
                exit();
            } else {
                $error = "Error booking boarding: " . $conn->error;
            }
            $stmt->close();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Book Pet Boarding - Pet Care Center</title>
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
            <h2>Book Pet Boarding</h2>
            <p>Reserve a comfortable stay for your pet at our boarding facility.</p>
        </section>

        <?php if ($error): ?>
            <div class="alert alert-danger">
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="alert alert-success">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <?php if (count($pets) === 0): ?>
            <div class="alert alert-warning">
                <p>You need to register at least one pet before booking boarding.</p>
                <a href="add_pet.php" class="btn btn-primary mt-2">Register a Pet</a>
            </div>
        <?php else: ?>
            <form method="post" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" class="booking-form">
                <div class="form-group">
                    <label for="pet_id">Select Pet:</label>
                    <select id="pet_id" name="pet_id" class="form-control" required>
                        <option value="">-- Select a Pet --</option>
                        <?php foreach ($pets as $pet): ?>
                            <option value="<?php echo $pet['pet_id']; ?>">
                                <?php echo htmlspecialchars($pet['name']) . ' (' . 
                                htmlspecialchars($pet['species_name']) . ' - ' . 
                                htmlspecialchars($pet['breed_name'] ?? 'Unknown Breed') . ')'; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-row">
                    <div class="form-group col-md-6">
                        <label for="check_in_date">Check-in Date:</label>
                        <input type="date" id="check_in_date" name="check_in_date" 
                               class="form-control" required min="<?php echo date('Y-m-d'); ?>">
                    </div>
                    <div class="form-group col-md-6">
                        <label for="check_out_date">Check-out Date:</label>
                        <input type="date" id="check_out_date" name="check_out_date" 
                               class="form-control" required min="<?php echo date('Y-m-d', strtotime('+1 day')); ?>">
                    </div>
                </div>

                <div class="form-group">
                    <label for="daily_rate">Boarding Rate:</label>
                    <select id="daily_rate" name="daily_rate" class="form-control" required>
                        <option value="">-- Select Rate --</option>
                        <?php foreach ($boarding_rates as $rate): ?>
                            <option value="<?php echo $rate['price']; ?>">
                                <?php echo htmlspecialchars($rate['service_name']) . 
                                ' - Rs. ' . number_format($rate['price'], 2) . ' per day'; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="special_instructions">Special Instructions:</label>
                    <textarea id="special_instructions" name="special_instructions" 
                              class="form-control" rows="4" 
                              placeholder="Please provide any special instructions or requirements for your pet's stay (feeding schedule, medication, etc.)"></textarea>
                </div>

                <div class="boarding-summary" id="boardingSummary">
                    <h4>Booking Summary</h4>
                    <p>Days: <span id="totalDays">0</span></p>
                    <p>Total Cost: Rs. <span id="totalCost">0.00</span></p>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">Book Boarding</button>
                    <a href="boarding.php" class="btn btn-secondary">Cancel</a>
                </div>
            </form>

            <div class="boarding-info mt-5">
                <h3>Boarding Information</h3>
                <div class="card mb-3">
                    <div class="card-header">What to Expect</div>
                    <div class="card-body">
                        <ul>
                            <li>We provide comfortable, clean, and safe accommodation for your pet.</li>
                            <li>Each pet receives daily attention and exercise.</li>
                            <li>Our staff monitors pets closely for any health or behavioral concerns.</li>
                            <li>You can bring your pet's favorite toys, bed, or blanket for their comfort.</li>
                        </ul>
                    </div>
                </div>
                <div class="card">
                    <div class="card-header">What to Bring</div>
                    <div class="card-body">
                        <ul>
                            <li>Your pet's regular food to avoid digestive issues.</li>
                            <li>Any medication your pet is currently taking with clear instructions.</li>
                            <li>Comfort items such as a favorite toy, blanket, or bed.</li>
                            <li>Updated vaccination records if not already on file.</li>
                        </ul>
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

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const checkInDate = document.getElementById('check_in_date');
            const checkOutDate = document.getElementById('check_out_date');
            const dailyRate = document.getElementById('daily_rate');
            const totalDays = document.getElementById('totalDays');
            const totalCost = document.getElementById('totalCost');
            
            function updateSummary() {
                if(checkInDate.value && checkOutDate.value && dailyRate.value) {
                    const startDate = new Date(checkInDate.value);
                    const endDate = new Date(checkOutDate.value);
                    
                    // Calculate difference in days
                    const timeDiff = endDate.getTime() - startDate.getTime();
                    const dayDiff = Math.ceil(timeDiff / (1000 * 3600 * 24));
                    
                    if(dayDiff > 0) {
                        totalDays.textContent = dayDiff;
                        const cost = dayDiff * parseFloat(dailyRate.value);
                        totalCost.textContent = cost.toFixed(2);
                    } else {
                        totalDays.textContent = '0';
                        totalCost.textContent = '0.00';
                    }
                }
            }
            
            // Set minimum check-out date when check-in date changes
            checkInDate.addEventListener('change', function() {
                if(checkInDate.value) {
                    const nextDay = new Date(checkInDate.value);
                    nextDay.setDate(nextDay.getDate() + 1);
                    const nextDayStr = nextDay.toISOString().split('T')[0];
                    checkOutDate.min = nextDayStr;
                    
                    // Reset check-out date if it's now invalid
                    if(checkOutDate.value && checkOutDate.value <= checkInDate.value) {
                        checkOutDate.value = nextDayStr;
                    }
                }
                updateSummary();
            });
            
            checkOutDate.addEventListener('change', updateSummary);
            dailyRate.addEventListener('change', updateSummary);
        });
    </script>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.5/dist/js/bootstrap.bundle.min.js" integrity="sha384-k6d4wzSIapyDyv1kpU366/PK5hCdSbCRGRCMv+eplOQJWyd1fbcAu9OCUj5zNLiq" crossorigin="anonymous"></script>
</body>
</html>