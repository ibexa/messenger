CREATE TABLE ibexa_messenger_messages (id BIGSERIAL NOT NULL, body TEXT NOT NULL, headers TEXT NOT NULL, queue_name VARCHAR(190) NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, available_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, delivered_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, PRIMARY KEY(id));
-- ibexa:sql-statement-separator
CREATE INDEX ibexa_messenger_messages_queue_available_delivered_idx ON ibexa_messenger_messages (queue_name, available_at, delivered_at, id);
-- ibexa:sql-statement-separator
COMMENT ON COLUMN ibexa_messenger_messages.created_at IS '(DC2Type:datetime_immutable)';
-- ibexa:sql-statement-separator
COMMENT ON COLUMN ibexa_messenger_messages.available_at IS '(DC2Type:datetime_immutable)';
-- ibexa:sql-statement-separator
COMMENT ON COLUMN ibexa_messenger_messages.delivered_at IS '(DC2Type:datetime_immutable)';
-- ibexa:sql-statement-separator
CREATE TABLE ibexa_messenger_lock_keys (key_id VARCHAR(64) NOT NULL, key_token VARCHAR(44) NOT NULL, key_expiration INT NOT NULL, PRIMARY KEY(key_id));
