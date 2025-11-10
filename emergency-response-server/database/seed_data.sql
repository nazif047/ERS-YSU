-- Yobe State University Emergency Response System Seed Data
-- Initial data for locations, emergency types, and admin users

-- Insert campus locations
INSERT INTO locations (name, category, description, latitude, longitude) VALUES
-- Academic Buildings
('Lecture Theatre A', 'academic', 'Main lecture hall for science faculty with 500 seat capacity', 11.5123, 13.1546),
('Lecture Theatre B', 'academic', 'Secondary lecture hall for arts and social sciences', 11.5134, 13.1557),
('Faculty of Science', 'academic', 'Science faculty building with laboratories and classrooms', 11.5145, 13.1568),
('Faculty of Arts', 'academic', 'Arts and humanities faculty building', 11.5156, 13.1579),
('Faculty of Education', 'academic', 'Education faculty building with teaching labs', 11.5167, 13.1590),
('Faculty of Social Sciences', 'academic', 'Social sciences faculty building', 11.5178, 13.1601),
('University Library', 'academic', 'Main library building with study spaces and digital resources', 11.5189, 13.1612),
('ICT Centre', 'academic', 'Information and Communication Technology center with computer labs', 11.5200, 13.1623),
('Medical College', 'academic', 'College of Medicine with modern medical facilities', 11.5211, 13.1634),
('Engineering Building', 'academic', 'Faculty of Engineering with workshops and labs', 11.5222, 13.1645),

-- Hostel Accommodations
('Hostel A (Male)', 'hostel', 'Male undergraduate hostel accommodation for 200 students', 11.5233, 13.1656),
('Hostel B (Female)', 'hostel', 'Female undergraduate hostel accommodation for 200 students', 11.5244, 13.1667),
('Hostel C (Male)', 'hostel', 'Male postgraduate hostel accommodation', 11.5255, 13.1678),
('Hostel D (Female)', 'hostel', 'Female postgraduate hostel accommodation', 11.5266, 13.1689),
('Postgraduate Hostel', 'hostel', 'Mixed postgraduate hostel with family accommodations', 11.5277, 13.1690),

-- Administrative Buildings
('Administrative Block', 'admin', 'Main administrative building with vice-chancellor office', 11.5288, 13.1701),
('Bursary Department', 'admin', 'Financial and payments department', 11.5299, 13.1712),
('Registry Office', 'admin', 'Student records and academic registry', 11.5310, 13.1723),
('Student Affairs Division', 'admin', 'Student welfare and affairs department', 11.5321, 13.1734),

-- Recreational Facilities
('Sports Complex', 'recreational', 'Multi-purpose sports facility with gymnasium', 11.5332, 13.1745),
('Cafeteria', 'recreational', 'Main student cafeteria and dining hall', 11.5343, 13.1756),
('Students\' Union Building', 'recreational', 'Student union office and activity center', 11.5354, 13.1767),
('Health Centre', 'medical', 'Campus medical center with emergency services', 11.5365, 13.1778),

-- Other Locations
('Main Gate', 'other', 'University main entrance with security post', 11.5376, 13.1789),
('Parking Area A', 'other', 'Main parking area for academic buildings', 11.5387, 13.1790),
('Generator House', 'other', 'Power generation and backup systems', 11.5398, 13.1801),
('Water Treatment Plant', 'other', 'Campus water supply and treatment facility', 11.5409, 13.1812),
('Research Centre', 'academic', 'Advanced research and innovation center', 11.5420, 13.1823),
('Auditorium', 'recreational', 'Main university auditorium for events and ceremonies', 11.5431, 13.1834),
('Mosque', 'recreational', 'Central mosque for Friday prayers and daily worship', 11.5442, 13.1845),
('Campus Bank', 'admin', 'Branch bank serving university community', 11.5453, 13.1856);

-- Insert emergency types
INSERT INTO emergency_types (name, department, description, icon, color, priority) VALUES
-- Health Emergencies
('Medical Emergency', 'health', 'Serious medical situations requiring immediate attention', '🏥', '#4CD964', 4),
('Injury/Accident', 'health', 'Physical injuries requiring medical treatment', '🩹', '#4CD964', 3),
('Fainting/Collapse', 'health', 'Person who has fainted or collapsed', '😵', '#4CD964', 4),
('Allergic Reaction', 'health', 'Severe allergic reactions requiring medical help', '🤧', '#4CD964', 3),
('Chest Pain', 'health', 'Chest pain or breathing difficulties', '💔', '#4CD964', 4),
('Poisoning', 'health', 'Suspected poisoning or overdose', '☠️', '#4CD964', 4),
('Mental Health Crisis', 'health', 'Mental health emergency requiring immediate support', '🧠', '#4CD964', 3),

