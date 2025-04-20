-- Add status column to users table
ALTER TABLE users ADD COLUMN IF NOT EXISTS status ENUM('active', 'pending', 'suspended') NOT NULL DEFAULT 'active';

-- Update existing teacher accounts to pending status
UPDATE users SET status = 'pending' WHERE role = 'teacher' AND status = 'active';

-- Make sure all student accounts are active
UPDATE users SET status = 'active' WHERE role = 'student'; 