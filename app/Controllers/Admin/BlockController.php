<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Flash;
use App\Core\View;
use App\Core\TextProcessor;
use App\Models\Block;
use App\Models\BlockRevision;
use App\Models\FormDef;
use App\Models\Language;
use App\Models\Page;

final class BlockController
{
    private const TYPES = ['text', 'html', 'cta', 'advantages', 'slider', 'gallery', 'form', 'columns', 'testimonials', 'counters', 'team_list', 'projects_list', 'news_latest', 'partners', 'banner', 'faq', 'subscribe', 'contact_cards', 'hero', 'categories_grid', 'media_materials', 'cards_grid', 'image_cards', 'media_gallery', 'news_feature', 'person_cards', 'timeline', 'news_docs', 'cta_band', 'person_profile', 'feature_band', 'bio_education', 'anchor_nav', 'stages', 'text_image', 'docs_list', 'map_point'];

    public function store(array $params): void
    {
        Auth::requireLogin();
        Csrf::verifyRequest();

        $pageId = (int) $params['id'];
        $page = Page::findById($pageId);
        if (!$page) {
            http_response_code(404);
            View::render('errors/404');
            return;
        }

        $type = (string) ($_POST['type'] ?? '');
        $lang = (string) ($_POST['block_lang'] ?? Language::defaultCode());
        if (!Language::isActive($lang)) {
            $lang = Language::defaultCode();
        }

        if (!in_array($type, self::TYPES, true)) {
            Flash::error('Неизвестный тип блока.');
            header('Location: /admin/pages/' . $pageId . '/edit?block_lang=' . urlencode($lang));
            exit;
        }

        // Блок сырого HTML может создавать только супер-администратор.
        if ($type === 'html' && !Auth::isSuperAdmin()) {
            Flash::error('Блок «HTML-код» доступен только супер-администратору.');
            header('Location: /admin/pages/' . $pageId . '/edit?block_lang=' . urlencode($lang));
            exit;
        }

        // Вложенность в колонки (группа 4.1): блок можно добавить внутрь блока
        // columns, передав parent_block_id + column_index.
        $parentBlockId = null;
        $columnIndex = 0;
        $redirectTo = '/admin/pages/' . $pageId . '/edit?block_lang=' . urlencode($lang);
        if (!empty($_POST['parent_block_id'])) {
            $parent = Block::findById((int) $_POST['parent_block_id']);
            if (!$parent || (int) $parent['page_id'] !== $pageId || (string) $parent['type'] !== 'columns') {
                Flash::error('Некорректный родительский блок для колонки.');
                header('Location: ' . $redirectTo);
                exit;
            }
            // Запрет columns-в-columns.
            if ($type === 'columns') {
                Flash::error('Блок «Колонки» нельзя вкладывать в колонки.');
                header('Location: ' . $redirectTo);
                exit;
            }
            $parentBlockId = (int) $parent['id'];
            $columnIndex = max(0, (int) ($_POST['column_index'] ?? 0));
        }

        $title = trim((string) ($_POST['title'] ?? ''));
        $blockId = Block::create(
            $pageId,
            $lang,
            $type,
            $title !== '' ? $title : null,
            \App\Core\BlockRenderer::defaultsFor($type),
            '',
            $parentBlockId,
            $columnIndex
        );
        \App\Core\Cache::forgetPrefix('page:' . $pageId);

        Flash::success('Блок добавлен. Заполните его содержимое.');
        header('Location: /admin/blocks/' . $blockId . '/edit');
        exit;
    }

    public function edit(array $params): void
    {
        Auth::requireLogin();

        $block = Block::findById((int) $params['id']);
        if (!$block) {
            http_response_code(404);
            View::render('errors/404');
            return;
        }

        $data = json_decode((string) $block['data'], true) ?: [];

        View::render('admin/pages/block_form', [
            'block' => $block,
            'data' => $data,
            'forms' => $block['type'] === 'form' ? FormDef::all() : [],
        ]);
    }

