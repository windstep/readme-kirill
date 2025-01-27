<?php
//fixme опиши все передаваемые и возвращаемые значения
//fixme проверь возвращаемые значения и их корректность
//fixme проверь уровень вложенности - не должен быть больше 3
//fixme этот класс умеет слишком многое

use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mailer\Transport;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mime\Email;

/**
 * Проверяет переданную дату на соответствие формату 'ГГГГ-ММ-ДД'
 *
 * Примеры использования:
 * is_date_valid('2019-01-01'); // true
 * is_date_valid('2016-02-29'); // true
 * is_date_valid('2019-04-31'); // false
 * is_date_valid('10.10.2010'); // false
 * is_date_valid('10/10/2010'); // false
 *
 * @param string $date Дата в виде строки
 *
 * @return bool true при совпадении с форматом 'ГГГГ-ММ-ДД', иначе false
 */
function is_date_valid(string $date): bool
{
    $format_to_check = 'Y-m-d';
    $dateTimeObj = date_create_from_format($format_to_check, $date);

    return $dateTimeObj !== false && array_sum(date_get_last_errors()) === 0;
}

/**
 * Создает подготовленное выражение на основе готового SQL запроса и переданных данных
 *
 * @param $link mysqli Ресурс соединения
 * @param $sql string SQL запрос с плейсхолдерами вместо значений
 * @param array $data Данные для вставки на место плейсхолдеров
 *
 * @return mysqli_stmt Подготовленное выражение
 */
function db_get_prepare_stmt($link, $sql, $data = []): mysqli_stmt
{
    $stmt = mysqli_prepare($link, $sql);

    if ($stmt === false) {
        $errorMsg = 'Не удалось инициализировать подготовленное выражение: ' . mysqli_error($link);
        die($errorMsg);
    }

    if ($data) {
        $types = '';
        $stmt_data = [];

        foreach ($data as $value) {
            $type = 's';

            if (is_int($value)) {
                $type = 'i';
            } else if (is_string($value)) {
                $type = 's';
            } else if (is_float($value)) {
                $type = 'd';
            }

            if ($type) {
                $types .= $type;
                $stmt_data[] = $value;
            }
        }

        $values = array_merge([$stmt, $types], $stmt_data);

        $func = 'mysqli_stmt_bind_param';
        $func(...$values);

        if (mysqli_errno($link) > 0) {
            $errorMsg = 'Не удалось связать подготовленное выражение с параметрами: ' . mysqli_error($link);
            die($errorMsg);
        }
    }

    return $stmt;
}

//fixme константы лучше вынести в отдельный файлик или задавать в начале. Это не соответствует PSR
const QUERY_DEFAULT = 'default';
const QUERY_ASSOC = 'assoc';
const QUERY_EXECUTE = 'execute';

function db_query_prepare_stmt(mysqli $link, $sql, $data = [], $type = QUERY_DEFAULT): array|null
{
    $answer = null;
    $stmt = db_get_prepare_stmt($link, $sql, $data);

    mysqli_stmt_execute($stmt);

    if ($type !== QUERY_EXECUTE) {
        $result = mysqli_stmt_get_result($stmt);

        if ($type === QUERY_ASSOC) {
            $answer = mysqli_fetch_all($result, MYSQLI_ASSOC);
        } else {
            $answer = mysqli_fetch_assoc($result);
        }
    }

    mysqli_stmt_close($stmt);
    return $answer;
}

/**
 * Возвращает корректную форму множественного числа
 * Ограничения: только для целых чисел
 *
 * Пример использования:
 * $remaining_minutes = 5;
 * echo "Я поставил таймер на {$remaining_minutes} " .
 *     get_noun_plural_form(
 *         $remaining_minutes,
 *         'минута',
 *         'минуты',
 *         'минут'
 *     );
 * Результат: "Я поставил таймер на 5 минут"
 *
 * @param int $number Число, по которому вычисляем форму множественного числа
 * @param string $one Форма единственного числа: яблоко, час, минута
 * @param string $two Форма множественного числа для 2, 3, 4: яблока, часа, минуты
 * @param string $many Форма множественного числа для остальных чисел
 *
 * @return string Рассчитанная форма множественнго числа
 */
