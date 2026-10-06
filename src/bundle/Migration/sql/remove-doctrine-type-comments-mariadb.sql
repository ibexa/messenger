ALTER TABLE ibexa_messenger_messages
    MODIFY available_at DATETIME NOT NULL,
    MODIFY created_at DATETIME NOT NULL,
    MODIFY delivered_at DATETIME DEFAULT NULL;
