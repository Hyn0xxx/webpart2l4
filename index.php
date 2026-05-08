<?php
/**
 * Реализация Lab 4: Валидация на бэкенде с использованием Cookies
 */
header('Content-Type: text/html; charset=UTF-8');

// Параметры подключения
$db_user = 'u82464';
$db_pass = '8104996';
$db_name = 'u82464';
$db_host = 'localhost';

try {
    $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8", $db_user, $db_pass, [
        PDO::ATTR_PERSISTENT => true,
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
} catch(PDOException $e) {
    die('Ошибка подключения к базе данных: ' . $e->getMessage());
}

// Список языков (нужен и для валидации, и для формы)
$languagesList = $pdo->query("SELECT id, name FROM programming_languages ORDER BY name")->fetchAll();
$allowedLanguageIds = array_column($languagesList, 'id');

// --- ОБРАБОТКА POST ---
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $errors = false;

    // 1. ФИО
    if (empty($_POST['full_name']) || !preg_match('/^[a-zA-Zа-яА-ЯёЁ\s\-]+$/u', $_POST['full_name'])) {
        setcookie('full_name_error', 'ФИО обязательно и может содержать только буквы, пробелы и дефисы.', time() + 24 * 3600);
        $errors = true;
    }
    setcookie('full_name_value', $_POST['full_name'], time() + 30 * 24 * 3600);

    // 2. Телефон
    if (empty($_POST['phone']) || !preg_match('/^(\+7|8)?[\s\-]?\(?[0-9]{3}\)?[\s\-]?[0-9]{3}[\s\-]?[0-9]{2}[\s\-]?[0-9]{2}$/', $_POST['phone'])) {
        setcookie('phone_error', 'Введите корректный номер. Допустимы цифры, скобки, пробел и дефис.', time() + 24 * 3600);
        $errors = true;
    }
    setcookie('phone_value', $_POST['phone'], time() + 30 * 24 * 3600);

    // 3. Email
    if (empty($_POST['email']) || !filter_var($_POST['email'], FILTER_VALIDATE_EMAIL)) {
        setcookie('email_error', 'Введите валидный e-mail (например, user@example.com).', time() + 24 * 3600);
        $errors = true;
    }
    setcookie('email_value', $_POST['email'], time() + 30 * 24 * 3600);

    // 4. Дата рождения
    if (empty($_POST['birth_date'])) {
        setcookie('birth_date_error', 'Выберите дату рождения.', time() + 24 * 3600);
        $errors = true;
    }
    setcookie('birth_date_value', $_POST['birth_date'], time() + 30 * 24 * 3600);

    // 5. Пол
    if (empty($_POST['gender']) || !in_array($_POST['gender'], ['male', 'female', 'other'])) {
        setcookie('gender_error', 'Выберите пол из предложенных вариантов.', time() + 24 * 3600);
        $errors = true;
    }
    setcookie('gender_value', $_POST['gender'], time() + 30 * 24 * 3600);

    // 6. Языки
    $selectedLangs = $_POST['languages'] ?? [];
    foreach ($selectedLangs as $id) {
        if (!in_array($id, $allowedLanguageIds)) {
            setcookie('languages_error', 'Выбран недопустимый язык.', time() + 24 * 3600);
            $errors = true;
        }
    }
    if (empty($selectedLangs)) {
        setcookie('languages_error', 'Выберите хотя бы один язык.', time() + 24 * 3600);
        $errors = true;
    }
    setcookie('languages_value', serialize($selectedLangs), time() + 30 * 24 * 3600);

    // 7. Биография
    setcookie('bio_value', $_POST['bio'], time() + 30 * 24 * 3600);

    // 8. Контракт
    if (!isset($_POST['contract'])) {
        setcookie('contract_error', 'Необходимо принять условия контракта.', time() + 24 * 3600);
        $errors = true;
    }
    setcookie('contract_value', isset($_POST['contract']) ? '1' : '', time() + 30 * 24 * 3600);

    if ($errors) {
        header('Location: index.php');
        exit();
    }

    // --- СОХРАНЕНИЕ В БД ---
    try {
        $pdo->beginTransaction();
        $stmt = $pdo->prepare("INSERT INTO applications (full_name, phone, email, birth_date, gender, bio, contract_accepted) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$_POST['full_name'], $_POST['phone'], $_POST['email'], $_POST['birth_date'], $_POST['gender'], $_POST['bio'], 1]);
        
        $appId = $pdo->lastInsertId();
        $stmtLang = $pdo->prepare("INSERT INTO application_languages (application_id, language_id) VALUES (?, ?)");
        foreach ($selectedLangs as $langId) {
            $stmtLang->execute([$appId, $langId]);
        }
        $pdo->commit();
        setcookie('save_success', '1', time() + 24 * 3600);
    } catch(PDOException $e) {
        $pdo->rollBack();
        setcookie('db_error', 'Ошибка базы данных: ' . $e->getMessage(), time() + 24 * 3600);
    }

    header('Location: index.php');
    exit();
}

