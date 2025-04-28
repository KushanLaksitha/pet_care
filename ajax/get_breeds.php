<?php
// Include database connection
require_once '../includes/db_connect.php';

// Check if species_id is provided
if (!isset($_GET['species_id']) || empty($_GET['species_id'])) {
    // Return empty array if no species_id provided
    echo json_encode([]);
    exit();
}

// Sanitize input
$species_id = (int)$_GET['species_id'];

// Prepare and execute query to get breeds for the selected species
$stmt = $conn->prepare("SELECT * FROM breeds WHERE species_id = ? ORDER BY breed_name");
$stmt->bind_param("i", $species_id);
$stmt->execute();
$result = $stmt->get_result();

// Fetch all breeds
$breeds = [];
while ($row = $result->fetch_assoc()) {
    $breeds[] = $row;
}

// Close statement
$stmt->close();

// Return breeds as JSON
header('Content-Type: application/json');
echo json_encode($breeds);
exit();
?>