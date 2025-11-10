-- Test Data for Emergency Response System
-- Yobe State University

-- Insert test emergency types
INSERT IGNORE INTO emergency_types (id, name, department, description, icon, color, is_active, created_at) VALUES
(1, 'Medical Emergency', 'health', 'Requires immediate medical attention', 'medical', '#FF6B6B', 1, NOW()),
(2, 'Fire Emergency', 'fire', 'Fire outbreak or fire hazard', 'fire', '#FF9F40', 1, NOW()),
(3, 'Security Emergency', 'security', 'Security threat or incident', 'security', '#4ECDC4', 1, NOW()),
(4, 'Accident', 'health', 'Accident requiring medical attention', 'accident', '#95E77E', 1, NOW()),
(5, 'Theft', 'security', 'Report of theft or burglary', 'theft', '#A78BFA', 1, NOW()),
(6, 'Electrical Hazard', 'fire', 'Electrical emergency or hazard', 'electrical', '#FBBF24', 1, NOW());

-- Insert test locations
INSERT IGNORE INTO locations (id, name, description, category, latitude, longitude, is_active, created_at) VALUES
(1, 'Main Library', 'University main library building', 'academic', 12.4567, 10.1234, 1, NOW()),
(2, 'Science Laboratory Block', 'Science faculty laboratories', 'academic', 12.4578, 10.1245, 1, NOW()),
(3, 'Student Hostel A', 'Male student accommodation', 'hostel', 12.4589, 10.1256, 1, NOW()),
(4, 'Student Hostel B', 'Female student accommodation', 'hostel', 12.4600, 10.1267, 1, NOW()),
(5, 'Administrative Block', 'University administrative offices', 'admin', 12.4611, 10.1278, 1, NOW()),
(6, 'University Health Center', 'Campus medical facility', 'medical', 12.4622, 10.1289, 1, NOW()),
(7, 'Sports Complex', 'Sports and recreation facilities', 'recreational', 12.4633, 10.1300, 1, NOW()),
(8, 'Main Gate', 'University main entrance', 'other', 12.4644, 10.1311, 1, NOW());

-- Insert test departments
INSERT IGNORE INTO departments (code, name, description, contact_email, contact_phone, created_at) VALUES
('academic', 'Academic Affairs', 'Academic departments and faculties', 'academic@ysu.edu.ng', '+2348000000001', NOW()),
('admin', 'Administration', 'University administrative offices', 'admin@ysu.edu.ng', '+2348000000002', NOW()),
('health', 'Health Services', 'University health center and medical services', 'health@ysu.edu.ng', '+2348000000003', NOW()),
('security', 'Security', 'Campus security and safety', 'security@ysu.edu.ng', '+2348000000004', NOW()),
('fire', 'Fire Safety', 'Fire prevention and emergency response', 'fire@ysu.edu.ng', '+2348000000005', NOW()),
('technical', 'Technical', 'Technical services and maintenance', 'technical@ysu.edu.ng', '+2348000000006', NOW());

