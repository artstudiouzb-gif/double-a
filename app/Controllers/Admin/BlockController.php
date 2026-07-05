<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Flash;
use App\Core\View;
use App\Core\TextProcessor;
use App\Models\Block;
use App\Models\FormDef;
use App\Models\Language;
use App\Models\Page;

final class BlockController
{
    private const TYPES = ['text', 'html', 'cta', 'advantages', 'slider', 'gallery', 'form'];

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

        $title = trim((string) ($_POST['title'] ?? ''));
        $blockId = Block::create($pageId, $lang, $type, $title !== '' ? $title : null, \App\Core\BlockRenderer::defaultsFor($type), '');
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

        Block::update((int) $block['id'], $title !== '' ? $title : null, $data, $customCss);
        \App\Core\Cache::forgetPrefix('page:' . (int) $block['page_id']);

        Flash::success('Блок сохранён.');
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
                // Произвольный HTML/код не типографим.
                return ['html' => (string) ($_POST['html'] ?? '')];
            case 'cta':
                return [
                    'title' => TextProcessor::typographPlain(trim((string) ($_POST['title_field'] ?? '')), $locale),
                    'text' => TextProcessor::typographPlain(trim((string) ($_POST['text'] ?? '')), $locale),
                    'button_text' => trim((string) ($_POST['button_text'] ?? '')),
                    'button_url' => trim((string) ($_POST['button_url'] ?? '')),
                ];
            case 'advantages':
                $items = [];
                foreach ((array) ($_POST['items'] ?? []) as $item) {
                    $itemTitle = trim((string) ($item['title'] ?? ''));
                    $itemText = trim((string) ($item['text'] ?? ''));
                    if ($itemTitle === '' && $itemText === '') {
                        continue;
                    }
                    $items[] = [
                        'icon' => trim((string) ($item['icon'] ?? '')),
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
            default:
                return [];
        }
    }
}
