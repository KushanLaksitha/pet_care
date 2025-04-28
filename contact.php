<?php
// Start session
session_start();

// Include database connection
require_once 'includes/db_connect.php';

// Initialize variables
$success_message = '';
$error_message = '';
$name = '';
$email = '';
$phone = '';
$message = '';
$subject = '';

// Check if user is logged in
$is_logged_in = isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);

// If logged in, get user information
if ($is_logged_in) {
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
    $stmt->close();
    
    // Pre-fill form fields with user data
    $name = $user['full_name'];
    $email = $user['email'];
    $phone = $user['phone'] ?? '';
}

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get form data
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $subject = trim($_POST['subject']);
    $message = trim($_POST['message']);
    
    // Simple validation
    if (empty($name) || empty($email) || empty($message) || empty($subject)) {
        $error_message = "Please fill in all required fields.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error_message = "Please enter a valid email address.";
    } else {
        // In a real application, you would:
        // 1. Store the message in a database table
        // 2. Send an email notification to the admin
        // 3. Send a confirmation email to the user
        
        // For this example, we'll just show a success message
        $success_message = "Your message has been sent successfully! We will contact you shortly.";
        
        // Reset form fields after successful submission
        if (!$is_logged_in) {
            $name = '';
            $email = '';
            $phone = '';
        }
        $subject = '';
        $message = '';
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contact Us - Pet Care Center</title>
    <link rel="stylesheet" href="css/styles.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.5/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-SgOJa3DmI69IUzQ2PVdRZhwQ+dy64/BUtbMJw1MZ8t5HZApcHrRKUc4W0kG879m7" crossorigin="anonymous">
    <style>
        .contact-section {
            background-color: #f8f9fa;
            border-radius: 10px;
            padding: 25px;
            margin-bottom: 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .contact-info {
            background-color: #007bff;
            color: white;
            border-radius: 10px;
            padding: 25px;
            height: 100%;
        }
        
        .contact-info h3 {
            border-bottom: 2px solid white;
            padding-bottom: 15px;
            margin-bottom: 20px;
        }
        
        .contact-info-item {
            margin-bottom: 15px;
            display: flex;
            align-items: center;
        }
        
        .contact-info-item i {
            margin-right: 10px;
            font-size: 1.2rem;
        }
        
        .map-container {
            height: 300px;
            border-radius: 10px;
            overflow: hidden;
            margin-top: 30px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .btn-send {
            background-color: #007bff;
            color: white;
            padding: 10px 30px;
        }
        
        .btn-send:hover {
            background-color: #0056b3;
            color: white;
        }
        
        .contact-header {
            border-bottom: 2px solid #007bff;
            margin-bottom: 20px;
            padding-bottom: 10px;
        }
        
        .emergency-contact {
            background-color: #dc3545;
            color: white;
            padding: 15px;
            border-radius: 10px;
            margin-top: 20px;
        }
        
        .working-hours {
            margin-top: 20px;
        }
        
        .working-hours ul {
            list-style: none;
            padding-left: 0;
        }
        
        .working-hours ul li {
            display: flex;
            justify-content: space-between;
            padding: 5px 0;
            border-bottom: 1px dashed rgba(255,255,255,0.3);
        }
    </style>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
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
                    <li><a href="services.php">Services</a></li>
                    <li><a href="profile.php">My Profile</a></li>
                    <li><a href="contact.php" class="active">Contact Us</a></li>
                    <?php if ($is_logged_in): ?>
                        <li><a href="auth/logout.php">Logout</a></li>
                    <?php else: ?>
                        <li><a href="auth/login.php">Login</a></li>
                    <?php endif; ?>
                </ul>
            </nav>
        </div>
    </header>

    <main class="container py-4">
        <section class="page-header">
            <h2>Contact Us</h2>
            <p>Have questions? We're here to help! Get in touch with our team.</p>
        </section>

        <?php if (!empty($success_message)): ?>
            <div class="alert alert-success"><?php echo $success_message; ?></div>
        <?php endif; ?>
        
        <?php if (!empty($error_message)): ?>
            <div class="alert alert-danger"><?php echo $error_message; ?></div>
        <?php endif; ?>

        <div class="row">
            <div class="col-md-8">
                <section class="contact-section">
                    <div class="contact-header">
                        <h3>Send Us a Message</h3>
                    </div>
                    
                    <form method="post" action="contact.php">
                        <div class="form-group">
                            <label for="name">Full Name: <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="name" name="name" value="<?php echo htmlspecialchars($name); ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="email">Email Address: <span class="text-danger">*</span></label>
                            <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($email); ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="phone">Phone Number:</label>
                            <input type="tel" class="form-control" id="phone" name="phone" value="<?php echo htmlspecialchars($phone); ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="subject">Subject: <span class="text-danger">*</span></label>
                            <select class="form-select" id="subject" name="subject" required>
                                <option value="" <?php echo empty($subject) ? 'selected' : ''; ?>>Select a subject</option>
                                <option value="General Inquiry" <?php echo $subject == 'General Inquiry' ? 'selected' : ''; ?>>General Inquiry</option>
                                <option value="Appointment Request" <?php echo $subject == 'Appointment Request' ? 'selected' : ''; ?>>Appointment Request</option>
                                <option value="Boarding Request" <?php echo $subject == 'Boarding Request' ? 'selected' : ''; ?>>Boarding Request</option>
                                <option value="Service Feedback" <?php echo $subject == 'Service Feedback' ? 'selected' : ''; ?>>Service Feedback</option>
                                <option value="Custom Service Request" <?php echo $subject == 'Custom Service Request' ? 'selected' : ''; ?>>Custom Service Request</option>
                                <option value="Technical Support" <?php echo $subject == 'Technical Support' ? 'selected' : ''; ?>>Technical Support</option>
                                <option value="Other" <?php echo $subject == 'Other' ? 'selected' : ''; ?>>Other</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="message">Message: <span class="text-danger">*</span></label>
                            <textarea class="form-control" id="message" name="message" rows="5" required><?php echo htmlspecialchars($message); ?></textarea>
                        </div>
                        
                        <button type="submit" class="btn btn-send">Send Message</button>
                    </form>
                </section>
            </div>
            
            <div class="col-md-4">
                <div class="contact-info">
                    <h3>Contact Information</h3>
                    
                    <div class="contact-info-item">
                        <i class="fas fa-map-marker-alt"></i>
                        <div>
                            <p>123 Veterinary Drive<br>Ambilipitiya 70100<br>Sri Lanka</p>
                        </div>
                    </div>
                    
                    <div class="contact-info-item">
                        <i class="fas fa-phone"></i>
                        <div>
                            <p>+94 123 456 789</p>
                        </div>
                    </div>
                    
                    <div class="contact-info-item">
                        <i class="fas fa-envelope"></i>
                        <div>
                            <p>info@petcare.lk</p>
                        </div>
                    </div>
                    
                    <div class="working-hours">
                        <h4>Working Hours</h4>
                        <ul>
                            <li>
                                <span>Monday - Friday</span>
                                <span>8:00 AM - 6:00 PM</span>
                            </li>
                            <li>
                                <span>Saturday</span>
                                <span>9:00 AM - 4:00 PM</span>
                            </li>
                            <li>
                                <span>Sunday</span>
                                <span>9:00 AM - 1:00 PM</span>
                            </li>
                        </ul>
                    </div>
                    
                    <div class="emergency-contact">
                        <h4><i class="fas fa-exclamation-circle"></i> Emergency Contact</h4>
                        <p>For emergencies outside working hours:</p>
                        <p class="h5">+94 777 123 456</p>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="map-container">
            <!-- In a real implementation, this would be an actual Google Maps embed -->
            <div style="width: 100%; height: 100%; background-color: #e9ecef; display: flex; align-items: center; justify-content: center;">
                <p class="text-center text-muted">
                    <i class="fas fa-map fa-3x mb-2"></i><br>
                    Interactive map would be displayed here<br>
                    <iframe src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d63449.73689240085!2d80.85963244999999!3d6.315041399999999!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x3ae4002f298e95e3%3A0x62e2b8bc9ea7a79b!2sEmbilipitiya!5e0!3m2!1sen!2slk!4v1745495379701!5m2!1sen!2slk" width="600" height="450" style="border:0;" allowfullscreen="" loading="lazy" referrerpolicy="no-referrer-when-downgrade"></iframe>
                </p>
            </div>
        </div>
        
        <section class="faqs mt-5">
            <div class="card">
                <div class="card-body">
                    <h3>Frequently Asked Questions</h3>
                    
                    <div class="accordion" id="faqAccordion">
                        <div class="accordion-item">
                            <h2 class="accordion-header" id="headingOne">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseOne" aria-expanded="false" aria-controls="collapseOne">
                                    How do I book an appointment?
                                </button>
                            </h2>
                            <div id="collapseOne" class="accordion-collapse collapse" aria-labelledby="headingOne" data-bs-parent="#faqAccordion">
                                <div class="accordion-body">
                                    You can book an appointment through your online account dashboard. Simply log in, click on "Book Appointment" in the quick actions section, select the service you need, choose your pet, preferred date and time, and confirm your booking.
                                </div>
                            </div>
                        </div>
                        
                        <div class="accordion-item">
                            <h2 class="accordion-header" id="headingTwo">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseTwo" aria-expanded="false" aria-controls="collapseTwo">
                                    What should I do in case of a pet emergency?
                                </button>
                            </h2>
                            <div id="collapseTwo" class="accordion-collapse collapse" aria-labelledby="headingTwo" data-bs-parent="#faqAccordion">
                                <div class="accordion-body">
                                    During working hours, please call our main number immediately or come directly to our center. For emergencies outside working hours, please call our emergency number at +94 777 123 456. We recommend calling ahead so our staff can prepare for your arrival.
                                </div>
                            </div>
                        </div>
                        
                        <div class="accordion-item">
                            <h2 class="accordion-header" id="headingThree">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseThree" aria-expanded="false" aria-controls="collapseThree">
                                    How do I cancel or reschedule an appointment?
                                </button>
                            </h2>
                            <div id="collapseThree" class="accordion-collapse collapse" aria-labelledby="headingThree" data-bs-parent="#faqAccordion">
                                <div class="accordion-body">
                                    To cancel or reschedule an appointment, log in to your account, go to the "Appointments" section, find the appointment you wish to modify, and click on "Cancel" or "Reschedule". Alternatively, you can call us directly at +94 123 456 789.
                                </div>
                            </div>
                        </div>
                        
                        <div class="accordion-item">
                            <h2 class="accordion-header" id="headingFour">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseFour" aria-expanded="false" aria-controls="collapseFour">
                                    What payment methods do you accept?
                                </button>
                            </h2>
                            <div id="collapseFour" class="accordion-collapse collapse" aria-labelledby="headingFour" data-bs-parent="#faqAccordion">
                                <div class="accordion-body">
                                    We accept cash, credit/debit cards, and online bank transfers. Payment can be made in person at our center or through our online payment gateway for selected services.
                                </div>
                            </div>
                        </div>
                    </div>
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