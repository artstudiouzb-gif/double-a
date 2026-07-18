<?php

declare(strict_types=1);

test('Печать новости: служебные блоки помечены в шаблоне, а не только в CSS', function () {
    $view = (string) file_get_contents(dirname(__DIR__, 2) . '/app/Views/site/news_show.php');

    // Пометка живёт в разметке: переименование CSS-классов при редизайне не
    // должно снова возвращать на печать подписку и «Другие новости».
    foreach ([
        'newsdetail-share no-print' => 'кнопки «Поделиться»',
        'newsdetail-subscribe no-print' => 'блок подписки',
        'newsdetail-related no-print' => 'похожие новости',
        'newsdetail-adjacent no-print' => 'предыдущая/следующая новость',
    ] as $needle => $what) {
        assert_contains($needle, $view, "на печать попадёт {$what}");
    }

    // Сама статья и её содержательные части не помечаются как непечатаемые.
    assert_not_contains('newsdetail-body no-print', $view);
    assert_not_contains('newsdetail-card no-print', $view);
});

test('Печать новости: печатные стили знают текущие классы страницы', function () {
    $css = (string) file_get_contents(dirname(__DIR__, 2) . '/public/assets/css/frontend.css');
    $printBlock = substr($css, (int) strpos($css, '@media print'), 3000);

    // Общий выключатель для помеченной разметки.
    assert_contains('.no-print', $printBlock);
    // Интерактив внутри статьи на бумаге бесполезен.
    foreach ([
        '.newsdetail-gallery__nav',
        '.newsdetail-toc',
        '.crumbs',
        '.scroll-top',
    ] as $selector) {
        assert_contains($selector, $printBlock, "не скрыт {$selector}");
    }
    // Белый текст обложки на бумаге нечитаем — печатаем чёрным без подложки.
    assert_contains('.newsdetail-phero { background-image: none', $printBlock);
    assert_contains('.newsdetail-phero__title', $printBlock);
    // Колонки статьи на A4 разворачиваются в один поток.
    assert_contains('.newsdetail-body, .newsdetail-head { display: block', $printBlock);
});
