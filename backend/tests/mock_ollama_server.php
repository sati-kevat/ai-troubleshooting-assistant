<?php

declare(strict_types=1);

/**
 * Local MOCK of the Ollama chat API for testing without installing Ollama
 * or downloading a model. It checks that requests have the shape Ollama
 * expects, records each request, and replies according to a scenario so
 * every success and failure path of the backend can be tested.
 *
 * Start:  C:\xampp\php\php.exe -S 127.0.0.1:8098 backend\tests\mock_ollama_server.php
 * Then set AI_PROVIDER=ollama and OLLAMA_BASE_URL=http://127.0.0.1:8098
 *
 * Scenario is read from backend/tests/.mock_state/scenario (default "valid"):
 *   valid, partial_invalid, invalid_refs, not_applicable, length, bad_json,
 *   wrong_shape, model_missing, http_500, slow
 */

$stateDir = __DIR__ . '/.mock_state';
if (!is_dir($stateDir)) {
    mkdir($stateDir, 0777, true);
}

function reply(int $status, array $body): never
{
    http_response_code($status);
    header('Content-Type: application/json');
    echo json_encode($body, JSON_UNESCAPED_SLASHES);
    exit;
}

function chat(string $model, string $content, string $doneReason = 'stop'): never
{
    reply(200, [
        'model'             => $model,
        'created_at'        => gmdate('Y-m-d\TH:i:s\Z'),
        'message'           => ['role' => 'assistant', 'content' => $content],
        'done'              => true,
        'done_reason'       => $doneReason,
        'total_duration'    => 4200000000,
        'prompt_eval_count' => 1800,
        'eval_count'        => 210,
    ]);
}

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
if ($path === '/api/version') {
    reply(200, ['version' => 'mock']);
}
if ($path !== '/api/chat' || ($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    reply(404, ['error' => 'mock only implements POST /api/chat']);
}

$raw = file_get_contents('php://input');
$body = json_decode((string) $raw, true);

// Record the request for test assertions.
$count = (int) @file_get_contents("$stateDir/count") + 1;
file_put_contents("$stateDir/count", (string) $count);
file_put_contents("$stateDir/last_request.json", json_encode($body, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

// Request-shape checks mirroring what the backend must send.
if (!is_array($body) || !isset($body['model'], $body['messages']) || !is_array($body['messages'])) {
    reply(400, ['error' => 'model and messages are required']);
}
if (($body['stream'] ?? true) !== false) {
    reply(400, ['error' => 'mock expects stream=false']);
}
if (($body['format']['type'] ?? null) !== 'object') {
    reply(400, ['error' => 'mock expects a JSON schema in "format"']);
}

$model = (string) $body['model'];
$scenario = trim((string) @file_get_contents("$stateDir/scenario")) ?: 'valid';
$userText = '';
foreach ($body['messages'] as $message) {
    if (($message['role'] ?? '') === 'user') {
        $userText = (string) $message['content'];
    }
}
preg_match_all('/<article code="(KB-\d+)">/', $userText, $found);
$first = $found[1][0] ?? 'KB-000';

$validOutput = [
    'sources_apply'          => true,
    'summary'                => "Mock summary: the issue matches $first.",
    'causes'                 => [['source' => $first, 'item_number' => 1]],
    'steps'                  => [['source' => $first, 'item_number' => 1], ['source' => $first, 'item_number' => 2], ['source' => $first, 'item_number' => 3]],
    'next_action'            => "Mock next action: follow the selected steps from $first.",
    'escalation_recommended' => false,
    'escalation_reason'      => '',
    'uncertainty_note'       => 'Mock note: confirm the transaction reference first.',
];

switch ($scenario) {
    case 'valid':
        chat($model, json_encode($validOutput));
    case 'partial_invalid':
        $output = $validOutput;
        $output['steps'] = [
            ['source' => 'KB-999', 'item_number' => 1],   // article that was never sent
            ['source' => $first, 'item_number' => 99],    // item that does not exist
            ['source' => $first, 'item_number' => 2],     // valid
            ['source' => $first, 'item_number' => 2],     // duplicate
        ];
        $output['causes'] = [['source' => 'KB-999', 'item_number' => 1]];
        chat($model, json_encode($output));
    case 'invalid_refs':
        $output = $validOutput;
        $output['steps'] = [['source' => 'KB-999', 'item_number' => 1], ['source' => $first, 'item_number' => 0]];
        chat($model, json_encode($output));
    case 'not_applicable':
        chat($model, json_encode([
            'sources_apply' => false, 'summary' => 'Mock summary: the articles do not cover this issue.',
            'causes' => [], 'steps' => [], 'next_action' => 'Mock: escalate to L2.',
            'escalation_recommended' => true, 'escalation_reason' => 'Mock: not covered by the knowledge base.', 'uncertainty_note' => '',
        ]));
    case 'length':
        chat($model, substr(json_encode($validOutput), 0, 60), 'length');
    case 'bad_json':
        chat($model, 'Sure! Here are the steps: restart the terminal.');
    case 'wrong_shape':
        chat($model, json_encode(['summary' => 'Missing every other field']));
    case 'model_missing':
        reply(404, ['error' => "model \"$model\" not found, try pulling it first"]);
    case 'http_500':
        reply(500, ['error' => 'mock internal error']);
    case 'slow':
        sleep(8);
        chat($model, json_encode($validOutput));
    default:
        reply(400, ['error' => "unknown mock scenario: $scenario"]);
}
