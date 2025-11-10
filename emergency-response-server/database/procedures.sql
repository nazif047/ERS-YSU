-- Additional Stored Procedures for Emergency Response System
-- Yobe State University

DELIMITER //

-- Get user emergency history
CREATE PROCEDURE IF NOT EXISTS get_user_emergency_history(
    IN user_id_param INT,
    IN limit_count INT DEFAULT 10,
    IN offset_count INT DEFAULT 0
)
BEGIN
    SELECT
        e.id,
        e.description,
        e.status,
        e.severity,
        e.reported_at,
        e.resolved_at,
        e.response_time_minutes,
        et.name as emergency_type,
        et.department as emergency_department,
        et.icon as emergency_icon,
        et.color as emergency_color,
        l.name as location_name,
        l.category as location_category,
        assigned_user.full_name as assigned_responder_name,
        (SELECT COUNT(*) FROM emergency_updates eu WHERE eu.emergency_id = e.id) as update_count
    FROM emergencies e
    JOIN emergency_types et ON e.emergency_type_id = et.id
    JOIN locations l ON e.location_id = l.id
    LEFT JOIN users assigned_user ON e.assigned_to = assigned_user.id
    WHERE e.user_id = user_id_param
    ORDER BY e.reported_at DESC
    LIMIT limit_count OFFSET offset_count;
END //

-- Get department workload
CREATE PROCEDURE IF NOT EXISTS get_department_workload(
    IN dept_name VARCHAR(50),
    IN time_period_hours INT DEFAULT 24
)
BEGIN
    SELECT
        u.id as responder_id,
        u.full_name as responder_name,
        u.role,
        COUNT(e.id) as assigned_count,
        SUM(CASE WHEN e.status IN ('pending', 'in_progress') THEN 1 ELSE 0 END) as active_count,
        SUM(CASE WHEN e.status = 'resolved' THEN 1 ELSE 0 END) as resolved_count,
        AVG(e.response_time_minutes) as avg_response_time,
        MAX(e.reported_at) as last_assignment,
        TIMESTAMPDIFF(MINUTE, MAX(e.reported_at), NOW()) as minutes_since_last_assignment
    FROM users u
    LEFT JOIN emergencies e ON u.id = e.assigned_to
        AND e.reported_at >= DATE_SUB(NOW(), INTERVAL time_period_hours HOUR)
    JOIN emergency_types et ON e.emergency_type_id = et.id
    WHERE (u.role = CONCAT(dept_name, '_admin') OR u.role = 'super_admin')
        AND u.is_active = 1
    GROUP BY u.id, u.full_name, u.role
    ORDER BY active_count DESC, assigned_count DESC;
END //

-- Get location emergency statistics
CREATE PROCEDURE IF NOT EXISTS get_location_emergency_stats(
    IN location_id_param INT,
    IN days_period INT DEFAULT 30
)
BEGIN
    SELECT
        l.id as location_id,
        l.name as location_name,
        l.category as location_category,
        COUNT(e.id) as total_emergencies,
        SUM(CASE WHEN e.severity = 'critical' THEN 1 ELSE 0 END) as critical_emergencies,
        SUM(CASE WHEN e.severity = 'high' THEN 1 ELSE 0 END) as high_emergencies,
        SUM(CASE WHEN e.status IN ('pending', 'in_progress') THEN 1 ELSE 0 END) as active_emergencies,
        AVG(e.response_time_minutes) as avg_response_time,
        MIN(e.response_time_minutes) as min_response_time,
        MAX(e.response_time_minutes) as max_response_time,
        COUNT(DISTINCT e.user_id) as unique_reporters,
        COUNT(DISTINCT et.id) as emergency_types_involved
    FROM locations l
    LEFT JOIN emergencies e ON l.id = e.location_id
        AND e.reported_at >= DATE_SUB(NOW(), INTERVAL days_period DAY)
    LEFT JOIN emergency_types et ON e.emergency_type_id = et.id
    WHERE l.id = location_id_param
    GROUP BY l.id, l.name, l.category;
END //

