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

// If owner doesn't exist for this user, redirect to create owner profile
if (!$owner_id) {
    header("Location: create_owner_profile.php");
    exit();
}

// Check if pet_id is provided
if (!isset($_GET['pet_id']) || empty($_GET['pet_id'])) {
    header("Location: pets.php");
    exit();
}

$pet_id = $_GET['pet_id'];

// Verify pet belongs to this owner before proceeding
$stmt = $conn->prepare("SELECT * FROM pets WHERE pet_id = ? AND owner_id = ?");
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

// Get all species
$species_query = "SELECT * FROM species ORDER BY species_name";
$species_result = $conn->query($species_query);
$species = [];
while ($row = $species_result->fetch_assoc()) {
    $species[] = $row;
}

// Get breeds for the current species
$breeds = [];
if ($pet['species_id']) {
    $breed_stmt = $conn->prepare("SELECT * FROM breeds WHERE species_id = ? ORDER BY breed_name");
    $breed_stmt->bind_param("i", $pet['species_id']);
    $breed_stmt->execute();
    $breed_result = $breed_stmt->get_result();
    
    while ($row = $breed_result->fetch_assoc()) {
        $breeds[] = $row;
    }
    $breed_stmt->close();
}

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate inputs
    $name = trim($_POST['name']);
    $species_id = $_POST['species_id'];
    $breed_id = !empty($_POST['breed_id']) ? $_POST['breed_id'] : null;
    $gender = $_POST['gender'];
    $date_of_birth = !empty($_POST['date_of_birth']) ? $_POST['date_of_birth'] : null;
    $weight = !empty($_POST['weight']) ? $_POST['weight'] : null;
    $color = !empty($_POST['color']) ? trim($_POST['color']) : null;
    $microchip_number = !empty($_POST['microchip_number']) ? trim($_POST['microchip_number']) : null;
    $special_notes = !empty($_POST['special_notes']) ? trim($_POST['special_notes']) : null;
    
    $errors = [];
    
    // Basic validation
    if (empty($name)) {
        $errors[] = "Pet name is required.";
    }
    
    if (empty($species_id)) {
        $errors[] = "Species is required.";
    }
    
    if (empty($gender)) {
        $errors[] = "Gender is required.";
    }
    
    // If no errors, update the pet
    if (empty($errors)) {
        $stmt = $conn->prepare("
            UPDATE pets 
            SET name = ?, species_id = ?, breed_id = ?, gender = ?, 
                date_of_birth = ?, weight = ?, color = ?, 
                microchip_number = ?, special_notes = ?
            WHERE pet_id = ? AND owner_id = ?
        ");
        
        $stmt->bind_param(
            "siissdssii", 
            $name, $species_id, $breed_id, $gender, 
            $date_of_birth, $weight, $color, 
            $microchip_number, $special_notes,
            $pet_id, $owner_id
        );
        
        if ($stmt->execute()) {
            // Redirect to pet details page with success message
            header("Location: pet_detail.php?pet_id=$pet_id&update=success");
            exit();
        } else {
            $errors[] = "Error updating pet: " . $stmt->error;
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
    <title>Edit Pet - Pet Care Center</title>
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
        <div class="mb-4">
            <a href="pet_detail.php?pet_id=<?php echo $pet_id; ?>" class="btn btn-secondary mb-3"><i class="fas fa-arrow-left"></i> Back to Pet Details</a>
            <h2>Edit Pet: <?php echo htmlspecialchars($pet['name']); ?></h2>
        </div>

        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger">
                <h5>Please correct the following errors:</h5>
                <ul>
                    <?php foreach ($errors as $error): ?>
                        <li><?php echo $error; ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <div class="card mb-4">
            <div class="card-body">
                <form action="" method="POST">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="name" class="form-label">Pet Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="name" name="name" required 
                                   value="<?php echo htmlspecialchars($pet['name']); ?>">
                        </div>
                        
                        <div class="col-md-6">
                            <label for="species_id" class="form-label">Species <span class="text-danger">*</span></label>
                            <select class="form-select" id="species_id" name="species_id" required onchange="getBreeds(this.value)">
                                <option value="">Select Species</option>
                                <?php foreach ($species as $item): ?>
                                    <option value="<?php echo $item['species_id']; ?>" 
                                            <?php echo ($pet['species_id'] == $item['species_id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($item['species_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="breed_id" class="form-label">Breed</label>
                            <select class="form-select" id="breed_id" name="breed_id">
                                <option value="">Select Breed</option>
                                <?php foreach ($breeds as $breed): ?>
                                    <option value="<?php echo $breed['breed_id']; ?>" 
                                            <?php echo ($pet['breed_id'] == $breed['breed_id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($breed['breed_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="col-md-6">
                            <label for="gender" class="form-label">Gender <span class="text-danger">*</span></label>
                            <select class="form-select" id="gender" name="gender" required>
                                <option value="">Select Gender</option>
                                <option value="male" <?php echo ($pet['gender'] == 'male') ? 'selected' : ''; ?>>Male</option>
                                <option value="female" <?php echo ($pet['gender'] == 'female') ? 'selected' : ''; ?>>Female</option>
                                <option value="unknown" <?php echo ($pet['gender'] == 'unknown') ? 'selected' : ''; ?>>Unknown</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="date_of_birth" class="form-label">Date of Birth</label>
                            <input type="date" class="form-control" id="date_of_birth" name="date_of_birth"
                                   value="<?php echo $pet['date_of_birth'] ? $pet['date_of_birth'] : ''; ?>">
                            <div class="form-text">Leave blank if unknown</div>
                        </div>
                        
                        <div class="col-md-6">
                            <label for="weight" class="form-label">Weight (kg)</label>
                            <input type="number" step="0.01" class="form-control" id="weight" name="weight" 
                                   min="0" max="999.99" placeholder="Weight in kilograms"
                                   value="<?php echo $pet['weight'] ? htmlspecialchars($pet['weight']) : ''; ?>">
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="color" class="form-label">Color</label>
                            <input type="text" class="form-control" id="color" name="color" 
                                   placeholder="E.g., Black, Brown, White, etc."
                                   value="<?php echo $pet['color'] ? htmlspecialchars($pet['color']) : ''; ?>">
                        </div>
                        
                        <div class="col-md-6">
                            <label for="microchip_number" class="form-label">Microchip Number</label>
                            <input type="text" class="form-control" id="microchip_number" name="microchip_number"
                                   placeholder="If your pet has a microchip"
                                   value="<?php echo $pet['microchip_number'] ? htmlspecialchars($pet['microchip_number']) : ''; ?>">
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="special_notes" class="form-label">Special Notes</label>
                        <textarea class="form-control" id="special_notes" name="special_notes" rows="4" 
                                  placeholder="Allergies, medical conditions, behavior traits, etc."><?php echo $pet['special_notes'] ? htmlspecialchars($pet['special_notes']) : ''; ?></textarea>
                    </div>
                    
                    <div class="d-flex justify-content-between">
                        <a href="pet_detail.php?pet_id=<?php echo $pet_id; ?>" class="btn btn-secondary">Cancel</a>
                        <button type="submit" class="btn btn-primary">Update Pet</button>
                    </div>
                </form>
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
        // Get breeds for selected species
        function getBreeds(speciesId, selectedBreedId = '') {
            if (!speciesId) {
                document.getElementById('breed_id').innerHTML = '<option value="">Select Species First</option>';
                return;
            }
            
            fetch('ajax/get_breeds.php?species_id=' + speciesId)
                .then(response => response.json())
                .then(data => {
                    let options = '<option value="">Select Breed</option>';
                    data.forEach(breed => {
                        const selected = breed.breed_id == selectedBreedId ? 'selected' : '';
                        options += `<option value="${breed.breed_id}" ${selected}>${breed.breed_name}</option>`;
                    });
                    document.getElementById('breed_id').innerHTML = options;
                })
                .catch(error => console.error('Error fetching breeds:', error));
        }
    </script>
</body>
</html>