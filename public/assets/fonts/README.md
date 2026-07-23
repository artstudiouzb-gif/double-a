# Самохостинг шрифтов

Локальные `.woff2` подмножества (cyrillic + latin) двух гарнитур:

- **Montserrat** — заголовки (веса 700, 800);
- **Manrope** — основной текст (веса 400, 500, 600, 700).

Обе гарнитуры распространяются под лицензией **SIL Open Font License 1.1**
(© их авторы, Google Fonts). Файлы получены из Google Fonts API (`css2`) и
подключаются через `public/assets/css/fonts.css` без обращения к внешним CDN —
это важно для строгого CSP и работы сайта в закрытом контуре.

Кириллические подмножества обязательны для RU/UZ-контента: у оригинального
Poppins из референса кириллицы нет, поэтому в роли «тела» используется Manrope
(геометрический гротеск близкого характера с полной кириллицей).

## Перегенерация

```bash
# из Google Fonts css2 с User-Agent современного браузера (иначе отдаётся ttf)
curl -A '<современный UA>' \
  'https://fonts.googleapis.com/css2?family=Montserrat:wght@700;800&display=swap' -o m.css
curl -A '<современный UA>' \
  'https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700&display=swap' -o mn.css
# затем скачать woff2 из блоков /* cyrillic */ и /* latin */ и обновить fonts.css.
```

## Гос-тема

- **PT Serif** — заголовки, **PT Sans** — текст (веса 400/700, cyrillic+latin, OFL).
  Подключаются через `public/assets/css/gov-fonts.css` (см. `gov-theme.css`).

## Тема DOUBLE A (modern)

- **Noto Serif** (condensed, ось ширины) — заголовки; **Noto Sans** — текст.
  Веса вариативные (Sans 300–800; Serif 400–900 + italic), подмножества
  cyrillic+latin, OFL. Подключаются через `public/assets/css/noto-fonts.css`
  (см. `_header.php`, ветка `double_a`) — без внешних CDN ради строгого CSP.