-- Clean up old notifications
CREATE PROCEDURE IF NOT EXISTS cleanup_old_notifications(
    IN days_to_keep INT DEFAULT 90
)
BEGIN
    DECLARE deleted_count INT DEFAULT 0;

    DELETE FROM notifications
    WHERE created_at < DATE_SUB(NOW(), INTERVAL days_to_keep DAY)
        AND is_read = 1;

    SET deleted_count = ROW_COUNT();

    SELECT deleted_count as notifications_deleted;
END //

-- Update response metrics
CREATE PROCEDURE IF NOT EXISTS update_response_metrics(
    IN emergency_id_param INT
)
BEGIN
    DECLARE emergency_status VARCHAR(20);
    DECLARE response_time_minutes DECIMAL(10,2);

    -- Get current emergency status and calculate response time if resolved
    SELECT status, TIMESTAMPDIFF(MINUTE, reported_at, COALESCE(resolved_at, NOW())) as response_time
    INTO emergency_status, response_time_minutes
    FROM emergencies
    WHERE id = emergency_id_param;

    -- Update response time only for resolved emergencies
    IF emergency_status IN ('resolved', 'closed') THEN
        UPDATE emergencies
        SET response_time_minutes = response_time_minutes,
            updated_at = NOW()
        WHERE id = emergency_id_param AND response_time_minutes IS NULL;
    END IF;

    SELECT
        emergency_id_param as emergency_id,
        emergency_status as current_status,
        response_time_minutes as calculated_response_time;
END //

-- Get emergency type distribution by department
CREATE PROCEDURE IF NOT EXISTS get_emergency_type_distribution(
    IN start_date DATE,
    IN end_date DATE,
    IN department_filter VARCHAR(50) DEFAULT NULL
)
BEGIN
    SELECT
        et.department,
        et.name as emergency_type,
        COUNT(e.id) as total_count,
        SUM(CASE WHEN e.severity = 'critical' THEN 1 ELSE 0 END) as critical_count,
        SUM(CASE WHEN e.severity = 'high' THEN 1 ELSE 0 END) as high_count,
        SUM(CASE WHEN e.status = 'resolved' THEN 1 ELSE 0 END) as resolved_count,
        AVG(e.response_time_minutes) as avg_response_time,
        ROUND(COUNT(e.id) * 100.0 / SUM(COUNT(e.id)) OVER (PARTITION BY et.department), 2) as percentage_in_department
    FROM emergency_types et
    LEFT JOIN emergencies e ON et.id = e.emergency_type_id
        AND e.reported_at BETWEEN start_date AND end_date
    WHERE et.is_active = 1
        AND (department_filter IS NULL OR et.department = department_filter)
    GROUP BY et.department, et.id, et.name
    ORDER BY et.department, total_count DESC;
END //

-- Get responder performance metrics
CREATE PROCEDURE IF NOT EXISTS get_responder_performance(
    IN responder_id_param INT,
    IN days_period INT DEFAULT 30
)
BEGIN
    SELECT
        u.id as responder_id,
        u.full_name as responder_name,
        u.role,
        COUNT(e.id) as total_assigned,
        SUM(CASE WHEN e.status = 'resolved' THEN 1 ELSE 0 END) as resolved_count,
        SUM(CASE WHEN e.status IN ('pending', 'in_progress') THEN 1 ELSE 0 END) as active_count,
        AVG(e.response_time_minutes) as avg_response_time,
        MIN(e.response_time_minutes) as fastest_response,
        MAX(e.response_time_minutes) as slowest_response,
        ROUND((SUM(CASE WHEN e.status = 'resolved' THEN 1 ELSE 0 END) / COUNT(e.id)) * 100, 2) as resolution_rate,
        AVG(TIMESTAMPDIFF(MINUTE, e.reported_at, (
            SELECT MIN(created_at)
            FROM emergency_updates eu
            WHERE eu.emergency_id = e.id AND eu.responder_id = u.id
        ))) as avg_first_response_time
    FROM users u
    LEFT JOIN emergencies e ON u.id = e.assigned_to
        AND e.reported_at >= DATE_SUB(NOW(), INTERVAL days_period DAY)
    WHERE u.id = responder_id_param
    GROUP BY u.id, u.full_name, u.role;
END //

