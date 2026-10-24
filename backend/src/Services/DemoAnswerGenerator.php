<?php

declare(strict_types=1);

/**
 * Demo mode: builds the answer directly from the retrieved knowledge-base
 * articles using fixed templates. No AI model is called. Every cause, step
 * and escalation condition is copied from an article and tagged with its
 * source code, so nothing is invented.
 */
final class DemoAnswerGenerator
{
    public function generate(string $issueText, array $retrieval): array
    {
        $confidence = $retrieval['confidence'];
        $primary = $retrieval['matches'][0] ?? null;

        if ($primary === null || $confidence === 'none') {
            return AnswerFormat::unsupported();
        }

        $article = $primary['article'];
        $code = $article['code'];
        $tag = fn (array $items) => array_map(fn ($text) => ['text' => $text, 'source' => $code], $items);

        // A weak match is not reliable enough to present the article's causes
        // and steps as a solution; point to it for review and escalate instead.
        if ($confidence === 'low') {
            return [
                'summary'                => sprintf(
                    'No article clearly matches this issue. The closest article, %s "%s", only shares the terms: %s.',
                    $code,
                    $article['title'],
                    implode(', ', array_slice($primary['matched_terms'], 0, 6))
                ),
                'possible_causes'        => [],
                'steps'                  => [],
                'next_action'            => sprintf(
                    'Do not apply steps from a weakly matching article. Review %s only if the symptoms clearly match; otherwise collect the details below and escalate to %s.',
                    $code,
                    $article['escalate_to']
                ),
                'escalation'             => [
                    'recommended' => true,
                    'reason'      => 'The knowledge base only weakly matches this issue, so no reliable troubleshooting steps can be given.',
                    'escalate_to' => $article['escalate_to'],
                    'conditions'  => [],
                ],
                'uncertainty'            => AnswerFormat::uncertaintyMessage($confidence, $retrieval['ambiguous']),
                'information_to_collect' => AnswerFormat::INFORMATION_TO_COLLECT,
            ];
        }

        $summary = sprintf(
            'The reported issue matches %s "%s" (%s), based on the terms: %s.',
            $code,
            $article['title'],
            $article['category']['name'],
            implode(', ', array_slice($primary['matched_terms'], 0, 6))
        );

        $nextAction = $confidence === 'high'
            ? sprintf('Follow the steps from %s in order and confirm the result with the merchant. If the issue continues, escalate to %s.', $code, $article['escalate_to'])
            : sprintf('First confirm the symptoms match %s. If they do, follow its steps; if not, collect the details below and escalate to %s.', $code, $article['escalate_to']);

        return [
            'summary'                => $summary,
            'possible_causes'        => $tag($article['causes']),
            'steps'                  => $tag($article['steps']),
            'next_action'            => $nextAction,
            'escalation'             => [
                'recommended' => false,
                'reason'      => sprintf('Escalate if any of the conditions from %s apply.', $code),
                'escalate_to' => $article['escalate_to'],
                'conditions'  => $tag($article['escalation_conditions']),
            ],
            'uncertainty'            => AnswerFormat::uncertaintyMessage($confidence, $retrieval['ambiguous']),
            'information_to_collect' => AnswerFormat::INFORMATION_TO_COLLECT,
        ];
    }
}
