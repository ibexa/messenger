CREATE TABLE ibexa_messenger_messages (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, body CLOB NOT NULL, headers CLOB NOT NULL, queue_name VARCHAR(190) NOT NULL, created_at DATETIME NOT NULL --(DC2Type:datetime_immutable)
, available_at DATETIME NOT NULL --(DC2Type:datetime_immutable)
, delivered_at DATETIME DEFAULT NULL --(DC2Type:datetime_immutable)
)
-- ibexa:sql-statement-separator
CREATE INDEX ibexa_messenger_created_at_idx ON ibexa_messenger_messages (created_at)
-- ibexa:sql-statement-separator
CREATE INDEX ibexa_messenger_available_at_idx ON ibexa_messenger_messages (available_at)
-- ibexa:sql-statement-separator
CREATE INDEX ibexa_messenger_delivered_at_idx ON ibexa_messenger_messages (delivered_at)
-- ibexa:sql-statement-separator
CREATE TABLE ibexa_messenger_lock_keys (key_id VARCHAR(64) NOT NULL, key_token VARCHAR(44) NOT NULL, key_expiration INTEGER UNSIGNED NOT NULL, PRIMARY KEY(key_id))
