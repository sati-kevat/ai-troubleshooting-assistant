<?php

declare(strict_types=1);

/**
 * Front controller for the REST API.
 *
 * Apache (XAMPP):  http://localhost/pinelabs-api/api/...
 * PHP built-in:    php -S localhost:8000 backend/public/index.php
 */

require_once dirname(__DIR__) . '/src/bootstrap.php';

Http::applyCors(Config::list('CORS_ALLOWED_ORIGINS'));

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if ($method === 'OPTIONS') {
    http_response_code(204);
    exit;
}

$router = new Router();

$router->add('GET', '/api/health', function () {
    $database = 'connected';
    $articleCount = null;
    try {
        $articleCount = (new ArticleRepository(Database::connection()))->count();
    } catch (ApiException) {
        $database = 'unavailable';
    }
    $ai = OllamaAnswerGenerator::fromConfig();
    return [
        'status'      => $database === 'connected' ? 'ok' : 'degraded',
        'database'    => $database,
        'kb_articles' => $articleCount,
        'mode'        => $ai ? 'ai' : 'demo',
        'ai_provider' => $ai ? 'ollama' : null,
        'ai_model'    => $ai?->model(),
        'time'        => date(DATE_ATOM),
    ];
});

$router->add('GET', '/api/categories', function () {
    return ['items' => (new ArticleRepository(Database::connection()))->categories()];
});

$router->add('GET', '/api/articles', function () {
    $errors = [];
    $category = Validator::optionalSlug(Http::query('category'), 'category', $errors);
    if ($errors) {
        throw ApiException::validation($errors);
    }
    $items = (new ArticleRepository(Database::connection()))->search(Validator::search(Http::query('search')), $category);
    return ['items' => $items, 'total' => count($items)];
});

$router->add('GET', '/api/articles/{code}', function (array $params) {
    if (!preg_match('/^KB-\d{1,6}$/i', $params['code'])) {
        throw ApiException::notFound('Article not found.');
    }
    $article = (new ArticleRepository(Database::connection()))->findByCode(strtoupper($params['code']));
    return $article ?? throw ApiException::notFound('Article not found.');
});

$router->add('POST', '/api/troubleshoot', function () {
    $body = Http::jsonBody();
    $errors = [];
    $issueText = Validator::issueText($body['issue_text'] ?? null, $errors);
    $category = Validator::optionalSlug($body['category'] ?? null, 'category', $errors);
    if ($errors) {
        throw ApiException::validation($errors);
    }
    $db = Database::connection();
    $service = new TroubleshootService(
        new ArticleRepository($db),
        new QueryRepository($db),
        new DemoAnswerGenerator(),
        OllamaAnswerGenerator::fromConfig()
    );
    return $service->handle($issueText, $category);
});

$router->add('GET', '/api/queries', function () {
    $errors = [];
    $filters = [
        'status'         => Validator::status(Http::query('status'), $errors, false),
        'category'       => Validator::optionalSlug(Http::query('category'), 'category', $errors),
        'search'         => Validator::search(Http::query('search')),
        'include_sample' => Validator::bool(Http::query('include_sample'), true),
    ];
    if ($errors) {
        throw ApiException::validation($errors);
    }
    $page = Validator::intInRange(Http::query('page'), 1, 1, 100000);
    $perPage = Validator::intInRange(Http::query('per_page'), 10, 1, 50);
    return (new QueryRepository(Database::connection()))->paginate($filters, $page, $perPage);
});

$router->add('GET', '/api/queries/{id}', function (array $params) {
    $query = (new QueryRepository(Database::connection()))->find(Validator::positiveId($params['id']));
    return $query ?? throw ApiException::notFound('Query not found.');
});

$router->add('PATCH', '/api/queries/{id}', function (array $params) {
    $id = Validator::positiveId($params['id']);
    $body = Http::jsonBody();
    $errors = [];
    $status = Validator::status($body['status'] ?? null, $errors);
    $note = Validator::optionalNote($body['note'] ?? null, $errors);
    if ($errors) {
        throw ApiException::validation($errors);
    }
    $repository = new QueryRepository(Database::connection());
    if (!$repository->updateStatus($id, $status, $note)) {
        throw ApiException::notFound('Query not found.');
    }
    return $repository->find($id);
});

$router->add('GET', '/api/analytics', function () {
    $includeSample = Validator::bool(Http::query('include_sample'), true);
    $days = Validator::intInRange(Http::query('days'), 14, 7, 90);
    return (new AnalyticsRepository(Database::connection()))->summary($includeSample, $days);
});

$router->dispatch($method, Router::currentPath());
