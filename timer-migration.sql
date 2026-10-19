ALTER TABLE user_subscriptions
    ADD COLUMN renewal_time TIME NULL AFTER renewal_date;
