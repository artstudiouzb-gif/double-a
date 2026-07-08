-- Аренда строк очередей (защита от гонки параллельных cron-воркеров):
-- строка, забранная воркером через FOR UPDATE SKIP LOCKED, помечается
-- locked_until и не выдаётся другим процессам до истечения аренды.
ALTER TABLE mail_queue         ADD COLUMN IF NOT EXISTS locked_until DATETIME NULL AFTER attempts;
ALTER TABLE webhook_deliveries ADD COLUMN IF NOT EXISTS locked_until DATETIME NULL AFTER attempts;
ALTER TABLE social_posts       ADD COLUMN IF NOT EXISTS locked_until DATETIME NULL AFTER attempts;
