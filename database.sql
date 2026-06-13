CREATE DATABASE IF NOT EXISTS hair_clinic_system;
USE hair_clinic_system;

CREATE TABLE IF NOT EXISTS admins (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    full_name VARCHAR(150) NOT NULL,
    role ENUM('Administrator','Receptionist','Doctor','Inventory Officer') NOT NULL DEFAULT 'Administrator',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS patients (
    id INT AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(150) NOT NULL,
    phone VARCHAR(30) NOT NULL,
    email VARCHAR(150),
    gender ENUM('Male', 'Female', 'Other') NOT NULL,
    date_of_birth DATE,
    address VARCHAR(255),
    medical_notes TEXT,
    assigned_doctor_id INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS appointments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    patient_id INT NOT NULL,
    appointment_date DATE NOT NULL,
    appointment_time TIME NOT NULL,
    reason VARCHAR(255) NOT NULL,
    status ENUM('Pending', 'Completed', 'Cancelled') NOT NULL DEFAULT 'Pending',
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS treatments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    patient_id INT NOT NULL,
    treatment_name VARCHAR(150) NOT NULL,
    treatment_date DATE NOT NULL,
    progress ENUM('Started', 'In Progress', 'Completed') NOT NULL DEFAULT 'Started',
    cost DECIMAL(10,2) NOT NULL DEFAULT 0,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS followups (
    id INT AUTO_INCREMENT PRIMARY KEY,
    patient_id INT NOT NULL,
    treatment_id INT,
    followup_date DATE NOT NULL,
    result TEXT,
    status ENUM('Scheduled', 'Done', 'Missed') NOT NULL DEFAULT 'Scheduled',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE,
    FOREIGN KEY (treatment_id) REFERENCES treatments(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS inventory_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    item_name VARCHAR(150) NOT NULL,
    category VARCHAR(80) NOT NULL,
    stock_level INT NOT NULL DEFAULT 0,
    unit_price DECIMAL(10,2) NOT NULL DEFAULT 0,
    vendor VARCHAR(150) NOT NULL,
    status ENUM('In Stock', 'Low Stock', 'Out of Stock') NOT NULL DEFAULT 'In Stock',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS inventory_orders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    item_id INT NOT NULL,
    order_code VARCHAR(30) NOT NULL UNIQUE,
    quantity INT NOT NULL,
    priority VARCHAR(40) NOT NULL DEFAULT 'Normal',
    shipping_cost DECIMAL(10,2) NOT NULL DEFAULT 0,
    total_estimate DECIMAL(10,2) NOT NULL DEFAULT 0,
    order_status ENUM('Pending', 'Shipped', 'Delivered', 'Cancelled') NOT NULL DEFAULT 'Pending',
    note VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (item_id) REFERENCES inventory_items(id) ON DELETE CASCADE
);

INSERT INTO admins (username, password, full_name, role) VALUES
('admin', '$2y$10$we9pFfF6WmRthWYXJNIP7OIk0Y5/gxDfEagOyGw8b501ow4P5RS4q', 'System Administrator', 'Administrator'),
('receptionist', '$2y$10$we9pFfF6WmRthWYXJNIP7OIk0Y5/gxDfEagOyGw8b501ow4P5RS4q', 'Amina Receptionist', 'Receptionist'),
('doctor', '$2y$10$we9pFfF6WmRthWYXJNIP7OIk0Y5/gxDfEagOyGw8b501ow4P5RS4q', 'Dr. Sarah Jenkins', 'Doctor'),
('inventory', '$2y$10$we9pFfF6WmRthWYXJNIP7OIk0Y5/gxDfEagOyGw8b501ow4P5RS4q', 'Yusuf Inventory', 'Inventory Officer')
ON DUPLICATE KEY UPDATE
full_name = VALUES(full_name),
role = VALUES(role);

INSERT INTO patients (id, full_name, phone, email, gender, date_of_birth, address, medical_notes, created_at) VALUES
(1, 'Alexander Wright', '+44 7700 900 123', 'a.wright.tech@email.com', 'Male', '1989-04-16', '24 Baker Street, Marylebone, London NW1 6XE', 'Allergies: Penicillin, Latex. Past conditions: Hypertension managed, minor scalp eczema in 2021. Family history: paternal male pattern baldness Grade IV.', '2023-10-24 09:12:00'),
(2, 'Alice Schmidt', '+1 (555) 098-7654', 'alice.s@webmail.com', 'Female', '1994-08-09', '118 Maple Avenue, Boston, MA', 'Sensitive scalp after chemical treatments. No known drug allergies.', '2023-09-12 11:20:00'),
(3, 'Robert King', '+1 (555) 234-5678', 'king.robert@outlook.com', 'Male', '1975-01-29', '77 West Lake Road, Chicago, IL', 'Previous hair transplant consultation. Family history of androgenetic alopecia.', '2023-10-23 14:05:00'),
(4, 'Maria Lopez', '+1 (555) 876-5432', 'm.lopez@health.org', 'Female', '1982-06-18', '45 Cedar Lane, Austin, TX', 'Mild dermatitis. Prefers non-invasive scalp therapy.', '2023-10-30 10:40:00'),
(5, 'Peter Thompson', '+1 (555) 432-1098', 'peter.t@design.com', 'Male', '1968-02-03', '301 King Street, Seattle, WA', 'Post-op review patient. Monitoring donor area healing.', '2023-08-15 16:25:00'),
(6, 'Elena Rodriguez', '+1 (555) 678-2234', 'elena.r@example.com', 'Female', '1991-11-22', '29 Palm Court, Miami, FL', 'Interested in PRP therapy. No known allergies.', '2023-11-01 08:50:00'),
(7, 'Marcus Sterling', '+1 (555) 444-1102', 'marcus.s@example.com', 'Male', '1986-07-12', '15 Harbor View, San Diego, CA', 'Follow-up exam for FUE restoration.', '2023-11-02 13:15:00'),
(8, 'David Chen', '+1 (555) 332-5567', 'd.chen@example.com', 'Male', '1990-03-05', '88 Queens Road, New York, NY', 'Initial consultation for crown thinning.', '2023-11-05 12:10:00')
ON DUPLICATE KEY UPDATE
full_name = VALUES(full_name),
phone = VALUES(phone),
email = VALUES(email),
gender = VALUES(gender),
date_of_birth = VALUES(date_of_birth),
address = VALUES(address),
medical_notes = VALUES(medical_notes);

INSERT INTO treatments (id, patient_id, treatment_name, treatment_date, progress, cost, notes, created_at) VALUES
(1, 1, 'FUE Hair Restoration', '2023-10-24', 'In Progress', 4200.00, 'Follicular Unit Extraction focused on frontal hairline and crown areas. Target: 2,500 grafts across high-density zones.', '2023-10-24 15:30:00'),
(2, 1, 'Laser Therapy', '2023-11-12', 'In Progress', 650.00, 'Post-op recovery therapy. Session 4 of 12 completed with good tolerance.', '2023-11-12 14:20:00'),
(3, 2, 'Initial Consultation', '2023-09-12', 'Completed', 85.00, 'Reviewed scalp health and recommended non-surgical treatment plan.', '2023-09-12 12:30:00'),
(4, 3, 'FUE Transplant Follow-Up', '2023-11-02', 'Completed', 120.00, 'Recipient site healing well. No infection signs observed.', '2023-11-02 10:25:00'),
(5, 4, 'Laser Therapy', '2023-10-30', 'Started', 500.00, 'Low-level laser therapy started for density support.', '2023-10-30 11:15:00'),
(6, 5, 'Post-Op Review', '2023-08-15', 'Completed', 100.00, 'Donor area healing complete. Maintenance medication discussed.', '2023-08-15 17:05:00'),
(7, 6, 'PRP Treatment', '2023-11-06', 'In Progress', 750.00, 'PRP platelet therapy initiated for thinning crown area.', '2023-11-06 11:00:00'),
(8, 7, 'FUE Restoration', '2023-11-08', 'Started', 3900.00, 'FUE treatment planned for frontal zone restoration.', '2023-11-08 09:30:00')
ON DUPLICATE KEY UPDATE
patient_id = VALUES(patient_id),
treatment_name = VALUES(treatment_name),
treatment_date = VALUES(treatment_date),
progress = VALUES(progress),
cost = VALUES(cost),
notes = VALUES(notes);

INSERT INTO appointments (id, patient_id, appointment_date, appointment_time, reason, status, notes, created_at) VALUES
(1, 1, '2023-10-24', '09:30:00', 'Initial Consultation', 'Completed', 'Baseline assessment and treatment plan created.', '2023-10-20 09:00:00'),
(2, 1, '2023-11-12', '14:00:00', 'FUE Procedure - Day 1', 'Completed', 'Procedure completed successfully. Patient tolerated local anesthesia well.', '2023-11-01 10:30:00'),
(3, 1, '2023-12-05', '11:00:00', 'Follow-up Assessment', 'Pending', 'Review healing and medication adherence.', '2023-11-25 13:00:00'),
(4, 2, '2023-09-12', '10:30:00', 'Initial Consultation', 'Completed', 'Recommended scalp therapy plan.', '2023-09-10 08:40:00'),
(5, 3, '2023-11-02', '14:45:00', 'Follow-up Exam', 'Completed', 'Checked graft healing and density progress.', '2023-10-28 15:10:00'),
(6, 4, '2023-10-30', '13:00:00', 'Laser Therapy', 'Pending', 'First laser session scheduled.', '2023-10-25 16:45:00'),
(7, 5, '2023-08-15', '15:30:00', 'Post-Op Review', 'Completed', 'Patient cleared for maintenance plan.', '2023-08-10 11:20:00'),
(8, 6, '2023-11-06', '10:30:00', 'PRP Platelet Therapy', 'Pending', 'PRP session and progress photos.', '2023-11-01 12:35:00'),
(9, 7, '2023-11-08', '14:45:00', 'Follow-up Exam', 'Pending', 'Assess FUE restoration readiness.', '2023-11-03 09:15:00'),
(10, 8, '2023-11-10', '13:00:00', 'Initial Consultation', 'Pending', 'Crown thinning assessment.', '2023-11-05 12:30:00')
ON DUPLICATE KEY UPDATE
patient_id = VALUES(patient_id),
appointment_date = VALUES(appointment_date),
appointment_time = VALUES(appointment_time),
reason = VALUES(reason),
status = VALUES(status),
notes = VALUES(notes);

INSERT INTO followups (id, patient_id, treatment_id, followup_date, result, status, created_at) VALUES
(1, 1, 1, '2023-11-22', 'Week 4 review: scalp healing well. Recipient site redness has subsided significantly.', 'Done', '2023-11-22 11:40:00'),
(2, 1, 1, '2023-12-20', 'Week 8 update: patient reporting minimal discomfort and good medication adherence.', 'Done', '2023-12-20 10:15:00'),
(3, 1, 1, '2024-01-18', 'Post-op review week 12 scheduled.', 'Scheduled', '2023-12-20 10:20:00'),
(4, 3, 4, '2023-11-16', 'Follow-up exam completed. Donor site healing normally.', 'Done', '2023-11-16 13:00:00'),
(5, 4, 5, '2023-11-13', 'Laser therapy response check scheduled.', 'Scheduled', '2023-10-30 11:30:00'),
(6, 6, 7, '2023-11-20', 'PRP response review scheduled.', 'Scheduled', '2023-11-06 11:20:00'),
(7, 7, 8, '2023-11-22', 'FUE readiness follow-up scheduled.', 'Scheduled', '2023-11-08 09:50:00')
ON DUPLICATE KEY UPDATE
patient_id = VALUES(patient_id),
treatment_id = VALUES(treatment_id),
followup_date = VALUES(followup_date),
result = VALUES(result),
status = VALUES(status);

INSERT INTO inventory_items (id, item_name, category, stock_level, unit_price, vendor, status) VALUES
(1, 'FUE Graft Preservation Solution', 'Surgical', 8, 124.00, 'BioMed Logistics', 'Low Stock'),
(2, 'Minoxidil 5% Topical Sol.', 'Post-Op', 72, 38.00, 'ClinicSupplies Co.', 'In Stock'),
(3, 'Nitrile Gloves (Box of 100)', 'General', 45, 18.00, 'ClinicSupplies Co.', 'In Stock'),
(4, 'Sterile Scalpels #11', 'Surgical', 85, 42.00, 'SurgiTech Global', 'In Stock')
ON DUPLICATE KEY UPDATE
item_name = VALUES(item_name),
category = VALUES(category),
stock_level = VALUES(stock_level),
unit_price = VALUES(unit_price),
vendor = VALUES(vendor),
status = VALUES(status);

INSERT INTO inventory_orders (id, item_id, order_code, quantity, priority, shipping_cost, total_estimate, order_status, note, created_at) VALUES
(1, 1, '#ORD-28491', 4, 'Urgent (24h)', 45.00, 665.00, 'Shipped', 'Est. Arrival: Oct 24, 2023', '2023-10-22 09:30:00'),
(2, 2, '#ORD-28485', 8, 'Normal', 20.00, 324.00, 'Delivered', 'Arrived: Oct 21, 2023', '2023-10-19 14:15:00'),
(3, 4, '#ORD-28502', 6, 'Normal', 30.00, 282.00, 'Pending', 'Awaiting Supplier', '2023-10-23 10:40:00')
ON DUPLICATE KEY UPDATE
item_id = VALUES(item_id),
quantity = VALUES(quantity),
priority = VALUES(priority),
shipping_cost = VALUES(shipping_cost),
total_estimate = VALUES(total_estimate),
order_status = VALUES(order_status),
note = VALUES(note);
