<?php

declare(strict_types=1);

/**
 * Main flow: retrieve relevant articles -> generate a grounded answer ->
 * store the query and its sources -> return the answer with citations.
 *
 * Answer generation:
 *  - AI mode (AI_PROVIDER=ollama, a local model) is used only when retrieval found
 *    a medium- or high-confidence article; otherwise there is nothing reliable
 *    to ground an AI answer in, so the escalation answer is used directly.
 *  - If the AI call fails for any reason, the template (demo) answer is used
 *    and the response says why. A failed call is never shown as AI output.
 */
final class TroubleshootService
{
    public function __construct(
        private ArticleRepository $articles,
        private QueryRepository $queries,
        private DemoAnswerGenerator $demoGenerator,
        private ?OllamaAnswerGenerator $aiGenerator = null
    ) {
    }

    public function handle(string $issueText, ?string $categorySlug): array
    {
        $categoryId = $categorySlug !== null ? $this->articles->categoryIdBySlug($categorySlug) : null;
        if ($categorySlug !== null && $categoryId === null) {
            throw ApiException::validation(['category' => 'Unknown category.']);
        }

        $retriever = new KnowledgeRetriever($this->articles->allActive());
        $retrieval = $retriever->retrieve($issueText, $categorySlug);
        $confidence = $retrieval['confidence'];

        [$answer, $mode, $model, $notice, $aiFallbackReason, $confidence] = $this->generateAnswer($issueText, $retrieval);

        // Category: the agent's choice wins; otherwise use the best match only when it is
        // reliable (medium/high), so weak guesses do not distort the category analytics.
        $primary = $retrieval['matches'][0] ?? null;
        $category = null;
        $categorySource = 'none';
        if ($categorySlug !== null) {
            $category = $categorySlug;
            $categorySource = 'user';
        } elseif ($primary !== null && in_array($confidence, ['high', 'medium'], true)) {
            $category = $primary['article']['category']['slug'];
            $categorySource = 'detected';
            $categoryId = $this->articles->categoryIdBySlug($category);
        }

        $sources = array_map(fn ($match) => [
            'id'            => $match['article']['id'],
            'code'          => $match['article']['code'],
            'title'         => $match['article']['title'],
            'category'      => $match['article']['category'],
            'escalate_to'   => $match['article']['escalate_to'],
            'score'         => $match['score'],
            'matched_terms' => $match['matched_terms'],
            'is_fictional'  => $match['article']['is_fictional'],
        ], $retrieval['matches']);

        $response = [
            'mode'               => $mode,
            'model'              => $model,
            'ai_configured'      => $this->aiGenerator !== null,
            'ai_fallback_reason' => $aiFallbackReason,
            'confidence'         => $confidence,
            'top_score'          => $retrieval['top_score'],
            'category'           => $category,
            'category_source'    => $categorySource,
            'issue_text'         => $issueText,
            'answer'             => $answer,
            'sources'            => $sources,
            'notice'             => $notice,
        ];

        // The query, its full response and its cited sources are saved in one transaction.
        $queryId = $this->queries->create([
            'issue_text'             => $issueText,
            'category_id'            => $categoryId,
            'category_source'        => $categorySource,
            'summary'                => $answer['summary'],
            'confidence'             => $confidence,
            'top_score'              => $retrieval['top_score'],
            'answer_mode'            => $mode,
            'ai_model'               => $model,
            'response'               => $response,
            'escalation_recommended' => $answer['escalation']['recommended'],
        ], $sources);

        return ['query_id' => $queryId, 'status' => 'open', 'created_at' => date(DATE_ATOM)] + $response;
    }

    /** @return array{0: array, 1: string, 2: ?string, 3: string, 4: ?string, 5: string} */
    private function generateAnswer(string $issueText, array $retrieval): array
    {
        $confidence = $retrieval['confidence'];
        $templateNotice = 'Demo mode: no AI model was called. This answer was assembled from the matched knowledge-base articles using fixed templates.';

        if ($this->aiGenerator === null) {
            return [$this->demoGenerator->generate($issueText, $retrieval), 'demo', null, $templateNotice, null, $confidence];
        }

        if (!in_array($confidence, ['high', 'medium'], true)) {
            return [
                $this->demoGenerator->generate($issueText, $retrieval),
                'demo',
                null,
                'No AI call was made: no knowledge-base article matched reliably, so there is nothing to ground an AI answer in. This escalation guidance comes from fixed templates.',
                null,
                $confidence,
            ];
        }

        try {
            $result = $this->aiGenerator->generate($issueText, $retrieval['matches'], $confidence, $retrieval['ambiguous']);
            return [
                $result['answer'],
                'ai',
                $result['model'],
                'AI-generated summary and step selection by a local Ollama model. Every cause and step is quoted from the cited knowledge-base article; verify each result with the merchant.',
                null,
                $result['sources_apply'] ? $confidence : 'low',
            ];
        } catch (AiUnavailableException $e) {
            Logger::error('AI answer unavailable, using template answer', ['reason' => $e->getMessage()] + $e->context);
            return [
                $this->demoGenerator->generate($issueText, $retrieval),
                'demo',
                null,
                'AI unavailable: ' . $e->getMessage() . ' This answer was assembled from the matched knowledge-base articles using fixed templates.',
                $e->getMessage(),
                $confidence,
            ];
        }
    }
}
