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

// If owner doesn't exist for this user, redirect to create owner profile
if (!$owner_id) {
    header("Location: create_owner_profile.php");
    exit();
}

// Initialize variables
$errors = [];
$success_message = "";
$pet_name = "";
$species_id = "";
$breed_id = "";
$gender = "";
$date_of_birth = "";
$weight = "";
$color = "";
$microchip_number = "";
$special_notes = "";

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate and sanitize input
    $pet_name = trim($_POST['pet_name']);
    $species_id = isset($_POST['species_id']) ? (int)$_POST['species_id'] : 0;
    $breed_id = isset($_POST['breed_id']) && !empty($_POST['breed_id']) ? (int)$_POST['breed_id'] : null;
    $gender = isset($_POST['gender']) ? trim($_POST['gender']) : "";
    $date_of_birth = !empty($_POST['date_of_birth']) ? trim($_POST['date_of_birth']) : null;
    $weight = !empty($_POST['weight']) ? trim($_POST['weight']) : null;
    $color = !empty($_POST['color']) ? trim($_POST['color']) : null;
    $microchip_number = !empty($_POST['microchip_number']) ? trim($_POST['microchip_number']) : null;
    $special_notes = !empty($_POST['special_notes']) ? trim($_POST['special_notes']) : null;
    
    // Validation
    if (empty($pet_name)) {
        $errors[] = "Pet name is required.";
    }
    
    if ($species_id <= 0) {
        $errors[] = "Please select a species.";
    }
    
    if (empty($gender)) {
        $errors[] = "Please select a gender.";
    }
    
    if (!empty($weight) && (!is_numeric($weight) || $weight <= 0)) {
        $errors[] = "Weight must be a positive number.";
    }
    
    // Process if no errors
    if (empty($errors)) {
        try {
            // Create pet record with prepared statement
            $stmt = $conn->prepare("
                INSERT INTO pets (
                    owner_id, name, species_id, breed_id, gender, date_of_birth, 
                    weight, color, microchip_number, special_notes
                ) VALUES (
                    ?, ?, ?, ?, ?, ?, ?, ?, ?, ?
                )
            ");
            
            // Fix the binding parameters to match the actual data types
            $stmt->bind_param(
                "isiissdsss", // Added an extra 's' for special_notes
                $owner_id, 
                $pet_name, 
                $species_id, 
                $breed_id, 
                $gender, 
                $date_of_birth, 
                $weight, 
                $color, 
                $microchip_number, 
                $special_notes
            );
            
            $stmt->execute();
            
            if ($stmt->affected_rows > 0) {
                $new_pet_id = $stmt->insert_id;
                $success_message = "Pet successfully added!";
                
                // Clear form data
                $pet_name = "";
                $species_id = "";
                $breed_id = "";
                $gender = "";
                $date_of_birth = "";
                $weight = "";
                $color = "";
                $microchip_number = "";
                $special_notes = "";
            } else {
                $errors[] = "Failed to add pet. Please try again.";
            }
            
            $stmt->close();
        } catch (Exception $e) {
            $errors[] = "Database error: " . $e->getMessage();
        }
    }
}

// Get all species for dropdown
$species_query = "SELECT * FROM species ORDER BY species_name";
$species_result = $conn->query($species_query);
$species_list = [];
while ($row = $species_result->fetch_assoc()) {
    $species_list[] = $row;
}

