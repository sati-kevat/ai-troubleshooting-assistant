<?php

declare(strict_types=1);

/**
 * End-to-end API test against the running backend (Apache or PHP built-in
 * server). Creates a few queries, checks responses, validation, status
 * updates and analytics, then deletes the queries it created.
 *
 * Run: C:\xampp_new\php\php.exe backend\tests\api_test.php [base-url]
 * Default base URL: http://localhost/pinelabs-api
 */

require_once dirname(__DIR__) . '/src/bootstrap.php';
restore_exception_handler();
restore_error_handler();

$base = rtrim($argv[1] ?? 'http://localhost/pinelabs-api', '/');
$db = Database::connection();
$createdIds = [];
$passed = 0;
$failed = 0;

function request(string $method, string $url, ?string $body = null, array $headers = []): array
{
    $curl = curl_init($url);
    $responseHeaders = [];
    curl_setopt_array($curl, [
        CURLOPT_CUSTOMREQUEST  => $method,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_HEADERFUNCTION => function ($curl, $line) use (&$responseHeaders) {
            if (str_contains($line, ':')) {
                [$name, $value] = explode(':', $line, 2);
                $responseHeaders[strtolower(trim($name))] = trim($value);
            }
            return strlen($line);
        },
    ]);
    if ($body !== null) {
        curl_setopt($curl, CURLOPT_POSTFIELDS, $body);
    }
    $raw = curl_exec($curl);
    $status = curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
    curl_close($curl);
    return ['status' => $status, 'json' => json_decode((string) $raw, true), 'raw' => $raw, 'headers' => $responseHeaders];
}

function postJson(string $url, mixed $data, string $method = 'POST'): array
{
    return request($method, $url, json_encode($data), ['Content-Type: application/json']);
}

function check(string $name, bool $condition, string $detail = ''): void
{
    global $passed, $failed;
    $condition ? $passed++ : $failed++;
    printf("%s  %s%s\n", $condition ? 'PASS' : 'FAIL', $name, !$condition && $detail ? "  -> $detail" : '');
}

// --- Health ---------------------------------------------------------------
$r = request('GET', "$base/api/health");
check('GET /api/health returns ok with database connected', $r['status'] === 200 && ($r['json']['database'] ?? '') === 'connected', (string) $r['raw']);

// --- Troubleshoot: supported issue ---------------------------------------
$r = postJson("$base/api/troubleshoot", ['issue_text' => 'Customer paid by UPI, money debited but the terminal shows pending']);
$createdIds[] = $r['json']['query_id'] ?? null;
check('POST /api/troubleshoot (UPI issue) returns 200', $r['status'] === 200, (string) $r['raw']);
check('  top source is KB-005', ($r['json']['sources'][0]['code'] ?? '') === 'KB-005');
check('  confidence is high and no escalation recommended', ($r['json']['confidence'] ?? '') === 'high' && ($r['json']['answer']['escalation']['recommended'] ?? true) === false);
check('  mode is demo and a demo notice is present', ($r['json']['mode'] ?? '') === 'demo' && !empty($r['json']['notice']));
check('  steps exist and every step cites a source', !empty($r['json']['answer']['steps']) && array_reduce($r['json']['answer']['steps'], fn ($ok, $s) => $ok && !empty($s['source']), true));
check('  status starts as open (not resolved)', ($r['json']['status'] ?? '') === 'open');
check('  category detected as upi-qr', ($r['json']['category'] ?? '') === 'upi-qr' && ($r['json']['category_source'] ?? '') === 'detected');

// --- Troubleshoot: unsupported issue -> escalation -------------------------
$r = postJson("$base/api/troubleshoot", ['issue_text' => 'How do I apply for annual leave in the HR system?']);
$createdIds[] = $r['json']['query_id'] ?? null;
check('POST /api/troubleshoot (out of scope) returns 200', $r['status'] === 200, (string) $r['raw']);
check('  confidence none, no sources, no steps', ($r['json']['confidence'] ?? '') === 'none' && ($r['json']['sources'] ?? null) === [] && ($r['json']['answer']['steps'] ?? null) === []);
check('  escalation recommended', ($r['json']['answer']['escalation']['recommended'] ?? false) === true);
check('  category is not guessed', array_key_exists('category', $r['json'] ?? []) && $r['json']['category'] === null);

