<?php

declare(strict_types=1);

/**
 * Finds the knowledge-base articles most relevant to an issue description.
 *
 * Method (weighted keyword matching, chosen because it is transparent and
 * needs no extra services):
 *  1. Normalise text: lowercase, remove punctuation, drop stop words and
 *     apply light suffix stemming ("printing", "printed" -> "print").
 *  2. For every query term found in an article, add the weight of the most
 *     important field it appears in (keywords > title > symptoms > causes)
 *     multiplied by an IDF factor, so rare terms count more than terms that
 *     appear in many articles (e.g. "terminal").
 *  3. Add a bonus for multi-word keyword phrases ("charged twice").
 *  4. Convert the best score into a confidence level. Low or no confidence
 *     means the knowledge base does not reliably cover the issue and the
 *     answer must recommend escalation.
 */
final class KnowledgeRetriever
{
    private const FIELD_WEIGHTS = ['keywords' => 3.0, 'title' => 2.5, 'symptoms' => 2.0, 'causes' => 1.0, 'category' => 1.0];
    private const PHRASE_BONUS = 1.5;
    private const CATEGORY_HINT_BOOST = 1.15;
    private const MAX_SOURCES = 3;

    // Confidence thresholds on the best article score (tuned on the sample KB,
    // see backend/tests/retrieval_test.php).
    public const MIN_SCORE = 6.0;
    public const MEDIUM_SCORE = 12.0;
    public const HIGH_SCORE = 18.0;
    // If the runner-up is this close to the best match, the result is ambiguous.
    private const AMBIGUITY_RATIO = 0.85;

    private const STOP_WORDS = [
        'a', 'an', 'the', 'and', 'or', 'but', 'if', 'then', 'is', 'are', 'was', 'were', 'be', 'been', 'being',
        'am', 'do', 'does', 'did', 'doing', 'have', 'has', 'had', 'having', 'i', 'me', 'my', 'we', 'our', 'you',
        'your', 'he', 'she', 'it', 'its', 'they', 'them', 'their', 'this', 'that', 'these', 'those', 'of', 'to',
        'in', 'on', 'at', 'by', 'for', 'with', 'from', 'as', 'into', 'about', 'after', 'before', 'since', 'so',
        'than', 'too', 'very', 'can', 'could', 'should', 'would', 'will', 'just', 'also', 'any', 'some', 'all',
        'there', 'here', 'what', 'when', 'where', 'which', 'who', 'why', 'how', 'please', 'help', 'issue',
        'issues', 'problem', 'problems', 'customer', 'merchant', 'user', 'getting', 'get', 'got', 'showing', 'shows', 'show', 'says',
        'say', 'still', 'again', 'even', 'today', 'yesterday', 'now', 'one', 'out', 'up', 'via', 'per', 'while',
        'working', 'work', 'works', 'happening', 'happen', 'happens', 'facing', 'face', 'tried', 'try', 'need',
        'want', 'unable', 'cannot', 'cant', 'dont', 'doesnt', 'didnt', 'isnt', 'wont', 'im', 'ive', 'hi', 'hello',
    ];

    // Too generic to count as evidence on their own, but kept for phrase
    // matching ("not received", "response code").
    private const NEUTRAL_TOKENS = ['not', 'no', 'error', 'code', 'fail'];

    private array $articles;
    private array $index = [];
    private array $idf = [];

    public function __construct(array $articles)
    {
        $this->articles = $articles;
        $this->buildIndex();
    }

    /**
     * @return array{confidence:string, top_score:float, ambiguous:bool, matches:array}
     */
    public function retrieve(string $issueText, ?string $categoryHint = null): array
    {
        $queryTokens = self::tokenize($issueText);
        $uniqueTokens = array_diff(array_unique($queryTokens), self::NEUTRAL_TOKENS);
        $queryString = ' ' . implode(' ', $queryTokens) . ' ';
        $originalWords = self::originalWords($issueText);

        $results = [];
        foreach ($this->index as $position => $entry) {
            $score = 0.0;
            $matched = [];
            $termCount = 0;
            foreach ($uniqueTokens as $token) {
                $weight = 0.0;
                foreach (self::FIELD_WEIGHTS as $field => $fieldWeight) {
                    if (isset($entry['fields'][$field][$token])) {
                        $weight = max($weight, $fieldWeight);
                    }
                }
                if ($weight > 0) {
                    $score += $weight * $this->idf[$token];
                    $matched[] = $originalWords[$token] ?? $token;
                    $termCount++;
                }
            }
            foreach ($entry['phrases'] as $phrase => $label) {
                if (str_contains($queryString, ' ' . $phrase . ' ')) {
                    $score += self::PHRASE_BONUS * substr_count($phrase, ' ') + self::PHRASE_BONUS;
                    $matched[] = $label;
                }
            }
            if ($score <= 0) {
                continue;
            }
            $article = $this->articles[$position];
            if ($categoryHint !== null && $article['category']['slug'] === $categoryHint) {
                $score *= self::CATEGORY_HINT_BOOST;
            }
            $results[] = [
                'article'       => $article,
                'score'         => round($score, 2),
                'term_count'    => $termCount,
                'matched_terms' => array_values(array_unique($matched)),
            ];
        }

        usort($results, fn ($a, $b) => $b['score'] <=> $a['score'] ?: strcmp($a['article']['code'], $b['article']['code']));

        $topScore = $results[0]['score'] ?? 0.0;
        $secondScore = $results[1]['score'] ?? 0.0;
        $ambiguous = $topScore > 0
            && $secondScore >= $topScore * self::AMBIGUITY_RATIO
            && $results[0]['article']['category']['slug'] !== $results[1]['article']['category']['slug'];

        $matches = array_values(array_filter($results, fn ($r) => $r['score'] >= self::MIN_SCORE));

        return [
            'confidence' => self::confidence($topScore, $results[0]['term_count'] ?? 0, $ambiguous),
            'top_score'  => $topScore,
            'ambiguous'  => $ambiguous,
            'matches'    => array_slice($matches, 0, self::MAX_SOURCES),
        ];
    }

