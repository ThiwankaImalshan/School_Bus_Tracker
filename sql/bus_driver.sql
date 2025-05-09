CREATE TABLE IF NOT EXISTS bus (
    bus_id INT AUTO_INCREMENT PRIMARY KEY,
    bus_number VARCHAR(50) NOT NULL,
    plate_number VARCHAR(20) NOT NULL,
    capacity INT NOT NULL
);

CREATE TABLE IF NOT EXISTS driver (
    driver_id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    phone VARCHAR(20),
    license_number VARCHAR(50)
);

CREATE TABLE IF NOT EXISTS bus_driver (
    id INT AUTO_INCREMENT PRIMARY KEY,
    bus_id INT NOT NULL,
    driver_id INT NOT NULL,
    status ENUM('active', 'inactive') DEFAULT 'active',
    assigned_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (bus_id) REFERENCES bus(bus_id),
    FOREIGN KEY (driver_id) REFERENCES driver(driver_id)
);
