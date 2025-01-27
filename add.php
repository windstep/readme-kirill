<?php
require_once 'requires_guest.php';

$data = $_POST;
$post_type = $_GET['type'] ?? 'photo';

$post_data = ['errors' => []];

// fixme: указать значения параметров
function getCategoryId($link, $type): string|int
{
    $sql = "SELECT ct.id FROM content_types ct WHERE `name` = ?";

    $result = db_query_prepare_stmt($link, $sql, [$type]);
    return $result['id'];
}

// fixme: указать значения параметров
function validateUrl($url, $type): array|bool
{
    $isUrlValid = filter_var($url, FILTER_VALIDATE_URL);

    if (strlen($url) == 0 || !$isUrlValid) {
        return ['target' => 'url', 'text' => 'Укажите корректную ссылку на источник.'];
    }

    if ($type == 'video' && !check_youtube_url($url)) {
        return ['target' => 'url', 'text' => 'Указанная в ссылке видеозапись недоступна.'];
    }

    return false;
}

// fixme: указать значения параметров
function validateData($data, $link, $type, $user): array
{
    $ct = getCategoryId($link, $type);

    $files_path = __DIR__ . '/uploads/';

    $errors = [];

    $title = $data[$type . '-heading'] ?? null;
    $content = $data[$type . '-content'] ?? null;
    $author = $data[$type . '-author'] ?? null;
    $image_url = $data['image-url'] ?? null;
    $video_url = $data['video-url'] ?? null;
    $site_url = $data[$type . '-url'] ?? null;
    $tags = $data[$type . '-tags'] ?? null;
    $file = $_FILES['userpic-file-photo'] ?? null;

    $url = match ($type) {
        'photo' => $file ? $files_path . $file['name'] : $image_url,
        'video' => $video_url,
        'link' => $site_url,
        default => ''
    };

    if (strlen($title) == 0) {
        $errors[] = ['target' => 'title', 'text' => 'Укажите заголовок.'];
    }
    if (strlen($title) > 70) {
        $errors[] = ['target' => 'title', 'text' => 'Заголовок не может превышать 70 символов.'];
    }
    if ($url && validateUrl($url, $type)) {
        $errors[] = validateUrl($url, $type);
    }
    if ($file && validateFile($file, $files_path)) {
        $errors[] = validateFile($file, $files_path);
    }

    return [
        "data" => [
            "title" => $title,
            "content" => $content,
            "cite_author" => $author,
            "content_type" => $ct,
            "author" => $user['id'],
            "image_url" => $image_url,
            "video_url" => $video_url,
            "site_url" => $site_url,
        ],
        "errors" => $errors
    ];
}

// fixme: указать значения параметров и возвращаемые значения
function addPost($link, $post)
{
    $sql = "INSERT INTO `posts` (`date`, `title`, `content`, `cite_author`, `content_type`, `author`, `image_url`, `video_url`, `site_url`)" .
        " VALUES (NOW(), ?, ?, ?, ?, ?, ?, ?, ?)";

    return db_query_prepare_stmt($link, $sql, $post['data'], QUERY_EXECUTE);
}

if (count($data) > 0) {
    $post_data = validateData($data, $link, $post_type, $user); // fixme переменная $user может быть null. Как и переменная $link

    // fixme: строгое сравнение
    if (count($post_data['errors']) == 0) {
        addPost($link, $post_data); //fixme здесь может быть null и произойти ошибка сохранения
        header("Location: /popular.php?tab=all&page=1&sort=views"); // fixme редирект должен происходить на страницу поста, а не на страницу популярного
        exit();
    }
}

$content = include_template('adding-post.php', [
    "post_type" => $post_type,
    "errors" => $post_data['errors'],
]);
$layout = include_template('layout.php', [
    "content" => $content,
    "title" => "readme: создание поста",
    "user" => $user,
    "is_auth" => $is_auth,
]);

print($layout);
