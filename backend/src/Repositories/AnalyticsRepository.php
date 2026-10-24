<?php

declare(strict_types=1);

/**
 * Dashboard metrics. Every number is calculated from support_queries at
 * request time; nothing is cached or estimated.
 */
final class AnalyticsRepository
{
    public function __construct(private PDO $db)
    {
    }

    public function summary(bool $includeSample, int $days): array
    {
        $sampleFilter = $includeSample ? '' : 'AND q.is_sample = 0';

        $totals = $this->db->query(
            "SELECT COUNT(*) AS total,
                    COALESCE(SUM(q.status = 'open'), 0)       AS open,
                    COALESCE(SUM(q.status = 'resolved'), 0)   AS resolved,
                    COALESCE(SUM(q.status = 'unresolved'), 0) AS unresolved,
                    COALESCE(SUM(q.status = 'escalated'), 0)  AS escalated,
                    COALESCE(SUM(q.escalation_recommended = 1), 0) AS escalation_recommended,
                    COALESCE(SUM(q.answer_mode = 'demo'), 0)  AS demo_answers,
                    COALESCE(SUM(q.answer_mode = 'ai'), 0)    AS ai_answers,
                    COALESCE(SUM(q.is_sample = 1), 0)         AS sample_records
             FROM support_queries q
             WHERE 1 = 1 $sampleFilter"
        )->fetch();
        $totals = array_map('intval', $totals);

        $withOutcome = $totals['resolved'] + $totals['unresolved'] + $totals['escalated'];

        $byCategory = $this->db->query(
            "SELECT c.slug, c.name,
                    COUNT(q.id) AS total,
                    COALESCE(SUM(q.status = 'open'), 0)       AS open,
                    COALESCE(SUM(q.status = 'resolved'), 0)   AS resolved,
                    COALESCE(SUM(q.status = 'unresolved'), 0) AS unresolved,
                    COALESCE(SUM(q.status = 'escalated'), 0)  AS escalated
             FROM categories c
             LEFT JOIN support_queries q ON q.category_id = c.id $sampleFilter
             GROUP BY c.id, c.slug, c.name
             UNION ALL
             SELECT 'uncategorised', 'Uncategorised',
                    COUNT(q.id),
                    COALESCE(SUM(q.status = 'open'), 0),
                    COALESCE(SUM(q.status = 'resolved'), 0),
                    COALESCE(SUM(q.status = 'unresolved'), 0),
                    COALESCE(SUM(q.status = 'escalated'), 0)
             FROM support_queries q
             WHERE q.category_id IS NULL $sampleFilter
             ORDER BY total DESC, name"
        )->fetchAll();
        foreach ($byCategory as &$row) {
            foreach (['total', 'open', 'resolved', 'unresolved', 'escalated'] as $key) {
                $row[$key] = (int) $row[$key];
            }
        }
        unset($row);

        $byConfidence = array_fill_keys(['high', 'medium', 'low', 'none'], 0);
        foreach ($this->db->query("SELECT q.confidence, COUNT(*) AS total FROM support_queries q WHERE 1 = 1 $sampleFilter GROUP BY q.confidence") as $row) {
            $byConfidence[$row['confidence']] = (int) $row['total'];
        }

        return [
            'include_sample'  => $includeSample,
            'generated_at'    => date(DATE_ATOM),
            'totals'          => $totals,
            'resolution_rate' => $withOutcome > 0 ? round($totals['resolved'] / $withOutcome * 100, 1) : null,
            'with_outcome'    => $withOutcome,
            'by_category'     => $byCategory,
            'by_confidence'   => $byConfidence,
            'daily'           => $this->daily($sampleFilter, $days),
            'top_articles'    => $this->topArticles($sampleFilter),
            'recent'          => $this->recent($sampleFilter),
        ];
    }

    /** Query counts per day for the last $days days, including days with zero queries. */
    private function daily(string $sampleFilter, int $days): array
    {
        $statement = $this->db->prepare(
            "SELECT DATE(q.created_at) AS day, COUNT(*) AS total,
                    COALESCE(SUM(q.status = 'escalated'), 0) AS escalated
             FROM support_queries q
             WHERE q.created_at >= :start $sampleFilter
             GROUP BY DATE(q.created_at)"
        );
        $start = (new DateTimeImmutable('today'))->modify('-' . ($days - 1) . ' days');
        $statement->execute(['start' => $start->format('Y-m-d 00:00:00')]);

        $counts = [];
        foreach ($statement->fetchAll() as $row) {
            $counts[$row['day']] = ['total' => (int) $row['total'], 'escalated' => (int) $row['escalated']];
        }

        $series = [];
        for ($i = 0; $i < $days; $i++) {
            $day = $start->modify("+$i days")->format('Y-m-d');
            $series[] = ['date' => $day] + ($counts[$day] ?? ['total' => 0, 'escalated' => 0]);
        }
        return $series;
    }

    private function topArticles(string $sampleFilter): array
    {
        $rows = $this->db->query(
            "SELECT a.article_code AS code, a.title, COUNT(*) AS total
             FROM query_sources s
             JOIN support_queries q ON q.id = s.query_id
             JOIN kb_articles a ON a.id = s.article_id
             WHERE s.rank_position = 1 $sampleFilter
             GROUP BY a.id, a.article_code, a.title
             ORDER BY total DESC, a.article_code
             LIMIT 5"
        )->fetchAll();
        return array_map(fn ($row) => ['code' => $row['code'], 'title' => $row['title'], 'total' => (int) $row['total']], $rows);
    }

    private function recent(string $sampleFilter): array
    {
        $rows = $this->db->query(
            "SELECT q.id, q.summary, q.status, q.confidence, q.is_sample, q.created_at, c.name AS category_name
             FROM support_queries q
             LEFT JOIN categories c ON c.id = q.category_id
             WHERE 1 = 1 $sampleFilter
             ORDER BY q.created_at DESC, q.id DESC
             LIMIT 6"
        )->fetchAll();
        return array_map(fn ($row) => [
            'id'         => (int) $row['id'],
            'summary'    => $row['summary'],
            'status'     => $row['status'],
            'confidence' => $row['confidence'],
            'category'   => $row['category_name'],
            'is_sample'  => (bool) $row['is_sample'],
            'created_at' => QueryRepository::isoDate($row['created_at']),
        ], $rows);
    }
}