    public function update(array $params): void
    {
        Auth::requireLogin();
        Csrf::verifyRequest();

        $block = Block::findById((int) $params['id']);
        if (!$block) {
            http_response_code(404);
            View::render('errors/404');
            return;
        }

        $title = trim((string) ($_POST['title'] ?? ''));
        // Кастомный CSS может менять только супер-администратор; для редактора
        // сохраняем прежнее значение независимо от присланных данных.
        $customCss = Auth::isSuperAdmin()
            ? (string) ($_POST['custom_css'] ?? '')
            : (string) ($block['custom_css'] ?? '');
        $locale = ((string) $block['lang'] === 'en') ? 'en' : 'ru';
        $data = $this->collectData($block['type'], $locale);

        // Дизайн-система (общие для всех типов): пресет отступов и анимация.
        $data['_spacing'] = in_array($_POST['spacing'] ?? 'premium', ['none', 'small', 'premium', 'max'], true)
            ? $_POST['spacing'] : 'premium';
        // Анимация появления (группа 4.2): {enabled, type}. Пустой тип = выключено.
        $revealType = (string) ($_POST['reveal_type'] ?? '');
        $allowedReveal = ['fade', 'slide-up', 'slide-left', 'slide-right', 'zoom-in'];
        $data['_reveal'] = in_array($revealType, $allowedReveal, true)
            ? ['enabled' => true, 'type' => $revealType]
            : ['enabled' => false, 'type' => 'fade'];

        // Фон секции и её ширина (полноширинная подложка), а также независимые
        // отступы сверху/снизу — общие оформительские опции для любого блока.
        $data['_bg'] = in_array($_POST['bg'] ?? 'none', ['none', 'light', 'tint', 'navy'], true)
            ? $_POST['bg'] : 'none';
        $data['_fullwidth'] = !empty($_POST['fullwidth']);
        $padOptions = ['default', 'none', 'small', 'medium', 'large'];
        $data['_pad_top'] = in_array($_POST['pad_top'] ?? 'default', $padOptions, true) ? $_POST['pad_top'] : 'default';
        $data['_pad_bottom'] = in_array($_POST['pad_bottom'] ?? 'default', $padOptions, true) ? $_POST['pad_bottom'] : 'default';

        // История версий (группа 5.1): снимаем текущее состояние ПЕРЕД перезаписью.
        BlockRevision::snapshot(
            (int) $block['id'],
            $block['title'] !== null ? (string) $block['title'] : null,
            json_decode((string) $block['data'], true) ?: [],
            $block['custom_css'] !== null ? (string) $block['custom_css'] : null,
            Auth::id()
        );

        Block::update((int) $block['id'], $title !== '' ? $title : null, $data, $customCss);
        \App\Core\Cache::forgetPrefix('page:' . (int) $block['page_id']);

        Flash::success('Блок сохранён.');
        header('Location: ' . $this->pageEditUrl($block));
        exit;
    }

    /** История версий блока (группа 5.1). */
    public function revisions(array $params): void
    {
        Auth::requireLogin();
        $block = Block::findById((int) $params['id']);
        if (!$block) {
            http_response_code(404);
            View::render('errors/404');
            return;
        }

        View::render('admin/blocks/revisions', [
            'block' => $block,
            'revisions' => BlockRevision::forBlock((int) $block['id']),
            'backUrl' => $this->pageEditUrl($block),
        ]);
    }

    /** Восстановление блока из ревизии (создаёт новую ревизию, группа 5.1). */
    public function restoreRevision(array $params): void
    {
        Auth::requireLogin();
        Csrf::verifyRequest();

        $block = Block::findById((int) $params['id']);
        if (!$block) {
            http_response_code(404);
            View::render('errors/404');
            return;
        }
        $rev = BlockRevision::findById((int) ($_POST['revision_id'] ?? 0));
        if (!$rev || (int) $rev['block_id'] !== (int) $block['id']) {
            Flash::error('Ревизия не найдена.');
            header('Location: /admin/blocks/' . (int) $block['id'] . '/revisions');
            exit;
        }

        // custom_css трогает только супер-админ; редактору оставляем текущий.
        $customCss = Auth::isSuperAdmin()
            ? ($rev['custom_css'] !== null ? (string) $rev['custom_css'] : '')
            : (string) ($block['custom_css'] ?? '');

        // Снимок текущего состояния, затем применяем ревизию.
        BlockRevision::snapshot(
            (int) $block['id'],
            $block['title'] !== null ? (string) $block['title'] : null,
            json_decode((string) $block['data'], true) ?: [],
            $block['custom_css'] !== null ? (string) $block['custom_css'] : null,
            Auth::id()
        );

        Block::update(
            (int) $block['id'],
            $rev['title'] !== null ? (string) $rev['title'] : null,
            json_decode((string) $rev['data'], true) ?: [],
            $customCss
        );
        \App\Core\Cache::forgetPrefix('page:' . (int) $block['page_id']);

        Flash::success('Блок восстановлен из выбранной версии.');
        header('Location: ' . $this->pageEditUrl($block));
        exit;
    }

    public function destroy(array $params): void
    {
        Auth::requireLogin();
        Csrf::verifyRequest();

        $block = Block::findById((int) $params['id']);
        if (!$block) {
            http_response_code(404);
            View::render('errors/404');
            return;
        }

        Block::delete((int) $block['id']);
        \App\Core\Cache::forgetPrefix('page:' . (int) $block['page_id']);
        Flash::success('Блок удалён.');
        header('Location: ' . $this->pageEditUrl($block));
        exit;
    }

    /** AJAX-сохранение нового порядка блоков (drag-and-drop, задача 134). */
    public function reorder(): void
    {
        Auth::requireLogin();
        header('Content-Type: application/json; charset=UTF-8');

        if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
            http_response_code(419);
            echo json_encode(['ok' => false, 'error' => 'CSRF']);
            return;
        }

        $pageId = (int) ($_POST['page_id'] ?? 0);
        $lang = (string) ($_POST['block_lang'] ?? Language::defaultCode());
        if (!Language::isActive($lang)) {
            $lang = Language::defaultCode();
        }
        $order = array_map('intval', (array) ($_POST['order'] ?? []));