// Get all breeds if species is selected
$breeds_list = [];
if (!empty($species_id)) {
    $breeds_query = "SELECT * FROM breeds WHERE species_id = ? ORDER BY breed_name";
    $stmt = $conn->prepare($breeds_query);
    $stmt->bind_param("i", $species_id);
    $stmt->execute();
    $breeds_result = $stmt->get_result();
    
    while ($row = $breeds_result->fetch_assoc()) {
        $breeds_list[] = $row;
    }
    
    $stmt->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add New Pet - Pet Care Center</title>
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
        <section class="page-header">
            <h2>Add New Pet</h2>
            <a href="pets.php" class="btn btn-secondary">Back to My Pets</a>
        </section>

        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger">
                <h4>Please correct the following errors:</h4>
                <ul>
                    <?php foreach ($errors as $error): ?>
                        <li><?php echo $error; ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <?php if ($success_message): ?>
            <div class="alert alert-success">
                <?php echo $success_message; ?>
                <p><a href="pets.php" class="alert-link">View all your pets</a> or add another pet below.</p>
            </div>
        <?php endif; ?>

        <div class="form-container">
            <form method="POST" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="pet_name" class="form-label">Pet Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="pet_name" name="pet_name" value="<?php echo htmlspecialchars($pet_name); ?>" required>
                    </div>
                    
                    <div class="col-md-6 mb-3">
                        <label for="species_id" class="form-label">Species <span class="text-danger">*</span></label>
                        <select class="form-select" id="species_id" name="species_id" required>
                            <option value="">Select Species</option>
                            <?php foreach ($species_list as $species_item): ?>
                                <option value="<?php echo $species_item['species_id']; ?>" <?php echo ($species_id == $species_item['species_id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($species_item['species_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="breed_id" class="form-label">Breed</label>
                        <select class="form-select" id="breed_id" name="breed_id">
                            <option value="">Select Breed</option>
                            <?php foreach ($breeds_list as $breed_item): ?>
                                <option value="<?php echo $breed_item['breed_id']; ?>" <?php echo ($breed_id == $breed_item['breed_id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($breed_item['breed_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Gender <span class="text-danger">*</span></label>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="gender" id="gender_male" value="male" <?php echo ($gender === 'male') ? 'checked' : ''; ?> required>
                            <label class="form-check-label" for="gender_male">Male</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="gender" id="gender_female" value="female" <?php echo ($gender === 'female') ? 'checked' : ''; ?> required>
                            <label class="form-check-label" for="gender_female">Female</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="gender" id="gender_unknown" value="unknown" <?php echo ($gender === 'unknown') ? 'checked' : ''; ?> required>
                            <label class="form-check-label" for="gender_unknown">Unknown</label>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="date_of_birth" class="form-label">Date of Birth</label>
                        <input type="date" class="form-control" id="date_of_birth" name="date_of_birth" value="<?php echo htmlspecialchars($date_of_birth); ?>" max="<?php echo date('Y-m-d'); ?>">
                        <div class="form-text">Leave blank if unknown</div>
                    </div>
                    
                    <div class="col-md-6 mb-3">
                        <label for="weight" class="form-label">Weight (kg)</label>
                        <input type="number" class="form-control" id="weight" name="weight" value="<?php echo htmlspecialchars($weight); ?>" step="0.01" min="0">
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="color" class="form-label">Color</label>
                        <input type="text" class="form-control" id="color" name="color" value="<?php echo htmlspecialchars($color); ?>">
                    </div>
                    
                    <div class="col-md-6 mb-3">
                        <label for="microchip_number" class="form-label">Microchip Number</label>
                        <input type="text" class="form-control" id="microchip_number" name="microchip_number" value="<?php echo htmlspecialchars($microchip_number); ?>">
                    </div>
                </div>

                <div class="mb-3">
                    <label for="special_notes" class="form-label">Special Notes</label>
                    <textarea class="form-control" id="special_notes" name="special_notes" rows="3"><?php echo htmlspecialchars($special_notes); ?></textarea>
                    <div class="form-text">Please include any important information about your pet's health, behavior, or special care needs.</div>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">Add Pet</button>
                    <a href="pets.php" class="btn btn-outline-secondary">Cancel</a>
                </div>
            </form>
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
    // When the page loads
    document.addEventListener('DOMContentLoaded', function() {
        // Add event listener to species dropdown
        document.getElementById('species_id').addEventListener('change', function() {
            getBreeds(this.value);
        });
        
        // If there's already a species selected on page load, fetch its breeds
        const selectedSpecies = document.getElementById('species_id').value;
        if (selectedSpecies) {
            getBreeds(selectedSpecies);
        }
    });
    
    // Get breeds for selected species
    function getBreeds(speciesId) {
        if (!speciesId) {
            document.getElementById('breed_id').innerHTML = '<option value="">Select Species First</option>';
            return;
        }
        
        fetch('ajax/get_breeds.php?species_id=' + speciesId)
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok: ' + response.statusText);
                }
                return response.json();
            })
            .then(data => {
                let options = '<option value="">Select Breed</option>';
                if (Array.isArray(data)) {
                    data.forEach(breed => {
                        options += `<option value="${breed.breed_id}">${breed.breed_name}</option>`;
                    });
                }
                document.getElementById('breed_id').innerHTML = options;
            })
            .catch(error => {
                console.error('Error fetching breeds:', error);
                document.getElementById('breed_id').innerHTML = '<option value="">Error loading breeds</option>';
                // Alert user about the error
                alert('Failed to load breeds. Please try again or contact support.');
            });
    }
    </script>
</body>
</html>