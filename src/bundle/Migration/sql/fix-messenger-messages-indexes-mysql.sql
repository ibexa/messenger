DROP INDEX ibexa_messenger_created_at_idx ON ibexa_messenger_messages;
-- ibexa:sql-statement-separator
DROP INDEX ibexa_messenger_available_at_idx ON ibexa_messenger_messages;
-- ibexa:sql-statement-separator
DROP INDEX ibexa_messenger_delivered_at_idx ON ibexa_messenger_messages;
-- ibexa:sql-statement-separator
CREATE INDEX IDX_837A775AFB7336F0E3BD61CE16BA31DBBF396750 ON ibexa_messenger_messages (queue_name, available_at, delivered_at, id);
