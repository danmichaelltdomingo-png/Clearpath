-- ============================================================
-- ClearPath BPC - FULL DATABASE (Clean + Migration v2)
-- Ready for GitHub / Fresh Import
-- ============================================================

CREATE DATABASE IF NOT EXISTS bpc_clearpath;
USE bpc_clearpath;

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

-- ============================================================
-- USERS
-- ============================================================
CREATE TABLE IF NOT EXISTS users (
  id INT(11) NOT NULL AUTO_INCREMENT,
  student_id VARCHAR(50) DEFAULT NULL,
  email VARCHAR(150) NOT NULL,
  password VARCHAR(255) NOT NULL,
  full_name VARCHAR(200) NOT NULL,
  role ENUM('admin','signatory','student') NOT NULL DEFAULT 'student',
  office VARCHAR(100) DEFAULT NULL,
  profile_photo VARCHAR(255) DEFAULT NULL,
  year_level VARCHAR(20) DEFAULT NULL,
  section VARCHAR(50) DEFAULT NULL,
  course VARCHAR(100) DEFAULT NULL,
  is_active TINYINT(1) DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY email (email),
  UNIQUE KEY student_id (student_id)
) ENGINE=InnoDB;

-- ============================================================
-- OFFICES
-- ============================================================
CREATE TABLE IF NOT EXISTS offices (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(150) NOT NULL,
  description VARCHAR(255),
  is_active TINYINT(1) DEFAULT 1,
  sort_order INT DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ============================================================
-- CLEARANCE REQUESTS
-- ============================================================
CREATE TABLE IF NOT EXISTS clearance_requests (
  id INT AUTO_INCREMENT PRIMARY KEY,
  student_id INT NOT NULL,
  school_year VARCHAR(20) NOT NULL,
  semester ENUM('1st','2nd','Summer') NOT NULL,
  status ENUM('pending','in_progress','cleared','rejected') DEFAULT 'pending',
  clearance_deadline DATE DEFAULT NULL,
  notified_start TINYINT(1) DEFAULT 0,
  submitted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================================
-- CLEARANCE ITEMS
-- ============================================================
CREATE TABLE IF NOT EXISTS clearance_items (
  id INT AUTO_INCREMENT PRIMARY KEY,
  clearance_request_id INT NOT NULL,
  office_id INT NOT NULL,
  signatory_id INT DEFAULT NULL,
  status ENUM('pending','approved','rejected') DEFAULT 'pending',
  remarks TEXT,
  requirements_submitted TINYINT(1) DEFAULT 0,
  file_path VARCHAR(500),
  deadline DATE DEFAULT NULL,
  notified_deadline TINYINT(1) DEFAULT 0,
  notified_failed TINYINT(1) DEFAULT 0,
  reviewed_at TIMESTAMP NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (clearance_request_id) REFERENCES clearance_requests(id) ON DELETE CASCADE,
  FOREIGN KEY (office_id) REFERENCES offices(id),
  FOREIGN KEY (signatory_id) REFERENCES users(id)
) ENGINE=InnoDB;

-- ============================================================
-- ACTIVITY LOGS
-- ============================================================
CREATE TABLE IF NOT EXISTS activity_logs (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT,
  action VARCHAR(255) NOT NULL,
  description TEXT,
  ip_address VARCHAR(45),
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ============================================================
-- PASSWORD RESETS
-- ============================================================
CREATE TABLE IF NOT EXISTS password_resets (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  token VARCHAR(64) NOT NULL UNIQUE,
  expires_at DATETIME NOT NULL,
  used TINYINT(1) DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================================
-- OFFICE REQUIREMENTS
-- ============================================================
CREATE TABLE IF NOT EXISTS office_requirements (
  id INT AUTO_INCREMENT PRIMARY KEY,
  office_id INT NOT NULL,
  requirement_name VARCHAR(255) NOT NULL,
  description TEXT,
  is_required TINYINT(1) DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (office_id) REFERENCES offices(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================================
-- COURSES (NEW)
-- ============================================================
CREATE TABLE IF NOT EXISTS courses (
  id INT AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(20) NOT NULL UNIQUE,
  name VARCHAR(150) NOT NULL,
  department VARCHAR(100),
  is_active TINYINT(1) DEFAULT 1,
  sort_order INT DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

INSERT IGNORE INTO courses (code, name, department, sort_order) VALUES
('BSIT','Bachelor of Science in Information Technology','College of Computing',1),
('BSCS','Bachelor of Science in Computer Science','College of Computing',2);

-- ============================================================
-- EMAIL LOGS (NEW)
-- ============================================================
CREATE TABLE IF NOT EXISTS email_logs (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  type ENUM('clearance_start','deadline_warning','failed_compliance','cleared') NOT NULL,
  reference_id INT,
  sent_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================================
-- CLEARANCE FILES (NEW)
-- ============================================================
CREATE TABLE IF NOT EXISTS clearance_files (
  id INT AUTO_INCREMENT PRIMARY KEY,
  clearance_item_id INT NOT NULL,
  file_path VARCHAR(500) NOT NULL,
  file_type ENUM('upload','camera') DEFAULT 'upload',
  original_name VARCHAR(255),
  uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (clearance_item_id) REFERENCES clearance_items(id) ON DELETE CASCADE
) ENGINE=InnoDB;

COMMIT;