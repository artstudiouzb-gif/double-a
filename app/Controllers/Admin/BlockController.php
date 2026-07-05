<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Flash;
use App\Core\View;
use App\Models\Block;
use App\Models\FormDef;
use App\Models\Page;

final class BlockController
{
    private const TYPES = ['text', 'html', 'cta', 'advantages', 'slider', 'gallery', 'form'];

    private const DEFAULTS = [
        'text' => ['title' => '', 'content' => ''],
        'html' => ['html' => ''],
        'cta' => ['title' => '', 'text' => '', 'button_text' => '', 'button_url' => ''],
        'advantages' => ['title' => '', 'items' => []],
        'slider' => ['slides' => []],
        'gallery' => ['title' => '', 'images' => []],
        'form' => ['form_id' => null],
    ];

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
        if (!in_array($type, self::TYPES, true)) {
            Flash::error('Неизвестный тип блока.');
            header('Location: /admin/pages/' . $pageId . '/edit');
            exit;
        }

        $title = trim((string) ($_POST['title'] ?? ''));
        $blockId = Block::create($pageId, $type, $title !== '' ? $title : null, self::DEFAULTS[$type], '');

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
        $customCss = (string) ($_POST['custom_css'] ?? '');
        $data = $this->collectData($block['type']);

        Block::update((int) $block['id'], $title !== '' ? $title : null, $data, $customCss);

        Flash::success('Блок сохранён.');
        header('Location: /admin/pages/' . (int) $block['page_id'] . '/edit');
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
        Flash::success('Блок удалён.');
        header('Location: /admin/pages/' . (int) $block['page_id'] . '/edit');
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

        $direction = $_POST['direction'] ?? '';
        if ($direction === 'up') {
            Block::moveUp((int) $block['id'], (int) $block['page_id']);
        } elseif ($direction === 'down') {
            Block::moveDown((int) $block['id'], (int) $block['page_id']);
        }

        header('Location: /admin/pages/' . (int) $block['page_id'] . '/edit');
        exit;
    }

    private function collectData(string $type): array
    {
        switch ($type) {
            case 'text':
                return [
                    'title' => trim((string) ($_POST['title_field'] ?? '')),
                    'content' => (string) ($_POST['content'] ?? ''),
                ];
            case 'html':
                return ['html' => (string) ($_POST['html'] ?? '')];
            case 'cta':
                return [
                    'title' => trim((string) ($_POST['title_field'] ?? '')),
                    'text' => trim((string) ($_POST['text'] ?? '')),
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
                        'title' => $itemTitle,
                        'text' => $itemText,
                    ];
                }
                return [
                    'title' => trim((string) ($_POST['title_field'] ?? '')),
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
