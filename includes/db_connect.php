<?php
/**
 * Database Connection
 * 
 * This file establishes connection to the Pet Care Center database.
 * Include this file in any script that needs database access.
 */

// Database configuration
$db_host = 'localhost';     // Database host (usually localhost)
$db_name = 'pet_care_center'; // Database name as defined in your SQL file
$db_user = 'root';          // Database username - change in production
$db_pass = '';              // Database password - change in production

// Create database connection
$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Set character set to utf8mb4
$conn->set_charset("utf8mb4");

// Optional: Set timezone for date functions
date_default_timezone_set('Asia/Colombo'); // Sri Lanka timezone

/**
 * Sanitize function to help prevent SQL Injection
 * Use this function when directly inserting user input into queries
 * However, prefer prepared statements for most operations
 */
function sanitize($conn, $input) {
    if (is_array($input)) {
        $output = array();
        foreach ($input as $key => $val) {
            $output[$key] = sanitize($conn, $val);
        }
        return $output;
    } else {
        return $conn->real_escape_string($input);
    }
}

/**
 * Debug function to safely print database errors during development
 * Remove or disable this in production
 */
function db_error($message = 'Database error') {
    global $conn;
    // Comment out the next line in production
    echo $message . ': ' . $conn->error;
    
    // In production, log errors instead of displaying them
    error_log("Database Error: " . $conn->error);
    
    // Return false to indicate failure
    return false;
}