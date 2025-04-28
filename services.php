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

// Get all active services
$stmt = $conn->prepare("SELECT * FROM services WHERE status = 'active' ORDER BY service_name");
$stmt->execute();
$services_result = $stmt->get_result();
$services = [];
while ($service = $services_result->fetch_assoc()) {
    $services[] = $service;
}
$stmt->close();

// Get service categories (grouped services)
$service_categories = [
    'Medical' => [],
    'Grooming' => [],
    'Boarding' => [],
    'Other' => []
];

// Categorize services (based on service_name for simplicity, could be more sophisticated)
foreach ($services as $service) {
    if (strpos(strtolower($service['service_name']), 'surgery') !== false || 
        strpos(strtolower($service['service_name']), 'check-up') !== false || 
        strpos(strtolower($service['service_name']), 'vaccination') !== false || 
        strpos(strtolower($service['service_name']), 'deworming') !== false ||
        strpos(strtolower($service['service_name']), 'dental') !== false ||
        strpos(strtolower($service['service_name']), 'microchip') !== false) {
        $service_categories['Medical'][] = $service;
    } elseif (strpos(strtolower($service['service_name']), 'grooming') !== false) {
        $service_categories['Grooming'][] = $service;
    } elseif (strpos(strtolower($service['service_name']), 'boarding') !== false) {
        $service_categories['Boarding'][] = $service;
    } else {
        $service_categories['Other'][] = $service;
    }
}

// Get user's pets for booking form if needed
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
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Our Services - Pet Care Center</title>
    <link rel="stylesheet" href="css/styles.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.5/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-SgOJa3DmI69IUzQ2PVdRZhwQ+dy64/BUtbMJw1MZ8t5HZApcHrRKUc4W0kG879m7" crossorigin="anonymous">
    <style>
        .service-card {
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
            transition: transform 0.3s, box-shadow 0.3s;
        }
        
        .service-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.1);
        }
        
        .service-price {
            font-size: 1.2rem;
            font-weight: bold;
            color: #28a745;
        }
        
        .service-duration {
            font-size: 0.9rem;
            color: #6c757d;
        }
        
        .category-section {
            margin-bottom: 40px;
        }
        
        .category-heading {
            border-bottom: 2px solid #007bff;
            padding-bottom: 10px;
            margin-bottom: 20px;
        }
        
        .book-now-btn {
            background-color: #007bff;
            color: white;
            border: none;
            padding: 10px 15px;
            border-radius: 4px;
            cursor: pointer;
            transition: background-color 0.3s;
        }
        
        .book-now-btn:hover {
            background-color: #0056b3;
        }
        
        .service-image {
            height: 150px;
            object-fit: cover;
            border-radius: 8px;
            margin-bottom: 15px;
        }
    </style>
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
                    <li><a href="boarding.php">Boarding</a></li>
                    <li><a href="bills.php">Billing</a></li>
                    <li><a href="services.php" class="active">Services</a></li>
                    <li><a href="profile.php">My Profile</a></li>
                    <li><a href="auth/logout.php">Logout</a></li>
                </ul>
            </nav>
        </div>
    </header>

    <main class="container py-4">
        <section class="page-header">
            <h2>Our Services</h2>
            <p>Explore our comprehensive range of pet care services designed for the well-being of your furry friends.</p>
        </section>

        <?php foreach ($service_categories as $category => $category_services): ?>
            <?php if (!empty($category_services)): ?>
                <section class="category-section">
                    <h3 class="category-heading"><?php echo $category; ?> Services</h3>
                    <div class="row">
                        <?php foreach ($category_services as $service): ?>
                            <div class="col-md-4 mb-4">
                                <div class="service-card">
                                    <h4><?php echo htmlspecialchars($service['service_name']); ?></h4>
                                    <p><?php echo htmlspecialchars($service['description'] ?? 'No description available.'); ?></p>
                                    <div class="service-meta my-3">
                                        <p class="service-price">Rs. <?php echo number_format($service['price'], 2); ?></p>
                                        <p class="service-duration">Duration: <?php echo $service['duration']; ?> minutes</p>
                                    </div>
                                    <a href="book_appointment.php?service_id=<?php echo $service['service_id']; ?>" class="book-now-btn">Book Now</a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </section>
            <?php endif; ?>
        <?php endforeach; ?>

        <!-- Service Information Section -->
        <section class="service-info mt-5">
            <div class="row">
                <div class="col-md-6">
                    <div class="card mb-4">
                        <div class="card-body">
                            <h3>Why Choose Our Services?</h3>
                            <ul>
                                <li>Experienced and qualified veterinary professionals</li>
                                <li>State-of-the-art facilities and equipment</li>
                                <li>Personalized care for each pet</li>
                                <li>Convenient appointment scheduling</li>
                                <li>Affordable prices without compromising on quality</li>
                            </ul>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-body">
                            <h3>Booking Information</h3>
                            <p>To book an appointment for any of our services:</p>
                            <ol>
                                <li>Click the "Book Now" button next to the desired service</li>
                                <li>Select your pet from your registered pets</li>
                                <li>Choose a convenient date and time</li>
                                <li>Add any special instructions if needed</li>
                                <li>Confirm your appointment</li>
                            </ol>
                            <p>For emergency services, please call our hotline: <strong>+94 123 456 789</strong></p>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Custom Service Request -->
        <section class="custom-service mt-5">
            <div class="card">
                <div class="card-body">
                    <h3>Need a Custom Service?</h3>
                    <p>Don't see what you're looking for? We may be able to accommodate special requests based on your pet's needs.</p>
                    <a href="contact.php" class="btn btn-outline-primary">Contact Us</a>
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

    <script src="js/script.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.5/dist/js/bootstrap.bundle.min.js" integrity="sha384-k6d4wzSIapyDyv1kpU366/PK5hCdSbCRGRCMv+eplOQJWyd1fbcAu9OCUj5zNLiq" crossorigin="anonymous"></script>
</body>
</html>