// --- Troubleshoot: weak match -> escalation ---------------------------------
$r = postJson("$base/api/troubleshoot", ['issue_text' => 'getting error 403']);
$createdIds[] = $r['json']['query_id'] ?? null;
check('POST /api/troubleshoot (weak match) recommends escalation', ($r['json']['confidence'] ?? '') === 'low' && ($r['json']['answer']['escalation']['recommended'] ?? false) === true, (string) $r['raw']);
check('  weak match presents no steps or causes as a solution', ($r['json']['answer']['steps'] ?? null) === [] && ($r['json']['answer']['possible_causes'] ?? null) === []);
check('  weak match still lists the closest article for review', ($r['json']['sources'][0]['code'] ?? '') === 'KB-009');
check('  weak match does not auto-assign a category', array_key_exists('category', $r['json'] ?? []) && $r['json']['category'] === null);

// --- Troubleshoot: user-selected category ---------------------------------
$r = postJson("$base/api/troubleshoot", ['issue_text' => 'Printer prints blank receipts', 'category' => 'pos-hardware']);
$createdIds[] = $r['json']['query_id'] ?? null;
check('POST /api/troubleshoot with category keeps user category', ($r['json']['category'] ?? '') === 'pos-hardware' && ($r['json']['category_source'] ?? '') === 'user', (string) $r['raw']);

// --- Validation -------------------------------------------------------------
$r = postJson("$base/api/troubleshoot", ['issue_text' => '']);
check('Empty issue -> 422 with field error', $r['status'] === 422 && isset($r['json']['error']['fields']['issue_text']));
$r = postJson("$base/api/troubleshoot", ['issue_text' => 'short']);
check('Too-short issue -> 422', $r['status'] === 422);
$r = postJson("$base/api/troubleshoot", ['issue_text' => str_repeat('printer jam ', 200)]);
check('Too-long issue (2400 chars) -> 422', $r['status'] === 422);
$r = postJson("$base/api/troubleshoot", ['issue_text' => '1234567890 12345']);
check('Issue without words -> 422', $r['status'] === 422);
$r = postJson("$base/api/troubleshoot", ['issue_text' => 123456789012]);
check('Non-string issue -> 422', $r['status'] === 422);
$r = postJson("$base/api/troubleshoot", ['issue_text' => 'Printer prints blank receipts', 'category' => 'not-a-category']);
check('Unknown category -> 422', $r['status'] === 422);
$r = postJson("$base/api/troubleshoot", ['issue_text' => 'Printer prints blank receipts', 'category' => "x'; DROP TABLE support_queries;--"]);
check('Malformed category (SQL-like) -> 422', $r['status'] === 422);
$r = request('POST', "$base/api/troubleshoot", '{"issue_text": "broken json', ['Content-Type: application/json']);
check('Invalid JSON -> 400', $r['status'] === 400);
$r = request('POST', "$base/api/troubleshoot", 'issue_text=hello+world+printer', ['Content-Type: application/x-www-form-urlencoded']);
check('Wrong content type -> 415', $r['status'] === 415);
$r = request('DELETE', "$base/api/troubleshoot");
check('Wrong method -> 405', $r['status'] === 405);

// --- Query history and status updates --------------------------------------
$firstId = $createdIds[0];
$r = request('GET', "$base/api/queries/$firstId");
check('GET /api/queries/{id} returns stored response with sources', $r['status'] === 200 && ($r['json']['response']['sources'][0]['code'] ?? '') === 'KB-005');
$r = postJson("$base/api/queries/$firstId", ['status' => 'resolved', 'note' => 'Payment confirmed via UTR'], 'PATCH');
check('PATCH status resolved -> 200 and saved', $r['status'] === 200 && ($r['json']['status'] ?? '') === 'resolved' && ($r['json']['status_note'] ?? '') === 'Payment confirmed via UTR', (string) $r['raw']);
$r = postJson("$base/api/queries/{$createdIds[1]}", ['status' => 'escalated'], 'PATCH');
check('PATCH status escalated -> 200', $r['status'] === 200 && ($r['json']['status'] ?? '') === 'escalated');
$r = postJson("$base/api/queries/$firstId", ['status' => 'done'], 'PATCH');
check('PATCH invalid status -> 422', $r['status'] === 422);
$r = postJson("$base/api/queries/99999999", ['status' => 'resolved'], 'PATCH');
check('PATCH unknown id -> 404', $r['status'] === 404);
$r = request('GET', "$base/api/queries/abc");
check('GET non-numeric id -> 404', $r['status'] === 404);

