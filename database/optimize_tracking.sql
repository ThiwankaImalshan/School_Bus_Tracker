-- Create temporary table to store compressed data
CREATE TABLE IF NOT EXISTS `bus_tracking_temp` (
  `tracking_id` int NOT NULL AUTO_INCREMENT,
  `bus_id` int NOT NULL,
  `date` DATE NOT NULL,
  `route_type` enum('morning','evening') NOT NULL,
  `tracking_data` JSON NOT NULL,
  `status` enum('ongoing','completed','delayed') DEFAULT 'ongoing',
  PRIMARY KEY (`tracking_id`),
  KEY `idx_bus_date` (`bus_id`,`date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Insert compressed data from existing table
INSERT INTO bus_tracking_temp (bus_id, date, route_type, tracking_data, status)
SELECT 
    bus_id,
    DATE(timestamp) as date,
    CASE 
        WHEN HOUR(timestamp) < 12 THEN 'morning'
        ELSE 'evening'
    END as route_type,
    JSON_ARRAYAGG(
        JSON_OBJECT(
            'time', TIME_FORMAT(timestamp, '%H:%i:%s'),
            'lat', latitude,
            'lng', longitude,
            'speed', speed
        )
    ) as tracking_data,
    MAX(status) as status
FROM bus_tracking
GROUP BY bus_id, DATE(timestamp), 
    CASE 
        WHEN HOUR(timestamp) < 12 THEN 'morning'
        ELSE 'evening'
    END;

-- Optional: Rename tables to make the change permanent
-- RENAME TABLE bus_tracking TO bus_tracking_old;
-- RENAME TABLE bus_tracking_temp TO bus_tracking;
