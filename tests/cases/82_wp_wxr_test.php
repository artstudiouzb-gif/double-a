<?php

declare(strict_types=1);

use App\Core\WordPressWxrImporter;

// Разбор файла экспорта WordPress (WXR): посты, вложения, язык и группа перевода.

test('WXR parse извлекает пост, вложение, язык и группу перевода', function () {
    $xml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<rss version="2.0"
    xmlns:content="http://purl.org/rss/1.0/modules/content/"
    xmlns:excerpt="http://wordpress.org/export/1.2/excerpt/"
    xmlns:wp="http://wordpress.org/export/1.2/">
<channel>
  <wp:base_site_url>https://asdr.gov.uz</wp:base_site_url>
  <item>
    <title>Attachment</title>
    <wp:post_id>4073</wp:post_id>
    <wp:post_type>attachment</wp:post_type>
    <wp:attachment_url>https://asdr.gov.uz/wp-content/uploads/2026/07/1-scaled.jpg</wp:attachment_url>
  </item>
  <item>
    <title>Тестовая новость</title>
    <link>https://asdr.gov.uz/test-novost/</link>
    <content:encoded><![CDATA[<p>Тело <img src="https://asdr.gov.uz/wp-content/uploads/2026/07/2.jpg"></p>]]></content:encoded>
    <excerpt:encoded><![CDATA[Анонс]]></excerpt:encoded>
    <wp:post_id>4072</wp:post_id>
    <wp:post_name>test-novost</wp:post_name>
    <wp:post_date>2026-07-02 19:03:00</wp:post_date>
    <wp:status>publish</wp:status>
    <wp:post_type>post</wp:post_type>
    <category domain="language" nicename="uz">Uzbek</category>
    <category domain="post_translations" nicename="pll_abc">Translations</category>
    <wp:postmeta><wp:meta_key>_thumbnail_id</wp:meta_key><wp:meta_value>4073</wp:meta_value></wp:postmeta>
  </item>
</channel>
</rss>
XML;

    $d = WordPressWxrImporter::parse($xml);
    assert_same('https://asdr.gov.uz', $d['site'], 'база сайта разобрана');
    assert_same(1, count($d['posts']), 'один пост (attachment не считается постом)');
    $p = $d['posts'][0];
    assert_same('test-novost', $p['slug'], 'slug');
    assert_same('uz', $p['lang'], 'язык из category domain=language');
    assert_same('pll_abc', $p['group'], 'группа перевода из post_translations');
    assert_same(4073, $p['thumb_id'], '_thumbnail_id прочитан');
    assert_same('https://asdr.gov.uz/wp-content/uploads/2026/07/1-scaled.jpg', $d['attachments'][4073] ?? '', 'вложение по id');
    assert_true(str_contains($p['content'], '<img'), 'контент с картинкой сохранён');
});
