USE staylocal_update;
-- Adds starter policies and a demo tenant/bills without dropping application data.
-- Requires apartments.policies and tenant_bills; if missing, run migrate_add_billing_policies.sql first.
UPDATE apartments SET policies = '• QUIET HOURS — Keep noise low from 10:00 PM–7:00 AM in this walkable heritage district; take late gatherings indoors and keep voices down.
• OCCUPANCY — Only named residents may use the unit as a home. Discuss long-stay guests with management before they arrive.
• VISITORS & SECURITY — Follow sign-in rules and do not allow unverified visitors through secured entrances.
• PETS — Written approval is required before bringing a pet. Approved pets must be controlled on heritage walkways and waste removed immediately.
• HISTORIC FABRIC — No drilling, painting, signage, exterior changes, fixture replacement, or attachment to shared historic surfaces without written owner approval.
• FIRE & ACCESS — Keep narrow corridors, stairs, and exits clear. Confirm the building's evacuation plan and any access limitations before booking.
• WASTE — Seal household waste and use the designated area. Do not leave rubbish against historic walls, doors, or passageways.
• UTILITIES — Confirm whether each utility is metered or allocated, what period a bill covers, and the due date stated in the signed lease.
• WATER & HUMIDITY — Report leaks or moisture promptly; avoid drying wet items where they damage timber, walls, or neighboring property.
• DELIVERIES — Arrange large deliveries in advance to protect shared surfaces and keep public sidewalks and heritage access routes open.
• SMOKING — No smoking/vaping inside the unit or enclosed shared areas; comply with all local posted restrictions.
• PARKING — Parking is not promised unless specifically assigned in writing. Use legal public parking and never obstruct pedestrian paths.
• MOVE-IN RECORD — Complete a written condition/inventory checklist with photos before placing furniture or hanging items.
• Demo rules only. Verify current heritage/building restrictions, accessibility information, utilities, and owner-approved terms before signing.' WHERE id = 5 AND (policies IS NULL OR policies = '');
INSERT INTO tenants (full_name, email, phone, password_hash) VALUES
('Maria Santos (Demo)', 'tenant@staylocal.local', '0917 123 4567', '$2b$12$m2NTqhadLQtUI87.Qi.Ly.owmKvSq1hEKvVyc/qMb4U.B/2hbhcsO')
ON DUPLICATE KEY UPDATE id = LAST_INSERT_ID(id);
SET @staylocal_demo_tenant = LAST_INSERT_ID();
INSERT INTO reservations (tenant_id, unit_id, full_name, email, phone, move_in_date, notes, amount_due, status)
SELECT @staylocal_demo_tenant, 2, 'Maria Santos (Demo)', 'tenant@staylocal.local', '0917 123 4567', DATE_SUB(CURDATE(), INTERVAL 2 MONTH), 'Demo tenant record for dashboard preview.', 15800.00, 'paid'
WHERE EXISTS (SELECT 1 FROM units WHERE id=2) AND NOT EXISTS (SELECT 1 FROM reservations WHERE tenant_id=@staylocal_demo_tenant AND unit_id=2 AND status<>'cancelled');
INSERT INTO tenant_bills (tenant_id, unit_id, bill_type, description, amount, due_date, status, payment_reference, paid_at)
SELECT @staylocal_demo_tenant, 2, 'rent', CONCAT(DATE_FORMAT(CURDATE(), '%M %Y'), ' rent'), 15800.00, CURDATE(), 'unpaid', NULL, NULL
WHERE EXISTS (SELECT 1 FROM units WHERE id=2) AND NOT EXISTS (SELECT 1 FROM tenant_bills WHERE tenant_id=@staylocal_demo_tenant AND unit_id=2 AND bill_type='rent' AND due_date=CURDATE());
INSERT INTO tenant_bills (tenant_id, unit_id, bill_type, description, amount, due_date, status, payment_reference, paid_at)
SELECT @staylocal_demo_tenant, 2, 'electricity', 'Electricity demo bill - overdue', 1840.00, DATE_SUB(CURDATE(), INTERVAL 12 DAY), 'overdue', NULL, NULL
WHERE EXISTS (SELECT 1 FROM units WHERE id=2) AND NOT EXISTS (SELECT 1 FROM tenant_bills WHERE tenant_id=@staylocal_demo_tenant AND unit_id=2 AND bill_type='electricity' AND due_date=DATE_SUB(CURDATE(), INTERVAL 12 DAY));
INSERT INTO tenant_bills (tenant_id, unit_id, bill_type, description, amount, due_date, status, payment_reference, paid_at)
SELECT @staylocal_demo_tenant, 2, 'water', 'Water demo bill - paid', 620.00, DATE_SUB(CURDATE(), INTERVAL 5 DAY), 'paid', 'DEMO-WATER-001', NOW()
WHERE EXISTS (SELECT 1 FROM units WHERE id=2) AND NOT EXISTS (SELECT 1 FROM tenant_bills WHERE tenant_id=@staylocal_demo_tenant AND unit_id=2 AND bill_type='water' AND due_date=DATE_SUB(CURDATE(), INTERVAL 5 DAY));


