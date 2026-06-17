-- Voer dit uit in phpMyAdmin op een database 'minesweeper'.

CREATE TABLE users (
  id              INT AUTO_INCREMENT PRIMARY KEY,
  gebruikersnaam  VARCHAR(40) UNIQUE NOT NULL,
  wachtwoord_hash VARCHAR(255) NOT NULL,
  aangemaakt      TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE scores (
  id        INT AUTO_INCREMENT PRIMARY KEY,
  user_id   INT NOT NULL,
  level     ENUM('beginner','intermediate','expert') NOT NULL,
  tijd      INT NOT NULL,
  gewonnen  BOOLEAN NOT NULL,
  gespeeld  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id)
);

CREATE TABLE controller_state (
  code     VARCHAR(8) PRIMARY KEY,
  command  VARCHAR(16) NOT NULL DEFAULT '',
  seq      INT NOT NULL DEFAULT 0,
  updated  TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE user_achievements (
  user_id        INT NOT NULL,
  achievement_id VARCHAR(40) NOT NULL,
  behaald_op     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (user_id, achievement_id),
  FOREIGN KEY (user_id) REFERENCES users(id)
);
