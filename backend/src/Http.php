<?php

declare(strict_types=1);

/**
 * Small helpers for JSON responses, CORS and request parsing.
 */
final class Http
{
    private const MAX_BODY_BYTES = 16384;

    public static function applyCors(array $allowedOrigins): void
    {
        $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
        if ($origin !== '' && in_array($origin, $allowedOrigins, true)) {
            header('Access-Control-Allow-Origin: ' . $origin);
            header('Access-Control-Allow-Methods: GET, POST, PATCH, OPTIONS');
            header('Access-Control-Allow-Headers: Content-Type');
            header('Access-Control-Max-Age: 600');
        }
        header('Vary: Origin');
    }

    public static function json(mixed $data, int $status = 200): never
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        header('X-Content-Type-Options: nosniff');
        echo json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
        exit;
    }

    public static function error(ApiException $e): never
    {
        $body = ['error' => ['code' => $e->errorCode, 'message' => $e->getMessage()]];
        if ($e->fieldErrors) {
            $body['error']['fields'] = $e->fieldErrors;
        }
        self::json($body, $e->status);
    }

    /** Reads and decodes a JSON object request body. */
    public static function jsonBody(): array
    {
        $contentType = $_SERVER['CONTENT_TYPE'] ?? $_SERVER['HTTP_CONTENT_TYPE'] ?? '';
        if (stripos($contentType, 'application/json') === false) {
            throw new ApiException(415, 'unsupported_media_type', 'Request body must be JSON (Content-Type: application/json).');
        }
        $raw = file_get_contents('php://input', false, null, 0, self::MAX_BODY_BYTES + 1);
        if ($raw === false || strlen($raw) > self::MAX_BODY_BYTES) {
            throw new ApiException(413, 'payload_too_large', 'Request body is too large.');
        }
        $data = json_decode($raw, true);
        if (!is_array($data) || (array_is_list($data) && $data !== [])) {
            throw new ApiException(400, 'invalid_json', 'Request body must be a valid JSON object.');
        }
        return $data;
    }

    public static function query(string $key): ?string
    {
        $value = $_GET[$key] ?? null;
        return is_string($value) ? trim($value) : null;
    }
}
