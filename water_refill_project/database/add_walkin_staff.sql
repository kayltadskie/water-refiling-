-- Add walkin_assigned column to tb_users for staff walk-in assignment
ALTER TABLE tb_users 
ADD COLUMN IF NOT EXISTS walkin_assigned TINYINT DEFAULT 0;
