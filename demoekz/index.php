<?php
require_once 'config.php';

$page = $_GET['page'] ?? 'home';
$action = $_GET['action'] ?? '';
$errors = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    if (isset($_POST['register'])) {
        $login = trim($_POST['login'] ?? '');
        $password = $_POST['password'] ?? '';
        $fullName = trim($_POST['full_name'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $email = trim($_POST['email'] ?? '');
        
        if (!validate($login, 'login')) $errors[] = 'Логин: минимум 8 символов';
        if (!validate($password, 'password')) $errors[] = 'Пароль: минимум 8 символов';
        if (!validate($fullName, 'name')) $errors[] = 'ФИО: только кириллица';
        if (!validate($phone, 'phone')) $errors[] = 'Телефон: +7(XXX)-XXX-XX-XX';
        if (!validate($email, 'email')) $errors[] = 'Некорректный email';
        
        if (empty($errors)) {
            $stmt = $pdo->prepare("SELECT id FROM users WHERE login = ?");
            $stmt->execute([$login]);
            if ($stmt->fetch()) $errors[] = 'Логин занят';
        }
        
        if (empty($errors)) {
            $stmt = $pdo->prepare("INSERT INTO users (login, password, full_name, phone, email, role) VALUES (?, ?, ?, ?, ?, 'user')");
            $stmt->execute([$login, password_hash($password, PASSWORD_DEFAULT), $fullName, $phone, $email]);
            header('Location: index.php?page=login&registered=1');
            exit;
        }
    }
    
    if (isset($_POST['auth_login'])) {
        $login = trim($_POST['login'] ?? '');
        $password = $_POST['password'] ?? '';
        
        if (empty($login) || empty($password)) {
            $errors[] = 'Заполните все поля';
        } else {
            $stmt = $pdo->prepare("SELECT * FROM users WHERE login = ?");
            $stmt->execute([$login]);
            $user = $stmt->fetch();
            
            if ($user && password_verify($password, $user['password'])) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_name'] = $user['full_name'];
                $_SESSION['role'] = $user['role'];
                
                if ($user['role'] === 'admin') {
                    header('Location: index.php?page=admin');
                } else {
                    header('Location: index.php?page=orders');
                }
                exit;
            } else {
                $errors[] = 'Неверный логин или пароль';
            }
        }
    }
    
    if (isset($_POST['create_order']) && isLoggedIn()) {
        $address = trim($_POST['address'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $date = $_POST['date'] ?? '';
        $time = $_POST['time'] ?? '';
        $service = $_POST['service'] ?? '';
        $isCustom = isset($_POST['is_custom']);
        $customService = trim($_POST['custom_service'] ?? '');
        $payment = $_POST['payment'] ?? '';
        
        if (empty($address)) $errors[] = 'Укажите адрес';
        if (!validate($phone, 'phone')) $errors[] = 'Неверный формат телефона';
        if (empty($date)) $errors[] = 'Выберите дату';
        if (empty($time)) $errors[] = 'Выберите время';
        if (!$isCustom && empty($service)) $errors[] = 'Выберите услугу';
        if ($isCustom && empty($customService)) $errors[] = 'Опишите услугу';
        if (!in_array($payment, ['cash', 'card'])) $errors[] = 'Выберите тип оплаты';
        
        if (empty($errors)) {
            $serviceType = $isCustom ? 'Иная услуга' : $service;
            $stmt = $pdo->prepare("INSERT INTO orders (user_id, address, phone, service_type, custom_service, preferred_date, preferred_time, payment_type) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$_SESSION['user_id'], $address, $phone, $serviceType, $isCustom ? $customService : null, $date, $time, $payment]);
            $success = 'Заявка создана!';
        }
    }
    
    if (isset($_POST['update_status']) && isAdmin()) {
        $orderId = $_POST['order_id'];
        $status = $_POST['status'];
        $reason = trim($_POST['cancel_reason'] ?? '');
        
        if ($status === 'cancelled' && empty($reason)) {
            $errors[] = 'Укажите причину отмены';
        } else {
            $stmt = $pdo->prepare("UPDATE orders SET status = ?, cancel_reason = ? WHERE id = ?");
            $stmt->execute([$status, $reason ?: null, $orderId]);
            $success = 'Статус обновлен';
        }
    }
}

if ($action === 'add_review' && isLoggedIn()) {
    header('Content-Type: application/json');
    $orderId = $_POST['order_id'] ?? 0;
    $rating = (int)($_POST['rating'] ?? 0);
    $comment = trim($_POST['comment'] ?? '');
    
    // Изменено: теперь можно оставить отзыв на completed и cancelled
    $stmt = $pdo->prepare("SELECT id FROM orders WHERE id = ? AND user_id = ? AND (status = 'completed' OR status = 'cancelled')");
    $stmt->execute([$orderId, $_SESSION['user_id']]);
    if (!$stmt->fetch()) {
        echo json_encode(['error' => 'Нельзя оставить отзыв']);
        exit;
    }
    
    $stmt = $pdo->prepare("SELECT id FROM reviews WHERE order_id = ?");
    $stmt->execute([$orderId]);
    if ($stmt->fetch()) {
        echo json_encode(['error' => 'Отзыв уже есть']);
        exit;
    }
    
    $stmt = $pdo->prepare("INSERT INTO reviews (order_id, user_id, rating, comment) VALUES (?, ?, ?, ?)");
    $stmt->execute([$orderId, $_SESSION['user_id'], $rating, $comment]);
    echo json_encode(['success' => true]);
    exit;
}

if ($action === 'logout') {
    session_destroy();
    header('Location: index.php?page=home');
    exit;
}

$reviews = $pdo->query("SELECT r.*, u.full_name FROM reviews r JOIN users u ON r.user_id = u.id ORDER BY r.created_at DESC LIMIT 6")->fetchAll();

if (isLoggedIn() && isUser()) {
    $stmt = $pdo->prepare("SELECT * FROM orders WHERE user_id = ? ORDER BY created_at DESC");
    $stmt->execute([$_SESSION['user_id']]);
    $userOrders = $stmt->fetchAll();
}

if (isAdmin()) {
    $allOrders = $pdo->query("SELECT o.*, u.full_name, u.email FROM orders o JOIN users u ON o.user_id = u.id ORDER BY o.created_at DESC")->fetchAll();
}

$sliderImages = [
    ['image' => 'slider1.jpg', 'title' => 'Профессиональная уборка'],
    ['image' => 'slider2.jpg', 'title' => 'Генеральная уборка'],
    ['image' => 'slider3.jpg', 'title' => 'Химчистка мебели'],
    ['image' => 'slider4.jpg', 'title' => 'Уборка офисов'],
    ['image' => 'slider5.jpg', 'title' => 'Послестроительная уборка'],
    ['image' => 'slider6.jpg', 'title' => 'Клининговые услуги']
];

$serviceIcons = [
    ['icon' => 'icon1.png', 'title' => 'Общий клининг', 'desc' => 'Регулярная уборка'],
    ['icon' => 'icon2.png', 'title' => 'Генеральная уборка', 'desc' => 'Тщательная уборка'],
    ['icon' => 'icon3.png', 'title' => 'Послестроительная', 'desc' => 'После ремонта'],
    ['icon' => 'icon4.png', 'title' => 'Химчистка', 'desc' => 'Чистка мебели']
];

$socialLinks = [
    ['icon' => 'vk.png', 'alt' => 'VK'],
    ['icon' => 'telegram.png', 'alt' => 'Telegram'],
    ['icon' => 'whatsapp.png', 'alt' => 'WhatsApp']
];
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Мой Не Сам</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <?php if ($page === 'admin' && isAdmin()): ?>
    
    <header>
        <div class="container">
            <div class="logo">
                <img src="images/logo.png" alt="">
                <h1>Админ-панель</h1>
            </div>
            <nav>
                <a href="index.php?action=logout">Выход</a>
            </nav>
        </div>
    </header>
    
    <main>
        <div class="container">
            <div class="admin-page">
                <h2>Управление заявками</h2>
                <?php if ($errors): ?>
                    <div class="alert error"><?= implode('<br>', array_map('htmlspecialchars', $errors)) ?></div>
                <?php endif; ?>
                <?php if ($success): ?>
                    <div class="alert success"><?= htmlspecialchars($success) ?></div>
                <?php endif; ?>
                <div class="admin-grid">
                    <?php foreach ($allOrders as $order): ?>
                    <div class="admin-card">
                        <div class="order-header">
                            <span>Заявка #<?= $order['id'] ?></span>
                            <span class="status status-<?= $order['status'] ?>"><?= ['new'=>'Новая','in_progress'=>'В работе','completed'=>'Выполнена','cancelled'=>'Отменена'][$order['status']] ?></span>
                        </div>
                        <p><strong>Заявитель:</strong> <?= htmlspecialchars($order['full_name']) ?></p>
                        <p><strong>Телефон:</strong> <?= htmlspecialchars($order['phone']) ?></p>
                        <p><strong>Адрес:</strong> <?= htmlspecialchars($order['address']) ?></p>
                        <p><strong>Услуга:</strong> <?= htmlspecialchars($order['service_type']) ?></p>
                        <?php if($order['custom_service']): ?>
                            <p><strong>Описание:</strong> <?= htmlspecialchars($order['custom_service']) ?></p>
                        <?php endif; ?>
                        <p><strong>Дата:</strong> <?= $order['preferred_date'] ?> <?= $order['preferred_time'] ?></p>
                        <p><strong>Оплата:</strong> <?= $order['payment_type']==='cash'?'Наличные':'Карта' ?></p>
                        <?php if($order['cancel_reason']): ?>
                            <p><strong>Причина отмены:</strong> <?= htmlspecialchars($order['cancel_reason']) ?></p>
                        <?php endif; ?>
                        <form method="POST" class="status-form">
                            <input type="hidden" name="order_id" value="<?= $order['id'] ?>">
                            <select name="status" onchange="this.nextElementSibling.style.display=this.value==='cancelled'?'block':'none'">
                                <option value="in_progress">В работе</option>
                                <option value="completed">Выполнено</option>
                                <option value="cancelled">Отменено</option>
                            </select>
                            <input type="text" name="cancel_reason" placeholder="Причина отмены" style="display:none;">
                            <button type="submit" name="update_status">Обновить</button>
                        </form>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </main>

    <?php elseif ($page === 'orders' && isUser()): ?>
    
    <header>
        <div class="container">
            <div class="logo">
                <img src="images/logo.png" alt="">
                <h1>Личный кабинет</h1>
            </div>
            <nav>
                <a href="index.php?action=logout">Выход</a>
            </nav>
        </div>
    </header>
    
    <main>
        <div class="container">
            <div class="orders-page">
                <div class="orders-history">
                    <h2>История заявок</h2>
                    <?php if(empty($userOrders)): ?>
                        <p class="text-center">У вас пока нет заявок</p>
                    <?php else: foreach($userOrders as $order): ?>
                    <div class="order-card">
                        <div class="order-header">
                            <span class="status status-<?= $order['status'] ?>"><?= ['new'=>'Новая','in_progress'=>'В работе','completed'=>'Выполнена','cancelled'=>'Отменена'][$order['status']] ?></span>
                            <span><?= date('d.m.Y', strtotime($order['created_at'])) ?></span>
                        </div>
                        <p><strong>Услуга:</strong> <?= htmlspecialchars($order['service_type']) ?></p>
                        <?php if($order['custom_service']): ?>
                            <p><strong>Описание:</strong> <?= htmlspecialchars($order['custom_service']) ?></p>
                        <?php endif; ?>
                        <p><strong>Дата:</strong> <?= $order['preferred_date'] ?> в <?= $order['preferred_time'] ?></p>
                        <p><strong>Адрес:</strong> <?= htmlspecialchars($order['address']) ?></p>
                        <p><strong>Оплата:</strong> <?= $order['payment_type']==='cash'?'Наличные':'Карта' ?></p>
                        <?php if($order['status'] === 'completed' || $order['status'] === 'cancelled'): ?>
                            <?php
                            $revStmt = $pdo->prepare("SELECT id FROM reviews WHERE order_id=?");
                            $revStmt->execute([$order['id']]);
                            if(!$revStmt->fetch()):
                            ?>
                            <button onclick="review(<?= $order['id'] ?>)" class="btn btn-sm">Оставить отзыв</button>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; endif; ?>
                </div>

                <div class="order-form-wrapper">
                    <h2>Новая заявка</h2>
                    <?php if($errors): ?>
                        <div class="alert error"><?= implode('<br>', array_map('htmlspecialchars', $errors)) ?></div>
                    <?php endif; ?>
                    <?php if($success): ?>
                        <div class="alert success"><?= htmlspecialchars($success) ?></div>
                    <?php endif; ?>
                    <form method="POST">
                        <textarea name="address" placeholder="Адрес" required></textarea>
                        <input type="tel" name="phone" placeholder="+7(XXX)-XXX-XX-XX" required>
                        <div class="row">
                            <input type="date" name="date" required>
                            <input type="time" name="time" required>
                        </div>
                        <select name="service" id="serviceSelect">
                            <option value="">Выберите услугу</option>
                            <option>Общий клининг</option>
                            <option>Генеральная уборка</option>
                            <option>Послестроительная уборка</option>
                            <option>Химчистка ковров и мебели</option>
                        </select>
                        <label class="checkbox">
                            <input type="checkbox" name="is_custom" id="isCustom"> Иная услуга
                        </label>
                        <textarea name="custom_service" id="customService" placeholder="Опишите услугу" style="display:none;"></textarea>
                        <select name="payment" required>
                            <option value="">Тип оплаты</option>
                            <option value="cash">Наличные</option>
                            <option value="card">Банковская карта</option>
                        </select>
                        <button type="submit" name="create_order">Отправить</button>
                    </form>
                </div>
            </div>
        </div>
    </main>

    <?php else: ?>
    
    <header>
        <div class="container">
            <a href="index.php?page=home" class="logo-link">
                <div class="logo">
                    <img src="images/logo.png" alt="">
                    <h1>Мой Не Сам</h1>
                </div>
            </a>
            <nav>
                <a href="index.php?page=home">Главная</a>
                <?php if(!isLoggedIn()): ?>
                    <a href="index.php?page=login">Авторизация</a>
                <?php endif; ?>
            </nav>
        </div>
    </header>

    <main>
        <div class="container">
            <?php if($page === 'home'): ?>
                
                <section class="about-section">
                    <h2>Добро пожаловать в "Мой Не Сам"</h2>
                    <p>Мы предоставляем профессиональные услуги по уборке жилых и производственных помещений.</p>
                    <p>Работаем с 2018 года. Более 5000 довольных клиентов.</p>
                </section>

                <section class="hero">
                    <div class="slider">
                        <div class="slides">
                            <?php foreach($sliderImages as $img): ?>
                            <div class="slide">
                                <img src="images/<?= $img['image'] ?>" alt="<?= $img['title'] ?>">
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <div class="slider-nav">
                            <button class="slider-btn prev" type="button">◀</button>
                            <button class="slider-btn next" type="button">▶</button>
                        </div>
                        <div class="slider-dots"></div>
                    </div>
                    <div class="hero-content">
                        <h2>Профессиональный клининг</h2>
                        <p>Доверьте уборку профессионалам.</p>
                        <?php if(!isLoggedIn()): ?>
                            <a href="index.php?page=login" class="btn">Авторизоваться</a>
                        <?php endif; ?>
                    </div>
                </section>

                <section class="services">
                    <h2>Наши услуги</h2>
                    <div class="services-grid">
                        <?php foreach($serviceIcons as $service): ?>
                        <div class="service-card">
                            <img src="images/<?= $service['icon'] ?>" alt="">
                            <h3><?= $service['title'] ?></h3>
                            <p><?= $service['desc'] ?></p>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </section>

                <?php if($reviews): ?>
                <section class="reviews">
                    <h2>Отзывы</h2>
                    <div class="reviews-grid">
                        <?php foreach($reviews as $review): ?>
                        <div class="review-card">
                            <strong><?= htmlspecialchars($review['full_name']) ?></strong>
                            <div class="stars"><?= str_repeat('★', $review['rating']).str_repeat('☆', 5-$review['rating']) ?></div>
                            <p><?= htmlspecialchars($review['comment']) ?></p>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </section>
                <?php endif; ?>

            <?php elseif($page === 'login' && !isLoggedIn()): ?>
                <div class="auth-wrapper">
                    <form method="POST" class="form">
                        <h2>Авторизация</h2>
                        <?php if($errors): ?>
                            <div class="alert error"><?= implode('<br>', array_map('htmlspecialchars', $errors)) ?></div>
                        <?php endif; ?>
                        <?php if(isset($_GET['registered'])): ?>
                            <div class="alert success">Регистрация успешна!</div>
                        <?php endif; ?>
                        <input type="text" name="login" placeholder="Логин" required>
                        <input type="password" name="password" placeholder="Пароль" required>
                        <button type="submit" name="auth_login">Войти</button>
                        <p class="auth-link"><a href="index.php?page=register">Регистрация</a></p>
                    </form>
                </div>

            <?php elseif($page === 'register' && !isLoggedIn()): ?>
                <div class="auth-wrapper">
                    <form method="POST" class="form">
                        <h2>Регистрация</h2>
                        <?php if($errors): ?>
                            <div class="alert error"><?= implode('<br>', array_map('htmlspecialchars', $errors)) ?></div>
                        <?php endif; ?>
                        <input type="text" name="login" placeholder="Логин (мин. 8 символов)" required>
                        <input type="password" name="password" placeholder="Пароль (мин. 8 символов)" required>
                        <input type="text" name="full_name" placeholder="ФИО" required>
                        <input type="tel" name="phone" placeholder="+7(XXX)-XXX-XX-XX" required>
                        <input type="email" name="email" placeholder="Email" required>
                        <button type="submit" name="register">Зарегистрироваться</button>
                        <p class="auth-link"><a href="index.php?page=login">Войти</a></p>
                    </form>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <footer>
        <div class="container">
            <div class="footer-content">
                <div class="social-links">
                    <?php foreach($socialLinks as $social): ?>
                    <a href="#"><img src="images/<?= $social['icon'] ?>" alt=""></a>
                    <?php endforeach; ?>
                </div>
                <div class="footer-info">
                    <p>© 2024 Мой Не Сам</p>
                    <p>+7(999)-123-45-67</p>
                </div>
            </div>
        </div>
    </footer>
    <?php endif; ?>

    <div id="reviewModal" class="modal" style="display:none;">
        <div class="modal-content">
            <span class="close" onclick="document.getElementById('reviewModal').style.display='none'">&times;</span>
            <h3>Отзыв</h3>
            <form id="reviewForm">
                <input type="hidden" name="order_id" id="reviewOrderId">
                <select name="rating" required>
                    <option value="5">5</option>
                    <option value="4">4</option>
                    <option value="3">3</option>
                    <option value="2">2</option>
                    <option value="1">1</option>
                </select>
                <textarea name="comment" placeholder="Ваш отзыв" required></textarea>
                <button type="submit">Отправить</button>
            </form>
        </div>
    </div>

    <script src="js/scripts.js"></script>
</body>
</html>