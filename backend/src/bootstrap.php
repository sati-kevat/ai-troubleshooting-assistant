<?php

declare(strict_types=1);

$root = dirname(__DIR__);

foreach ([
    'Config.php', 'ApiException.php', 'Logger.php', 'Http.php', 'Database.php', 'Router.php', 'Validator.php',
    'Repositories/ArticleRepository.php', 'Repositories/QueryRepository.php', 'Repositories/AnalyticsRepository.php',
    'Services/AnswerFormat.php', 'Services/KnowledgeRetriever.php', 'Services/DemoAnswerGenerator.php',
    'Services/OllamaAnswerGenerator.php', 'Services/TroubleshootService.php',
] as $file) {
    require_once __DIR__ . '/' . $file;
}

// Never show PHP errors to API clients; they go to the log instead.
ini_set('display_errors', '0');
error_reporting(E_ALL);

Config::load($root . '/.env');
Logger::init($root . '/storage/logs/app.log');
date_default_timezone_set(Config::get('APP_TIMEZONE', 'Asia/Kolkata'));

set_error_handler(function (int $severity, string $message, string $file, int $line): bool {
    if (!(error_reporting() & $severity)) {
        return false; // Suppressed with the @ operator.
    }
    throw new ErrorException($message, 0, $severity, $file, $line);
});

set_exception_handler(function (Throwable $e): void {
    if ($e instanceof ApiException) {
        Http::error($e);
    }
    Logger::error('Unhandled exception', [
        'type'    => get_class($e),
        'message' => $e->getMessage(),
        'file'    => basename($e->getFile()) . ':' . $e->getLine(),
    ]);
    Http::error(new ApiException(500, 'server_error', 'An unexpected server error occurred. Please try again.'));
});
