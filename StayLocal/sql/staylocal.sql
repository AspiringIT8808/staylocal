CREATE DATABASE IF NOT EXISTS staylocal_update CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE staylocal_update;

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS users;
DROP TABLE IF EXISTS apartment_amenities;
DROP TABLE IF EXISTS tenant_bills;
DROP TABLE IF EXISTS payments;
DROP TABLE IF EXISTS tenants;
DROP TABLE IF EXISTS admin_users;
DROP TABLE IF EXISTS reservations;
DROP TABLE IF EXISTS visits;
DROP TABLE IF EXISTS unit_images;
DROP TABLE IF EXISTS units;
DROP TABLE IF EXISTS apartments;
SET FOREIGN_KEY_CHECKS = 1;

CREATE TABLE admin_users (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    full_name VARCHAR(120) NOT NULL,
    email VARCHAR(180) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    last_login_at TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY unique_admin_email (email)
) ENGINE=InnoDB;


CREATE TABLE apartments (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(120) NOT NULL,
    area VARCHAR(100) NOT NULL,
    address VARCHAR(180) NOT NULL,
    description TEXT NOT NULL,
    policies TEXT DEFAULT NULL,
    pet_friendly TINYINT(1) NOT NULL DEFAULT 0,
    image_url VARCHAR(255) NOT NULL DEFAULT 'assets/feat-view.jpg',
    featured TINYINT(1) NOT NULL DEFAULT 0,
    PRIMARY KEY (id),
    INDEX idx_apartments_area (area),
    INDEX idx_apartments_pet_friendly (pet_friendly)
) ENGINE=InnoDB;

