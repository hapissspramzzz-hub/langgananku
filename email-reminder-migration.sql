CREATE TABLE IF NOT EXISTS subscription_email_reminders (
    subscription_id CHAR(36) NOT NULL,
    renewal_at DATETIME NOT NULL,
    sent_at DATETIME NOT NULL,
    PRIMARY KEY (subscription_id, renewal_at),
    CONSTRAINT fk_email_reminder_subscription
        FOREIGN KEY (subscription_id)
        REFERENCES user_subscriptions (id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
