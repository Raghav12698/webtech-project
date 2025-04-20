-- Disable foreign key checks temporarily
SET FOREIGN_KEY_CHECKS = 0;

-- Create temporary table to store existing users
CREATE TEMPORARY TABLE IF NOT EXISTS temp_users AS 
SELECT * FROM users;

-- Drop existing foreign keys
ALTER TABLE attendance
DROP FOREIGN KEY IF EXISTS fk_attendance_student;

ALTER TABLE marks
DROP FOREIGN KEY IF EXISTS fk_marks_student;

ALTER TABLE enrollments
DROP FOREIGN KEY IF EXISTS fk_enrollments_student;

-- Drop the existing users table
DROP TABLE IF EXISTS users;

-- Create the users table with the correct structure
CREATE TABLE users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin', 'teacher', 'student') NOT NULL,
    status ENUM('active', 'pending', 'suspended') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Restore existing users with their original IDs
INSERT INTO users (id, username, email, password, role, status, created_at)
SELECT 
    id,
    username,
    email,
    password,
    role,
    CASE 
        WHEN role = 'teacher' THEN 'pending'
        ELSE 'active'
    END as status,
    IFNULL(created_at, CURRENT_TIMESTAMP) as created_at
FROM temp_users
WHERE role IN ('student', 'teacher');

-- Insert admin user if not exists
INSERT IGNORE INTO users (username, email, password, role, status) VALUES 
('Admin', 'admin@admin.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', 'active');

-- Drop temporary table
DROP TEMPORARY TABLE IF EXISTS temp_users;

-- Recreate foreign key constraints
ALTER TABLE attendance
ADD CONSTRAINT fk_attendance_student
FOREIGN KEY (student_id) REFERENCES users(id)
ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE marks
ADD CONSTRAINT fk_marks_student
FOREIGN KEY (student_id) REFERENCES users(id)
ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE enrollments
ADD CONSTRAINT fk_enrollments_student
FOREIGN KEY (student_id) REFERENCES users(id)
ON DELETE CASCADE ON UPDATE CASCADE;

-- Re-enable foreign key checks
SET FOREIGN_KEY_CHECKS = 1; 