-- Fire Emergencies
('Fire Outbreak', 'fire', 'Active fire or visible smoke', '🔥', '#FF9500', 4),
('Fire Alarm', 'fire', 'Fire alarm activated or fire drill', '🚨', '#FF9500', 3),
('Burning Smell', 'fire', 'Strong smell of burning but no visible fire', '👃', '#FF9500', 2),
('Electrical Fire', 'fire', 'Fire from electrical equipment or wiring', '⚡', '#FF9500', 4),
('Gas Leak', 'fire', 'Suspected gas leak or gas smell', '💨', '#FF9500', 4),
('Explosion', 'fire', 'Explosion or blast incident', '💥', '#FF9500', 4),

-- Security Emergencies
('Security Threat', 'security', 'Immediate threat to personal safety', '⚠️', '#007AFF', 4),
('Theft/Robbery', 'security', 'Theft, robbery, or burglary in progress', '🔫', '#007AFF', 3),
('Assault/Fight', 'security', 'Physical assault or fight in progress', '🥊', '#007AFF', 3),
('Suspicious Person', 'security', 'Suspicious person or activity on campus', '👤', '#007AFF', 2),
('Vandalism', 'security', 'Property damage or vandalism in progress', '🔨', '#007AFF', 2),
('Harassment', 'security', 'Harassment or threatening behavior', '🚫', '#007AFF', 3),
('Unauthorized Access', 'security', 'Unauthorized person in restricted area', '🚷', '#007AFF', 2),
('Vehicle Accident', 'security', 'Motor vehicle accident on campus', '🚗', '#007AFF', 3);

-- Create default admin accounts (passwords will be hashed in the application)
-- Note: These should be changed immediately after deployment
INSERT INTO users (school_id, email, password_hash, full_name, phone, role, department) VALUES
('YSU/ADMIN/001', 'security@ysu.edu.ng', '$2y$10$example_hash_security', 'Security Department Admin', '08012345678', 'security_admin', 'Security'),
('YSU/ADMIN/002', 'health@ysu.edu.ng', '$2y$10$example_hash_health', 'Health Centre Admin', '08012345679', 'health_admin', 'Medical Centre'),
('YSU/ADMIN/003', 'fire@ysu.edu.ng', '$2y$10$example_hash_fire', 'Fire Safety Admin', '08012345680', 'fire_admin', 'Fire Safety'),
('YSU/ADMIN/000', 'superadmin@ysu.edu.ng', '$2y$10$example_hash_super', 'System Administrator', '08012345681', 'super_admin', 'ICT');

-- Create sample student accounts for testing
INSERT INTO users (school_id, email, password_hash, full_name, phone, role, department) VALUES
('YSU/2023/1001', 'john.doe@ysu.edu.ng', '$2y$10$example_hash_student', 'John Doe', '08012345682', 'student', 'Computer Science'),
('YSU/2023/1002', 'jane.smith@ysu.edu.ng', '$2y$10$example_hash_student', 'Jane Smith', '08012345683', 'student', 'Medicine'),
('YSU/2022/2001', 'ahmed.bello@ysu.edu.ng', '$2y$10$example_hash_student', 'Ahmed Bello', '08012345684', 'student', 'Engineering'),
('YSU/2021/3001', 'fatima.ibrahim@ysu.edu.ng', '$2y$10$example_hash_student', 'Fatima Ibrahim', '08012345685', 'student', 'Education');

-- Create sample staff accounts for testing
INSERT INTO users (school_id, email, password_hash, full_name, phone, role, department) VALUES
('YSU/STAFF/001', 'dr.mohammed@ysu.edu.ng', '$2y$10$example_hash_staff', 'Dr. Mohammed Ibrahim', '08012345686', 'staff', 'Computer Science'),
('YSU/STAFF/002', 'prof.aisha@ysu.edu.ng', '$2y$10$example_hash_staff', 'Prof. Aisha Hassan', '08012345687', 'staff', 'Medicine');