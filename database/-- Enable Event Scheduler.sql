-- Enable the Event Scheduler (if not already enabled)
SET GLOBAL event_scheduler = ON;

-- Drop the event if it already exists
DROP EVENT IF EXISTS daily_attendance_creation;

-- Create the event to run daily at 5:00 AM
DELIMITER $

CREATE EVENT daily_attendance_creation
ON SCHEDULE EVERY 1 DAY
STARTS CONCAT(CURDATE(), ' 05:00:00')
ON COMPLETION PRESERVE
ENABLE
COMMENT 'Creates daily attendance records for ALL active children every day at 5:00 AM'
DO
BEGIN
    -- Declare variables for error handling
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        -- Log error (you can customize this based on your logging requirements)
        INSERT INTO event_log (event_name, error_message, created_at) 
        VALUES ('daily_attendance_creation', 'Error occurred during attendance creation', NOW());
        ROLLBACK;
    END;
    
    -- Start transaction
    START TRANSACTION;
    
    -- Insert attendance records for ALL active children for current date
    -- Create records for every active child regardless of existing records
    INSERT INTO attendance (
        child_id, 
        bus_seat_id, 
        attendance_date, 
        status, 
        notification_sent, 
        last_updated, 
        updated_at
    )
    SELECT 
        cr.child_id,
        cr.seat_id as bus_seat_id,
        CURDATE() as attendance_date,
        'pending' as status,
        0 as notification_sent,
        CURRENT_TIMESTAMP as last_updated,
        CURRENT_TIMESTAMP as updated_at
    FROM child_reservation cr
    WHERE cr.is_active = 1
    AND NOT EXISTS (
        SELECT 1 
        FROM attendance a 
        WHERE a.child_id = cr.child_id 
        AND a.attendance_date = CURDATE()
    );
    
    -- Log successful execution
    INSERT INTO event_log (event_name, error_message, created_at) 
    VALUES ('daily_attendance_creation', CONCAT('Successfully created attendance records for ', ROW_COUNT(), ' children'), NOW());
    
    -- Commit the transaction
    COMMIT;
    
END$$

DELIMITER ;

-- Optional: Create a log table to track event execution (if it doesn't exist)
CREATE TABLE IF NOT EXISTS event_log (
    log_id INT AUTO_INCREMENT PRIMARY KEY,
    event_name VARCHAR(100) NOT NULL,
    error_message TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Check if the event was created successfully
SHOW EVENTS LIKE 'daily_attendance_creation';

-- To verify the event scheduler is running
SHOW VARIABLES LIKE 'event_scheduler';

-- Manual test query to see how many active children will get records
SELECT 
    COUNT(*) as total_active_children,
    GROUP_CONCAT(cr.child_id ORDER BY cr.child_id) as child_ids
FROM child_reservation cr
WHERE cr.is_active = 1;

-- Manual test to create today's attendance (for testing purposes)
-- Uncomment and run this to test the logic manually:
/*
INSERT INTO attendance (child_id, bus_seat_id, attendance_date, status, notification_sent, last_updated, updated_at)
SELECT 
    cr.child_id,
    cr.seat_id as bus_seat_id,
    CURDATE() as attendance_date,
    'pending' as status,
    0 as notification_sent,
    CURRENT_TIMESTAMP as last_updated,
    CURRENT_TIMESTAMP as updated_at
FROM child_reservation cr
WHERE cr.is_active = 1
AND NOT EXISTS (
    SELECT 1 FROM attendance a 
    WHERE a.child_id = cr.child_id 
    AND a.attendance_date = CURDATE()
);
*/

-- Query to check today's attendance records
SELECT 
    a.child_id,
    a.bus_seat_id,
    a.attendance_date,
    a.status,
    a.last_updated
FROM attendance a 
WHERE a.attendance_date = CURDATE()
ORDER BY a.child_id;