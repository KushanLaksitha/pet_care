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

// Process delete pet request
if (isset($_GET['action']) && $_GET['action'] == 'delete' && isset($_GET['pet_id'])) {
    $pet_id = $_GET['pet_id'];
    
    // Verify pet belongs to this owner
    $stmt = $conn->prepare("SELECT * FROM pets WHERE pet_id = ? AND owner_id = ?");
    $stmt->bind_param("ii", $pet_id, $owner_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        // Check if pet has appointments or medical records before allowing deletion
        $check_stmt = $conn->prepare("
            SELECT COUNT(*) as count FROM appointments WHERE pet_id = ? AND status IN ('scheduled', 'confirmed')
        ");
        $check_stmt->bind_param("i", $pet_id);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        $check_data = $check_result->fetch_assoc();
        $check_stmt->close();
        
        if ($check_data['count'] > 0) {
            $delete_error = "Cannot delete pet with upcoming appointments. Please cancel appointments first.";
        } else {
            // Safe to delete
            $delete_stmt = $conn->prepare("DELETE FROM pets WHERE pet_id = ? AND owner_id = ?");
            $delete_stmt->bind_param("ii", $pet_id, $owner_id);
            $delete_stmt->execute();
            
            if ($delete_stmt->affected_rows > 0) {
                $delete_success = "Pet has been successfully removed.";
            } else {
                $delete_error = "Error removing pet. Please try again.";
            }
            $delete_stmt->close();
        }
    } else {
        $delete_error = "Pet not found or you don't have permission to delete this pet.";
    }
    $stmt->close();
}

// Get all species
$species_query = "SELECT * FROM species ORDER BY species_name";
$species_result = $conn->query($species_query);
$species = [];
while ($row = $species_result->fetch_assoc()) {
    $species[] = $row;
}

// Get all pets for this owner
$stmt = $conn->prepare("
    SELECT p.*, s.species_name, b.breed_name 
    FROM pets p 
    LEFT JOIN species s ON p.species_id = s.species_id 
    LEFT JOIN breeds b ON p.breed_id = b.breed_id 
    WHERE p.owner_id = ?
    ORDER BY p.name
");
$stmt->bind_param("i", $owner_id);
$stmt->execute();
$pets_result = $stmt->get_result();
$pets = [];
while ($pet = $pets_result->fetch_assoc()) {
    $pets[] = $pet;
}
$stmt->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Pets - Pet Care Center</title>
    <link rel="stylesheet" href="css/styles.css">
    <link rel="stylesheet" href="css/pets.css">
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
            <h2>My Pets</h2>
            <a href="add_pet.php" class="btn btn-primary">Add New Pet</a>
        </section>

        <?php if (isset($delete_success)): ?>
            <div class="alert alert-success">
                <?php echo $delete_success; ?>
            </div>
        <?php endif; ?>

        <?php if (isset($delete_error)): ?>
            <div class="alert alert-danger">
                <?php echo $delete_error; ?>
            </div>
        <?php endif; ?>

        <?php if (count($pets) > 0): ?>
            <div class="pets-container">
                <?php foreach ($pets as $pet): ?>
                    <div class="pet-card">
                        <div class="pet-header">
                            <h3><?php echo htmlspecialchars($pet['name']); ?></h3>
                            <div class="pet-species-breed">
                                <?php echo htmlspecialchars($pet['species_name']); ?> 
                                <?php if ($pet['breed_name']): ?>
                                    (<?php echo htmlspecialchars($pet['breed_name']); ?>)
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div class="pet-details">
                            <div class="pet-info">
                                <p><strong>Gender:</strong> <?php echo ucfirst(htmlspecialchars($pet['gender'])); ?></p>
                                
                                <?php if ($pet['date_of_birth']): ?>
                                    <p><strong>Date of Birth:</strong> <?php echo date('M d, Y', strtotime($pet['date_of_birth'])); ?></p>
                                    <p><strong>Age:</strong> 
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
                                    </p>
                                <?php endif; ?>
                                
                                <?php if ($pet['weight']): ?>
                                    <p><strong>Weight:</strong> <?php echo htmlspecialchars($pet['weight']); ?> kg</p>
                                <?php endif; ?>
                                
                                <?php if ($pet['color']): ?>
                                    <p><strong>Color:</strong> <?php echo htmlspecialchars($pet['color']); ?></p>
                                <?php endif; ?>
                                
                                <?php if ($pet['microchip_number']): ?>
                                    <p><strong>Microchip:</strong> <?php echo htmlspecialchars($pet['microchip_number']); ?></p>
                                <?php endif; ?>
                                
                                <?php if ($pet['special_notes']): ?>
                                    <p><strong>Notes:</strong> <?php echo htmlspecialchars($pet['special_notes']); ?></p>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div class="pet-actions">
                            <a href="pet_detail.php?pet_id=<?php echo $pet['pet_id']; ?>" class="btn btn-info btn-sm">View Details</a>
                            <a href="edit_pet.php?pet_id=<?php echo $pet['pet_id']; ?>" class="btn btn-secondary btn-sm">Edit</a>
                            <a href="pets.php?action=delete&pet_id=<?php echo $pet['pet_id']; ?>" 
                               class="btn btn-danger btn-sm"
                               onclick="return confirm('Are you sure you want to delete this pet? This action cannot be undone.');">Delete</a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="no-pets">
                <p>You haven't registered any pets yet.</p>
                <a href="add_pet.php" class="btn btn-primary">Register Your First Pet</a>
            </div>
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