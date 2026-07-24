-- Кейсы: измеримый результат (паттерн консалтинга — доказательство результата).
-- result_metric — короткий акцент для карточки/детальной («−3 месяца», «12 стран»),
-- result_label — пояснение к метрике («сокращение сроков разрешений»).
ALTER TABLE projects
    ADD COLUMN result_metric VARCHAR(80) NULL COMMENT 'короткий результат-акцент для карточки кейса' AFTER description,
    ADD COLUMN result_label VARCHAR(160) NULL COMMENT 'пояснение к метрике результата' AFTER result_metric;
