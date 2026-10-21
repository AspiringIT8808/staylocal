USE staylocal_update;

-- Keep the old value temporarily so existing rows can be converted without data loss.
ALTER TABLE payments MODIFY payment_method ENUM('card','gcash','bank_transfer','qr_code') NOT NULL;
UPDATE payments SET payment_method='qr_code' WHERE payment_method='bank_transfer';
ALTER TABLE payments MODIFY payment_method ENUM('card','gcash','qr_code') NOT NULL;
