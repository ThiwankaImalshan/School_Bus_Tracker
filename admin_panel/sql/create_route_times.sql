CREATE TABLE IF NOT EXISTS route_times (
    id INT PRIMARY KEY AUTO_INCREMENT,
    bus_id INT NOT NULL,
    route_type ENUM('morning', 'evening') NOT NULL,
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_bus_route (bus_id, route_type),
    FOREIGN KEY (bus_id) REFERENCES bus(bus_id)
);