-- Additional demo tenants, linked to existing occupied sample units. Shared demo password: tenant123.
INSERT INTO tenants (full_name,email,phone,password_hash) VALUES
('Luis Moreno (Demo)','luis@staylocal.local','0917 234 5678','$2b$12$m2NTqhadLQtUI87.Qi.Ly.owmKvSq1hEKvVyc/qMb4U.B/2hbhcsO'),
('Ana Reyes (Demo)','ana@staylocal.local','0917 345 6789','$2b$12$m2NTqhadLQtUI87.Qi.Ly.owmKvSq1hEKvVyc/qMb4U.B/2hbhcsO'),
('Rafael Cruz (Demo)','rafael@staylocal.local','0917 456 7890','$2b$12$m2NTqhadLQtUI87.Qi.Ly.owmKvSq1hEKvVyc/qMb4U.B/2hbhcsO')
ON DUPLICATE KEY UPDATE email=VALUES(email);
SET @staylocal_luis=(SELECT id FROM tenants WHERE email='luis@staylocal.local');
SET @staylocal_ana=(SELECT id FROM tenants WHERE email='ana@staylocal.local');
SET @staylocal_rafael=(SELECT id FROM tenants WHERE email='rafael@staylocal.local');
INSERT INTO reservations (tenant_id,unit_id,full_name,email,phone,move_in_date,notes,amount_due,status)
SELECT @staylocal_luis,9,'Luis Moreno (Demo)','luis@staylocal.local','0917 234 5678',DATE_SUB(CURDATE(),INTERVAL 4 MONTH),'Demo tenant for billing preview.',19500.00,'paid' WHERE EXISTS(SELECT 1 FROM units WHERE id=9) AND NOT EXISTS(SELECT 1 FROM reservations WHERE tenant_id=@staylocal_luis AND unit_id=9 AND status<>'cancelled');
INSERT INTO reservations (tenant_id,unit_id,full_name,email,phone,move_in_date,notes,amount_due,status)
SELECT @staylocal_ana,18,'Ana Reyes (Demo)','ana@staylocal.local','0917 345 6789',DATE_SUB(CURDATE(),INTERVAL 3 MONTH),'Demo tenant for billing preview.',14600.00,'paid' WHERE EXISTS(SELECT 1 FROM units WHERE id=18) AND NOT EXISTS(SELECT 1 FROM reservations WHERE tenant_id=@staylocal_ana AND unit_id=18 AND status<>'cancelled');
INSERT INTO reservations (tenant_id,unit_id,full_name,email,phone,move_in_date,notes,amount_due,status)
SELECT @staylocal_rafael,23,'Rafael Cruz (Demo)','rafael@staylocal.local','0917 456 7890',DATE_SUB(CURDATE(),INTERVAL 5 MONTH),'Demo tenant for billing preview.',17500.00,'paid' WHERE EXISTS(SELECT 1 FROM units WHERE id=23) AND NOT EXISTS(SELECT 1 FROM reservations WHERE tenant_id=@staylocal_rafael AND unit_id=23 AND status<>'cancelled');
INSERT INTO tenant_bills (tenant_id,unit_id,bill_type,description,amount,due_date,status,payment_reference,paid_at)
SELECT @staylocal_luis,9,'rent',CONCAT(DATE_FORMAT(CURDATE(),'%M %Y'),' rent'),19500.00,CURDATE(),'paid','DEMO-CARD-LUIS',NOW() WHERE EXISTS(SELECT 1 FROM units WHERE id=9) AND NOT EXISTS(SELECT 1 FROM tenant_bills WHERE tenant_id=@staylocal_luis AND bill_type='rent' AND due_date=CURDATE());
INSERT INTO tenant_bills (tenant_id,unit_id,bill_type,description,amount,due_date,status,payment_reference,paid_at)
SELECT @staylocal_luis,9,'water','Water bill - demo unpaid',740.00,DATE_ADD(CURDATE(),INTERVAL 4 DAY),'unpaid',NULL,NULL WHERE EXISTS(SELECT 1 FROM units WHERE id=9) AND NOT EXISTS(SELECT 1 FROM tenant_bills WHERE tenant_id=@staylocal_luis AND bill_type='water' AND due_date=DATE_ADD(CURDATE(),INTERVAL 4 DAY));
INSERT INTO tenant_bills (tenant_id,unit_id,bill_type,description,amount,due_date,status,payment_reference,paid_at)
SELECT @staylocal_ana,18,'rent',CONCAT(DATE_FORMAT(CURDATE(),'%M %Y'),' rent'),14600.00,CURDATE(),'submitted','DEMO-GCASH-ANA',NULL WHERE EXISTS(SELECT 1 FROM units WHERE id=18) AND NOT EXISTS(SELECT 1 FROM tenant_bills WHERE tenant_id=@staylocal_ana AND bill_type='rent' AND due_date=CURDATE());
INSERT INTO tenant_bills (tenant_id,unit_id,bill_type,description,amount,due_date,status,payment_reference,paid_at)
SELECT @staylocal_ana,18,'electricity','Electricity bill - demo unpaid',2120.00,DATE_ADD(CURDATE(),INTERVAL 3 DAY),'unpaid',NULL,NULL WHERE EXISTS(SELECT 1 FROM units WHERE id=18) AND NOT EXISTS(SELECT 1 FROM tenant_bills WHERE tenant_id=@staylocal_ana AND bill_type='electricity' AND due_date=DATE_ADD(CURDATE(),INTERVAL 3 DAY));
INSERT INTO tenant_bills (tenant_id,unit_id,bill_type,description,amount,due_date,status,payment_reference,paid_at)
SELECT @staylocal_rafael,23,'rent',CONCAT(DATE_FORMAT(CURDATE(),'%M %Y'),' rent'),17500.00,DATE_SUB(CURDATE(),INTERVAL 8 DAY),'overdue',NULL,NULL WHERE EXISTS(SELECT 1 FROM units WHERE id=23) AND NOT EXISTS(SELECT 1 FROM tenant_bills WHERE tenant_id=@staylocal_rafael AND bill_type='rent' AND due_date=DATE_SUB(CURDATE(),INTERVAL 8 DAY));
INSERT INTO tenant_bills (tenant_id,unit_id,bill_type,description,amount,due_date,status,payment_reference,paid_at)
SELECT @staylocal_rafael,23,'water','Water bill - demo paid',530.00,DATE_SUB(CURDATE(),INTERVAL 10 DAY),'paid','DEMO-BANK-RAFAEL',NOW() WHERE EXISTS(SELECT 1 FROM units WHERE id=23) AND NOT EXISTS(SELECT 1 FROM tenant_bills WHERE tenant_id=@staylocal_rafael AND bill_type='water' AND due_date=DATE_SUB(CURDATE(),INTERVAL 10 DAY));