function get_noun_plural_form(int $number, string $one, string $two, string $many): string
{
    $number = (int)$number;
    $mod10 = $number % 10;
    $mod100 = $number % 100;

    return match (true) {
        $mod100 >= 11 && $mod100 <= 20 => $many,
        $mod10 > 5 => $many,
        $mod10 === 1 => $one,
        $mod10 >= 2 && $mod10 <= 4 => $two,
        default => $many,
    };
}

/**
 * Подключает шаблон, передает туда данные и возвращает итоговый HTML контент
 * @param string $name Путь к файлу шаблона относительно папки templates
 * @param array $data Ассоциативный массив с данными для шаблона
 * @return string Итоговый HTML
 */
function include_template(string $name, array $data = []): string
{
    $name = 'templates/' . $name;
    $result = '';

    if (!is_readable($name)) {
        return $result;
    }

    ob_start();
    extract($data);
    require $name;

    return ob_get_clean();
}

function validateFile($file, $path): array|bool
{
    var_dump($file);die();
    if (!$file['name']) {
        return ['target' => 'file', 'text' => 'Прикрепите или укажите ссылку на изображение.'];
    }

    $mime = $file['type'];
    $name = $file['name'];
    $tmp_name = $file['tmp_name'];

    if ($mime != 'image/gif' && $mime != 'image/jpeg' && $mime != 'image/png') {
        return [
            'target' => 'file',
            'text' => 'Вы можете загрузить файлы только в следующих форматах: .png, .jpeg, .gif.'
        ];
    }

    move_uploaded_file($tmp_name, $path . $name);
    return false;
}

function setUserDataCookies($email, $password, $expires): void
{
    setcookie('user_email', $email, $expires);
    setcookie('user_password', $password, $expires);
}

// fixme по документации возвращает string, а фактически - bool
/**
 * Функция проверяет доступно ли видео по ссылке на youtube
 * @param string $url ссылка на видео
 *
 * @return string Ошибку если валидация не прошла
 */
function check_youtube_url($url)
{
    $id = extract_youtube_id($url);

    set_error_handler(function () {
    }, E_WARNING);
    $headers = get_headers('https://www.youtube.com/oembed?format=json&url=http://www.youtube.com/watch?v=' . $id);
    restore_error_handler();

    if (!is_array($headers)) {
        return false;
    }

    $err_flag = strpos($headers[0], '200') ? 200 : 404;

    if ($err_flag !== 200) {
        return false;
    }

    return true;
}

/**
 * Возвращает код iframe для вставки youtube видео на страницу
 * @param string $youtube_url Ссылка на youtube видео
 * @return string
 */
function embed_youtube_video($youtube_url)
{
    $res = "";
    $id = extract_youtube_id($youtube_url);

    if ($id) {
        $src = "https://www.youtube.com/embed/" . $id;
        $res = '<iframe width="760" height="400" src="' . $src . '" frameborder="0"></iframe>';
    }

    return $res;
}

/**
 * Возвращает img-тег с обложкой видео для вставки на страницу
 * @param string|null $youtube_url Ссылка на youtube видео
 * @return string
 */
function embed_youtube_cover(string $youtube_url = null)
{
    $res = "";
    $id = extract_youtube_id($youtube_url);

    if ($id) {
        $src = sprintf("https://img.youtube.com/vi/%s/mqdefault.jpg", $id);
        $res = '<img alt="youtube cover" width="320" height="120" src="' . $src . '" />';
    }

    return $res;
}

/**
 * Извлекает из ссылки на youtube видео его уникальный ID
 * @param string $youtube_url Ссылка на youtube видео
 * @return array
 */
function extract_youtube_id($youtube_url): ?string
{
    $id = false;

    $parts = parse_url($youtube_url);

    if ($parts) {
        if ($parts['path'] === '/watch') {
            parse_str($parts['query'], $vars);
            $id = $vars['v'] ?? null;
        } else {
            if ($parts['host'] == 'youtu.be') {
                $id = substr($parts['path'], 1);
            }
        }
    }

    return $id;
}