-- Insert test users (password: TestPass123!)
INSERT IGNORE INTO users (id, full_name, email, phone, school_id, department, role, password_hash, is_active, email_verified_at, phone_verified_at, created_at) VALUES
(1, 'Test Student', 'teststudent@ysu.edu.ng', '+2348012345678', 'YSU/2023/0001', 'academic', 'student', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 1, NOW(), NOW(), NOW()),
(2, 'Test Staff', 'teststaff@ysu.edu.ng', '+2348012345679', 'YSU/STAFF/001', 'admin', 'staff', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 1, NOW(), NOW(), NOW()),
(3, 'Health Admin', 'healthadmin@ysu.edu.ng', '+2348012345680', 'YSU/ADMIN/001', 'health', 'health_admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 1, NOW(), NOW(), NOW()),
(4, 'Fire Admin', 'fireadmin@ysu.edu.ng', '+2348012345681', 'YSU/ADMIN/002', 'fire', 'fire_admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 1, NOW(), NOW(), NOW()),
(5, 'Security Admin', 'securityadmin@ysu.edu.ng', '+2348012345682', 'YSU/ADMIN/003', 'security', 'security_admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 1, NOW(), NOW(), NOW()),
(6, 'Super Admin', 'superadmin@ysu.edu.ng', '+2348012345683', 'YSU/ADMIN/000', 'admin', 'super_admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 1, NOW(), NOW(), NOW());

-- Insert sample emergencies
INSERT IGNORE INTO emergencies (id, user_id, emergency_type_id, location_id, description, severity, status, assigned_to, reported_at, resolved_at, response_time_minutes) VALUES
(1, 1, 1, 6, 'Student feeling dizzy and experiencing chest pain', 'high', 'resolved', 3, DATE_SUB(NOW(), INTERVAL 2 HOUR), DATE_SUB(NOW(), INTERVAL 1 HOUR 45 MINUTE), 15),
(2, 1, 3, 8, 'Suspicious person lurking around main gate', 'medium', 'resolved', 5, DATE_SUB(NOW(), INTERVAL 1 DAY), DATE_SUB(NOW(), INTERVAL 23 HOUR), 60),
(3, 2, 2, 2, 'Electrical short circuit in chemistry lab', 'critical', 'resolved', 4, DATE_SUB(NOW(), INTERVAL 3 DAY), DATE_SUB(NOW(), INTERVAL 2 DAY 23 HOUR), 60),
(4, 1, 4, 3, 'Student slipped and fell in hostel bathroom', 'medium', 'in_progress', 3, DATE_SUB(NOW(), INTERVAL 30 MINUTE), NULL, NULL),
(5, 2, 5, 1, 'Laptop stolen from library reading area', 'low', 'pending', NULL, DATE_SUB(NOW(), INTERVAL 5 MINUTE), NULL, NULL);

-- Insert sample emergency updates
INSERT IGNORE INTO emergency_updates (id, emergency_id, responder_id, update_text, status, created_at) VALUES
(1, 1, 1, 'Emergency reported: Student feeling dizzy and experiencing chest pain', 'pending', DATE_SUB(NOW(), INTERVAL 2 HOUR)),
(2, 1, 3, 'Health team dispatched to location. Student vitals being checked.', 'in_progress', DATE_SUB(NOW(), INTERVAL 1 HOUR 55 MINUTE)),
(3, 1, 3, 'Student stabilized and transferred to health center for observation.', 'resolved', DATE_SUB(NOW(), INTERVAL 1 HOUR 45 MINUTE)),
(4, 2, 2, 'Suspicious person reported at main gate', 'pending', DATE_SUB(NOW(), INTERVAL 1 DAY)),
(5, 2, 5, 'Security team deployed to investigate. Area being secured.', 'in_progress', DATE_SUB(NOW(), INTERVAL 1 DAY)),
(6, 2, 5, 'Individual identified as visitor. Situation resolved peacefully.', 'resolved', DATE_SUB(NOW(), INTERVAL 23 HOUR)),
(7, 3, 2, 'Fire emergency: Electrical short circuit in chemistry lab', 'pending', DATE_SUB(NOW(), INTERVAL 3 DAY)),
(8, 3, 4, 'Fire team on scene. Power disconnected. Area evacuated.', 'in_progress', DATE_SUB(NOW(), INTERVAL 2 DAY 23 HOUR)),
(9, 3, 4, 'Electrical issue resolved. Lab inspected and declared safe.', 'resolved', DATE_SUB(NOW(), INTERVAL 2 DAY 23 HOUR)),
(10, 4, 1, 'Accident reported: Student fell in hostel bathroom', 'pending', DATE_SUB(NOW(), INTERVAL 30 MINUTE)),
(11, 4, 3, 'Health team responding to hostel for medical assistance.', 'in_progress', DATE_SUB(NOW(), INTERVAL 25 MINUTE)),
(12, 5, 2, 'Theft reported: Laptop stolen from library', 'pending', DATE_SUB(NOW(), INTERVAL 5 MINUTE));

-- Insert sample notifications
INSERT IGNORE INTO notifications (id, user_id, emergency_id, title, message, type, priority, is_read, created_at, read_at) VALUES
(1, 1, 1, 'Emergency Response Update', 'Your reported medical emergency has been resolved. The student has been stabilized and transferred to the health center.', 'emergency_completed', 'medium', 1, DATE_SUB(NOW(), INTERVAL 1 HOUR 45 MINUTE), DATE_SUB(NOW(), INTERVAL 1 HOUR 30 MINUTE)),
(2, 1, 2, 'Security Incident Resolved', 'The suspicious person at the main gate has been identified and the situation resolved peacefully.', 'emergency_completed', 'medium', 1, DATE_SUB(NOW(), INTERVAL 23 HOUR), DATE_SUB(NOW(), INTERVAL 22 HOUR)),
(3, 2, 3, 'Fire Emergency Resolved', 'The electrical fire in the chemistry lab has been extinguished and the area is now safe.', 'emergency_completed', 'high', 1, DATE_SUB(NOW(), INTERVAL 2 DAY 23 HOUR), DATE_SUB(NOW(), INTERVAL 2 DAY 22 HOUR)),
(4, 1, 4, 'Medical Assistance in Progress', 'Health team is responding to your location to provide medical assistance.', 'emergency_status_update', 'medium', 0, DATE_SUB(NOW(), INTERVAL 25 MINUTE), NULL),
(5, 3, 1, 'New Emergency Assigned', 'Medical emergency reported at University Health Center requiring immediate attention.', 'emergency_assigned', 'high', 1, DATE_SUB(NOW(), INTERVAL 2 HOUR), DATE_SUB(NOW(), INTERVAL 1 HOUR 50 MINUTE)),
(6, 5, 2, 'Security Alert', 'Suspicious person reported at main gate. Security team deployed.', 'emergency_assigned', 'medium', 1, DATE_SUB(NOW(), INTERVAL 1 DAY), DATE_SUB(NOW(), INTERVAL 23 HOUR 30 MINUTE)),
(7, 4, 3, 'Fire Alert', 'Fire emergency reported at Science Laboratory Block. Immediate response required.', 'emergency_assigned', 'critical', 1, DATE_SUB(NOW(), INTERVAL 3 DAY), DATE_SUB(NOW(), INTERVAL 2 DAY 23 HOUR 30 MINUTE)),
(8, 3, 4, 'Medical Emergency', 'Accident reported at Student Hostel A. Student requires medical attention.', 'emergency_assigned', 'medium', 0, DATE_SUB(NOW(), INTERVAL 30 MINUTE), NULL),
(9, 2, 5, 'Theft Report', 'Laptop theft reported at Main Library. Security investigation required.', 'emergency_assigned', 'low', 0, DATE_SUB(NOW(), INTERVAL 5 MINUTE), NULL),
(10, 1, NULL, 'Welcome to ERS', 'Thank you for registering with the Yobe State University Emergency Response System.', 'system', 'low', 1, DATE_SUB(NOW(), INTERVAL 7 DAY), DATE_SUB(NOW(), INTERVAL 6 DAY 23 HOUR));

-- Create stored procedures for department statistics
DELIMITER //
CREATE PROCEDURE IF NOT EXISTS get_department_stats(
    IN dept_name VARCHAR(50),
    IN start_date DATE,
    IN end_date DATE
)
BEGIN
    SELECT
        et.name as emergency_type,
        COUNT(e.id) as total_count,
        SUM(CASE WHEN e.status IN ('pending', 'in_progress') THEN 1 ELSE 0 END) as active_count,
        SUM(CASE WHEN e.status = 'resolved' THEN 1 ELSE 0 END) as resolved_count,
        AVG(e.response_time_minutes) as avg_response_time,
        MIN(e.response_time_minutes) as min_response_time,
        MAX(e.response_time_minutes) as max_response_time
    FROM emergency_types et
    LEFT JOIN emergencies e ON et.id = e.emergency_type_id
        AND e.reported_at BETWEEN start_date AND end_date
    WHERE et.department = dept_name
    GROUP BY et.id, et.name
    ORDER BY total_count DESC;
END //
DELIMITER ;

-- Create triggers for automatic timestamp updates
DELIMITER //
CREATE TRIGGER IF NOT EXISTS before_user_update
BEFORE UPDATE ON users
FOR EACH ROW
BEGIN
    SET NEW.updated_at = NOW();
END //
DELIMITER ;

DELIMITER //
CREATE TRIGGER IF NOT EXISTS before_emergency_update
BEFORE UPDATE ON emergencies
FOR EACH ROW
BEGIN
    SET NEW.updated_at = NOW();
END //
DELIMITER ;

DELIMITER //
CREATE TRIGGER IF NOT EXISTS before_location_update
BEFORE UPDATE ON locations
FOR EACH ROW
BEGIN
    SET NEW.updated_at = NOW();
END //
DELIMITER ;

-- Create indexes for better performance
CREATE INDEX IF NOT EXISTS idx_emergencies_user_id ON emergencies(user_id);
CREATE INDEX IF NOT EXISTS idx_emergencies_status ON emergencies(status);
CREATE INDEX IF NOT EXISTS idx_emergencies_reported_at ON emergencies(reported_at);
CREATE INDEX IF NOT EXISTS idx_emergencies_type_location ON emergencies(emergency_type_id, location_id);
CREATE INDEX IF NOT EXISTS idx_notifications_user_id ON notifications(user_id);
CREATE INDEX IF NOT EXISTS idx_notifications_is_read ON notifications(is_read);
CREATE INDEX IF NOT EXISTS idx_emergency_updates_emergency_id ON emergency_updates(emergency_id);
CREATE INDEX IF NOT EXISTS idx_users_email ON users(email);
CREATE INDEX IF NOT EXISTS idx_users_school_id ON users(school_id);
CREATE INDEX IF NOT EXISTS idx_users_role ON users(role);

-- Set permissions for test data
-- Note: In production, these would be set according to security requirements
-- GRANT SELECT, INSERT, UPDATE ON emergencies TO 'ers_app'@'localhost';
-- GRANT SELECT, INSERT ON emergency_updates TO 'ers_app'@'localhost';
-- GRANT SELECT ON emergency_types TO 'ers_app'@'localhost';
-- GRANT SELECT ON locations TO 'ers_app'@'localhost';
-- GRANT SELECT ON users TO 'ers_app'@'localhost';
-- GRANT SELECT, INSERT, UPDATE ON notifications TO 'ers_app'@'localhost';

-- Test data insertion complete
SELECT 'Test data inserted successfully' as status;