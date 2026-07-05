<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Flash;
use App\Core\ImageField;
use App\Core\View;
use App\Models\TeamMember;

final class TeamController
{
    public function index(): void
    {
        Auth::requireLogin();
        View::render('admin/team/index', ['items' => TeamMember::all()]);
    }

    public function create(): void
    {
        Auth::requireLogin();
        View::render('admin/team/form', ['member' => null, 'error' => null]);
    }

    public function store(): void
    {
        Auth::requireLogin();
        Csrf::verifyRequest();

        [$data, $error] = $this->collectInput(null);

        if ($error !== null) {
            View::render('admin/team/form', ['member' => $data, 'error' => $error]);
            return;
        }

        TeamMember::create($data);
        Flash::success('Сотрудник добавлен.');
        header('Location: /admin/team');
        exit;
    }

    public function edit(array $params): void
    {
        Auth::requireLogin();

        $member = TeamMember::findById((int) $params['id']);
        if (!$member) {
            http_response_code(404);
            View::render('errors/404');
            return;
        }
        $member['socials'] = json_decode((string) $member['socials_json'], true) ?: [];

        View::render('admin/team/form', ['member' => $member, 'error' => null]);
    }

    public function update(array $params): void
    {
        Auth::requireLogin();
        Csrf::verifyRequest();

        $id = (int) $params['id'];
        $member = TeamMember::findById($id);
        if (!$member) {
            http_response_code(404);
            View::render('errors/404');
            return;
        }

        [$data, $error] = $this->collectInput($id, $member);

        if ($error !== null) {
            View::render('admin/team/form', ['member' => array_merge($member, $data), 'error' => $error]);
            return;
        }

        TeamMember::update($id, $data);
        Flash::success('Данные сотрудника обновлены.');
        header('Location: /admin/team');
        exit;
    }

    public function destroy(array $params): void
    {
        Auth::requireLogin();
        Csrf::verifyRequest();

        TeamMember::delete((int) $params['id']);
        Flash::success('Сотрудник удалён.');
        header('Location: /admin/team');
        exit;
    }

    /**
     * @return array{0: array, 1: string|null}
     */
    private function collectInput(?int $id, ?array $existing = null): array
    {
        $name = trim((string) ($_POST['name'] ?? ''));
        $position = trim((string) ($_POST['position'] ?? ''));
        $email = trim((string) ($_POST['email'] ?? ''));
        $phone = trim((string) ($_POST['phone'] ?? ''));
        $status = ($_POST['status'] ?? 'published') === 'draft' ? 'draft' : 'published';
        $sortOrder = (int) ($_POST['sort_order'] ?? 0);

        if ($name === '') {
            return [['name' => $name, 'position' => $position], 'Укажите имя сотрудника.'];
        }

        $photo = ImageField::resolve('photo_file', 'photo_url', $existing['photo'] ?? null, Auth::id());

        $socials = [];
        foreach (['facebook', 'instagram', 'telegram', 'linkedin', 'whatsapp'] as $network) {
            $value = trim((string) ($_POST['social_' . $network] ?? ''));
            if ($value !== '') {
                $socials[$network] = $value;
            }
        }

        $data = [
            'name' => $name,
            'position' => $position !== '' ? $position : null,
            'photo' => $photo,
            'email' => $email !== '' ? $email : null,
            'phone' => $phone !== '' ? $phone : null,
            'socials' => $socials,
            'status' => $status,
            'sort_order' => $sortOrder,
        ];

        return [$data, null];
    }
}
