<?php

declare(strict_types=1);

/**
 * Knowledge-base retrieval test. Reads the articles from MySQL and checks
 * that known issue descriptions match the expected article and confidence.
 * Does not write to the database.
 *
 * Run: C:\xampp_new\php\php.exe backend\tests\retrieval_test.php
 */

require_once dirname(__DIR__) . '/src/bootstrap.php';
restore_exception_handler();

$cases = [
    // [issue text, expected top article (null = no match), allowed confidence levels]
    ['Terminal shows host not reachable and no network since morning, wifi works on my phone', 'KB-001', ['high']],
    ['POS machine offline, SIM signal missing', 'KB-001', ['high', 'medium']],
    ['Card declined with response code 51 but customer says there is balance', 'KB-002', ['high']],
    ['Chip error please swipe appears for every card', 'KB-003', ['high', 'medium']],
    ['Printer prints blank receipts after I changed the paper roll', 'KB-004', ['high']],
    ['Customer paid by UPI, money debited but terminal shows pending', 'KB-005', ['high']],
    ['Settlement for yesterday not credited to bank account', 'KB-006', ['high']],
    ['Settlement report totals do not match the transaction report', 'KB-007', ['high', 'medium']],
    ['Cannot log in to merchant portal, OTP not received', 'KB-008', ['high']],
    ['API returns 401 invalid signature in production', 'KB-009', ['high']],
    ['Getting 504 gateway timeout from payment API intermittently', 'KB-010', ['high']],
    ['Webhook callback not received so orders stay pending', 'KB-011', ['high']],
    ['Terminal stuck on logo after firmware update', 'KB-012', ['high']],
    ['Battery not charging even with charger connected', 'KB-013', ['high']],
    ['Refund failed and customer has not got money back', 'KB-014', ['high', 'medium']],
    ['Customer charged twice for a single purchase', 'KB-015', ['high']],
    ['Paper jammed in printer', 'KB-004', ['high']],
    // Short or partial descriptions: a match is expected but not full confidence.
    ['card declined', 'KB-002', ['medium']],
    ['QR code not scanning', 'KB-005', ['medium', 'low']],
    ['money not received', null, ['medium', 'low']],
    ['error 403', 'KB-009', ['low']],
    // Out-of-scope or vague: must not produce a confident answer.
    ['Touch screen not responding', null, ['none', 'low']],
    ['How do I apply for annual leave in the HR system?', null, ['none']],
    ['Merchant asking about GST invoice format for monthly statement', null, ['none', 'low']],
    ['The office coffee machine is broken again', null, ['none']],
    ['terminal not working', null, ['none', 'low']],
    ['asdkjh qwerty zxcvb lorem ipsum', null, ['none']],
];

$retriever = new KnowledgeRetriever((new ArticleRepository(Database::connection()))->allActive());
$failures = 0;

foreach ($cases as [$text, $expectedCode, $allowedConfidence]) {
    $result = $retriever->retrieve($text);
    $topCode = $result['matches'][0]['article']['code'] ?? null;
    $codeOk = $expectedCode === null ? true : $topCode === $expectedCode;
    $confidenceOk = in_array($result['confidence'], $allowedConfidence, true);
    $passed = $codeOk && $confidenceOk;
    $failures += $passed ? 0 : 1;

    printf(
        "%s  %-6s %-7s score=%6.2f  top=%-7s expected=%-7s  %s\n",
        $passed ? 'PASS' : 'FAIL',
        $result['confidence'],
        $result['ambiguous'] ? 'ambig' : '',
        $result['top_score'],
        $topCode ?? '-',
        $expectedCode ?? '-',
        mb_strimwidth($text, 0, 60, '...')
    );
}

printf("\n%d of %d cases passed.\n", count($cases) - $failures, count($cases));
exit($failures === 0 ? 0 : 1);
