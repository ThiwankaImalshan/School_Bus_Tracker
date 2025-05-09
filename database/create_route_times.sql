CREATE TABLE IF NOT EXISTS route_times (
    id INT AUTO_INCREMENT PRIMARY KEY,
    bus_id INT NOT NULL,
    route_type ENUM('morning', 'evening') NOT NULL,
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    created_at DATE DEFAULT (CURDATE()),
    UNIQUE KEY unique_daily_route (bus_id, route_type, created_at)
);