        if ($pageId <= 0 || $order === []) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => 'bad params']);
            return;
        }

        Block::reorder($pageId, $lang, $order);
        \App\Core\Cache::forgetPrefix('page:' . $pageId);

        echo json_encode(['ok' => true]);
    }

    public function move(array $params): void
    {
        Auth::requireLogin();
        Csrf::verifyRequest();

        $block = Block::findById((int) $params['id']);
        if (!$block) {
            http_response_code(404);
            View::render('errors/404');
            return;
        }

        $lang = (string) $block['lang'];
        $direction = $_POST['direction'] ?? '';
        if ($direction === 'up') {
            Block::moveUp((int) $block['id'], (int) $block['page_id'], $lang);
        } elseif ($direction === 'down') {
            Block::moveDown((int) $block['id'], (int) $block['page_id'], $lang);
        }
        \App\Core\Cache::forgetPrefix('page:' . (int) $block['page_id']);

        header('Location: ' . $this->pageEditUrl($block));
        exit;
    }

    /** Включение/отключение вывода блока на сайте (без удаления). */
    public function toggle(array $params): void
    {
        Auth::requireLogin();
        Csrf::verifyRequest();

        $block = Block::findById((int) $params['id']);
        if (!$block) {
            http_response_code(404);
            View::render('errors/404');
            return;
        }

        $newState = (int) ($block['is_active'] ?? 1) !== 1;
        Block::setActive((int) $block['id'], $newState);
        \App\Core\Cache::forgetPrefix('page:' . (int) $block['page_id']);

        Flash::success($newState ? 'Блок включён и снова выводится на сайте.' : 'Блок отключён — он скрыт на сайте, но сохранён.');
        header('Location: ' . $this->pageEditUrl($block));
        exit;
    }

    private function pageEditUrl(array $block): string
    {
        return '/admin/pages/' . (int) $block['page_id'] . '/edit?block_lang=' . urlencode((string) $block['lang']);
    }

    private function collectData(string $type, string $locale = 'ru'): array
    {
        switch ($type) {
            case 'text':
                return [
                    'title' => TextProcessor::typographPlain(trim((string) ($_POST['title_field'] ?? '')), $locale),
                    'content' => TextProcessor::process((string) ($_POST['content'] ?? ''), $locale),
                ];
            case 'html':
                // Супер-администратор — доверенный источник (сырой HTML).
                // Роль editor: контент проходит строгий allowlist-санитайзер,
                // вырезающий <script>, обработчики on* и опасные URI.
                $rawHtml = (string) ($_POST['html'] ?? '');
                return [
                    'html' => Auth::isSuperAdmin()
                        ? $rawHtml
                        : \App\Core\HtmlSanitizer::sanitize($rawHtml),
                ];
            case 'cta':
                $buttonUrl = trim((string) ($_POST['button_url'] ?? ''));
                // Отсекаем javascript:/data: и прочие небезопасные схемы в ссылке.
                if ($buttonUrl !== '' && !\App\Core\UrlGuard::isSafeLink($buttonUrl)) {
                    $buttonUrl = '';
                }
                return [
                    'title' => TextProcessor::typographPlain(trim((string) ($_POST['title_field'] ?? '')), $locale),
                    'text' => TextProcessor::typographPlain(trim((string) ($_POST['text'] ?? '')), $locale),
                    'button_text' => trim((string) ($_POST['button_text'] ?? '')),
                    'button_url' => $buttonUrl,
                ];
            case 'advantages':
                $items = [];
                foreach ((array) ($_POST['items'] ?? []) as $item) {
                    $itemTitle = trim((string) ($item['title'] ?? ''));
                    $itemText = trim((string) ($item['text'] ?? ''));
                    if ($itemTitle === '' && $itemText === '') {
                        continue;
                    }
                    // SVG-иконка кодом (группа 4.3): сохраняем уже очищенную
                    // версию (вырезаем <script>, on*-обработчики, внешние ссылки).
                    $iconSvg = trim((string) ($item['icon_svg'] ?? ''));
                    if ($iconSvg !== '') {
                        $iconSvg = \App\Core\Uploader::sanitizeSvgString($iconSvg);
                    }
                    $items[] = [
                        'icon' => trim((string) ($item['icon'] ?? '')),
                        'icon_svg' => $iconSvg,
                        'title' => TextProcessor::typographPlain($itemTitle, $locale),
                        'text' => TextProcessor::typographPlain($itemText, $locale),
                    ];
                }
                return [
                    'title' => TextProcessor::typographPlain(trim((string) ($_POST['title_field'] ?? '')), $locale),
                    'items' => $items,
                ];
            case 'slider':
                $slides = [];
                foreach ((array) ($_POST['slides'] ?? []) as $slide) {
                    $image = trim((string) ($slide['image'] ?? ''));
                    if ($image === '') {
                        continue;
                    }
                    $slides[] = [
                        'image' => $image,
                        'alt' => trim((string) ($slide['alt'] ?? '')),
                        'caption' => trim((string) ($slide['caption'] ?? '')),
                    ];
                }
                return ['slides' => $slides];
            case 'gallery':
                $images = [];
                foreach ((array) ($_POST['images'] ?? []) as $image) {
                    $url = trim((string) ($image['url'] ?? ''));
                    if ($url === '') {
                        continue;
                    }
                    $images[] = [
                        'url' => $url,
                        'caption' => trim((string) ($image['caption'] ?? '')),
                    ];
                }
                return [
                    'title' => trim((string) ($_POST['title_field'] ?? '')),
                    'images' => $images,
                ];
            case 'form':
                $formId = (int) ($_POST['form_id'] ?? 0);
                return ['form_id' => $formId > 0 ? $formId : null];
            case 'columns':
                $cols = (int) ($_POST['columns'] ?? 2);
                if ($cols < 2 || $cols > 4) {
                    $cols = 2;
                }
                $gap = in_array($_POST['gap'] ?? 'medium', ['small', 'medium', 'large'], true)
                    ? (string) $_POST['gap'] : 'medium';
                return ['columns' => $cols, 'gap' => $gap];
            case 'testimonials':
                $items = [];
                foreach ((array) ($_POST['items'] ?? []) as $item) {
                    $quote = trim((string) ($item['quote'] ?? ''));
                    $name = trim((string) ($item['name'] ?? ''));
                    if ($quote === '' && $name === '') {
                        continue;
                    }
                    $photo = trim((string) ($item['photo'] ?? ''));
                    $items[] = [
                        'quote' => TextProcessor::typographPlain($quote, $locale),
                        'name' => TextProcessor::typographPlain($name, $locale),
                        'company' => TextProcessor::typographPlain(trim((string) ($item['company'] ?? '')), $locale),
                        'photo' => \App\Core\UrlGuard::isSafeLink($photo) ? $photo : '',
                    ];
                }
                return [
                    'title' => TextProcessor::typographPlain(trim((string) ($_POST['title_field'] ?? '')), $locale),
                    'items' => $items,
                ];
            case 'counters':
                $items = [];
                foreach ((array) ($_POST['items'] ?? []) as $item) {
                    $value = trim((string) ($item['value'] ?? ''));
                    $label = trim((string) ($item['label'] ?? ''));
                    if ($value === '' && $label === '') {
                        continue;
                    }
                    $iconSvg = trim((string) ($item['icon_svg'] ?? ''));
                    if ($iconSvg !== '') {
                        $iconSvg = \App\Core\Uploader::sanitizeSvgString($iconSvg);
                    }
                    $items[] = [
                        // Число хранится как целое (для анимации инкремента).
                        'value' => (int) preg_replace('/\D+/', '', $value),
                        'suffix' => trim((string) ($item['suffix'] ?? '')),
                        'label' => TextProcessor::typographPlain($label, $locale),
                        'icon_svg' => $iconSvg,
                    ];
                }
                return [
                    'title' => TextProcessor::typographPlain(trim((string) ($_POST['title_field'] ?? '')), $locale),
                    'items' => $items,
                ];
            case 'team_list':
            case 'projects_list':
            case 'news_latest':
                return [
                    'title' => TextProcessor::typographPlain(trim((string) ($_POST['title_field'] ?? '')), $locale),
                    'limit' => max(0, (int) ($_POST['limit'] ?? 0)),
                ];
            case 'partners':
                $items = [];
                foreach ((array) ($_POST['items'] ?? []) as $item) {
                    $logo = trim((string) ($item['logo'] ?? ''));
                    if ($logo === '') {
                        continue;
                    }
                    $url = trim((string) ($item['url'] ?? ''));
                    if ($url !== '' && !\App\Core\UrlGuard::isSafeLink($url)) {
                        $url = '';
                    }
                    $items[] = [
                        'logo' => $logo,
                        'name' => trim((string) ($item['name'] ?? '')),
                        'url' => $url,
                    ];
                }
                return [
                    'title' => TextProcessor::typographPlain(trim((string) ($_POST['title_field'] ?? '')), $locale),
                    'items' => $items,
                ];
            case 'subscribe':
                return [
                    'title' => TextProcessor::typographPlain(trim((string) ($_POST['title_field'] ?? '')), $locale),
                    'text' => TextProcessor::typographPlain(trim((string) ($_POST['text'] ?? '')), $locale),
                    'button_text' => trim((string) ($_POST['button_text'] ?? '')),
                ];
            case 'banner':
                $bannerUrl = trim((string) ($_POST['button_url'] ?? ''));
                if ($bannerUrl !== '' && !\App\Core\UrlGuard::isSafeLink($bannerUrl)) {
                    $bannerUrl = '';
                }
                return [
                    'title' => TextProcessor::typographPlain(trim((string) ($_POST['title_field'] ?? '')), $locale),
                    'text' => TextProcessor::typographPlain(trim((string) ($_POST['text'] ?? '')), $locale),
                    'image' => trim((string) ($_POST['image'] ?? '')),
                    'style' => ($_POST['style'] ?? 'dark') === 'light' ? 'light' : 'dark',
                    'button_text' => trim((string) ($_POST['button_text'] ?? '')),
                    'button_url' => $bannerUrl,
                ];
            case 'faq':
                $items = [];
                foreach ((array) ($_POST['items'] ?? []) as $item) {
                    $q = trim((string) ($item['question'] ?? ''));
                    $a = trim((string) ($item['answer'] ?? ''));
                    if ($q === '' && $a === '') {
                        continue;
                    }
                    $items[] = [
                        'question' => TextProcessor::typographPlain($q, $locale),
                        'answer' => TextProcessor::process($a, $locale),
                    ];
                }
                return [
                    'title' => TextProcessor::typographPlain(trim((string) ($_POST['title_field'] ?? '')), $locale),
                    'items' => $items,
                ];
            case 'contact_cards':
                $items = [];
                foreach ((array) ($_POST['items'] ?? []) as $item) {
                    $itemTitle = trim((string) ($item['title'] ?? ''));
                    $lines = trim((string) ($item['lines'] ?? ''));
                    if ($itemTitle === '' && $lines === '') {
                        continue;
                    }
                    $iconSvg = trim((string) ($item['icon_svg'] ?? ''));
                    if ($iconSvg !== '') {
                        $iconSvg = \App\Core\Uploader::sanitizeSvgString($iconSvg);
                    }
                    $items[] = [
                        'icon_svg' => $iconSvg,
                        'title' => TextProcessor::typographPlain($itemTitle, $locale),
                        'lines' => $lines,
                        'link_url' => trim((string) ($item['link_url'] ?? '')),
                        'link_text' => trim((string) ($item['link_text'] ?? '')),
                    ];
                }
                return [
                    'title' => TextProcessor::typographPlain(trim((string) ($_POST['title_field'] ?? '')), $locale),
                    'items' => $items,
                ];
            case 'hero':
                $safe = static fn (string $u): string => ($u !== '' && \App\Core\UrlGuard::isSafeLink($u)) ? $u : '';
                return [
                    'title' => TextProcessor::typographPlain(trim((string) ($_POST['title_field'] ?? '')), $locale),
                    'eyebrow' => TextProcessor::typographPlain(trim((string) ($_POST['eyebrow'] ?? '')), $locale),
                    'subtitle' => TextProcessor::typographPlain(trim((string) ($_POST['subtitle'] ?? '')), $locale),
                    'image' => trim((string) ($_POST['image'] ?? '')),
                    'video_url' => trim((string) ($_POST['video_url'] ?? '')),
                    'button_text' => trim((string) ($_POST['button_text'] ?? '')),
                    'button_url' => $safe(trim((string) ($_POST['button_url'] ?? ''))),
                    'button2_text' => trim((string) ($_POST['button2_text'] ?? '')),
                    'button2_url' => $safe(trim((string) ($_POST['button2_url'] ?? ''))),
                    'video_button_text' => trim((string) ($_POST['video_button_text'] ?? '')),
                    'video_button_url' => $safe(trim((string) ($_POST['video_button_url'] ?? ''))),
                ];
            case 'cards_grid':
            case 'image_cards':
            case 'media_gallery':
                $items = [];
                foreach ((array) ($_POST['items'] ?? []) as $item) {
                    $label = trim((string) ($item['title'] ?? $item['label'] ?? ''));
                    if ($label === '') {
                        continue;
                    }
                    $url = trim((string) ($item['url'] ?? ''));
                    if ($url !== '' && !\App\Core\UrlGuard::isSafeLink($url)) {
                        $url = '';
                    }
                    $iconSvg = trim((string) ($item['icon_svg'] ?? ''));
                    if ($iconSvg !== '') {
                        $iconSvg = \App\Core\Uploader::sanitizeSvgString($iconSvg);
                    }
                    $items[] = [
                        'icon_svg' => $iconSvg,
                        'image' => trim((string) ($item['image'] ?? '')),
                        'title' => TextProcessor::typographPlain($label, $locale),
                        'text' => TextProcessor::typographPlain(trim((string) ($item['text'] ?? '')), $locale),
                        'meta' => TextProcessor::typographPlain(trim((string) ($item['meta'] ?? '')), $locale),
                        'kind' => ($item['kind'] ?? '') === 'photo' ? 'photo' : 'video',
                        'url' => $url,
                    ];
                }
                $cols = (int) ($_POST['columns'] ?? 5);
                return [
                    'title' => TextProcessor::typographPlain(trim((string) ($_POST['title_field'] ?? '')), $locale),
                    'all_text' => trim((string) ($_POST['all_text'] ?? '')),
                    'all_url' => (trim((string) ($_POST['all_url'] ?? '')) !== '' && \App\Core\UrlGuard::isSafeLink(trim((string) ($_POST['all_url'] ?? '')))) ? trim((string) ($_POST['all_url'] ?? '')) : '',
                    'columns' => max(2, min(5, $cols)),
                    'items' => $items,
                ];
            case 'news_feature':
                return [
                    'title' => TextProcessor::typographPlain(trim((string) ($_POST['title_field'] ?? '')), $locale),
                    'all_text' => trim((string) ($_POST['all_text'] ?? '')),
                    'all_url' => (trim((string) ($_POST['all_url'] ?? '')) !== '' && \App\Core\UrlGuard::isSafeLink(trim((string) ($_POST['all_url'] ?? '')))) ? trim((string) ($_POST['all_url'] ?? '')) : '',
                    'limit' => max(2, min(12, (int) ($_POST['limit'] ?? 6))),
                ];
            case 'categories_grid':
                $items = [];
                foreach ((array) ($_POST['items'] ?? []) as $item) {
                    $label = trim((string) ($item['label'] ?? ''));
                    if ($label === '') {
                        continue;
                    }
                    $url = trim((string) ($item['url'] ?? ''));
                    if ($url !== '' && !\App\Core\UrlGuard::isSafeLink($url)) {
                        $url = '';
                    }
                    $iconSvg = trim((string) ($item['icon_svg'] ?? ''));
                    if ($iconSvg !== '') {
                        $iconSvg = \App\Core\Uploader::sanitizeSvgString($iconSvg);
                    }
                    $items[] = [
                        'icon_svg' => $iconSvg,
                        'label' => TextProcessor::typographPlain($label, $locale),
                        'url' => $url,
                    ];
                }
                return [
                    'title' => TextProcessor::typographPlain(trim((string) ($_POST['title_field'] ?? '')), $locale),
                    'items' => $items,
                ];
            case 'media_materials':
                $items = [];
                foreach ((array) ($_POST['items'] ?? []) as $item) {
                    $label = trim((string) ($item['label'] ?? ''));
                    if ($label === '') {
                        continue;
                    }
                    $url = trim((string) ($item['url'] ?? ''));
                    if ($url !== '' && !\App\Core\UrlGuard::isSafeLink($url)) {
                        $url = '';
                    }
                    $iconSvg = trim((string) ($item['icon_svg'] ?? ''));
                    if ($iconSvg !== '') {
                        $iconSvg = \App\Core\Uploader::sanitizeSvgString($iconSvg);
                    }
                    $items[] = [
                        'icon_svg' => $iconSvg,
                        'label' => TextProcessor::typographPlain($label, $locale),
                        'action' => TextProcessor::typographPlain(trim((string) ($item['action'] ?? '')), $locale),
                        'url' => $url,
                    ];
                }
                return [
                    'title' => TextProcessor::typographPlain(trim((string) ($_POST['title_field'] ?? '')), $locale),
                    'items' => $items,
                ];
            case 'person_cards':
                $items = [];
                foreach ((array) ($_POST['items'] ?? []) as $item) {
                    $name = trim((string) ($item['name'] ?? ''));
                    $role = trim((string) ($item['role'] ?? ''));
                    if ($name === '' && $role === '') {
                        continue;
                    }
                    $url = trim((string) ($item['url'] ?? ''));
                    if ($url !== '' && !\App\Core\UrlGuard::isSafeLink($url)) {
                        $url = '';
                    }
                    $items[] = [
                        'photo' => trim((string) ($item['photo'] ?? '')),
                        'name' => TextProcessor::typographPlain($name, $locale),
                        'role' => TextProcessor::typographPlain($role, $locale),
                        'url' => $url,
                    ];
                }
                return [
                    'title' => TextProcessor::typographPlain(trim((string) ($_POST['title_field'] ?? '')), $locale),
                    'all_text' => trim((string) ($_POST['all_text'] ?? '')),
                    'all_url' => $this->safeUrlField('all_url'),
                    'items' => $items,
                ];
            case 'timeline':
                $items = [];
                foreach ((array) ($_POST['items'] ?? []) as $item) {
                    $year = trim((string) ($item['year'] ?? ''));
                    $text = trim((string) ($item['text'] ?? ''));
                    if ($year === '' && $text === '') {
                        continue;
                    }
                    $items[] = [
                        'year' => $year,
                        'text' => TextProcessor::typographPlain($text, $locale),
                    ];
                }
                return [
                    'title' => TextProcessor::typographPlain(trim((string) ($_POST['title_field'] ?? '')), $locale),
                    'items' => $items,
                    'button_text' => trim((string) ($_POST['button_text'] ?? '')),
                    'button_url' => $this->safeUrlField('button_url'),
                    'cta_title' => TextProcessor::typographPlain(trim((string) ($_POST['cta_title'] ?? '')), $locale),
                    'cta_text' => TextProcessor::typographPlain(trim((string) ($_POST['cta_text'] ?? '')), $locale),
                    'cta_button_text' => trim((string) ($_POST['cta_button_text'] ?? '')),
                    'cta_button_url' => $this->safeUrlField('cta_button_url'),
                    'cta_image' => trim((string) ($_POST['cta_image'] ?? '')),
                ];
            case 'news_docs':
                $docs = [];
                foreach ((array) ($_POST['docs'] ?? []) as $doc) {
                    $docTitle = trim((string) ($doc['title'] ?? ''));
                    if ($docTitle === '') {
                        continue;
                    }
                    $url = trim((string) ($doc['url'] ?? ''));
                    if ($url !== '' && !\App\Core\UrlGuard::isSafeLink($url)) {
                        $url = '';
                    }
                    $docs[] = [
                        'title' => TextProcessor::typographPlain($docTitle, $locale),
                        'meta' => trim((string) ($doc['meta'] ?? '')),
                        'url' => $url,
                    ];
                }
                return [
                    'news_title' => TextProcessor::typographPlain(trim((string) ($_POST['news_title'] ?? '')), $locale),
                    'news_all_text' => trim((string) ($_POST['news_all_text'] ?? '')),
                    'news_all_url' => $this->safeUrlField('news_all_url'),
                    'limit' => max(1, min(6, (int) ($_POST['limit'] ?? 3))),
                    'docs_title' => TextProcessor::typographPlain(trim((string) ($_POST['docs_title'] ?? '')), $locale),
                    'docs_all_text' => trim((string) ($_POST['docs_all_text'] ?? '')),
                    'docs_all_url' => $this->safeUrlField('docs_all_url'),
                    'docs' => $docs,
                ];
            case 'cta_band':
                $iconSvg = trim((string) ($_POST['icon_svg'] ?? ''));
                if ($iconSvg !== '') {
                    $iconSvg = \App\Core\Uploader::sanitizeSvgString($iconSvg);
                }
                return [
                    'title' => TextProcessor::typographPlain(trim((string) ($_POST['title_field'] ?? '')), $locale),
                    'text' => TextProcessor::typographPlain(trim((string) ($_POST['text'] ?? '')), $locale),
                    'icon_svg' => $iconSvg,
                    'button_text' => trim((string) ($_POST['button_text'] ?? '')),
                    'button_url' => $this->safeUrlField('button_url'),
                ];
            case 'person_profile':
                return [
                    'photo' => trim((string) ($_POST['photo'] ?? '')),
                    'name' => TextProcessor::typographPlain(trim((string) ($_POST['name'] ?? '')), $locale),
                    'position' => TextProcessor::typographPlain(trim((string) ($_POST['position'] ?? '')), $locale),
                    'text' => TextProcessor::typographPlain(trim((string) ($_POST['text'] ?? '')), $locale),
                    'phone' => trim((string) ($_POST['phone'] ?? '')),
                    'phone_label' => trim((string) ($_POST['phone_label'] ?? 'Приёмная:')),
                    'email' => trim((string) ($_POST['email'] ?? '')),
                    'email_label' => trim((string) ($_POST['email_label'] ?? 'E-mail:')),
                    'button_text' => trim((string) ($_POST['button_text'] ?? '')),
                    'button_url' => $this->safeUrlField('button_url'),
                ];
            case 'feature_band':
                $items = [];
                foreach ((array) ($_POST['items'] ?? []) as $item) {
                    $itemTitle = trim((string) ($item['title'] ?? ''));
                    if ($itemTitle === '') {
                        continue;
                    }
                    $iconSvg = trim((string) ($item['icon_svg'] ?? ''));
                    if ($iconSvg !== '') {
                        $iconSvg = \App\Core\Uploader::sanitizeSvgString($iconSvg);
                    }
                    $items[] = [
                        'icon_svg' => $iconSvg,
                        'title' => TextProcessor::typographPlain($itemTitle, $locale),
                        'text' => TextProcessor::typographPlain(trim((string) ($item['text'] ?? '')), $locale),
                    ];
                }
                return [
                    'title' => TextProcessor::typographPlain(trim((string) ($_POST['title_field'] ?? '')), $locale),
                    'items' => $items,
                ];
            case 'bio_education':
                $collect = static function (string $key, array $fields) use ($locale): array {
                    $rows = [];
                    foreach ((array) ($_POST[$key] ?? []) as $row) {
                        $vals = [];
                        $empty = true;
                        foreach ($fields as $f) {
                            $v = trim((string) ($row[$f] ?? ''));
                            if ($v !== '') {
                                $empty = false;
                            }
                            $vals[$f] = $f === 'years' ? $v : TextProcessor::typographPlain($v, $locale);
                        }
                        if (!$empty) {
                            $rows[] = $vals;
                        }
                    }
                    return $rows;
                };
                return [
                    'bio_title' => TextProcessor::typographPlain(trim((string) ($_POST['bio_title'] ?? 'Биография')), $locale),
                    'bio_text' => TextProcessor::typographPlain(trim((string) ($_POST['bio_text'] ?? '')), $locale),
                    'career' => $collect('career', ['years', 'text']),
                    'edu_title' => TextProcessor::typographPlain(trim((string) ($_POST['edu_title'] ?? 'Образование')), $locale),
                    'edu_items' => $collect('edu_items', ['years', 'title', 'org']),
                    'extra_title' => TextProcessor::typographPlain(trim((string) ($_POST['extra_title'] ?? '')), $locale),
                    'extra_text' => TextProcessor::typographPlain(trim((string) ($_POST['extra_text'] ?? '')), $locale),
                    'quote_text' => TextProcessor::typographPlain(trim((string) ($_POST['quote_text'] ?? '')), $locale),
                    'quote_author' => TextProcessor::typographPlain(trim((string) ($_POST['quote_author'] ?? '')), $locale),
                ];
            case 'anchor_nav':
                $items = [];
                foreach ((array) ($_POST['items'] ?? []) as $item) {
                    $label = trim((string) ($item['label'] ?? ''));
                    $url = trim((string) ($item['url'] ?? ''));
                    if ($label === '') {
                        continue;
                    }
                    // Разрешаем якоря #... и обычные безопасные ссылки.
                    if ($url !== '' && $url[0] !== '#' && !\App\Core\UrlGuard::isSafeLink($url)) {
                        $url = '';
                    }
                    $items[] = ['label' => TextProcessor::typographPlain($label, $locale), 'url' => $url !== '' ? $url : '#'];
                }
                return ['items' => $items];
            case 'stages':
                $items = [];
                foreach ((array) ($_POST['items'] ?? []) as $item) {
                    $year = trim((string) ($item['year'] ?? ''));
                    $itemTitle = trim((string) ($item['title'] ?? ''));
                    if ($year === '' && $itemTitle === '') {
                        continue;
                    }
                    $items[] = [
                        'year' => $year,
                        'stage' => trim((string) ($item['stage'] ?? '')),
                        'title' => TextProcessor::typographPlain($itemTitle, $locale),
                        'text' => TextProcessor::typographPlain(trim((string) ($item['text'] ?? '')), $locale),
                        'status' => in_array($item['status'] ?? '', ['done', 'active', 'planned'], true) ? $item['status'] : 'planned',
                        'status_text' => trim((string) ($item['status_text'] ?? '')),
                    ];
                }
                return [
                    'title' => TextProcessor::typographPlain(trim((string) ($_POST['title_field'] ?? '')), $locale),
                    'all_text' => trim((string) ($_POST['all_text'] ?? '')),
                    'all_url' => $this->safeUrlField('all_url'),
                    'items' => $items,
                ];
            case 'text_image':
                $items = [];
                foreach ((array) ($_POST['items'] ?? []) as $item) {
                    $label = trim((string) ($item['label'] ?? ''));
                    if ($label === '') {
                        continue;
                    }
                    $iconSvg = trim((string) ($item['icon_svg'] ?? ''));
                    if ($iconSvg !== '') {
                        $iconSvg = \App\Core\Uploader::sanitizeSvgString($iconSvg);
                    }
                    $items[] = ['icon_svg' => $iconSvg, 'label' => TextProcessor::typographPlain($label, $locale)];
                }
                return [
                    'title' => TextProcessor::typographPlain(trim((string) ($_POST['title_field'] ?? '')), $locale),
                    'text' => TextProcessor::typographPlain(trim((string) ($_POST['text'] ?? '')), $locale),
                    'image' => trim((string) ($_POST['image'] ?? '')),
                    'items' => $items,
                ];
            case 'docs_list':
                $items = [];
                foreach ((array) ($_POST['items'] ?? []) as $item) {
                    $itemTitle = trim((string) ($item['title'] ?? ''));
                    if ($itemTitle === '') {
                        continue;
                    }
                    $url = trim((string) ($item['url'] ?? ''));
                    if ($url !== '' && !\App\Core\UrlGuard::isSafeLink($url)) {
                        $url = '';
                    }
                    $items[] = [
                        'title' => TextProcessor::typographPlain($itemTitle, $locale),
                        'meta' => trim((string) ($item['meta'] ?? '')),
                        'url' => $url,
                    ];
                }
                return [
                    'title' => TextProcessor::typographPlain(trim((string) ($_POST['title_field'] ?? '')), $locale),
                    'all_text' => trim((string) ($_POST['all_text'] ?? '')),
                    'all_url' => $this->safeUrlField('all_url'),
                    'columns' => max(1, min(4, (int) ($_POST['columns'] ?? 4))),
                    'items' => $items,
                ];
            case 'map_point':
                $embed = trim((string) ($_POST['embed_url'] ?? ''));
                // Только https-iframe (карты Google/Яндекс/OSM).
                if ($embed !== '' && !str_starts_with($embed, 'https://')) {
                    $embed = '';
                }
                return [
                    'title' => TextProcessor::typographPlain(trim((string) ($_POST['title_field'] ?? '')), $locale),
                    'image' => trim((string) ($_POST['image'] ?? '')),
                    'embed_url' => $embed,
                    'card_title' => TextProcessor::typographPlain(trim((string) ($_POST['card_title'] ?? '')), $locale),
                    'address' => trim((string) ($_POST['address'] ?? '')),
                    'button_text' => trim((string) ($_POST['button_text'] ?? '')),
                    'button_url' => $this->safeUrlField('button_url'),
                ];
            default:
                return [];
        }
    }

    /** Читает URL-поле из POST и отбрасывает небезопасные схемы (javascript: и т.п.). */
    private function safeUrlField(string $field): string
    {
        $url = trim((string) ($_POST[$field] ?? ''));

        return ($url !== '' && \App\Core\UrlGuard::isSafeLink($url)) ? $url : '';
    }
}
