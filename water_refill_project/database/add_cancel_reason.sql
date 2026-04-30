-- Add cancel_reason column to tb_orders
ALTER TABLE tb_orders 
ADD COLUMN IF NOT EXISTS cancel_reason TEXT NULL AFTER order_status;