    /**
     * none:   best score below MIN_SCORE - the KB does not cover the issue
     * low:    weak evidence (low score or only one matching term)
     * medium: reasonable score and at least 2 matching terms
     * high:   strong score, at least 3 matching terms and no close match
     *         from a different category
     */
    private static function confidence(float $score, int $termCount, bool $ambiguous): string
    {
        if ($score < self::MIN_SCORE) {
            return 'none';
        }
        if ($score < self::MEDIUM_SCORE || $termCount < 2) {
            return 'low';
        }
        if ($score >= self::HIGH_SCORE && $termCount >= 3 && !$ambiguous) {
            return 'high';
        }
        return 'medium';
    }

    private function buildIndex(): void
    {
        $documentFrequency = [];
        foreach ($this->articles as $position => $article) {
            $keywordText = implode(' ', $article['keywords']);
            $fields = [
                'keywords' => array_flip(self::tokenize($keywordText)),
                'title'    => array_flip(self::tokenize($article['title'])),
                'symptoms' => array_flip(self::tokenize(implode(' ', $article['symptoms']))),
                'causes'   => array_flip(self::tokenize(implode(' ', $article['causes']))),
                'category' => array_flip(self::tokenize($article['category']['name'])),
            ];
            $phrases = [];
            foreach ($article['keywords'] as $keyword) {
                $tokens = self::tokenize($keyword);
                if (count($tokens) > 1) {
                    $phrases[implode(' ', $tokens)] = strtolower($keyword);
                }
            }
            $this->index[$position] = ['fields' => $fields, 'phrases' => $phrases];

            // Array union (not array_merge) so numeric tokens such as "401" keep their keys.
            $allTokens = array_keys($fields['keywords'] + $fields['title'] + $fields['symptoms'] + $fields['causes'] + $fields['category']);
            foreach ($allTokens as $token) {
                $documentFrequency[$token] = ($documentFrequency[$token] ?? 0) + 1;
            }
        }

        $total = max(1, count($this->articles));
        foreach ($documentFrequency as $token => $frequency) {
            $this->idf[$token] = log(1 + $total / $frequency);
        }
    }

    /** @return string[] normalised, stemmed tokens in their original order */
    public static function tokenize(string $text): array
    {
        $text = mb_strtolower($text);
        $text = str_replace(['wi-fi', 'e-mail', 'log-in', 'sign-in', 'charge slip', "'"], ['wifi', 'email', 'login', 'signin', 'chargeslip', ''], $text);
        $words = preg_split('/[^\p{L}\p{N}]+/u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        $tokens = [];
        foreach ($words as $word) {
            if (in_array($word, self::STOP_WORDS, true) || (mb_strlen($word) < 2 && !ctype_digit($word))) {
                continue;
            }
            $tokens[] = self::stem($word);
        }
        return $tokens;
    }

    /** Maps each stemmed token to the first original word that produced it (for display). */
    private static function originalWords(string $text): array
    {
        $map = [];
        $words = preg_split('/[^\p{L}\p{N}]+/u', mb_strtolower($text), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        foreach ($words as $word) {
            $stem = self::stem($word);
            $map[$stem] ??= $word;
        }
        return $map;
    }

    private static function stem(string $word): string
    {
        if (ctype_digit($word) || mb_strlen($word) <= 3) {
            return $word;
        }
        foreach (['ies' => 'y', 'ing' => '', 'ed' => '', 'es' => '', 's' => ''] as $suffix => $replacement) {
            if (str_ends_with($word, $suffix) && mb_strlen($word) - strlen($suffix) >= 3) {
                if ($suffix === 's' && preg_match('/(ss|us|is)$/', $word)) {
                    break;
                }
                $word = substr($word, 0, -strlen($suffix)) . $replacement;
                // "scanning" -> "scann" -> "scan", "jammed" -> "jamm" -> "jam"
                if (($suffix === 'ing' || $suffix === 'ed') && preg_match('/([b-df-hj-np-tv-z])\1$/', $word) && !preg_match('/(ll|ss|zz)$/', $word)) {
                    $word = substr($word, 0, -1);
                }
                break;
            }
        }
        if (mb_strlen($word) > 4 && str_ends_with($word, 'e')) {
            $word = substr($word, 0, -1);
        }
        return $word;
    }
}
