CREATE TABLE IF NOT EXISTS route_settings (
    id INT PRIMARY KEY AUTO_INCREMENT,
    bus_id INT NOT NULL,
    morning_start INT NOT NULL DEFAULT 300,
    morning_end INT NOT NULL DEFAULT 720,
    evening_start INT NOT NULL DEFAULT 720,
    evening_end INT NOT NULL DEFAULT 1020,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (bus_id) REFERENCES bus(bus_id)
);

-- Insert default values for each bus
INSERT INTO route_settings (bus_id, morning_start, morning_end, evening_start, evening_end)
SELECT bus_id, 300, 720, 720, 1020 FROM bus;