-- Get campus safety overview
CREATE PROCEDURE IF NOT EXISTS get_campus_safety_overview(
    IN days_period INT DEFAULT 7
)
BEGIN
    SELECT
        'overall' as metric_type,
        COUNT(e.id) as total_emergencies,
        SUM(CASE WHEN e.severity = 'critical' THEN 1 ELSE 0 END) as critical_emergencies,
        SUM(CASE WHEN e.status IN ('pending', 'in_progress') THEN 1 ELSE 0 END) as active_emergencies,
        AVG(e.response_time_minutes) as avg_response_time,
        COUNT(DISTINCT e.location_id) as affected_locations,
        COUNT(DISTINCT DATE(e.reported_at)) as days_with_emergencies,
        ROUND(COUNT(e.id) / days_period, 2) as daily_average
    FROM emergencies e
    WHERE e.reported_at >= DATE_SUB(NOW(), INTERVAL days_period DAY)

    UNION ALL

    SELECT
        et.department as metric_type,
        COUNT(e.id) as total_emergencies,
        SUM(CASE WHEN e.severity = 'critical' THEN 1 ELSE 0 END) as critical_emergencies,
        SUM(CASE WHEN e.status IN ('pending', 'in_progress') THEN 1 ELSE 0 END) as active_emergencies,
        AVG(e.response_time_minutes) as avg_response_time,
        COUNT(DISTINCT e.location_id) as affected_locations,
        COUNT(DISTINCT DATE(e.reported_at)) as days_with_emergencies,
        ROUND(COUNT(e.id) / days_period, 2) as daily_average
    FROM emergency_types et
    LEFT JOIN emergencies e ON et.id = e.emergency_type_id
        AND e.reported_at >= DATE_SUB(NOW(), INTERVAL days_period DAY)
    WHERE et.is_active = 1
    GROUP BY et.department
    ORDER BY metric_type, total_emergencies DESC;
END //

-- Get user activity summary
CREATE PROCEDURE IF NOT EXISTS get_user_activity_summary(
    IN user_id_param INT,
    IN days_period INT DEFAULT 30
)
BEGIN
    SELECT
        u.id as user_id,
        u.full_name,
        u.email,
        u.role,
        u.department,
        u.last_login,
        u.created_at as user_since,
        COUNT(DISTINCT e.id) as total_emergencies_reported,
        SUM(CASE WHEN e.reported_at >= DATE_SUB(NOW(), INTERVAL days_period DAY) THEN 1 ELSE 0 END) as recent_emergencies,
        COUNT(DISTINCT n.id) as total_notifications,
        SUM(CASE WHEN n.created_at >= DATE_SUB(NOW(), INTERVAL days_period DAY) THEN 1 ELSE 0 END) as recent_notifications,
        SUM(CASE WHEN n.is_read = 0 AND n.created_at >= DATE_SUB(NOW(), INTERVAL days_period DAY) THEN 1 ELSE 0 END) as unread_notifications,
        AVG(e.response_time_minutes) as avg_emergency_response_time,
        MAX(e.reported_at) as last_emergency_reported
    FROM users u
    LEFT JOIN emergencies e ON u.id = e.user_id
    LEFT JOIN notifications n ON u.id = n.user_id
    WHERE u.id = user_id_param
    GROUP BY u.id, u.full_name, u.email, u.role, u.department, u.last_login, u.created_at;
END //

-- Auto-assign emergency to least busy responder
CREATE PROCEDURE IF NOT EXISTS auto_assign_emergency(
    IN emergency_id_param INT,
    IN department_name VARCHAR(50)
)
BEGIN
    DECLARE assigned_responder_id INT;

    -- Find the least busy responder in the department
    SELECT u.id INTO assigned_responder_id
    FROM users u
    LEFT JOIN emergencies e ON u.id = e.assigned_to
        AND e.status IN ('pending', 'in_progress')
    WHERE u.role = CONCAT(department_name, '_admin')
        AND u.is_active = 1
    GROUP BY u.id, u.full_name
    ORDER BY COUNT(e.id) ASC, u.last_login ASC
    LIMIT 1;

    -- Assign the emergency if a responder is found
    IF assigned_responder_id IS NOT NULL THEN
        UPDATE emergencies
        SET assigned_to = assigned_responder_id,
            updated_at = NOW()
        WHERE id = emergency_id_param;

        -- Create notification for the assigned responder
        INSERT INTO notifications (user_id, emergency_id, title, message, type, priority, created_at)
        SELECT
            assigned_responder_id,
            emergency_id_param,
            'New Emergency Assigned',
            CONCAT(
                'A new emergency has been assigned to you. Type: ',
                (SELECT et.name FROM emergency_types et
                 JOIN emergencies e ON et.id = e.emergency_type_id
                 WHERE e.id = emergency_id_param),
                ', Location: ',
                (SELECT l.name FROM locations l
                 JOIN emergencies e ON l.id = e.location_id
                 WHERE e.id = emergency_id_param)
            ),
            'emergency_assigned',
            'high',
            NOW();
    END IF;

    SELECT assigned_responder_id as responder_id;
