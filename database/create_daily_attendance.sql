-- Enable Event Scheduler
SET GLOBAL event_scheduler = ON;

-- Set delimiter for complex statements
DELIMITER //

-- Drop existing event if any
DROP EVENT IF EXISTS create_daily_attendance//

-- Create daily attendance event
CREATE EVENT create_daily_attendance
ON SCHEDULE EVERY 1 DAY
STARTS CONCAT(CURDATE(), ' 05:00:00')
DO
BEGIN
    -- Insert attendance records for all active students without today's record
    INSERT INTO attendance (child_id, bus_seat_id, attendance_date, status, created_at)
    SELECT 
        cr.child_id,
        cr.seat_id,
        CURDATE(),
        'pending',
        NOW()
    FROM child_reservation cr 
    WHERE cr.is_active = 1 
    AND NOT EXISTS (
        SELECT 1 FROM attendance a 
        WHERE a.child_id = cr.child_id 
        AND a.attendance_date = CURDATE()
    );

    -- Create notifications for parents
    INSERT INTO notification (recipient_type, recipient_id, child_id, title, message, notification_type, sent_at)
    SELECT 
        'parent',
        c.parent_id,
        c.child_id,
        'Attendance Reminder',
        CONCAT('Please mark attendance for your child for ', DATE_FORMAT(CURDATE(), '%d/%m/%Y')),
        'info',
        NOW()
    FROM child c
    JOIN child_reservation cr ON c.child_id = cr.child_id
    WHERE cr.is_active = 1;
END//

-- Reset delimiter
DELIMITER ;
