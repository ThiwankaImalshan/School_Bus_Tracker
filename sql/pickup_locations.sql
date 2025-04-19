CREATE TABLE IF NOT EXISTS pickup_locations (
    location_id INT NOT NULL AUTO_INCREMENT,
    child_id INT NOT NULL,
    name VARCHAR(100) NOT NULL,
    location VARCHAR(50) NOT NULL, -- Stores coordinates as "latitude,longitude"
    is_default BOOLEAN DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (location_id),
    FOREIGN KEY (child_id) REFERENCES child(child_id)
);
