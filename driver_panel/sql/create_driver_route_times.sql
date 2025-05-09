CREATE TABLE IF NOT EXISTS driver_route_times (
    id INT PRIMARY KEY AUTO_INCREMENT,
    driver_id INT NOT NULL,
    route_date DATE NOT NULL,
    morning_start TIME DEFAULT '05:00:00',
    morning_end TIME DEFAULT '12:00:00', 
    evening_start TIME DEFAULT '12:00:00',
    evening_end TIME DEFAULT '17:00:00',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_driver_date (driver_id, route_date),
    FOREIGN KEY (driver_id) REFERENCES driver(driver_id)
);