/**
 * @param $index
 * @return false|string
 */
function generate_random_date($index)
{
    $deltas = [['minutes' => 59], ['hours' => 23], ['days' => 6], ['weeks' => 4], ['months' => 11]];
    $dcnt = count($deltas);

    if ($index < 0) {
        $index = 0;
    }

    if ($index >= $dcnt) {
        $index = $dcnt - 1;
    }

    $delta = $deltas[$index];
    $timeval = rand(1, current($delta));
    $timename = key($delta);

    $ts = strtotime("$timeval $timename ago");
    $dt = date('Y-m-d H:i:s', $ts);

    return $dt;
}

function getContentClassById($link, $id): string|null
{
    $sql = "SELECT * FROM `content_types`" .
        " WHERE `id` = '$id'";

    $result = mysqli_query($link, $sql);

    if ($result === false) {
        print_r("Ошибка выполнения запроса: " . mysqli_error($link));
        die();
    }

    $result_arr = mysqli_fetch_all($result, MYSQLI_ASSOC);

    return $result_arr[0]["class_name"] ?? null;
}

function showData($text, $maxSymbols = 300): array
{
    $array = explode(' ', $text);
    $result = [
        'text' => null,
        'isLong' => 0
    ];

    $symbols = 0;

    foreach ($array as $word) {
        $symbols += strlen($word);

        if ($symbols < $maxSymbols) {
            $result['text'] .= ' ' . $word;
        } else {
            $result['text'] .= '...';
            $result['isLong'] = 1;
            break;
        }
    }

    return $result;
}

function normalizeDate($date): string
{
    $postUnix = strtotime($date);
    $interval = floor((time() - $postUnix) / 60);
    $type = "";
    $types = [
        "minutes" => ["минуту", "минуты", "минут"],
        "hours" => ["час", "часа", "часов"],
        "days" => ["день", "дня", "дней"],
        "weeks" => ["неделю", "недели", "недель"],
        "months" => ["месяц", "месяца", "месяцев"],
        "years" => ["год", "года", "лет"]
    ];

    if ($interval < 60) {
        $type = "minutes";
    } else if ($interval / 60 < 24) {
        $type = "hours";
        $interval = floor($interval / 60);
    } else if ($interval / 60 / 24 < 7) {
        $type = "days";
        $interval = floor($interval / 60 / 24);
    } else if ($interval / 60 / 24 / 7 < 5) {
        $type = "weeks";
        $interval = floor($interval / 60 / 24 / 7);
    } else if ($interval / 60 / 24 / 7 / 5 < 12) {
        $type = "months";
        $interval = floor($interval / 60 / 24 / 7 / 5);
    } else {
        $type = "years";
        $interval = floor($interval / 60 / 24 / 7 / 5 / 12);
    }

$correctWord = get_noun_plural_form($interval, $types[$type][0], $types[$type][1], $types[$type][2]);

return "$interval $correctWord";
}

function getUserData($link, $type, $var): array
{
    $sql = null;

    if ($type === 'email') {
        $sql = "SELECT * FROM `users` u WHERE u.email = ?";
    } else {
        $sql = "SELECT * FROM `users` u WHERE u.id = ?";
    }

    return db_query_prepare_stmt($link, $sql, [$var]) ?? [];
}

function getSubs($link, $id): array
{
    $sql = "SELECT * FROM `subscriptions` s WHERE s.user = ?";

    $result = db_query_prepare_stmt($link, $sql, [$id], QUERY_ASSOC);

    return $result ?? [];
}

function checkIsUserSubscribed($link, $user, $author)
{
    $sql = "SELECT * FROM `subscriptions` s WHERE s.subscriber = ? AND s.user = ?";

    return db_query_prepare_stmt($link, $sql, [$user, $author]);
}

