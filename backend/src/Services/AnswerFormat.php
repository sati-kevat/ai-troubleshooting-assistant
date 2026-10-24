<?php

declare(strict_types=1);

/**
 * Shared answer pieces used by both demo mode and AI mode, so the response
 * shape is identical regardless of how the answer was produced.
 */
final class AnswerFormat
{
    /** Generic details an L1 agent should capture before escalating. This is a checklist, not a fix. */
    public const INFORMATION_TO_COLLECT = [
        'Merchant ID and terminal ID (or API key ID for online integrations)',
        'Exact error message or response code, with a screenshot if possible',
        'Date and time of the first occurrence and how often it happens',
        'Transaction references (RRN, UTR or order ID) for affected payments',
        'Number of terminals, users or transactions affected',
        'Steps already tried and their results',
    ];

    /** Answer used when no knowledge-base article supports a solution. */
    public static function unsupported(): array
    {
        return [
            'summary'                => 'No knowledge-base article matches this issue closely enough to suggest a reliable solution.',
            'possible_causes'        => [],
            'steps'                  => [],
            'next_action'            => 'Do not attempt an unverified fix. Collect the details below and escalate the issue to L2 Support.',
            'escalation'             => [
                'recommended' => true,
                'reason'      => 'The knowledge base does not cover this issue, so no grounded troubleshooting steps are available.',
                'escalate_to' => 'L2 Support (general queue)',
                'conditions'  => [],
            ],
            'uncertainty'            => self::uncertaintyMessage('none', false),
            'information_to_collect' => self::INFORMATION_TO_COLLECT,
        ];
    }

    public static function uncertaintyMessage(string $confidence, bool $ambiguous): array
    {
        $message = match ($confidence) {
            'high'   => 'Strong match with a knowledge-base article. Still confirm each step result with the merchant.',
            'medium' => 'Partial match. Confirm the symptoms match the cited article before following its steps.',
            'low'    => 'Weak match. The cited article may not apply to this issue; escalation is recommended.',
            default  => 'Unsupported: no relevant knowledge-base article was found.',
        };
        if ($ambiguous) {
            $message .= ' Several articles from different categories matched similarly; check the other sources too.';
        }
        return ['level' => $confidence, 'message' => $message];
    }
}