$r = request('GET', "$base/api/queries?status=resolved&per_page=50&include_sample=0");
$ids = array_column($r['json']['items'] ?? [], 'id');
check('GET /api/queries?status=resolved includes the resolved query', $r['status'] === 200 && in_array($firstId, $ids, true));
check('  all returned rows have status resolved and are not samples', array_reduce($r['json']['items'] ?? [], fn ($ok, $q) => $ok && $q['status'] === 'resolved' && !$q['is_sample'], true));
$r = request('GET', "$base/api/queries?search=" . urlencode('annual leave'));
check('GET /api/queries?search= finds the matching query', in_array($createdIds[1], array_column($r['json']['items'] ?? [], 'id'), true));
$r = request('GET', "$base/api/queries?status=bogus");
check('GET /api/queries?status=bogus -> 422', $r['status'] === 422);

// --- Analytics vs. direct SQL ----------------------------------------------
foreach ([true, false] as $includeSample) {
    $r = request('GET', "$base/api/analytics?include_sample=" . ($includeSample ? '1' : '0'));
    $filter = $includeSample ? '' : 'WHERE is_sample = 0';
    $expected = $db->query("SELECT COUNT(*) total, COALESCE(SUM(status='resolved'),0) resolved, COALESCE(SUM(status='unresolved'),0) unresolved,
                                   COALESCE(SUM(status='escalated'),0) escalated, COALESCE(SUM(status='open'),0) open
                            FROM support_queries $filter")->fetch();
    $totals = $r['json']['totals'] ?? [];
    $label = $includeSample ? 'with samples' : 'without samples';
    $matches = true;
    foreach (['total', 'resolved', 'unresolved', 'escalated', 'open'] as $key) {
        $matches = $matches && (int) $expected[$key] === ($totals[$key] ?? -1);
    }
    check("Analytics totals match SQL ($label)", $r['status'] === 200 && $matches, json_encode([$expected, $totals]));
    $categorySum = array_sum(array_column($r['json']['by_category'] ?? [], 'total'));
    check("  category counts add up to total ($label)", $categorySum === ($totals['total'] ?? -1), "$categorySum vs " . ($totals['total'] ?? '?'));
    $dailySum = array_sum(array_column($r['json']['daily'] ?? [], 'total'));
    $expectedDaily = (int) $db->query("SELECT COUNT(*) FROM support_queries WHERE created_at >= CURDATE() - INTERVAL 13 DAY" . ($includeSample ? '' : ' AND is_sample = 0'))->fetchColumn();
    check("  14-day activity matches SQL ($label)", count($r['json']['daily'] ?? []) === 14 && $dailySum === $expectedDaily, "$dailySum vs $expectedDaily");
}

// --- CORS --------------------------------------------------------------------
$r = request('OPTIONS', "$base/api/troubleshoot", null, ['Origin: http://localhost:5173', 'Access-Control-Request-Method: POST']);
check('CORS preflight from Vite origin allowed', $r['status'] === 204 && ($r['headers']['access-control-allow-origin'] ?? '') === 'http://localhost:5173');
$r = request('GET', "$base/api/health", null, ['Origin: http://evil.example']);
check('CORS header not sent to unknown origin', !isset($r['headers']['access-control-allow-origin']));

// --- Clean up ----------------------------------------------------------------
$createdIds = array_values(array_filter($createdIds));
if ($createdIds) {
    $placeholders = implode(',', array_fill(0, count($createdIds), '?'));
    $db->prepare("DELETE FROM support_queries WHERE id IN ($placeholders)")->execute($createdIds);
    printf("\nCleaned up %d test queries.\n", count($createdIds));
}

printf("%d passed, %d failed.\n", $passed, $failed);
exit($failed === 0 ? 0 : 1);
