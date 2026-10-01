-- ============================================================
-- PC Maintenance & Diagnostic System (PCMS)
-- Database: pcms_db
-- Import this file via phpMyAdmin (XAMPP) or:
--   mysql -u root -p < schema.sql
-- ============================================================

CREATE DATABASE IF NOT EXISTS pcms_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE pcms_db;

-- ------------------------------------------------------------
-- Users (admin / technician accounts)
-- ------------------------------------------------------------
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    role ENUM('admin','technician') NOT NULL DEFAULT 'technician',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Default admin account -> username: admin
-- Password is set separately by setup_admin.php (see README) so the bcrypt
-- hash is always generated correctly by YOUR PHP install, not hardcoded here.
INSERT INTO users (username, password_hash, full_name, role) VALUES
('admin', 'PENDING_SETUP', 'System Administrator', 'admin');

-- ------------------------------------------------------------
-- Locations (labs / rooms / departments where units are found)
-- ------------------------------------------------------------
CREATE TABLE locations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL
) ENGINE=InnoDB;

INSERT INTO locations (name) VALUES ('Computer Lab 1'), ('Computer Lab 2'), ('Faculty Office'), ('Library'), ('Admin Office');

-- ------------------------------------------------------------
-- Units (the actual PCs being monitored, 15-30 typical)
-- ------------------------------------------------------------
CREATE TABLE units (
    id INT AUTO_INCREMENT PRIMARY KEY,
    asset_tag VARCHAR(30) NOT NULL UNIQUE,
    unit_name VARCHAR(100) NOT NULL,
    location_id INT NULL,
    cpu VARCHAR(100),
    ram VARCHAR(50),
    storage VARCHAR(100),
    os VARCHAR(50),
    purchase_date DATE NULL,
    status ENUM('healthy','at_risk','critical','under_repair','retired') NOT NULL DEFAULT 'healthy',
    maintenance_interval_days INT NOT NULL DEFAULT 90,
    last_maintenance_date DATE NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (location_id) REFERENCES locations(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- Symptom catalog (checklist items technicians / users can select)
-- ------------------------------------------------------------
CREATE TABLE symptoms (
    id INT AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(40) NOT NULL UNIQUE,
    label VARCHAR(150) NOT NULL,
    category ENUM('performance','hardware','power','display','network','storage','software','noise') NOT NULL
) ENGINE=InnoDB;

INSERT INTO symptoms (code, label, category) VALUES
('slow_boot', 'Slow to boot / long startup time', 'performance'),
('slow_general', 'Generally slow / lags when opening programs', 'performance'),
('freezes', 'Freezes or hangs randomly', 'performance'),
('random_restart', 'Restarts on its own / random reboots', 'power'),
('blue_screen', 'Blue screen of death (BSOD)', 'hardware'),
('no_power', 'Does not power on at all', 'power'),
('no_display', 'Powers on but no display / no signal', 'display'),
('flickering_screen', 'Screen flickers or shows artifacts', 'display'),
('loud_fan', 'Fan is unusually loud', 'noise'),
('clicking_noise', 'Clicking / grinding noise from drive', 'noise'),
('overheating', 'Feels hot / shuts down when hot', 'hardware'),
('burning_smell', 'Burning smell', 'hardware'),
('no_internet', 'No internet / network connection', 'network'),
('slow_network', 'Slow or intermittent network', 'network'),
('file_corruption', 'Files corrupted or missing', 'storage'),
('disk_full_warning', 'Low disk space warnings', 'storage'),
('usb_not_detected', 'USB / peripherals not detected', 'hardware'),
('keyboard_mouse_issue', 'Keyboard or mouse not responding', 'hardware'),
('software_crash', 'Specific application keeps crashing', 'software'),
('virus_popup', 'Suspicious pop-ups / possible malware', 'software'),
('battery_issue', 'Battery not charging (laptop units)', 'power'),
('audio_issue', 'No sound / audio distorted', 'hardware');

-- ------------------------------------------------------------
-- Diagnosis rules: symptom -> probable cause, weighted
-- Multiple rules can share a symptom (partial matches contribute score)
-- ------------------------------------------------------------
CREATE TABLE diagnosis_rules (
    id INT AUTO_INCREMENT PRIMARY KEY,
    symptom_id INT NOT NULL,
    probable_cause VARCHAR(150) NOT NULL,
    recommended_action VARCHAR(255) NOT NULL,
    weight INT NOT NULL DEFAULT 1,           -- contribution to cause score
    severity ENUM('low','medium','high','critical') NOT NULL DEFAULT 'medium',
    FOREIGN KEY (symptom_id) REFERENCES symptoms(id) ON DELETE CASCADE
) ENGINE=InnoDB;

INSERT INTO diagnosis_rules (symptom_id, probable_cause, recommended_action, weight, severity) VALUES
-- slow_boot
((SELECT id FROM symptoms WHERE code='slow_boot'), 'Too many startup programs', 'Disable unnecessary startup apps via Task Manager', 2, 'low'),
((SELECT id FROM symptoms WHERE code='slow_boot'), 'Failing / aging hard drive', 'Run disk health check (CHKDSK / SMART), plan for SSD upgrade', 3, 'high'),
((SELECT id FROM symptoms WHERE code='slow_boot'), 'Insufficient RAM', 'Check RAM usage; consider upgrade if consistently maxed', 2, 'medium'),
-- slow_general
((SELECT id FROM symptoms WHERE code='slow_general'), 'Malware / background processes', 'Run full antivirus scan and remove bloatware', 2, 'medium'),
((SELECT id FROM symptoms WHERE code='slow_general'), 'Insufficient RAM', 'Check RAM usage; consider upgrade if consistently maxed', 3, 'medium'),
((SELECT id FROM symptoms WHERE code='slow_general'), 'Failing / aging hard drive', 'Run disk health check (CHKDSK / SMART), plan for SSD upgrade', 2, 'high'),
-- freezes
((SELECT id FROM symptoms WHERE code='freezes'), 'RAM failure', 'Run memory diagnostic (Windows Memory Diagnostic / MemTest86)', 3, 'high'),
((SELECT id FROM symptoms WHERE code='freezes'), 'Overheating', 'Clean dust from vents/fans, check thermal paste', 3, 'high'),
((SELECT id FROM symptoms WHERE code='freezes'), 'Driver conflict', 'Update or roll back device drivers', 2, 'medium'),
-- random_restart
((SELECT id FROM symptoms WHERE code='random_restart'), 'Power supply (PSU) issue', 'Test with known-good PSU, check voltages', 3, 'critical'),
((SELECT id FROM symptoms WHERE code='random_restart'), 'Overheating', 'Clean dust from vents/fans, check thermal paste', 3, 'high'),
((SELECT id FROM symptoms WHERE code='random_restart'), 'RAM failure', 'Run memory diagnostic (Windows Memory Diagnostic / MemTest86)', 2, 'high'),
-- blue_screen
((SELECT id FROM symptoms WHERE code='blue_screen'), 'RAM failure', 'Run memory diagnostic (Windows Memory Diagnostic / MemTest86)', 3, 'high'),
((SELECT id FROM symptoms WHERE code='blue_screen'), 'Driver conflict', 'Update or roll back device drivers', 2, 'medium'),
((SELECT id FROM symptoms WHERE code='blue_screen'), 'Failing / aging hard drive', 'Run disk health check (CHKDSK / SMART), plan for SSD upgrade', 2, 'high'),
-- no_power
((SELECT id FROM symptoms WHERE code='no_power'), 'Power supply (PSU) failure', 'Test with known-good PSU and power cable', 4, 'critical'),
((SELECT id FROM symptoms WHERE code='no_power'), 'Loose internal connections', 'Re-seat power cables and RAM modules', 2, 'medium'),
((SELECT id FROM symptoms WHERE code='no_power'), 'Motherboard failure', 'Inspect for bulged capacitors, consider motherboard replacement', 2, 'critical'),
-- no_display
((SELECT id FROM symptoms WHERE code='no_display'), 'Graphics card / GPU failure', 'Re-seat GPU or test with onboard/alternate graphics', 3, 'critical'),
((SELECT id FROM symptoms WHERE code='no_display'), 'Loose display cable', 'Check monitor cable and connections', 2, 'low'),
((SELECT id FROM symptoms WHERE code='no_display'), 'RAM failure', 'Run memory diagnostic (Windows Memory Diagnostic / MemTest86)', 2, 'high'),
-- flickering_screen
((SELECT id FROM symptoms WHERE code='flickering_screen'), 'GPU driver issue', 'Update or reinstall graphics drivers', 2, 'medium'),
((SELECT id FROM symptoms WHERE code='flickering_screen'), 'Loose display cable', 'Check monitor cable and connections', 2, 'low'),
-- loud_fan
((SELECT id FROM symptoms WHERE code='loud_fan'), 'Dust buildup', 'Clean dust from fans and heatsinks', 3, 'low'),
((SELECT id FROM symptoms WHERE code='loud_fan'), 'Fan bearing failure', 'Replace case/CPU fan', 2, 'medium'),
-- clicking_noise
((SELECT id FROM symptoms WHERE code='clicking_noise'), 'Hard drive imminent failure', 'Back up data immediately, replace drive', 4, 'critical'),
-- overheating
((SELECT id FROM symptoms WHERE code='overheating'), 'Dust buildup / poor airflow', 'Clean dust from fans and heatsinks', 3, 'high'),
((SELECT id FROM symptoms WHERE code='overheating'), 'Dried thermal paste', 'Reapply thermal paste on CPU/GPU', 2, 'high'),
-- burning_smell
((SELECT id FROM symptoms WHERE code='burning_smell'), 'Component short / burnt part', 'Power off immediately, inspect PSU and motherboard', 5, 'critical'),
-- no_internet
((SELECT id FROM symptoms WHERE code='no_internet'), 'Faulty network cable / port', 'Check cable, switch port, and NIC status', 2, 'medium'),
((SELECT id FROM symptoms WHERE code='no_internet'), 'Network driver issue', 'Update or reinstall network adapter driver', 2, 'medium'),
-- slow_network
((SELECT id FROM symptoms WHERE code='slow_network'), 'Network congestion / driver issue', 'Check switch/router load, update NIC driver', 2, 'low'),
-- file_corruption
((SELECT id FROM symptoms WHERE code='file_corruption'), 'Failing hard drive', 'Run disk health check (CHKDSK / SMART), back up data', 4, 'critical'),
-- disk_full_warning
((SELECT id FROM symptoms WHERE code='disk_full_warning'), 'Storage nearing capacity', 'Clean up temp files, archive old data, consider upgrade', 2, 'low'),
-- usb_not_detected
((SELECT id FROM symptoms WHERE code='usb_not_detected'), 'USB port / controller issue', 'Test other ports, update chipset/USB drivers', 2, 'medium'),
-- keyboard_mouse_issue
((SELECT id FROM symptoms WHERE code='keyboard_mouse_issue'), 'Peripheral or port fault', 'Test with alternate keyboard/mouse and port', 2, 'low'),
-- software_crash
((SELECT id FROM symptoms WHERE code='software_crash'), 'Corrupted application / missing updates', 'Reinstall or update the affected application', 2, 'low'),
((SELECT id FROM symptoms WHERE code='software_crash'), 'Malware interference', 'Run full antivirus scan', 2, 'medium'),
-- virus_popup
((SELECT id FROM symptoms WHERE code='virus_popup'), 'Malware / adware infection', 'Run full antivirus and anti-malware scan, remove threats', 4, 'high'),
-- battery_issue
((SELECT id FROM symptoms WHERE code='battery_issue'), 'Degraded battery', 'Run battery health report, replace battery if below 50% capacity', 3, 'medium'),
-- audio_issue
((SELECT id FROM symptoms WHERE code='audio_issue'), 'Audio driver issue', 'Update or reinstall audio drivers', 2, 'low'),
((SELECT id FROM symptoms WHERE code='audio_issue'), 'Faulty audio hardware', 'Test with external speakers/headset to isolate fault', 2, 'medium');

-- ------------------------------------------------------------
-- Diagnostic logs (a reported check / prediction run on a unit)
-- ------------------------------------------------------------
CREATE TABLE diagnostic_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    unit_id INT NOT NULL,
    reported_by INT NULL,
    date_reported DATETIME DEFAULT CURRENT_TIMESTAMP,
    top_prediction VARCHAR(150),
    predicted_severity ENUM('low','medium','high','critical') DEFAULT 'low',
    status ENUM('open','in_progress','resolved') NOT NULL DEFAULT 'open',
    notes TEXT,
    resolved_at DATETIME NULL,
    FOREIGN KEY (unit_id) REFERENCES units(id) ON DELETE CASCADE,
    FOREIGN KEY (reported_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE diagnostic_log_symptoms (
    log_id INT NOT NULL,
    symptom_id INT NOT NULL,
    PRIMARY KEY (log_id, symptom_id),
    FOREIGN KEY (log_id) REFERENCES diagnostic_logs(id) ON DELETE CASCADE,
    FOREIGN KEY (symptom_id) REFERENCES symptoms(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- Maintenance records (preventive maintenance history)
-- ------------------------------------------------------------
CREATE TABLE maintenance_records (
    id INT AUTO_INCREMENT PRIMARY KEY,
    unit_id INT NOT NULL,
    performed_by INT NULL,
    maintenance_type ENUM('cleaning','inspection','repair','upgrade','os_reimage','other') NOT NULL DEFAULT 'cleaning',
    maintenance_date DATE NOT NULL,
    notes TEXT,
    next_due_date DATE NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (unit_id) REFERENCES units(id) ON DELETE CASCADE,
    FOREIGN KEY (performed_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- Sample units (adjust / add up to 15-30 as needed)
-- ------------------------------------------------------------
INSERT INTO units (asset_tag, unit_name, location_id, cpu, ram, storage, os, purchase_date, status, maintenance_interval_days, last_maintenance_date) VALUES
('PC-001','Lab1-PC01',1,'Intel Core i5-8500','8GB DDR4','256GB SSD','Windows 10','2021-06-15','healthy',90,'2026-06-01'),
('PC-002','Lab1-PC02',1,'Intel Core i5-8500','8GB DDR4','256GB SSD','Windows 10','2021-06-15','at_risk',90,'2026-02-10'),
('PC-003','Lab1-PC03',1,'Intel Core i5-8500','8GB DDR4','256GB SSD','Windows 10','2021-06-15','healthy',90,'2026-07-20'),
('PC-004','Lab1-PC04',1,'Intel Core i3-9100','8GB DDR4','1TB HDD','Windows 10','2020-03-10','critical',90,'2025-11-01'),
('PC-005','Lab1-PC05',1,'Intel Core i3-9100','8GB DDR4','1TB HDD','Windows 10','2020-03-10','healthy',90,'2026-07-05'),
('PC-006','Lab2-PC01',2,'Intel Core i5-10400','16GB DDR4','512GB SSD','Windows 11','2022-08-20','healthy',90,'2026-06-15'),
('PC-007','Lab2-PC02',2,'Intel Core i5-10400','16GB DDR4','512GB SSD','Windows 11','2022-08-20','healthy',90,'2026-06-15'),
('PC-008','Lab2-PC03',2,'Intel Core i5-10400','16GB DDR4','512GB SSD','Windows 11','2022-08-20','at_risk',90,'2026-01-15'),
('PC-009','Lab2-PC04',2,'Intel Core i5-10400','16GB DDR4','512GB SSD','Windows 11','2022-08-20','healthy',90,'2026-07-10'),
('PC-010','Lab2-PC05',2,'AMD Ryzen 5 3600','16GB DDR4','512GB SSD','Windows 11','2022-08-20','under_repair',90,'2025-09-01'),
('PC-011','Faculty-PC01',3,'Intel Core i5-9500','8GB DDR4','256GB SSD','Windows 10','2021-01-10','healthy',120,'2026-05-01'),
('PC-012','Faculty-PC02',3,'Intel Core i5-9500','8GB DDR4','256GB SSD','Windows 10','2021-01-10','healthy',120,'2026-05-01'),
('PC-013','Library-PC01',4,'Intel Core i3-10100','8GB DDR4','1TB HDD','Windows 10','2020-11-05','at_risk',90,'2026-01-20'),
('PC-014','Library-PC02',4,'Intel Core i3-10100','8GB DDR4','1TB HDD','Windows 10','2020-11-05','healthy',90,'2026-07-01'),
('PC-015','Admin-PC01',5,'Intel Core i7-11700','16GB DDR4','512GB SSD','Windows 11','2023-02-14','healthy',90,'2026-07-25');

-- ------------------------------------------------------------
-- Sample maintenance records
-- ------------------------------------------------------------
INSERT INTO maintenance_records (unit_id, performed_by, maintenance_type, maintenance_date, notes, next_due_date) VALUES
(1,1,'cleaning','2026-06-01','Routine dust cleaning and check-up.','2026-09-01'),
(2,1,'inspection','2026-02-10','Checked fan noise, minor dust found.','2026-05-10'),
(4,1,'repair','2025-11-01','Replaced faulty RAM stick.','2026-02-01'),
(6,1,'cleaning','2026-06-15','Routine cleaning.','2026-09-15'),
(10,1,'repair','2025-09-01','PSU replacement in progress.','2025-12-01');

-- ------------------------------------------------------------
-- Sample diagnostic logs
-- ------------------------------------------------------------
INSERT INTO diagnostic_logs (unit_id, reported_by, date_reported, top_prediction, predicted_severity, status, notes) VALUES
(2, 1, '2026-08-01 09:00:00', 'Failing / aging hard drive', 'high', 'open', 'Reported slow boot and occasional freezing.'),
(4, 1, '2026-08-10 14:30:00', 'Power supply (PSU) issue', 'critical', 'in_progress', 'Random restarts reported by faculty.'),
(8, 1, '2026-08-15 10:15:00', 'Dust buildup', 'low', 'resolved', 'Loud fan noise, cleaned during maintenance.'),
(13, 1, '2026-08-18 11:00:00', 'Malware / adware infection', 'high', 'open', 'Pop-ups reported by student.');

INSERT INTO diagnostic_log_symptoms (log_id, symptom_id) VALUES
(1, (SELECT id FROM symptoms WHERE code='slow_boot')),
(1, (SELECT id FROM symptoms WHERE code='freezes')),
(2, (SELECT id FROM symptoms WHERE code='random_restart')),
(3, (SELECT id FROM symptoms WHERE code='loud_fan')),
(4, (SELECT id FROM symptoms WHERE code='virus_popup'));
