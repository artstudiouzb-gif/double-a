<?php

declare(strict_types=1);

use App\Core\Database;
use App\Models\RepoFile;
use App\Models\RepoUser;

// Все проверки этого файла требуют тестовую БД (см. TEST_DB_* в run.php,
// ensure_test_db() определена в 08_bulk_duplicate_search_test.php).

test('RepoUser: создание, поиск, уникальность, активность и 2FA', function () {
    ensure_test_db();
    $pdo = Database::pdo();
    $pdo->exec('DELETE FROM repo_users');

    $id = RepoUser::create('portal_user', 'Иван Иванов', 'portal@example.com', 'Str0ng-Pass-99');
    assert_true($id > 0, 'вернулся id');

    $found = RepoUser::findByUsername('portal_user');
    assert_true($found !== null, 'пользователь найден');
    assert_same('Иван Иванов', $found['full_name']);
    assert_true(password_verify('Str0ng-Pass-99', $found['password_hash']), 'пароль хэширован');
    assert_same(1, (int) $found['is_active']);
    assert_same(0, (int) $found['totp_enabled']);

    assert_true(RepoUser::usernameExists('portal_user'), 'usernameExists');
    assert_true(RepoUser::emailExists('portal@example.com'), 'emailExists');
    assert_false(RepoUser::usernameExists('nope'), 'нет такого логина');

    RepoUser::setActive($id, false);
    assert_same(0, (int) RepoUser::findById($id)['is_active']);
    RepoUser::setActive($id, true);
    assert_same(1, (int) RepoUser::findById($id)['is_active']);

    RepoUser::enableTotp($id, 'JBSWY3DPEHPK3PXP');
    $u = RepoUser::findById($id);
    assert_same(1, (int) $u['totp_enabled']);
    assert_same('JBSWY3DPEHPK3PXP', $u['totp_secret']);
    RepoUser::disableTotp($id);
    $u = RepoUser::findById($id);
    assert_same(0, (int) $u['totp_enabled']);
    assert_true($u['totp_secret'] === null, 'секрет сброшен');

    RepoUser::updatePassword($id, 'New-Pass-2026!');
    assert_true(password_verify('New-Pass-2026!', RepoUser::findById($id)['password_hash']), 'пароль обновлён');

    RepoUser::delete($id);
    assert_true(RepoUser::findById($id) === null, 'пользователь удалён');
});

test('RepoFile: поиск по запросу, фильтр по категории и список категорий', function () {
    ensure_test_db();
    $pdo = Database::pdo();
    $pdo->exec('DELETE FROM repo_files');

    $insert = $pdo->prepare(
        'INSERT INTO repo_files (title, description, category, stored_name, original_name, mime_type, size, created_at)
         VALUES (:t, :d, :c, :s, :o, :m, :sz, NOW())'
    );
    $insert->execute([':t' => 'Приказ №1', ':d' => 'О назначении', ':c' => 'Приказы', ':s' => 'a.pdf', ':o' => 'prikaz1.pdf', ':m' => 'application/pdf', ':sz' => 1024]);
    $insert->execute([':t' => 'Отчёт за год', ':d' => 'Финансовый', ':c' => 'Отчёты', ':s' => 'b.pdf', ':o' => 'report.pdf', ':m' => 'application/pdf', ':sz' => 2048]);
    $insert->execute([':t' => 'Инструкция', ':d' => null, ':c' => '', ':s' => 'c.docx', ':o' => 'guide.docx', ':m' => 'application/octet-stream', ':sz' => 512]);

    assert_same(3, count(RepoFile::all()));

    $byQuery = RepoFile::all('Приказ');
    assert_same(1, count($byQuery));
    assert_same('Приказ №1', $byQuery[0]['title']);

    // Поиск также по имени файла и категории.
    assert_same(1, count(RepoFile::all('report.pdf')));
    assert_same(1, count(RepoFile::all('Отчёты')));

    $byCat = RepoFile::all('', 'Приказы');
    assert_same(1, count($byCat));

    $cats = RepoFile::categories();
    assert_true(in_array('Приказы', $cats, true), 'категория Приказы есть');
    assert_true(in_array('Отчёты', $cats, true), 'категория Отчёты есть');
    assert_false(in_array('', $cats, true), 'пустая категория исключена');

    $pdo->exec('DELETE FROM repo_files');
});

test('RepoFile::basePath указывает в подкаталог protected_uploads/repo', function () {
    $base = RepoFile::basePath();
    assert_contains('protected_uploads/repo', str_replace('\\', '/', $base));
});
