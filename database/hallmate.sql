-- hallmate.sql
CREATE DATABASE IF NOT EXISTS hallmate CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE hallmate;

--registration table
  CREATE TABLE reg (
  id INT NOT NULL AUTO_INCREMENT,
  rollno VARCHAR(50) NOT NULL,
  name VARCHAR(100) NOT NULL,
  email VARCHAR(100) NOT NULL UNIQUE,
  password VARCHAR(255) NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  role VARCHAR(50) DEFAULT 'student',
  last_login_time DATETIME DEFAULT NULL,
  PRIMARY KEY (id)
);

---login_history
CREATE TABLE IF NOT EXISTS login_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    login_timestamp DATETIME NOT NULL,
    FOREIGN KEY (user_id) REFERENCES reg(id)
);

-- students table
CREATE TABLE IF NOT EXISTS students (
  id INT AUTO_INCREMENT PRIMARY KEY,
  reg_no VARCHAR(100) NOT NULL UNIQUE,
  name VARCHAR(255) NOT NULL,
  batch VARCHAR(50) NOT NULL,
  department VARCHAR(100),
  semester INT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- faculty table
CREATE TABLE IF NOT EXISTS faculty (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(255) NOT NULL,
  email VARCHAR(150),
  department VARCHAR(100),
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- rooms table
CREATE TABLE IF NOT EXISTS rooms (
  id INT AUTO_INCREMENT PRIMARY KEY,
  room_no VARCHAR(50) NOT NULL UNIQUE,
  capacity INT NOT NULL DEFAULT 0,
  location VARCHAR(150),
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- exams table
CREATE TABLE IF NOT EXISTS exams (
  id INT AUTO_INCREMENT PRIMARY KEY,
  subject VARCHAR(255) NOT NULL,
  course_code VARCHAR(50),
  batch VARCHAR(50),
  exam_date DATE,
  session ENUM('Morning','Afternoon','Evening') DEFAULT 'Morning',
  expected_students INT DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- seating table
CREATE TABLE IF NOT EXISTS seating (
  id INT AUTO_INCREMENT PRIMARY KEY,
  exam_id INT NOT NULL,
  room_id INT NOT NULL,
  student_id INT NOT NULL,
  seat_number VARCHAR(50),
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (exam_id) REFERENCES exams(id) ON DELETE CASCADE,
  FOREIGN KEY (room_id) REFERENCES rooms(id) ON DELETE CASCADE,
  FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- invigilation table
CREATE TABLE IF NOT EXISTS invigilation (
  id INT AUTO_INCREMENT PRIMARY KEY,
  exam_id INT NOT NULL,
  faculty_id INT NOT NULL,
  room_id INT NOT NULL,
  notes VARCHAR(255),
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (exam_id) REFERENCES exams(id) ON DELETE CASCADE,
  FOREIGN KEY (faculty_id) REFERENCES faculty(id) ON DELETE CASCADE,
  FOREIGN KEY (room_id) REFERENCES rooms(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE admin (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL, -- store hashed password
    role VARCHAR(20) DEFAULT 'admin', -- default role
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

