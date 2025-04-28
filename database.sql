-- Pet Care Database Schema for Ambilipitiya, Sri Lanka
-- Created: April 24, 2025

-- Drop existing database if it exists (be careful with this in production)
DROP DATABASE IF EXISTS pet_care_center;

-- Create the database
CREATE DATABASE pet_care_center;

-- Use the database
USE pet_care_center;

-- Users table for authentication
CREATE TABLE users (
    user_id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    full_name VARCHAR(100) NOT NULL,
    phone VARCHAR(15),
    address TEXT,
    role ENUM('admin','customer') NOT NULL DEFAULT 'customer',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Pet Owners table
CREATE TABLE owners (
    owner_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    owner_name VARCHAR(100) NOT NULL,
    contact_number VARCHAR(15) NOT NULL,
    email VARCHAR(100),
    address TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE SET NULL
);

-- Pet Species table
CREATE TABLE species (
    species_id INT AUTO_INCREMENT PRIMARY KEY,
    species_name VARCHAR(50) NOT NULL UNIQUE
);

-- Pet Breeds table
CREATE TABLE breeds (
    breed_id INT AUTO_INCREMENT PRIMARY KEY,
    species_id INT NOT NULL,
    breed_name VARCHAR(50) NOT NULL,
    FOREIGN KEY (species_id) REFERENCES species(species_id) ON DELETE CASCADE,
    UNIQUE (species_id, breed_name)
);

-- Pets table
CREATE TABLE pets (
    pet_id INT AUTO_INCREMENT PRIMARY KEY,
    owner_id INT NOT NULL,
    name VARCHAR(50) NOT NULL,
    species_id INT NOT NULL,
    breed_id INT,
    gender ENUM('male', 'female', 'unknown') NOT NULL,
    date_of_birth DATE,
    weight DECIMAL(5,2),
    color VARCHAR(50),
    microchip_number VARCHAR(50),
    special_notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (owner_id) REFERENCES owners(owner_id) ON DELETE CASCADE,
    FOREIGN KEY (species_id) REFERENCES species(species_id) ON DELETE RESTRICT,
    FOREIGN KEY (breed_id) REFERENCES breeds(breed_id) ON DELETE SET NULL
);

-- Services table
CREATE TABLE services (
    service_id INT AUTO_INCREMENT PRIMARY KEY,
    service_name VARCHAR(100) NOT NULL,
    description TEXT,
    price DECIMAL(10,2) NOT NULL,
    duration INT,  -- in minutes
    status ENUM('active', 'inactive') DEFAULT 'active'
);

-- Staff table
CREATE TABLE staff (
    staff_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    staff_name VARCHAR(100) NOT NULL,
    position VARCHAR(50) NOT NULL,
    contact_number VARCHAR(15) NOT NULL,
    email VARCHAR(100),
    joining_date DATE NOT NULL,
    status ENUM('active', 'inactive') DEFAULT 'active',
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE SET NULL
);

-- Appointments table
CREATE TABLE appointments (
    appointment_id INT AUTO_INCREMENT PRIMARY KEY,
    pet_id INT NOT NULL,
    service_id INT NOT NULL,
    staff_id INT,
    appointment_date DATE NOT NULL,
    appointment_time TIME NOT NULL,
    status ENUM('scheduled', 'confirmed', 'completed', 'cancelled') DEFAULT 'scheduled',
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (pet_id) REFERENCES pets(pet_id) ON DELETE CASCADE,
    FOREIGN KEY (service_id) REFERENCES services(service_id) ON DELETE RESTRICT,
    FOREIGN KEY (staff_id) REFERENCES staff(staff_id) ON DELETE SET NULL
);

-- Medical Records table
CREATE TABLE medical_records (
    record_id INT AUTO_INCREMENT PRIMARY KEY,
    pet_id INT NOT NULL,
    staff_id INT,
    diagnosis TEXT NOT NULL,
    treatment TEXT,
    prescription TEXT,
    visit_date DATE NOT NULL,
    follow_up_date DATE,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (pet_id) REFERENCES pets(pet_id) ON DELETE CASCADE,
    FOREIGN KEY (staff_id) REFERENCES staff(staff_id) ON DELETE SET NULL
);

-- Vaccinations table
CREATE TABLE vaccinations (
    vaccination_id INT AUTO_INCREMENT PRIMARY KEY,
    pet_id INT NOT NULL,
    vaccine_name VARCHAR(100) NOT NULL,
    vaccination_date DATE NOT NULL,
    expiry_date DATE,
    administered_by INT,  -- staff_id
    notes TEXT,
    FOREIGN KEY (pet_id) REFERENCES pets(pet_id) ON DELETE CASCADE,
    FOREIGN KEY (administered_by) REFERENCES staff(staff_id) ON DELETE SET NULL
);

-- Inventory Categories
CREATE TABLE inventory_categories (
    category_id INT AUTO_INCREMENT PRIMARY KEY,
    category_name VARCHAR(50) NOT NULL UNIQUE,
    description TEXT
);

-- Inventory Items
CREATE TABLE inventory_items (
    item_id INT AUTO_INCREMENT PRIMARY KEY,
    category_id INT NOT NULL,
    item_name VARCHAR(100) NOT NULL,
    description TEXT,
    quantity INT NOT NULL DEFAULT 0,
    unit_price DECIMAL(10,2) NOT NULL,
    reorder_level INT NOT NULL DEFAULT 10,
    supplier VARCHAR(100),
    last_restock_date DATE,
    FOREIGN KEY (category_id) REFERENCES inventory_categories(category_id) ON DELETE RESTRICT
);

-- Billing table
CREATE TABLE billing (
    bill_id INT AUTO_INCREMENT PRIMARY KEY,
    appointment_id INT,
    owner_id INT NOT NULL,
    bill_date DATE NOT NULL,
    total_amount DECIMAL(10,2) NOT NULL,
    payment_status ENUM('pending', 'paid', 'partial', 'cancelled') DEFAULT 'pending',
    payment_method ENUM('cash', 'card', 'online', 'bank_transfer') DEFAULT 'cash',
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (appointment_id) REFERENCES appointments(appointment_id) ON DELETE SET NULL,
    FOREIGN KEY (owner_id) REFERENCES owners(owner_id) ON DELETE RESTRICT
);

-- Bill Details table
CREATE TABLE bill_details (
    detail_id INT AUTO_INCREMENT PRIMARY KEY,
    bill_id INT NOT NULL,
    service_id INT,
    item_id INT,
    description VARCHAR(255) NOT NULL,
    quantity INT NOT NULL DEFAULT 1,
    unit_price DECIMAL(10,2) NOT NULL,
    subtotal DECIMAL(10,2) NOT NULL,
    FOREIGN KEY (bill_id) REFERENCES billing(bill_id) ON DELETE CASCADE,
    FOREIGN KEY (service_id) REFERENCES services(service_id) ON DELETE SET NULL,
    FOREIGN KEY (item_id) REFERENCES inventory_items(item_id) ON DELETE SET NULL
);

-- Boarding table for pet boarding services
CREATE TABLE boarding (
    boarding_id INT AUTO_INCREMENT PRIMARY KEY,
    pet_id INT NOT NULL,
    check_in_date DATE NOT NULL,
    check_out_date DATE NOT NULL,
    cage_number VARCHAR(20),
    special_instructions TEXT,
    status ENUM('booked', 'checked_in', 'checked_out', 'cancelled') DEFAULT 'booked',
    daily_rate DECIMAL(10,2) NOT NULL,
    owner_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (pet_id) REFERENCES pets(pet_id) ON DELETE CASCADE,
    FOREIGN KEY (owner_id) REFERENCES owners(owner_id) ON DELETE RESTRICT
);

-- Payment History table
CREATE TABLE payment_history (
    payment_id INT AUTO_INCREMENT PRIMARY KEY,
    bill_id INT NOT NULL,
    owner_id INT NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    payment_method ENUM('cash', 'card', 'online', 'bank_transfer') NOT NULL,
    payment_date DATE NOT NULL,
    reference_number VARCHAR(100),
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (bill_id) REFERENCES billing(bill_id) ON DELETE CASCADE,
    FOREIGN KEY (owner_id) REFERENCES owners(owner_id) ON DELETE CASCADE
);


-- Insert some initial data for testing

-- Insert test admin user
INSERT INTO users (username, password, email, full_name, role) 
VALUES ('admin', '$2y$10$6SLQXCXYnJBFP7NjMxYFjuq4KkCtDuuHKh4q.4gh9IUl8YF9WcHES', 'admin@petcare.lk', 'Admin User', 'admin');
-- Password is 'admin123' hashed with bcrypt

-- Insert common pet species
INSERT INTO species (species_name) VALUES 
('Dog'), ('Cat'), ('Bird'), ('Rabbit'), ('Hamster'), ('Guinea Pig'), ('Turtle'), ('Fish');

-- Insert some common dog breeds
INSERT INTO breeds (species_id, breed_name) VALUES
(1, 'Labrador Retriever'), (1, 'German Shepherd'), (1, 'Golden Retriever'),
(1, 'Bulldog'), (1, 'Poodle'), (1, 'Rottweiler'), (1, 'Local Mix');

-- Insert some common cat breeds
INSERT INTO breeds (species_id, breed_name) VALUES
(2, 'Persian'), (2, 'Maine Coon'), (2, 'Siamese'),
(2, 'Ragdoll'), (2, 'British Shorthair'), (2, 'Local Mix');

-- Insert common services
INSERT INTO services (service_name, description, price, duration) VALUES
('General Check-up', 'Basic health assessment for your pet', 1500.00, 30),
('Vaccination', 'Various vaccines for your pet', 2500.00, 20),
('Deworming', 'Deworming treatment', 1000.00, 15),
('Grooming', 'Full grooming service including bath and haircut', 3000.00, 60),
('Dental Cleaning', 'Cleaning teeth and oral examination', 2000.00, 45),
('Microchipping', 'Microchip implantation for identification', 2500.00, 15),
('Surgery - Minor', 'Minor surgical procedures', 5000.00, 90),
('Surgery - Major', 'Major surgical procedures', 15000.00, 180),
('Boarding - Small Pet', 'Boarding for small pets per day', 1000.00, 1440),
('Boarding - Large Pet', 'Boarding for large pets per day', 1500.00, 1440);

-- Insert inventory categories
INSERT INTO inventory_categories (category_name, description) VALUES
('Medicines', 'All types of medicines and pharmaceuticals'),
('Food', 'Pet food of various brands'),
('Accessories', 'Pet accessories like collars, leashes, etc.'),
('Grooming Products', 'Shampoos, brushes and other grooming items'),
('Medical Supplies', 'Bandages, syringes and other medical supplies');