END //

DELIMITER ;

-- Create optimized views for common queries
CREATE OR REPLACE VIEW emergency_summary_view AS
SELECT
    e.id,
    e.description,
    e.status,
    e.severity,
    e.reported_at,
    e.resolved_at,
    e.response_time_minutes,
    et.name as emergency_type,
    et.department as emergency_department,
    et.icon as emergency_icon,
    et.color as emergency_color,
    l.name as location_name,
    l.category as location_category,
    u.full_name as reporter_name,
    u.department as reporter_department,
    assigned_user.full_name as assigned_responder_name,
    TIMESTAMPDIFF(MINUTE, e.reported_at, NOW()) as minutes_since_reported,
    (SELECT COUNT(*) FROM emergency_updates eu WHERE eu.emergency_id = e.id) as update_count
FROM emergencies e
JOIN emergency_types et ON e.emergency_type_id = et.id
JOIN locations l ON e.location_id = l.id
JOIN users u ON e.user_id = u.id
LEFT JOIN users assigned_user ON e.assigned_to = assigned_user.id;

CREATE OR REPLACE VIEW user_dashboard_view AS
SELECT
    u.id as user_id,
    u.full_name,
    u.email,
    u.role,
    u.department,
    COUNT(e.id) as total_emergencies,
    SUM(CASE WHEN e.status IN ('pending', 'in_progress') THEN 1 ELSE 0 END) as active_emergencies,
    SUM(CASE WHEN e.reported_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 1 ELSE 0 END) as recent_emergencies,
    AVG(e.response_time_minutes) as avg_response_time,
    COUNT(n.id) as total_notifications,
    SUM(CASE WHEN n.is_read = 0 THEN 1 ELSE 0 END) as unread_notifications,
    MAX(e.reported_at) as last_emergency_reported
FROM users u
LEFT JOIN emergencies e ON u.id = e.user_id
LEFT JOIN notifications n ON u.id = n.user_id
GROUP BY u.id, u.full_name, u.email, u.role, u.department;

CREATE OR REPLACE VIEW department_workload_view AS
SELECT
    et.department,
    COUNT(e.id) as total_emergencies,
    SUM(CASE WHEN e.status IN ('pending', 'in_progress') THEN 1 ELSE 0 END) as active_emergencies,
    SUM(CASE WHEN e.severity = 'critical' THEN 1 ELSE 0 END) as critical_emergencies,
    AVG(e.response_time_minutes) as avg_response_time,
    COUNT(DISTINCT e.assigned_to) as active_responders,
    COUNT(DISTINCT DATE(e.reported_at)) as days_with_emergencies
FROM emergency_types et
LEFT JOIN emergencies e ON et.id = e.emergency_type_id
WHERE et.is_active = 1
GROUP BY et.department;

-- Performance indexes for stored procedures
CREATE INDEX IF NOT EXISTS idx_emergencies_department_type ON emergencies(emergency_type_id)
INCLUDE (status, severity, reported_at, response_time_minutes);

CREATE INDEX IF NOT EXISTS idx_emergencies_responder_status ON emergencies(assigned_to, status)
INCLUDE (reported_at, emergency_type_id);

CREATE INDEX IF NOT EXISTS idx_notifications_user_unread ON notifications(user_id, is_read, created_at);

CREATE INDEX IF NOT EXISTS idx_emergency_updates_responder_time ON emergency_updates(responder_id, created_at);

CREATE INDEX IF NOT EXISTS idx_users_role_active ON users(role, is_active)
INCLUDE (full_name, last_login);

SELECT 'All stored procedures and views created successfully' as status;