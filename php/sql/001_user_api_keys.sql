CREATE TABLE IF NOT EXISTS user_api_keys (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  exchange VARCHAR(32) NOT NULL, -- 'binance'
  api_key TEXT NOT NULL,
  api_secret TEXT NOT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_user_exch (user_id, exchange)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
