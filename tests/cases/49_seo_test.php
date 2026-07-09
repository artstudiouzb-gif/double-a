<?php

declare(strict_types=1);

use App\Core\SchemaOrg;

test('SchemaOrg::organization собирает карточку, пустые поля опускаются', function () {
    $full = SchemaOrg::organization('АСДР', 'https://asdr.gov.uz', '+998 71 200-00-00', 'info@asdr.gov.uz', 'г. Ташкент', 'https://asdr.gov.uz/logo.png');
    assert_same('GovernmentOrganization', $full['@type']);
    assert_same('АСДР', $full['name']);
    assert_same('г. Ташкент', $full['address']['streetAddress']);

    $min = SchemaOrg::organization('АСДР', 'https://asdr.gov.uz');
    assert_false(isset($min['telephone']));
    assert_false(isset($min['address']));
    assert_false(isset($min['logo']));
});

test('SchemaOrg::newsArticle: headline обрезается, дата в ISO 8601', function () {
    $a = SchemaOrg::newsArticle(str_repeat('З', 150), 'https://x.uz/news/n1', '2026-07-09 10:00:00', 'Анонс', '', 'АСДР');
    assert_same(110, mb_strlen($a['headline']), 'headline не длиннее 110');
    assert_true(str_starts_with((string) $a['datePublished'], '2026-07-09T'));
    assert_same('АСДР', $a['publisher']['name']);
    assert_false(isset($a['image']), 'пустая картинка опущена');
});

test('SchemaOrg::event и breadcrumbs: структура позиций', function () {
    $e = SchemaOrg::event('Круглый стол', 'https://x.uz/e', '2026-07-15', 'Зал 1');
    assert_same('Event', $e['@type']);
    assert_same('Зал 1', $e['location']['name']);

    $b = SchemaOrg::breadcrumbs([['Главная', 'https://x.uz/'], ['Документы', 'https://x.uz/catalog/documenty'], ['Приказ №5', '']]);
    assert_same(3, count($b['itemListElement']));
    assert_same(1, $b['itemListElement'][0]['position']);
    assert_same(3, $b['itemListElement'][2]['position']);
    assert_false(isset($b['itemListElement'][2]['item']), 'последний элемент без ссылки');
});

test('SchemaOrg::render: валидный JSON и защита от </script>', function () {
    $html = SchemaOrg::render(SchemaOrg::organization('X</script><script>alert(1)</script>', 'https://x.uz'));
    assert_true(str_starts_with($html, '<script type="application/ld+json">'));
    assert_false(str_contains($html, '</script><script>'), 'HEX_TAG экранирует теги внутри JSON');

    $json = substr($html, strlen('<script type="application/ld+json">'), -strlen('</script>'));
    assert_true(is_array(json_decode($json, true)), 'внутри — валидный JSON');
});