function getPostLikes($link, $post)
{
    $sql = "SELECT * FROM `likes` l WHERE l.post = ?";

    return db_query_prepare_stmt($link, $sql, [$post], QUERY_ASSOC);
}

function isPostLiked($link, $user, $post)
{
    $sql = "SELECT l.id FROM `likes` l WHERE l.post = ? AND l.user = ?";

    return db_query_prepare_stmt($link, $sql, [$post, $user]);
}

function getComments($link, $id): array
{
    $sql = " SELECT * FROM `comments` c" .
        " JOIN `users` u ON c.author = u.id" .
        " WHERE c.post = ?";

    return db_query_prepare_stmt($link, $sql, [$id], QUERY_ASSOC);
}

function getPostById($link, $id)
{
    $sql = " SELECT p.*, ct.name, ct.class_name FROM `posts` p" .
        " JOIN `content_types` ct ON p.content_type = ct.id" .
        " WHERE p.id = ?";

    $post = db_query_prepare_stmt($link, $sql, [$id]);

    //fixme упрощай
    if (isset($post['id'])) {
        return $post;
    }

    http_response_code(404);
    die();

}

function checkIsUserViewPost($link, $user_id, $post_id): array {
    $sql = "SELECT COUNT(*) > 0 FROM `views` v" .
        " WHERE v.post_id = ? AND v.user_id = ?";

    return db_query_prepare_stmt($link, $sql, [$post_id, $user_id]);
}

function addPostView($link, $user_id, $post_id) {
    //fixme используй implode вместо join
    $isUserViewPost = join(' ', checkIsUserViewPost($link, $user_id, $post_id));

    if ($isUserViewPost) {
        return false;
    }

    $sql = "INSERT INTO `views` (`post_id`, `user_id`) VALUES (?, ?)";

    return db_query_prepare_stmt($link, $sql, [$post_id, $user_id], QUERY_EXECUTE);
}

function getPostViews($link, $id): array {
    $sql = "SELECT COUNT(*) v FROM `views` v" .
        " WHERE v.post_id = ?";

    return db_query_prepare_stmt($link, $sql, [$id], QUERY_ASSOC);
}

function addComment($link, $text, $post, $author)
{
    $sql = "INSERT INTO `comments` (`date`, `content`, `author`, `post`) VALUES(NOW(), ?, ?, ?)";

    return db_query_prepare_stmt($link, $sql, [$text, $author, $post], QUERY_EXECUTE);
}

const EMAIL_MESSAGE_TYPE = 'message';
const EMAIL_SUB_TYPE = 'subscription';
const EMAIL_MESSAGE_PRESET = [
    'subject' => 'message title text',
    'content' => '<p>new message</p>'
];
const EMAIL_SUB_PRESET = [
    'subject' => 'subscription title text',
    'content' => '<p>new subscriber</p>'
];

//fixme лучше передавать значения пресетов в качестве аргументов функции.
// сейчас она выглядит слишком много знающей
function sendEmailNotify($sender, $recipient, string $subject, string $body)
{
    // fixme параметры лучше вынести в отдельный метод инициализации, подгружать из конфига
    $transport = Transport::fromDsn('smtp://parismay.frontend@mail.ru:psswd@smtp.mail.ru:465');
    $mailer = new Mailer($transport);

    $email = (new Email())
        ->from('parismay.frontend@mail.ru')
        ->to($recipient['email'])
        ->subject($type == EMAIL_SUB_TYPE ? EMAIL_SUB_PRESET['subject'] : EMAIL_MESSAGE_PRESET['subject'])
        ->text($sender['login'] . ' ' . $type == EMAIL_SUB_TYPE ? 'your new follower' : 'send a new message')
        ->html($type == EMAIL_SUB_TYPE ? EMAIL_SUB_PRESET['content'] : EMAIL_MESSAGE_PRESET['content']);

    // fixme не учитываешь интерфейс ошибки TransportExceptionInterface.
    try {
        $mailer->send($email);
    } catch (\Throwable $e) {
        echo("<div class='error'>" . $e->getMessage() . "</div>");
    }
}
