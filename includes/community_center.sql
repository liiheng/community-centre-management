CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100),
    email VARCHAR(100) UNIQUE,
    password VARCHAR(255),
    role ENUM('member', 'organizer', 'admin') DEFAULT 'member'
);

CREATE TABLE activities (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(100),
    description TEXT,
    start_date DATE,
    end_date DATE,
    resources TEXT,
    target_audience TEXT,
    created_by INT,
    status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending'
);

CREATE TABLE reservations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    activity_id INT,
    location VARCHAR(100),
    start_time DATETIME,
    end_time DATETIME,
    FOREIGN KEY (activity_id) REFERENCES activities(id)
);

CREATE TABLE feedback (
    id INT AUTO_INCREMENT PRIMARY KEY,
    activity_id INT,
    user_id INT,
    comments TEXT,
    rating INT,
    FOREIGN KEY (activity_id) REFERENCES activities(id),
    FOREIGN KEY (user_id) REFERENCES users(id)
);
