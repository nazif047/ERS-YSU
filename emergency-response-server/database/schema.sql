-- Yobe State University Emergency Response System Database Schema
-- Created: November 2024

-- Drop existing tables if they exist (for development)
SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS emergency_updates;
DROP TABLE IF EXISTS emergencies;
DROP TABLE IF EXISTS emergency_types;
DROP TABLE IF EXISTS locations;
DROP TABLE IF EXISTS users;
DROP TABLE IF EXISTS notifications;
SET FOREIGN_KEY_CHECKS = 1;

-- Users table - stores all user accounts including students, staff, and admins
CREATE TABLE users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    school_id VARCHAR(50) UNIQUE,
    email VARCHAR(100) UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    phone VARCHAR(20),
    role ENUM('student', 'staff', 'security_admin', 'health_admin', 'fire_admin', 'super_admin') DEFAULT 'student',
    department VARCHAR(100),
    is_active BOOLEAN DEFAULT TRUE,
    last_login TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_email (email),
    INDEX idx_school_id (school_id),
    INDEX idx_role (role),
    INDEX idx_active (is_active)
);

-- Campus locations table
CREATE TABLE locations (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(200) NOT NULL,
    category ENUM('academic', 'hostel', 'admin', 'recreational', 'medical', 'other') NOT NULL,
    description TEXT,
    latitude DECIMAL(10, 8),
    longitude DECIMAL(11, 8),
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_category (category),
    INDEX idx_active (is_active),
    INDEX idx_name (name)
);

-- Emergency types table
CREATE TABLE emergency_types (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(50) NOT NULL,
    department ENUM('health', 'fire', 'security') NOT NULL,
    description TEXT,
    icon VARCHAR(50),
    color VARCHAR(7) DEFAULT '#FF3B30',
    priority INT DEFAULT 1 COMMENT '1=low, 2=medium, 3=high, 4=critical',
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_department (department),
    INDEX idx_active (is_active),
    INDEX idx_priority (priority)
);

-- Emergency reports table
CREATE TABLE emergencies (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    emergency_type_id INT NOT NULL,
    location_id INT NOT NULL,
    description TEXT,
    status ENUM('pending', 'in_progress', 'resolved', 'closed') DEFAULT 'pending',
    severity ENUM('low', 'medium', 'high', 'critical') DEFAULT 'medium',
    latitude DECIMAL(10, 8),
    longitude DECIMAL(11, 8),
    assigned_to INT,
    reported_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    resolved_at TIMESTAMP NULL,

    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE RESTRICT,
    FOREIGN KEY (emergency_type_id) REFERENCES emergency_types(id) ON DELETE RESTRICT,
    FOREIGN KEY (location_id) REFERENCES locations(id) ON DELETE RESTRICT,
    FOREIGN KEY (assigned_to) REFERENCES users(id) ON DELETE SET NULL,

    INDEX idx_user (user_id),
    INDEX idx_emergency_type (emergency_type_id),
    INDEX idx_location (location_id),
    INDEX idx_status (status),
    INDEX idx_severity (severity),
    INDEX idx_assigned_to (assigned_to),
    INDEX idx_reported_at (reported_at)
);

-- Emergency status updates table
CREATE TABLE emergency_updates (
    id INT PRIMARY KEY AUTO_INCREMENT,
    emergency_id INT NOT NULL,
    responder_id INT NOT NULL,
    update_text TEXT NOT NULL,
    status ENUM('pending', 'in_progress', 'resolved', 'closed') NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (emergency_id) REFERENCES emergencies(id) ON DELETE CASCADE,
    FOREIGN KEY (responder_id) REFERENCES users(id) ON DELETE RESTRICT,

    INDEX idx_emergency (emergency_id),
    INDEX idx_responder (responder_id),
    INDEX idx_status (status),
    INDEX idx_created_at (created_at)
);

-- Notifications table
CREATE TABLE notifications (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    emergency_id INT,
    title VARCHAR(200) NOT NULL,
    message TEXT NOT NULL,
    type ENUM('emergency_assigned', 'status_update', 'emergency_resolved', 'system') DEFAULT 'emergency_assigned',
    is_read BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (emergency_id) REFERENCES emergencies(id) ON DELETE SET NULL,

    INDEX idx_user (user_id),
    INDEX idx_emergency (emergency_id),
    INDEX idx_type (type),
    INDEX idx_is_read (is_read),
    INDEX idx_created_at (created_at)
);

-- Create stored procedures for common operations

DELIMITER //

-- Procedure to get department-specific dashboard statistics
PROCEDURE get_department_stats(IN department_name VARCHAR(20))
BEGIN
    SELECT
        COUNT(CASE WHEN e.status = 'pending' THEN 1 END) as pending_cases,
        COUNT(CASE WHEN e.status = 'in_progress' THEN 1 END) as active_cases,
        COUNT(CASE WHEN e.status = 'resolved' AND DATE(e.resolved_at) = CURDATE() THEN 1 END) as resolved_today,
        COUNT(CASE WHEN e.reported_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR) THEN 1 END) as last_24_hours,
        AVG(TIMESTAMPDIFF(MINUTE, e.reported_at, e.resolved_at)) as avg_resolution_time_minutes
    FROM emergencies e
    JOIN emergency_types et ON e.emergency_type_id = et.id
    WHERE et.department = department_name
    AND e.reported_at >= DATE_SUB(NOW(), INTERVAL 30 DAY);
END //

-- Procedure to assign emergency to appropriate department
PROCEDURE assign_emergency_to_department(IN emergency_id INT, IN department_name VARCHAR(20))
BEGIN
    DECLARE assigned_user_id INT DEFAULT NULL;

    -- Find an available admin from the department
    SELECT id INTO assigned_user_id
    FROM users
    WHERE role = CONCAT(department_name, '_admin')
    AND is_active = TRUE
    ORDER BY last_login ASC
    LIMIT 1;

    -- Update emergency with assigned responder
    IF assigned_user_id IS NOT NULL THEN
        UPDATE emergencies
        SET assigned_to = assigned_user_id
        WHERE id = emergency_id;

        -- Create notification for assigned responder
        INSERT INTO notifications (user_id, emergency_id, title, message, type)
        VALUES (assigned_user_id, emergency_id,
                'New Emergency Assigned',
                'You have been assigned a new emergency case. Please respond immediately.',
                'emergency_assigned');
    END IF;
END //

DELIMITER ;

-- Create triggers for automatic timestamp updates

DELIMITER //

CREATE TRIGGER before_user_update
BEFORE UPDATE ON users
FOR EACH ROW
BEGIN
    SET NEW.updated_at = CURRENT_TIMESTAMP;
END //

CREATE TRIGGER before_location_update
BEFORE UPDATE ON locations
FOR EACH ROW
BEGIN
    SET NEW.updated_at = CURRENT_TIMESTAMP;
END //

DELIMITER ;