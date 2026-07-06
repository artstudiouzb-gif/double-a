<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Auth;
use App\Core\Cache;
use App\Core\Csrf;
use App\Core\Flash;
use App\Core\View;
use App\Models\Block;
use App\Models\BlockSnippet;
use App\Models\Language;
use App\Models\Page;

/**
 * Сохранение набора блоков страницы как шаблона и вставка шаблона в страницу
 * (задача 133). При вставке блоки получают новые id (custom_css скоупится по
 * #block-{id} на рендере — конфликтов не возникает).
 */
final class SnippetController
{
    private function resolveLang(): string
    {
        $lang = (string) ($_POST['block_lang'] ?? Language::defaultCode());
        return Language::isActive($lang) ? $lang : Language::defaultCode();
    }

    public function save(array $params): void
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

        $lang = $this->resolveLang();
        $name = trim((string) ($_POST['snippet_name'] ?? ''));
        if ($name === '') {
            Flash::error('Укажите название шаблона.');
            $this->back($pageId, $lang);
        }

        $blocks = [];
        foreach (Block::forPage($pageId, $lang) as $block) {
            $blocks[] = [
                'type' => (string) $block['type'],
                'title' => $block['title'] !== null ? (string) $block['title'] : null,
                'data' => json_decode((string) $block['data'], true) ?: [],
                'custom_css' => (string) ($block['custom_css'] ?? ''),
            ];
        }

        if ($blocks === []) {
            Flash::error('На этом языке нет блоков для сохранения.');
            $this->back($pageId, $lang);
        }

        BlockSnippet::create($name, $blocks);
        Flash::success('Шаблон «' . $name . '» сохранён (' . count($blocks) . ' блоков).');
        $this->back($pageId, $lang);
    }

    public function insert(array $params): void
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

        $lang = $this->resolveLang();
        $snippet = BlockSnippet::findById((int) ($_POST['snippet_id'] ?? 0));
        if ($snippet === null) {
            Flash::error('Шаблон не найден.');
            $this->back($pageId, $lang);
        }

        $blocks = json_decode((string) $snippet['blocks_json'], true);
        if (!is_array($blocks)) {
            Flash::error('Шаблон повреждён.');
            $this->back($pageId, $lang);
        }

        $count = 0;
        foreach ($blocks as $b) {
            $type = (string) ($b['type'] ?? '');
            if ($type === '') {
                continue;
            }
            // Новые id присваиваются автоматически (Block::create) — важно для
            // изоляции CSS по #block-{id}.
            Block::create(
                $pageId,
                $lang,
                $type,
                isset($b['title']) && $b['title'] !== '' ? (string) $b['title'] : null,
                is_array($b['data'] ?? null) ? $b['data'] : [],
                (string) ($b['custom_css'] ?? '')
            );
            $count++;
        }

        Cache::forgetPrefix('page:' . $pageId);
        Flash::success('Вставлено блоков: ' . $count . '.');
        $this->back($pageId, $lang);
    }

    public function destroy(array $params): void
    {
        Auth::requireLogin();
        Csrf::verifyRequest();

        BlockSnippet::delete((int) $params['id']);
        Flash::success('Шаблон удалён.');
        $referer = $_SERVER['HTTP_REFERER'] ?? '/admin/pages';
        header('Location: ' . (str_starts_with($referer, '/') ? $referer : '/admin/pages'));
        exit;
    }

    private function back(int $pageId, string $lang): never
    {
        header('Location: /admin/pages/' . $pageId . '/edit?block_lang=' . urlencode($lang));
        exit;
    }
}
