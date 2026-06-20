-- TrioSense MySQL initialisation (runs once on first container start)

SET GLOBAL time_zone = '+05:30';

CREATE DATABASE IF NOT EXISTS triosense_test
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

GRANT ALL PRIVILEGES ON triosense.* TO 'triosense'@'%';
GRANT ALL PRIVILEGES ON triosense_test.* TO 'triosense'@'%';

FLUSH PRIVILEGES;