CREATE TABLE apartment_amenities (
    apartment_id INT UNSIGNED NOT NULL,
    amenity ENUM('parking','beach','elevator','pwd_accessible') NOT NULL,
    PRIMARY KEY (apartment_id, amenity),
    CONSTRAINT fk_apartment_amenity_apartment FOREIGN KEY (apartment_id) REFERENCES apartments(id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB;

CREATE TABLE units (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    apartment_id INT UNSIGNED NOT NULL,
    floor_number TINYINT UNSIGNED NOT NULL,
    unit_number VARCHAR(20) NOT NULL,
    unit_type VARCHAR(60) NOT NULL,
    size_sqm DECIMAL(6,2) NOT NULL,
    monthly_rent DECIMAL(10,2) NOT NULL,
    image_url VARCHAR(255) DEFAULT NULL,
    features TEXT DEFAULT NULL,
    status ENUM('available', 'occupied', 'reserved') NOT NULL DEFAULT 'available',
    PRIMARY KEY (id),
    UNIQUE KEY unique_apartment_unit (apartment_id, unit_number),
    INDEX idx_units_status (status),
    CONSTRAINT fk_units_apartment FOREIGN KEY (apartment_id) REFERENCES apartments(id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB;


CREATE TABLE unit_images (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    unit_id INT UNSIGNED NOT NULL,
    image_path VARCHAR(255) NOT NULL,
    caption VARCHAR(100) DEFAULT NULL,
    sort_order TINYINT UNSIGNED NOT NULL DEFAULT 0,
    PRIMARY KEY (id),
    CONSTRAINT fk_images_unit FOREIGN KEY (unit_id) REFERENCES units(id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB;

CREATE TABLE tenants (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    full_name VARCHAR(140) NOT NULL,
    email VARCHAR(180) NOT NULL,
    phone VARCHAR(40) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    last_login_at TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY unique_tenant_email (email)
) ENGINE=InnoDB;

CREATE TABLE reservations (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    tenant_id INT UNSIGNED NULL,
    unit_id INT UNSIGNED NOT NULL,
    full_name VARCHAR(140) NOT NULL,
    email VARCHAR(180) NOT NULL,
    phone VARCHAR(40) NOT NULL,
    move_in_date DATE NOT NULL,
    notes TEXT DEFAULT NULL,
    amount_due DECIMAL(10,2) NOT NULL,
    status ENUM('pending_payment', 'paid', 'cancelled') NOT NULL DEFAULT 'pending_payment',
    PRIMARY KEY (id),
    INDEX idx_reservations_unit (unit_id),
    INDEX idx_reservations_tenant (tenant_id),
    INDEX idx_reservations_status (status),
    INDEX idx_reservations_unit_status (unit_id, status),
    CONSTRAINT fk_reservations_unit FOREIGN KEY (unit_id) REFERENCES units(id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_reservations_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB;

CREATE TABLE payments (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    reservation_id INT UNSIGNED NOT NULL,
    payment_method ENUM('card', 'gcash', 'qr_code') NOT NULL,
    transaction_reference VARCHAR(80) NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    status ENUM('paid', 'failed', 'refunded') NOT NULL DEFAULT 'paid',
    paid_at TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY unique_transaction_reference (transaction_reference),
    INDEX idx_payments_reservation (reservation_id),
    CONSTRAINT fk_payments_reservation FOREIGN KEY (reservation_id) REFERENCES reservations(id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB;

CREATE TABLE tenant_bills (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    tenant_id INT UNSIGNED NOT NULL,
    unit_id INT UNSIGNED NOT NULL,
    bill_type ENUM('water','electricity','rent','other') NOT NULL,
    description VARCHAR(180) NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    due_date DATE NOT NULL,
    payment_reference VARCHAR(100) DEFAULT NULL,
    status ENUM('unpaid','submitted','paid','overdue') NOT NULL DEFAULT 'unpaid',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    paid_at TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (id), INDEX idx_tenant_bills_tenant_status (tenant_id, status), INDEX idx_tenant_bills_due (due_date),
    CONSTRAINT fk_tenant_bills_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_tenant_bills_unit FOREIGN KEY (unit_id) REFERENCES units(id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB;

CREATE TABLE visits (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    unit_id INT UNSIGNED NOT NULL,
    full_name VARCHAR(140) NOT NULL,
    email VARCHAR(180) NOT NULL,
    phone VARCHAR(40) NOT NULL,
    visit_date DATE NOT NULL,
    visit_time TIME NOT NULL,
    notes TEXT DEFAULT NULL,
    status ENUM('scheduled', 'completed', 'cancelled') NOT NULL DEFAULT 'scheduled',
    PRIMARY KEY (id),
    UNIQUE KEY unique_visit_slot (visit_date, visit_time),
    INDEX idx_visits_unit (unit_id),
    INDEX idx_visits_date_status (visit_date, status),
    CONSTRAINT fk_visits_unit FOREIGN KEY (unit_id) REFERENCES units(id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB;

INSERT INTO admin_users (full_name, email, password_hash) VALUES
('StayLocal Administrator', 'admin@staylocal.local', '$2y$10$9DNAz2Wfck.CSBQ6LmxdnuIfmbAEFmsH9ShsvbEnnA7GheZ.E96z.');


INSERT INTO apartments (name, area, address, description, pet_friendly, image_url, featured) VALUES
('Casa Pilar', 'Sampaloc', 'P. Campa Street, Manila', 'A light-filled, lived-in building close to university routes, neighborhood groceries, and the small cafés that make Sampaloc feel like home.', 1, 'assets/feat-view.jpg', 1),
('Northline Residences', 'Quezon City', 'Tomas Morato Avenue, Quezon City', 'A practical residence for renters who want a central address, generous common corridors, and a quieter place to land after a busy day.', 0, 'assets/feat-bedroom.jpg', 1),
('The Linden House', 'Ermita', 'Del Pilar Street, Manila', 'An older Manila address with high ceilings, compact layouts, and easy access to transit, shops, and the city’s waterfront edge.', 1, 'assets/feat-kitchen.jpg', 0),
('Mabini Court', 'Malate', 'Mabini Street, Manila', 'A calm, low-rise apartment court with flexible one-bedroom homes and a tucked-away location near the everyday rhythm of Malate.', 0, 'assets/feat-view.jpg', 0),
('Rizal Park Lofts', 'Intramuros', 'General Luna Street, Manila', 'A characterful building for renters who enjoy walkable heritage streets, smaller floor plans, and a home base in the heart of old Manila.', 1, 'assets/feat-bedroom.jpg', 0),
('Coral Point Access Residences', 'Laiya Coast, San Juan, Batangas', 'Demo Seaside Road, Laiya, San Juan, Batangas', 'DEMO LISTING — Coastal apartment concept with marked parking, elevator service, step-free shared access, and nearby Laiya beach destinations. Confirm exact site, shoreline route, and accessibility before publication. Gallery photos are generic sample placeholders and must be replaced before publication.', 0, 'assets/feat-view.jpg', 0);

INSERT INTO apartment_amenities (apartment_id,amenity) VALUES
(1,'parking'),(2,'parking'),(2,'elevator'),(3,'elevator'),(4,'pwd_accessible'),(5,'parking'),(5,'elevator'),
(6,'parking'),(6,'beach'),(6,'elevator'),(6,'pwd_accessible');

INSERT INTO units (apartment_id, floor_number, unit_number, unit_type, size_sqm, monthly_rent, image_url, features, status) VALUES
(1, 1, '101', 'Studio', 24.00, 12500.00, 'assets/unit-studio.jpg', 'Fiber Ready, Kitchenette, Air Conditioning, Built-in Wardrobe', 'available'),
(1, 1, '102', '1 Bedroom', 31.00, 15800.00, 'assets/unit-1br.jpg', 'Fiber Ready, Separate Bedroom, Full Kitchen, Balcony, Air Conditioning', 'occupied'),
(1, 2, '201', 'Studio', 25.00, 13000.00, 'assets/unit-studio.jpg', 'Fiber Ready, Kitchenette, Air Conditioning, Built-in Wardrobe', 'available'),
(1, 2, '202', '1 Bedroom', 34.00, 16900.00, 'assets/unit-1br.jpg', 'Fiber Ready, Separate Bedroom, Full Kitchen, Balcony, Air Conditioning', 'available'),
(1, 3, '301', '2 Bedroom', 51.00, 24500.00, 'assets/unit-2br.jpg', 'Fiber Ready, Master Suite, Second Bedroom, Full Kitchen, Large Balcony, Air Conditioning', 'available'),
(1, 3, '302', '1 Bedroom', 34.00, 17200.00, 'assets/unit-1br.jpg', 'Fiber Ready, Separate Bedroom, Full Kitchen, Balcony, Air Conditioning', 'reserved'),
(2, 1, '1A', 'Studio', 22.00, 14000.00, 'assets/unit-studio.jpg', 'Fiber Ready, Kitchenette, Air Conditioning, Built-in Wardrobe', 'available'),
(2, 1, '1B', '1 Bedroom', 36.00, 18800.00, 'assets/unit-1br.jpg', 'Fiber Ready, Separate Bedroom, Full Kitchen, Balcony, Air Conditioning', 'available'),
(2, 2, '2A', '1 Bedroom', 38.00, 19500.00, 'assets/unit-1br.jpg', 'Fiber Ready, Separate Bedroom, Full Kitchen, Balcony, Air Conditioning', 'occupied'),
(2, 2, '2B', '2 Bedroom', 55.00, 27500.00, 'assets/unit-2br.jpg', 'Fiber Ready, Master Suite, Second Bedroom, Full Kitchen, Large Balcony, Air Conditioning', 'available'),
(2, 3, '3A', '1 Bedroom', 38.00, 19800.00, 'assets/unit-1br.jpg', 'Fiber Ready, Separate Bedroom, Full Kitchen, Balcony, Air Conditioning', 'available'),
(2, 3, '3B', '2 Bedroom', 57.00, 28200.00, 'assets/unit-2br.jpg', 'Fiber Ready, Master Suite, Second Bedroom, Full Kitchen, Large Balcony, Air Conditioning', 'occupied'),
(3, 1, '101', 'Studio', 21.00, 11800.00, 'assets/unit-studio.jpg', 'Fiber Ready, Kitchenette, Air Conditioning, Built-in Wardrobe', 'available'),
(3, 1, '102', 'Studio', 22.00, 12100.00, 'assets/unit-studio.jpg', 'Fiber Ready, Kitchenette, Air Conditioning, Built-in Wardrobe', 'available'),
(3, 2, '201', '1 Bedroom', 33.00, 16500.00, 'assets/unit-1br.jpg', 'Fiber Ready, Separate Bedroom, Full Kitchen, Balcony, Air Conditioning', 'available'),
(3, 2, '202', '1 Bedroom', 35.00, 17100.00, 'assets/unit-1br.jpg', 'Fiber Ready, Separate Bedroom, Full Kitchen, Balcony, Air Conditioning', 'reserved'),
(3, 3, '301', '2 Bedroom', 49.00, 23400.00, 'assets/unit-2br.jpg', 'Fiber Ready, Master Suite, Second Bedroom, Full Kitchen, Large Balcony, Air Conditioning', 'available'),
(4, 1, '101', 'Studio', 20.00, 11000.00, 'assets/unit-studio.jpg', 'Fiber Ready, Kitchenette, Air Conditioning, Built-in Wardrobe', 'available'),
(4, 1, '102', '1 Bedroom', 29.00, 14600.00, 'assets/unit-1br.jpg', 'Fiber Ready, Separate Bedroom, Full Kitchen, Balcony, Air Conditioning', 'occupied'),
(4, 2, '201', '1 Bedroom', 31.00, 15200.00, 'assets/unit-1br.jpg', 'Fiber Ready, Separate Bedroom, Full Kitchen, Balcony, Air Conditioning', 'available'),
(4, 2, '202', '1 Bedroom', 31.00, 15200.00, 'assets/unit-1br.jpg', 'Fiber Ready, Separate Bedroom, Full Kitchen, Balcony, Air Conditioning', 'available'),
(4, 3, '301', '2 Bedroom', 47.00, 22000.00, 'assets/unit-2br.jpg', 'Fiber Ready, Master Suite, Second Bedroom, Full Kitchen, Large Balcony, Air Conditioning', 'available'),
(5, 1, '101', 'Studio', 23.00, 13500.00, 'assets/unit-studio.jpg', 'Fiber Ready, Kitchenette, Air Conditioning, Built-in Wardrobe', 'available'),
(5, 2, '201', '1 Bedroom', 32.00, 17500.00, 'assets/unit-1br.jpg', 'Fiber Ready, Separate Bedroom, Full Kitchen, Balcony, Air Conditioning', 'occupied'),
(5, 2, '202', '1 Bedroom', 33.00, 17900.00, 'assets/unit-1br.jpg', 'Fiber Ready, Separate Bedroom, Full Kitchen, Balcony, Air Conditioning', 'available'),
(5, 3, '301', '2 Bedroom', 52.00, 24800.00, 'assets/unit-2br.jpg', 'Fiber Ready, Master Suite, Second Bedroom, Full Kitchen, Large Balcony, Air Conditioning', 'available'),
(6, 1, 'A1', '1 Bedroom - Step-free access', 38.00, 22000.00, 'assets/unit-1br.jpg', 'Parking, Elevator, Step-free common route, Wide entry approach, Near Laiya coast (demo location)', 'available'),
(6, 2, 'B2', '1 Bedroom - Elevator access', 43.00, 28500.00, 'assets/unit-1br.jpg', 'Parking, Elevator, Accessible common entrance, Coast nearby (demo location)', 'available'),
(6, 3, 'C3', '2 Bedroom - Accessible route', 62.00, 38500.00, 'assets/unit-2br.jpg', 'Parking, Elevator, Step-free common route, Two bedrooms, Coast nearby (demo location)', 'available');



INSERT INTO unit_images (unit_id, image_path, caption, sort_order) VALUES
(1, 'assets/feat-kitchen.jpg', 'Modern Kitchen', 1),
(1, 'assets/feat-bathroom.jpg', 'Clean Bathroom', 2),
(1, 'assets/feat-view.jpg', 'City View', 3),
(1, 'assets/feat-bedroom.jpg', 'Cozy Bedroom', 4),
(2, 'assets/feat-kitchen.jpg', 'Modern Kitchen', 1),
(2, 'assets/feat-bathroom.jpg', 'Clean Bathroom', 2),
(2, 'assets/feat-view.jpg', 'City View', 3),
(2, 'assets/feat-bedroom.jpg', 'Cozy Bedroom', 4),
(3, 'assets/feat-kitchen.jpg', 'Modern Kitchen', 1),
(3, 'assets/feat-bathroom.jpg', 'Clean Bathroom', 2),
(3, 'assets/feat-view.jpg', 'City View', 3),
(3, 'assets/feat-bedroom.jpg', 'Cozy Bedroom', 4),
(4, 'assets/feat-kitchen.jpg', 'Modern Kitchen', 1),
(4, 'assets/feat-bathroom.jpg', 'Clean Bathroom', 2),
(4, 'assets/feat-view.jpg', 'City View', 3),
(4, 'assets/feat-bedroom.jpg', 'Cozy Bedroom', 4),
(5, 'assets/feat-kitchen.jpg', 'Modern Kitchen', 1),
(5, 'assets/feat-bathroom.jpg', 'Clean Bathroom', 2),
(5, 'assets/feat-view.jpg', 'City View', 3),
(5, 'assets/feat-bedroom.jpg', 'Cozy Bedroom', 4),
(6, 'assets/feat-kitchen.jpg', 'Modern Kitchen', 1),
(6, 'assets/feat-bathroom.jpg', 'Clean Bathroom', 2),
(6, 'assets/feat-view.jpg', 'City View', 3),
(6, 'assets/feat-bedroom.jpg', 'Cozy Bedroom', 4),
(7, 'assets/feat-kitchen.jpg', 'Modern Kitchen', 1),
(7, 'assets/feat-bathroom.jpg', 'Clean Bathroom', 2),
(7, 'assets/feat-view.jpg', 'City View', 3),
(7, 'assets/feat-bedroom.jpg', 'Cozy Bedroom', 4),
(8, 'assets/feat-kitchen.jpg', 'Modern Kitchen', 1),
(8, 'assets/feat-bathroom.jpg', 'Clean Bathroom', 2),
(8, 'assets/feat-view.jpg', 'City View', 3),
(8, 'assets/feat-bedroom.jpg', 'Cozy Bedroom', 4),
(9, 'assets/feat-kitchen.jpg', 'Modern Kitchen', 1),
(9, 'assets/feat-bathroom.jpg', 'Clean Bathroom', 2),
(9, 'assets/feat-view.jpg', 'City View', 3),
(9, 'assets/feat-bedroom.jpg', 'Cozy Bedroom', 4),
(10, 'assets/feat-kitchen.jpg', 'Modern Kitchen', 1),
(10, 'assets/feat-bathroom.jpg', 'Clean Bathroom', 2),
(10, 'assets/feat-view.jpg', 'City View', 3),
(10, 'assets/feat-bedroom.jpg', 'Cozy Bedroom', 4),
(11, 'assets/feat-kitchen.jpg', 'Modern Kitchen', 1),
(11, 'assets/feat-bathroom.jpg', 'Clean Bathroom', 2),
(11, 'assets/feat-view.jpg', 'City View', 3),
(11, 'assets/feat-bedroom.jpg', 'Cozy Bedroom', 4),
(12, 'assets/feat-kitchen.jpg', 'Modern Kitchen', 1),
(12, 'assets/feat-bathroom.jpg', 'Clean Bathroom', 2),
(12, 'assets/feat-view.jpg', 'City View', 3),
(12, 'assets/feat-bedroom.jpg', 'Cozy Bedroom', 4),
(13, 'assets/feat-kitchen.jpg', 'Modern Kitchen', 1),
(13, 'assets/feat-bathroom.jpg', 'Clean Bathroom', 2),
(13, 'assets/feat-view.jpg', 'City View', 3),
(13, 'assets/feat-bedroom.jpg', 'Cozy Bedroom', 4),
(14, 'assets/feat-kitchen.jpg', 'Modern Kitchen', 1),
(14, 'assets/feat-bathroom.jpg', 'Clean Bathroom', 2),
(14, 'assets/feat-view.jpg', 'City View', 3),
(14, 'assets/feat-bedroom.jpg', 'Cozy Bedroom', 4),
(15, 'assets/feat-kitchen.jpg', 'Modern Kitchen', 1),
(15, 'assets/feat-bathroom.jpg', 'Clean Bathroom', 2),
(15, 'assets/feat-view.jpg', 'City View', 3),
(15, 'assets/feat-bedroom.jpg', 'Cozy Bedroom', 4),
(16, 'assets/feat-kitchen.jpg', 'Modern Kitchen', 1),
(16, 'assets/feat-bathroom.jpg', 'Clean Bathroom', 2),
(16, 'assets/feat-view.jpg', 'City View', 3),
(16, 'assets/feat-bedroom.jpg', 'Cozy Bedroom', 4),
(17, 'assets/feat-kitchen.jpg', 'Modern Kitchen', 1),
(17, 'assets/feat-bathroom.jpg', 'Clean Bathroom', 2),
(17, 'assets/feat-view.jpg', 'City View', 3),
(17, 'assets/feat-bedroom.jpg', 'Cozy Bedroom', 4),
(18, 'assets/feat-kitchen.jpg', 'Modern Kitchen', 1),
(18, 'assets/feat-bathroom.jpg', 'Clean Bathroom', 2),
(18, 'assets/feat-view.jpg', 'City View', 3),
(18, 'assets/feat-bedroom.jpg', 'Cozy Bedroom', 4),
(19, 'assets/feat-kitchen.jpg', 'Modern Kitchen', 1),
(19, 'assets/feat-bathroom.jpg', 'Clean Bathroom', 2),
(19, 'assets/feat-view.jpg', 'City View', 3),
(19, 'assets/feat-bedroom.jpg', 'Cozy Bedroom', 4),
(20, 'assets/feat-kitchen.jpg', 'Modern Kitchen', 1),
(20, 'assets/feat-bathroom.jpg', 'Clean Bathroom', 2),
(20, 'assets/feat-view.jpg', 'City View', 3),
(20, 'assets/feat-bedroom.jpg', 'Cozy Bedroom', 4),
(21, 'assets/feat-kitchen.jpg', 'Modern Kitchen', 1),
(21, 'assets/feat-bathroom.jpg', 'Clean Bathroom', 2),
(21, 'assets/feat-view.jpg', 'City View', 3),
(21, 'assets/feat-bedroom.jpg', 'Cozy Bedroom', 4),
(22, 'assets/feat-kitchen.jpg', 'Modern Kitchen', 1),
(22, 'assets/feat-bathroom.jpg', 'Clean Bathroom', 2),
(22, 'assets/feat-view.jpg', 'City View', 3),
(22, 'assets/feat-bedroom.jpg', 'Cozy Bedroom', 4),
(23, 'assets/feat-kitchen.jpg', 'Modern Kitchen', 1),
(23, 'assets/feat-bathroom.jpg', 'Clean Bathroom', 2),
(23, 'assets/feat-view.jpg', 'City View', 3),
(23, 'assets/feat-bedroom.jpg', 'Cozy Bedroom', 4),
(24, 'assets/feat-kitchen.jpg', 'Modern Kitchen', 1),
(24, 'assets/feat-bathroom.jpg', 'Clean Bathroom', 2),
(24, 'assets/feat-view.jpg', 'City View', 3),
(24, 'assets/feat-bedroom.jpg', 'Cozy Bedroom', 4),
(25, 'assets/feat-kitchen.jpg', 'Modern Kitchen', 1),
(25, 'assets/feat-bathroom.jpg', 'Clean Bathroom', 2),
(25, 'assets/feat-view.jpg', 'City View', 3),
(25, 'assets/feat-bedroom.jpg', 'Cozy Bedroom', 4),
(26, 'assets/feat-kitchen.jpg', 'Modern Kitchen', 1),
(26, 'assets/feat-bathroom.jpg', 'Clean Bathroom', 2),
(26, 'assets/feat-view.jpg', 'City View', 3),
(26, 'assets/feat-bedroom.jpg', 'Cozy Bedroom', 4),
(27, 'assets/feat-kitchen.jpg', 'Kitchen and dining', 1),
(27, 'assets/feat-bathroom.jpg', 'Bathroom', 2),
(27, 'assets/feat-view.jpg', 'Generic sample photo — not a beach view', 3),
(27, 'assets/feat-bedroom.jpg', 'Bedroom', 4),
(28, 'assets/feat-kitchen.jpg', 'Kitchen and dining', 1),
(28, 'assets/feat-bathroom.jpg', 'Bathroom', 2),
(28, 'assets/feat-view.jpg', 'Generic sample photo — not a beach view', 3),
(28, 'assets/feat-bedroom.jpg', 'Bedroom', 4),
(29, 'assets/feat-kitchen.jpg', 'Kitchen and dining', 1),
(29, 'assets/feat-bathroom.jpg', 'Bathroom', 2),
(29, 'assets/feat-view.jpg', 'Generic sample photo — not a beach view', 3),
(29, 'assets/feat-bedroom.jpg', 'Bedroom', 4);

-- Starter property-specific guidelines. Replace demo guidance with owner-approved terms before publication.
UPDATE apartments SET policies = CASE id
    WHEN 1 THEN '• QUIET HOURS — Keep sound low from 10:00 PM to 7:00 AM; tenants are responsible for guests.\n• OCCUPANCY — Only lease-listed occupants may reside in the unit; ask before adding a long-term occupant.\n• PETS — Prior registration and written approval are required; leash or carry approved pets in shared areas and clean up immediately.\n• SMOKING — No smoking/vaping in the unit or enclosed shared areas.\n• FIRE SAFETY — Keep corridors, stairs, landings, and exits clear; do not wedge fire doors.\n• WASTE — Bag rubbish and use the marked collection point and schedule.\n• UTILITIES — Use the unit meter or written allocation method; ask for statements and keep receipts.\n• REPAIRS — Report leaks, electrical faults, pests, and damage promptly; do not make structural/electrical repairs.\n• PARKING — Not included unless assigned in writing; keep ramps and fire lanes clear.\n• ALTERATIONS — Written approval is required before drilling, painting, or changing fixtures.\n• MOVE-IN — Record condition, keys, and meter readings with management.\n• These sample rules require owner confirmation before publication or use.'
    WHEN 2 THEN '• QUIET HOURS — Keep noise low from 10:00 PM to 7:00 AM.\n• OCCUPANTS — Name residents in the lease and report long-term guest changes.\n• VISITORS — Follow security sign-in and do not share access cards.\n• PETS — No pets without prior written approval.\n• PARKING — Use only an assigned space; do not block accessible bays or loading areas.\n• ELEVATORS — Observe posted capacity and coordinate service-lift use for moves.\n• ACCESSIBLE ROUTES — Keep ramps, handrails, entrances, and lift landings clear.\n• FIRE SAFETY — Keep stairs, exits, and equipment unobstructed.\n• UTILITIES — Statements should show the period, meter/allocation basis, amount, and due date.\n• MAINTENANCE — Report urgent water/electrical issues promptly and get approval for contractors.\n• ALTERATIONS — Obtain written approval before permanent changes.\n• Demo rules only; confirm the actual signed lease and building notices.'
    WHEN 3 THEN '• QUIET HOURS — Keep noise low after 10:00 PM, especially near shared areas.\n• HERITAGE FINISHES — No drilling, painting, or fixture changes without written approval.\n• OCCUPANCY — List residents in the lease and get approval for long-term guests.\n• PETS — Register approved pets and clean shared areas immediately.\n• STAIRS & EXITS — Keep stair landings clear; confirm the accessible route before booking.\n• WATER & DRAINS — Do not flush wipes or grease; report leaks quickly.\n• UTILITIES — Confirm meters or written allocation and due dates before signing.\n• WASTE — Use the marked collection point; do not leave bags on stairs.\n• REPAIRS — Wait for owner approval before permanent work.\n• DELIVERIES — Coordinate large items to protect narrow hallways.\n• MOVE-IN — Photograph condition and record keys and fixtures.\n• These are sample rules; verify accessibility, safety notices, and written lease terms.'
    WHEN 4 THEN '• QUIET HOURS — Observe 10:00 PM to 7:00 AM.\n• COURTYARD — Leave shared areas clean and supervise children.\n• OCCUPANTS & GUESTS — Name regular occupants and observe sign-in rules.\n• PETS — Written owner approval is required.\n• PARKING — Get a written space assignment; do not block access routes.\n• ACCESSIBILITY — Keep ramps, handrails, entries, and turning areas unobstructed.\n• FIRE SAFETY — Keep exits and extinguishers accessible.\n• WASTE — Use posted collection point and schedule.\n• UTILITIES — Review the meter/allocation method, billing period, and due date.\n• MAINTENANCE — Report water, pest, lighting, and safety issues promptly.\n• ALTERATIONS — Written approval is required for fixtures or painting.\n• Confirm actual posted rules and signed lease; these are demo examples.'
    WHEN 5 THEN '• QUIET HOURS — Keep sound low from 10:00 PM to 7:00 AM.\n• OCCUPANCY — Only named residents; discuss long-stay guests in writing.\n• VISITORS — Follow security sign-in and access rules.\n• PETS — Written approval is required before bringing an animal.\n• HISTORIC FABRIC — No drilling, painting, or exterior changes without written approval.\n• FIRE & ACCESS — Keep narrow corridors and stairs clear; confirm access limitations before booking.\n• WASTE — Seal rubbish and use the designated collection point.\n• UTILITIES — Verify metering/allocation and due dates in the lease.\n• WATER & HUMIDITY — Report leaks or moisture promptly.\n• DELIVERIES — Coordinate large deliveries to protect heritage paths.\n• PARKING — Not guaranteed unless assigned in writing.\n• Confirm heritage/building restrictions and written terms; sample rules only.'
    WHEN 6 THEN '• BEACH & COAST — Demo coastal example near Laiya attractions; verify exact beach access, hours, fees, tides, and travel time. Shoreline access is not guaranteed by rent.\n• QUIET HOURS — Observe 10:00 PM to 7:00 AM.\n• SAND & SALT — Rinse sand only at the designated wash point; keep wet floors dry.\n• OCCUPANTS — Only lease-listed occupants may reside in the unit.\n• PETS — Prior written approval and immediate cleanup are required.\n• PARKING — Use assigned marked spaces; keep accessible bays, ramps, and fire lanes clear.\n• ELEVATOR — Follow posted capacity and keep doors clear.\n• PWD ROUTE — Keep the step-free entrance, ramp, handrails, lift landing, and access path unobstructed. Confirm unit dimensions and bathroom layout.\n• UTILITIES — Request itemized meter/allocation statements and retain receipts.\n• WASTE — Seal waste and do not litter on coastal paths or drains.\n• WEATHER — Follow official coastal/weather advisories and confirm emergency contacts.\n• REPAIRS — Report corrosion, water intrusion, slippery areas, and damaged handrails promptly.\n• Gallery photos are generic placeholders and must be replaced. Verify all amenities before publication.\n• These are demo guidelines, not an official rulebook or a guarantee of access.'
    ELSE policies END WHERE id IN (1,2,3,4,5,6);

-- DEMO TENANT AND BILLING DATA ONLY. Change credentials and remove these sample records before live use.
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