// --- ПОДГОТОВКА ДАННЫХ ДЛЯ ОТОБРАЖЕНИЯ (GET) ---
$messages = [];
$errors = [];

// Проверка на успех
if (!empty($_COOKIE['save_success'])) {
    setcookie('save_success', '', 100);
    $messages[] = '✅ Данные успешно сохранены!';
}

// Сбор ошибок из Cookies
$fields = ['full_name', 'phone', 'email', 'birth_date', 'gender', 'languages', 'contract', 'db_error'];
foreach ($fields as $f) {
    if (!empty($_COOKIE[$f . '_error'])) {
        $errors[$f] = $_COOKIE[$f . '_error'];
        setcookie($f . '_error', '', 100); // Удаляем после прочтения
    }
}

// Сбор значений из Cookies
$values = [];
foreach (['full_name', 'phone', 'email', 'birth_date', 'gender', 'bio', 'contract', 'languages'] as $f) {
    $values[$f] = $_COOKIE[$f . '_value'] ?? '';
}
// Десериализация языков
$values['languages'] = !empty($values['languages']) ? unserialize($values['languages']) : [];

?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Анкета (Lab 4)</title>
    <style>
        /* ... Ваши стили из задания ... */
        .form-error { border: 2px solid #e74c3c !important; background-color: #fff6f6 !important; }
        .error-message { color: #e74c3c; font-size: 0.85em; display: block; margin-top: 5px; }
        .success-banner { background: #d4edda; color: #155724; padding: 15px; border-radius: 10px; margin-bottom: 20px; }
        /* Тот же CSS, что был в index.php выше */
    </style>
</head>
<body>
    <div class="container">
        <div class="header"><h1>📝 Анкета разработчика</h1></div>
        
        <div class="form-content">
            <?php 
            foreach($messages as $m) echo "<div class='success-banner'>$m</div>";
            if (!empty($errors['db_error'])) echo "<div class='error-summary'>{$errors['db_error']}</div>";
            ?>

            <form action="" method="POST">
                <div class="form-group">
                    <label>ФИО *</label>
                    <input type="text" name="full_name" value="<?= htmlspecialchars($values['full_name']) ?>" class="<?= isset($errors['full_name']) ? 'form-error' : '' ?>">
                    <?php if(isset($errors['full_name'])) echo "<span class='error-message'>{$errors['full_name']}</span>"; ?>
                </div>

                <div class="form-group">
                    <label>Телефон *</label>
                    <input type="tel" name="phone" value="<?= htmlspecialchars($values['phone']) ?>" class="<?= isset($errors['phone']) ? 'form-error' : '' ?>">
                    <?php if(isset($errors['phone'])) echo "<span class='error-message'>{$errors['phone']}</span>"; ?>
                </div>

                <div class="form-group">
                    <label>E-mail *</label>
                    <input type="email" name="email" value="<?= htmlspecialchars($values['email']) ?>" class="<?= isset($errors['email']) ? 'form-error' : '' ?>">
                    <?php if(isset($errors['email'])) echo "<span class='error-message'>{$errors['email']}</span>"; ?>
                </div>

                <div class="form-group">
                    <label>Дата рождения *</label>
                    <input type="date" name="birth_date" value="<?= htmlspecialchars($values['birth_date']) ?>" class="<?= isset($errors['birth_date']) ? 'form-error' : '' ?>">
                    <?php if(isset($errors['birth_date'])) echo "<span class='error-message'>{$errors['birth_date']}</span>"; ?>
                </div>

                <div class="form-group <?= isset($errors['gender']) ? 'form-error' : '' ?>">
                    <label>Пол *</label>
                    <input type="radio" name="gender" value="male" <?= ($values['gender'] == 'male') ? 'checked' : '' ?>> Мужской
                    <input type="radio" name="gender" value="female" <?= ($values['gender'] == 'female') ? 'checked' : '' ?>> Женский
                    <?php if(isset($errors['gender'])) echo "<span class='error-message'>{$errors['gender']}</span>"; ?>
                </div>

                <div class="form-group">
                    <label>Любимые языки *</label>
                    <select name="languages[]" multiple class="<?= isset($errors['languages']) ? 'form-error' : '' ?>">
                        <?php foreach ($languagesList as $lang): ?>
                            <option value="<?= $lang['id'] ?>" <?= in_array($lang['id'], $values['languages']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($lang['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?php if(isset($errors['languages'])) echo "<span class='error-message'>{$errors['languages']}</span>"; ?>
                </div>

                <div class="form-group">
                    <label>Биография</label>
                    <textarea name="bio"><?= htmlspecialchars($values['bio']) ?></textarea>
                </div>

                <div class="form-group">
                    <input type="checkbox" name="contract" value="1" <?= !empty($values['contract']) ? 'checked' : '' ?>> Согласен с условиями *
                    <?php if(isset($errors['contract'])) echo "<span class='error-message'>{$errors['contract']}</span>"; ?>
                </div>

                <button type="submit" class="btn-submit">Отправить</button>
            </form>
        </div>
    </div>
</body>
</html>