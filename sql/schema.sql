-- =====================================================================
--  HR Management System - database structure (MySQL / MariaDB)
--  Import this file in phpMyAdmin, or let run.php do it for you.
-- =====================================================================
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

CREATE TABLE IF NOT EXISTS settings (
  skey   VARCHAR(100) NOT NULL PRIMARY KEY,
  svalue TEXT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS companies (
  id         INT AUTO_INCREMENT PRIMARY KEY,
  name       VARCHAR(200) NOT NULL,
  short_name VARCHAR(60)  NULL,
  address    VARCHAR(255) NULL,
  phone      VARCHAR(60)  NULL,
  email      VARCHAR(120) NULL,
  trn        VARCHAR(60)  NULL,
  active     TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS departments (
  id     INT AUTO_INCREMENT PRIMARY KEY,
  name   VARCHAR(120) NOT NULL,
  code   VARCHAR(30)  NULL,
  active TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS professions (
  id   INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL UNIQUE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS employees (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  code          VARCHAR(20) NOT NULL UNIQUE,
  name          VARCHAR(200) NOT NULL,
  name_ar       VARCHAR(200) NULL,
  company_id    INT NULL,
  department_id INT NULL,
  profession    VARCHAR(120) NULL,
  designation   VARCHAR(120) NULL,
  passport_no   VARCHAR(60)  NULL,
  nationality   VARCHAR(80)  NULL,
  sponsor       VARCHAR(40)  NULL,
  joining_date  DATE NULL,
  basic         DECIMAL(12,2) NOT NULL DEFAULT 0,
  allowance     DECIMAL(12,2) NOT NULL DEFAULT 0,
  total         DECIMAL(12,2) NOT NULL DEFAULT 0,
  active        TINYINT(1) NOT NULL DEFAULT 1,
  created_at    DATETIME NULL,
  updated_at    DATETIME NULL,
  KEY idx_emp_company (company_id), KEY idx_emp_dept (department_id), KEY idx_emp_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS users (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  username      VARCHAR(60) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  full_name     VARCHAR(150) NOT NULL,
  email         VARCHAR(150) NULL,
  role          ENUM('admin','HR','user') NOT NULL DEFAULT 'user',
  company_id    INT NULL,
  all_companies TINYINT(1) NOT NULL DEFAULT 1,
  employee_id   INT NULL,
  active        TINYINT(1) NOT NULL DEFAULT 1,
  last_login    DATETIME NULL,
  created_at    DATETIME NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS role_permissions (
  id       INT AUTO_INCREMENT PRIMARY KEY,
  role     VARCHAR(10) NOT NULL,
  perm_key VARCHAR(60) NOT NULL,
  allowed  TINYINT(1) NOT NULL DEFAULT 1,
  UNIQUE KEY uq_role_perm (role, perm_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS form_types (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  name        VARCHAR(150) NOT NULL,
  slug        VARCHAR(60)  NOT NULL UNIQUE,
  ref_prefix  VARCHAR(20)  NULL,
  description VARCHAR(255) NULL,
  company_id  INT NULL,
  sort_order  INT NOT NULL DEFAULT 50,
  active      TINYINT(1) NOT NULL DEFAULT 1,
  created_at  DATETIME NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS form_fields (
  id           INT AUTO_INCREMENT PRIMARY KEY,
  form_type_id INT NOT NULL,
  field_key    VARCHAR(60) NOT NULL,
  label        VARCHAR(150) NOT NULL,
  field_type   VARCHAR(20) NOT NULL DEFAULT 'text',
  options      TEXT NULL,
  is_required  TINYINT(1) NOT NULL DEFAULT 0,
  help_text    VARCHAR(255) NULL,
  sort_order   INT NOT NULL DEFAULT 50,
  active       TINYINT(1) NOT NULL DEFAULT 1,
  UNIQUE KEY uq_form_field (form_type_id, field_key),
  KEY idx_ff_form (form_type_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS requests (
  id                INT AUTO_INCREMENT PRIMARY KEY,
  ref_no            VARCHAR(40) NOT NULL UNIQUE,
  form_type_id      INT NULL,
  company_id        INT NULL,
  employee_id       INT NULL,
  subject           VARCHAR(200) NULL,
  data              LONGTEXT NULL,
  status            ENUM('Waiting','Approved','Rejected','Cancelled') NOT NULL DEFAULT 'Waiting',
  total_salary      DECIMAL(12,2) NOT NULL DEFAULT 0,
  attachment        VARCHAR(160) NULL,
  office_ref        VARCHAR(60) NULL,
  approved_salary   DECIMAL(12,2) NULL,
  payment_date      DATE NULL,
  remarks           TEXT NULL,
  created_by        INT NULL,
  created_by_name   VARCHAR(150) NULL,
  approved_by       INT NULL,
  approved_by_name  VARCHAR(150) NULL,
  approved_at       DATETIME NULL,
  approval_note     VARCHAR(255) NULL,
  created_at        DATETIME NULL,
  updated_at        DATETIME NULL,
  KEY idx_req_status (status), KEY idx_req_form (form_type_id), KEY idx_req_emp (employee_id), KEY idx_req_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS request_status_history (
  id         INT AUTO_INCREMENT PRIMARY KEY,
  request_id INT NOT NULL,
  status     VARCHAR(20) NOT NULL,
  notes      VARCHAR(255) NULL,
  user_id    INT NULL,
  user_name  VARCHAR(150) NULL,
  created_at DATETIME NULL,
  KEY idx_rsh_request (request_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS activity_log (
  id         INT AUTO_INCREMENT PRIMARY KEY,
  user_id    INT NULL,
  username   VARCHAR(60) NULL,
  action     VARCHAR(60) NULL,
  entity     VARCHAR(60) NULL,
  entity_id  INT NULL,
  detail     VARCHAR(255) NULL,
  ip         VARCHAR(45) NULL,
  created_at DATETIME NULL,
  KEY idx_log_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

SET FOREIGN_KEY_CHECKS = 1;
