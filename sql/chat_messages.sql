CREATE TABLE chat_messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    bus_id INT NOT NULL,
    sender_id INT NOT NULL,
    sender_type ENUM('parent', 'driver') NOT NULL,
    message TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    is_read BOOLEAN DEFAULT FALSE,
    FOREIGN KEY (bus_id) REFERENCES bus(bus_id)
);
