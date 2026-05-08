<?php
// Отправляем браузеру правильную кодировку
header('Content-Type: text/html; charset=UTF-8');

// Параметры подключения к БД - замените на свои!
$db_user = 'u82464';      // Ваш логин
$db_pass = '8104996';     // Ваш пароль
$db_name = 'u82464';      // Имя БД - ВАШ ЛОГИН!
$db_host = 'localhost';

try {
    // Подключение к БД
    $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8", $db_user, $db_pass, [
        PDO::ATTR_PERSISTENT => true,
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
    
    // Создание таблиц, если их нет
    createTables($pdo);
    
} catch(PDOException $e) {
    die('Ошибка подключения к базе данных: ' . $e->getMessage());
}

// Функция создания таблиц в 3-й нормальной форме
function createTables($pdo) {
    // Таблица заявок
    $sql_applications = "
        CREATE TABLE IF NOT EXISTS applications (
            id INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
            full_name VARCHAR(150) NOT NULL,
            phone VARCHAR(20) NOT NULL,
            email VARCHAR(100) NOT NULL,
            birth_date DATE NOT NULL,
            gender ENUM('male', 'female', 'other') NOT NULL,
            bio TEXT,
            contract_accepted TINYINT(1) NOT NULL DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ";
    $pdo->exec($sql_applications);
    
    // Таблица языков программирования
    $sql_languages = "
        CREATE TABLE IF NOT EXISTS programming_languages (
            id INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
            name VARCHAR(50) NOT NULL UNIQUE,
            PRIMARY KEY (id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ";
    $pdo->exec($sql_languages);
    
    // Заполнение таблицы языков начальными данными
    $languages = ['Pascal', 'C', 'C++', 'JavaScript', 'PHP', 'Python', 'Java', 'Haskell', 'Clojure', 'Prolog', 'Scala', 'Go'];
    $stmt = $pdo->prepare("INSERT IGNORE INTO programming_languages (name) VALUES (?)");
    foreach ($languages as $lang) {
        $stmt->execute([$lang]);
    }
    
    // Таблица связи заявка-язык (один ко многим)
    $sql_app_languages = "
        CREATE TABLE IF NOT EXISTS application_languages (
            id INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
            application_id INT(10) UNSIGNED NOT NULL,
            language_id INT(10) UNSIGNED NOT NULL,
            PRIMARY KEY (id),
            FOREIGN KEY (application_id) REFERENCES applications(id) ON DELETE CASCADE,
            FOREIGN KEY (language_id) REFERENCES programming_languages(id) ON DELETE CASCADE,
            UNIQUE KEY unique_app_lang (application_id, language_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ";
    $pdo->exec($sql_app_languages);
}

// Получение списка языков для формы
$languagesList = $pdo->query("SELECT id, name FROM programming_languages ORDER BY name")->fetchAll();

// Функция для сохранения ошибок в Cookies
function saveErrorsToCookie($errors) {
    setcookie('form_errors', json_encode($errors), 0, '/');
}

// Функция для получения ошибок из Cookies
function getErrorsFromCookie() {
    if (isset($_COOKIE['form_errors'])) {
        $errors = json_decode($_COOKIE['form_errors'], true);
        setcookie('form_errors', '', time() - 3600, '/'); // Удаляем после использования
        return $errors;
    }
    return [];
}

// Функция для сохранения данных формы в Cookies (на год)
function saveFormDataToCookie($formData) {
    $expire = time() + 365 * 24 * 3600; // На год
    setcookie('saved_form_data', json_encode($formData), $expire, '/');
}

// Функция для получения сохраненных данных из Cookies
function getSavedFormDataFromCookie() {
    if (isset($_COOKIE['saved_form_data'])) {
        return json_decode($_COOKIE['saved_form_data'], true);
    }
    return [];
}

// Обработка POST-запроса
$errors = [];
$success = false;
$formData = [];

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Получаем данные из формы
    $formData = [
        'full_name' => trim($_POST['full_name'] ?? ''),
        'phone' => trim($_POST['phone'] ?? ''),
        'email' => trim($_POST['email'] ?? ''),
        'birth_date' => $_POST['birth_date'] ?? '',
        'gender' => $_POST['gender'] ?? '',
        'languages' => $_POST['languages'] ?? [],
        'bio' => trim($_POST['bio'] ?? ''),
        'contract' => isset($_POST['contract']) ? 1 : 0
    ];
    
    // Валидация полей с подробными сообщениями о допустимых символах
    
    // 1. ФИО: только буквы, пробелы, дефисы, не длиннее 150 символов
    if (empty($formData['full_name'])) {
        $errors['full_name'] = 'Поле "ФИО" обязательно для заполнения.';
    } elseif (strlen($formData['full_name']) > 150) {
        $errors['full_name'] = 'ФИО не должно превышать 150 символов.';
    } elseif (!preg_match('/^[a-zA-Zа-яА-ЯёЁ\s\-]+$/u', $formData['full_name'])) {
        $errors['full_name'] = 'ФИО может содержать только буквы (русские или латинские), пробелы и дефисы. Недопустимы цифры и специальные символы.';
    }
    
    // 2. Телефон: проверка формата (российские номера)
    if (empty($formData['phone'])) {
        $errors['phone'] = 'Поле "Телефон" обязательно для заполнения.';
    } elseif (!preg_match('/^(\+7|8)?[\s\-]?\(?[0-9]{3}\)?[\s\-]?[0-9]{3}[\s\-]?[0-9]{2}[\s\-]?[0-9]{2}$/', $formData['phone'])) {
        $errors['phone'] = 'Введите корректный номер телефона. Допустимые символы: цифры, +, -, пробелы, скобки. Пример: +7(123)456-78-90 или 8-123-456-78-90.';
    }
    
    // 3. Email: валидация email
    if (empty($formData['email'])) {
        $errors['email'] = 'Поле "E-mail" обязательно для заполнения.';
    } elseif (!filter_var($formData['email'], FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Введите корректный E-mail адрес. Допустимые символы: буквы, цифры, точки, дефисы, знак @. Пример: username@domain.com';
    } elseif (!preg_match('/^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/', $formData['email'])) {
        $errors['email'] = 'E-mail может содержать только латинские буквы, цифры, точки, дефисы и знак @.';
    }
    
    // 4. Дата рождения: не может быть в будущем и не слишком старая
    if (empty($formData['birth_date'])) {
        $errors['birth_date'] = 'Поле "Дата рождения" обязательно для заполнения.';
    } else {
        $birthDate = DateTime::createFromFormat('Y-m-d', $formData['birth_date']);
        $today = new DateTime();
        $minDate = (new DateTime())->modify('-120 years');
        
        if (!$birthDate || $birthDate > $today) {
            $errors['birth_date'] = 'Дата рождения не может быть в будущем. Формат: ГГГГ-ММ-ДД.';
        } elseif ($birthDate < $minDate) {
            $errors['birth_date'] = 'Укажите реальную дату рождения (не старше 120 лет).';
        }
    }
    
    // 5. Пол: проверка допустимых значений
    $allowedGenders = ['male', 'female', 'other'];
    if (empty($formData['gender'])) {
        $errors['gender'] = 'Выберите пол.';
    } elseif (!in_array($formData['gender'], $allowedGenders)) {
        $errors['gender'] = 'Недопустимое значение поля "Пол".';
    }
    
    // 6. Любимый язык программирования: один или более из списка
    $allowedLanguageIds = array_column($languagesList, 'id');
    if (empty($formData['languages'])) {
        $errors['languages'] = 'Выберите хотя бы один язык программирования.';
    } else {
        foreach ($formData['languages'] as $langId) {
            if (!in_array($langId, $allowedLanguageIds)) {
                $errors['languages'] = 'Выбран недопустимый язык программирования.';
                break;
            }
        }
    }
    
    // 7. Биография: не длиннее 5000 символов
    if (strlen($formData['bio']) > 5000) {
        $errors['bio'] = 'Биография не должна превышать 5000 символов.';
    }
    
    // 8. Чекбокс с контрактом
    if (!$formData['contract']) {
        $errors['contract'] = 'Вы должны ознакомиться с контрактом и принять его условия.';
    }
    
    // Если есть ошибки - сохраняем в Cookies и перенаправляем GET-запросом
    if (!empty($errors)) {
        saveErrorsToCookie($errors);
        // Сохраняем введенные данные во временные Cookies для восстановления формы
        setcookie('temp_form_data', json_encode($formData), 0, '/');
        header('Location: ' . strtok($_SERVER["REQUEST_URI"], '?'));
        exit;
    }
    
    // Если нет ошибок - сохраняем в БД
    if (empty($errors)) {
        try {
            // Начинаем транзакцию
            $pdo->beginTransaction();
            
            // Подготовленный запрос для вставки в таблицу applications
            $stmt = $pdo->prepare("
                INSERT INTO applications (full_name, phone, email, birth_date, gender, bio, contract_accepted)
                VALUES (:full_name, :phone, :email, :birth_date, :gender, :bio, :contract_accepted)
            ");
            
            $stmt->execute([
                ':full_name' => $formData['full_name'],
                ':phone' => $formData['phone'],
                ':email' => $formData['email'],
                ':birth_date' => $formData['birth_date'],
                ':gender' => $formData['gender'],
                ':bio' => $formData['bio'],
                ':contract_accepted' => $formData['contract']
            ]);
            
            // Получаем ID последней вставленной записи
            $applicationId = $pdo->lastInsertId();
            
            // Вставка выбранных языков в таблицу связи
            $stmtLang = $pdo->prepare("
                INSERT INTO application_languages (application_id, language_id)
                VALUES (:application_id, :language_id)
            ");
            
            foreach ($formData['languages'] as $langId) {
                $stmtLang->execute([
                    ':application_id' => $applicationId,
                    ':language_id' => $langId
                ]);
            }
            
            // Подтверждаем транзакцию
            $pdo->commit();
            $success = true;
            
            // Сохраняем ВСЕ данные формы в Cookies на год для автозаполнения при следующих визитах
            saveFormDataToCookie($formData);
            
            // Очищаем временные данные
            $formData = [];
            setcookie('temp_form_data', '', time() - 3600, '/');
            
            // Перенаправляем GET-запросом, чтобы избежать повторной отправки формы
            header('Location: ' . strtok($_SERVER["REQUEST_URI"], '?') . '?success=1');
            exit;
            
        } catch(PDOException $e) {
            $pdo->rollBack();
            $errors['database'] = 'Ошибка при сохранении данных: ' . $e->getMessage();
            saveErrorsToCookie($errors);
            setcookie('temp_form_data', json_encode($formData), 0, '/');
            header('Location: ' . strtok($_SERVER["REQUEST_URI"], '?'));
            exit;
        }
    }
}

// Получаем данные для отображения формы
// Приоритет: 1. Временные данные из POST (через Cookies), 2. Сохраненные данные из Cookies, 3. Пустые значения

// Проверяем параметр success в URL
if (isset($_GET['success']) && $_GET['success'] == 1) {
    $success = true;
}

// Получаем ошибки из Cookies (если есть)
$errors = getErrorsFromCookie();

// Получаем временные данные формы (из ошибочного POST-запроса)
$tempFormData = [];
if (isset($_COOKIE['temp_form_data'])) {
    $tempFormData = json_decode($_COOKIE['temp_form_data'], true);
    setcookie('temp_form_data', '', time() - 3600, '/'); // Удаляем после использования
}

// Получаем сохраненные данные (успешные отправки)
$savedFormData = getSavedFormDataFromCookie();

// Формируем данные для отображения: сначала временные, потом сохраненные
// ИСПРАВЛЕНО: убрано условие !$success, чтобы данные показывались даже после успешной отправки
if (!empty($tempFormData)) {
    $displayFormData = $tempFormData;
} elseif (!empty($savedFormData) && $_SERVER['REQUEST_METHOD'] != 'POST') {
    $displayFormData = $savedFormData;
} else {
    $displayFormData = [];
}

// Функция для отображения полей с сохранёнными значениями
function getValue($fieldName, $formData, $default = '') {
    if (isset($formData[$fieldName])) {
        return htmlspecialchars($formData[$fieldName]);
    }
    return $default;
}

// Функция для проверки checked/selected состояний
function isChecked($fieldName, $value, $formData) {
    if (isset($formData[$fieldName])) {
        if (is_array($formData[$fieldName])) {
            return in_array($value, $formData[$fieldName]) ? 'checked' : '';
        }
        return $formData[$fieldName] == $value ? 'checked' : '';
    }
    return '';
}

function isSelected($fieldName, $value, $formData) {
    if (isset($formData[$fieldName]) && is_array($formData[$fieldName])) {
        return in_array($value, $formData[$fieldName]) ? 'selected' : '';
    }
    return '';
}

function getGenderText($gender) {
    $genders = [
        'male' => 'Мужской',
        'female' => 'Женский',
        'other' => 'Другой'
    ];
    return $genders[$gender] ?? '';
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Анкета разработчика</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #e8ecf2;
            min-height: 100vh;
            padding: 20px;
        }
        
        .container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            border-radius: 20px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        
        .header {
            background: #5a6e7c;
            color: white;
            padding: 30px;
            text-align: center;
        }
        
        .header h1 {
            font-size: 28px;
            margin-bottom: 10px;
        }
        
        .header p {
            opacity: 0.85;
            font-size: 14px;
        }
        
        .form-content {
            padding: 30px;
        }
        
        .form-group {
            margin-bottom: 25px;
        }
        
        .form-group label {
            display: block;
            font-weight: 600;
            margin-bottom: 8px;
            color: #333;
            font-size: 14px;
        }
        
        .form-group label .required {
            color: #e74c3c;
            margin-left: 5px;
        }
        
        .form-group input[type="text"],
        .form-group input[type="tel"],
        .form-group input[type="email"],
        .form-group input[type="date"],
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            font-size: 14px;
            transition: all 0.3s ease;
            font-family: inherit;
            background: #fafafa;
        }
        
        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #5a6e7c;
            box-shadow: 0 0 0 3px rgba(90, 110, 124, 0.1);
            background: white;
        }
        
        .radio-group {
            display: flex;
            gap: 20px;
            flex-wrap: wrap;
        }
        
        .radio-group label {
            display: flex;
            align-items: center;
            font-weight: normal;
            cursor: pointer;
        }
        
        .radio-group input[type="radio"] {
            margin-right: 8px;
            cursor: pointer;
        }
        
        .checkbox-group {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
        }
        
        .checkbox-group label {
            display: flex;
            align-items: center;
            font-weight: normal;
            cursor: pointer;
            background: #f0f2f5;
            padding: 8px 15px;
            border-radius: 20px;
            transition: all 0.3s ease;
        }
        
        .checkbox-group label:hover {
            background: #e0e4e8;
        }
        
        .checkbox-group input[type="checkbox"] {
            margin-right: 8px;
            cursor: pointer;
        }
        
        select[multiple] {
            height: auto;
            min-height: 150px;
        }
        
        select[multiple] option {
            padding: 8px;
            cursor: pointer;
        }
        
        select[multiple] option:checked {
            background: #5a6e7c linear-gradient(0deg, #5a6e7c 0%, #5a6e7c 100%);
            color: white;
        }
        
        .error-message {
            color: #e74c3c;
            font-size: 12px;
            margin-top: 5px;
            display: block;
        }
        
        .form-error {
            border-color: #e74c3c !important;
            background-color: #fff5f5 !important;
        }
        
        .success-message {
            background: #e8f5e9;
            color: #2e7d32;
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 25px;
            border-left: 4px solid #4caf50;
        }
        
        .error-summary {
            background: #ffebee;
            color: #c62828;
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 25px;
            border-left: 4px solid #f44336;
        }
        
        .error-summary ul {
            margin-left: 20px;
            margin-top: 10px;
        }
        
        .btn-submit {
            background: #5a6e7c;
            color: white;
            border: none;
            padding: 14px 30px;
            font-size: 16px;
            font-weight: 600;
            border-radius: 10px;
            cursor: pointer;
            width: 100%;
            transition: all 0.2s ease;
        }
        
        .btn-submit:hover {
            background: #4a5c68;
            transform: translateY(-1px);
        }
        
        .btn-submit:active {
            transform: translateY(0);
        }
        
        hr {
            margin: 20px 0;
            border: none;
            height: 1px;
            background: #e0e0e0;
        }
        
        .info-text {
            color: #666;
            font-size: 12px;
            margin-top: 5px;
        }
        
        @media (max-width: 600px) {
            .form-content {
                padding: 20px;
            }
            
            .radio-group {
                flex-direction: column;
                gap: 10px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>📝 Анкета разработчика</h1>
            <p>Заполните форму, чтобы стать частью нашего сообщества</p>
        </div>
        
        <div class="form-content">
            <?php if ($success): ?>
                <div class="success-message">
                    ✅ Спасибо! Ваши данные успешно сохранены.
                </div>
            <?php endif; ?>
            
            <?php if (!empty($errors)): ?>
                <div class="error-summary">
                    <strong>❌ Пожалуйста, исправьте следующие ошибки:</strong>
                    <ul>
                        <?php foreach ($errors as $error): ?>
                            <li><?= htmlspecialchars($error) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
            
            <form method="POST" action="">
                <!-- 1. ФИО -->
                <div class="form-group">
                    <label>ФИО <span class="required">*</span></label>
                    <input type="text" 
                           name="full_name" 
                           value="<?= getValue('full_name', $displayFormData) ?>"
                           class="<?= isset($errors['full_name']) ? 'form-error' : '' ?>"
                           placeholder="Иванов Иван Иванович">
                    <?php if (isset($errors['full_name'])): ?>
                        <span class="error-message"><?= $errors['full_name'] ?></span>
                    <?php endif; ?>
                    <div class="info-text">Допустимые символы: русские и латинские буквы, пробелы, дефисы</div>
                </div>
                
                <!-- 2. Телефон -->
                <div class="form-group">
                    <label>Телефон <span class="required">*</span></label>
                    <input type="tel" 
                           name="phone" 
                           value="<?= getValue('phone', $displayFormData) ?>"
                           class="<?= isset($errors['phone']) ? 'form-error' : '' ?>"
                           placeholder="+7(123)456-78-90">
                    <?php if (isset($errors['phone'])): ?>
                        <span class="error-message"><?= $errors['phone'] ?></span>
                    <?php endif; ?>
                    <div class="info-text">Допустимые символы: цифры, +, -, пробелы, скобки. Пример: +7(123)456-78-90</div>
                </div>
                
                <!-- 3. E-mail -->
                <div class="form-group">
                    <label>E-mail <span class="required">*</span></label>
                    <input type="email" 
                           name="email" 
                           value="<?= getValue('email', $displayFormData) ?>"
                           class="<?= isset($errors['email']) ? 'form-error' : '' ?>"
                           placeholder="ivan@example.com">
                    <?php if (isset($errors['email'])): ?>
                        <span class="error-message"><?= $errors['email'] ?></span>
                    <?php endif; ?>
                    <div class="info-text">Допустимые символы: латинские буквы, цифры, точки, дефисы, знак @</div>
                </div>
                
                <!-- 4. Дата рождения -->
                <div class="form-group">
                    <label>Дата рождения <span class="required">*</span></label>
                    <input type="date" 
                           name="birth_date" 
                           value="<?= getValue('birth_date', $displayFormData) ?>"
                           class="<?= isset($errors['birth_date']) ? 'form-error' : '' ?>">
                    <?php if (isset($errors['birth_date'])): ?>
                        <span class="error-message"><?= $errors['birth_date'] ?></span>
                    <?php endif; ?>
                </div>
                
                <!-- 5. Пол -->
                <div class="form-group">
                    <label>Пол <span class="required">*</span></label>
                    <div class="radio-group">
                        <label>
                            <input type="radio" name="gender" value="male" <?= isChecked('gender', 'male', $displayFormData) ?>>
                            Мужской
                        </label>
                        <label>
                            <input type="radio" name="gender" value="female" <?= isChecked('gender', 'female', $displayFormData) ?>>
                            Женский
                        </label>
                        <label>
                            <input type="radio" name="gender" value="other" <?= isChecked('gender', 'other', $displayFormData) ?>>
                            Другой
                        </label>
                    </div>
                    <?php if (isset($errors['gender'])): ?>
                        <span class="error-message"><?= $errors['gender'] ?></span>
                    <?php endif; ?>
                </div>
                
                <!-- 6. Любимый язык программирования -->
                <div class="form-group">
                    <label>Любимый язык программирования <span class="required">*</span></label>
                    <select name="languages[]" multiple size="6" class="<?= isset($errors['languages']) ? 'form-error' : '' ?>">
                        <?php foreach ($languagesList as $lang): ?>
                            <option value="<?= $lang['id'] ?>" <?= isSelected('languages', $lang['id'], $displayFormData) ?>>
                                <?= htmlspecialchars($lang['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <small style="color: #666; display: block; margin-top: 5px;">Удерживайте Ctrl (Cmd на Mac) для выбора нескольких языков</small>
                    <?php if (isset($errors['languages'])): ?>
                        <span class="error-message"><?= $errors['languages'] ?></span>
                    <?php endif; ?>
                </div>
                
                <!-- 7. Биография -->
                <div class="form-group">
                    <label>Биография</label>
                    <textarea name="bio" rows="5" placeholder="Расскажите немного о себе... (не более 5000 символов)"><?= getValue('bio', $displayFormData) ?></textarea>
                    <?php if (isset($errors['bio'])): ?>
                        <span class="error-message"><?= $errors['bio'] ?></span>
                    <?php endif; ?>
                </div>
                
                <!-- 8. Чекбокс с контрактом -->
                <div class="form-group">
                    <label>
                        <input type="checkbox" name="contract" value="1" <?= getValue('contract', $displayFormData) ? 'checked' : '' ?>>
                        Я ознакомлен(а) с условиями контракта и принимаю их <span class="required">*</span>
                    </label>
                    <?php if (isset($errors['contract'])): ?>
                        <span class="error-message"><?= $errors['contract'] ?></span>
                    <?php endif; ?>
                </div>
                
                <hr>
                
                <button type="submit" class="btn-submit">💾 Сохранить</button>
            </form>
        </div>
    </div>
</body>
</html>
