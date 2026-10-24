<?php

declare(strict_types=1);

final class QueryRepository
{
    public function __construct(private PDO $db)
    {
    }

    /** Saves a query together with the knowledge-base sources it cited. Returns the new id. */
    public function create(array $query, array $sources): int
    {
        $this->db->beginTransaction();
        try {
            $statement = $this->db->prepare(
                'INSERT INTO support_queries
                   (issue_text, category_id, category_source, summary, confidence, top_score,
                    answer_mode, ai_model, response_json, escalation_recommended, status)
                 VALUES
                   (:issue_text, :category_id, :category_source, :summary, :confidence, :top_score,
                    :answer_mode, :ai_model, :response_json, :escalation_recommended, \'open\')'
            );
            $statement->execute([
                'issue_text'             => $query['issue_text'],
                'category_id'            => $query['category_id'],
                'category_source'        => $query['category_source'],
                'summary'                => mb_substr($query['summary'], 0, 500),
                'confidence'             => $query['confidence'],
                'top_score'              => $query['top_score'],
                'answer_mode'            => $query['answer_mode'],
                'ai_model'               => $query['ai_model'],
                'response_json'          => json_encode($query['response'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                'escalation_recommended' => $query['escalation_recommended'] ? 1 : 0,
            ]);
            $id = (int) $this->db->lastInsertId();

            $sourceStatement = $this->db->prepare(
                'INSERT INTO query_sources (query_id, article_id, rank_position, score)
                 VALUES (:query_id, :article_id, :rank_position, :score)'
            );
            foreach ($sources as $index => $source) {
                $sourceStatement->execute([
                    'query_id'      => $id,
                    'article_id'    => $source['id'],
                    'rank_position' => $index + 1,
                    'score'         => $source['score'],
                ]);
            }
            $this->db->commit();
            return $id;
        } catch (Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    public function paginate(array $filters, int $page, int $perPage): array
    {
        [$where, $params] = $this->filters($filters);

        $count = $this->db->prepare("SELECT COUNT(*) FROM support_queries q LEFT JOIN categories c ON c.id = q.category_id $where");
        $count->execute($params);
        $total = (int) $count->fetchColumn();

        $statement = $this->db->prepare(
            "SELECT q.id, q.issue_text, q.summary, q.confidence, q.answer_mode, q.escalation_recommended,
                    q.status, q.status_note, q.status_updated_at, q.is_sample, q.created_at,
                    c.slug AS category_slug, c.name AS category_name,
                    (SELECT a.article_code FROM query_sources s JOIN kb_articles a ON a.id = s.article_id
                      WHERE s.query_id = q.id ORDER BY s.rank_position LIMIT 1) AS top_article
             FROM support_queries q
             LEFT JOIN categories c ON c.id = q.category_id
             $where
             ORDER BY q.created_at DESC, q.id DESC
             LIMIT :limit OFFSET :offset"
        );
        foreach ($params as $key => $value) {
            $statement->bindValue($key, $value);
        }
        $statement->bindValue('limit', $perPage, PDO::PARAM_INT);
        $statement->bindValue('offset', ($page - 1) * $perPage, PDO::PARAM_INT);
        $statement->execute();

        return [
            'items'      => array_map([self::class, 'formatRow'], $statement->fetchAll()),
            'pagination' => [
                'page'        => $page,
                'per_page'    => $perPage,
                'total'       => $total,
                'total_pages' => max(1, (int) ceil($total / $perPage)),
            ],
        ];
    }

    public function find(int $id): ?array
    {
        $statement = $this->db->prepare(
            'SELECT q.*, c.slug AS category_slug, c.name AS category_name
             FROM support_queries q
             LEFT JOIN categories c ON c.id = q.category_id
             WHERE q.id = :id'
        );
        $statement->execute(['id' => $id]);
        $row = $statement->fetch();
        if (!$row) {
            return null;
        }
        $item = self::formatRow($row);
        $item['response'] = json_decode($row['response_json'], true) ?: null;
        return $item;
    }

    public function updateStatus(int $id, string $status, ?string $note): bool
    {
        $statement = $this->db->prepare(
            'UPDATE support_queries
             SET status = :status, status_note = :note, status_updated_at = NOW()
             WHERE id = :id'
        );
        $statement->execute(['status' => $status, 'note' => $note, 'id' => $id]);
        return $statement->rowCount() > 0 || $this->exists($id);
    }

    private function exists(int $id): bool
    {
        $statement = $this->db->prepare('SELECT 1 FROM support_queries WHERE id = :id');
        $statement->execute(['id' => $id]);
        return (bool) $statement->fetchColumn();
    }

    private function filters(array $filters): array
    {
        $conditions = [];
        $params = [];
        if (!empty($filters['status'])) {
            $conditions[] = 'q.status = :status';
            $params['status'] = $filters['status'];
        }
        if (!empty($filters['category'])) {
            if ($filters['category'] === 'uncategorised') {
                $conditions[] = 'q.category_id IS NULL';
            } else {
                $conditions[] = 'c.slug = :category';
                $params['category'] = $filters['category'];
            }
        }
        if (!empty($filters['search'])) {
            $conditions[] = '(q.issue_text LIKE :search1 OR q.summary LIKE :search2)';
            $like = '%' . addcslashes($filters['search'], '%_\\') . '%';
            $params['search1'] = $like;
            $params['search2'] = $like;
        }
        if (empty($filters['include_sample'])) {
            $conditions[] = 'q.is_sample = 0';
        }
        return [$conditions ? 'WHERE ' . implode(' AND ', $conditions) : '', $params];
    }

    private static function formatRow(array $row): array
    {
        return [
            'id'                     => (int) $row['id'],
            'issue_text'             => $row['issue_text'],
            'summary'                => $row['summary'],
            'category'               => $row['category_slug'] ? ['slug' => $row['category_slug'], 'name' => $row['category_name']] : null,
            'confidence'             => $row['confidence'],
            'mode'                   => $row['answer_mode'],
            'escalation_recommended' => (bool) $row['escalation_recommended'],
            'status'                 => $row['status'],
            'status_note'            => $row['status_note'],
            'status_updated_at'      => self::isoDate($row['status_updated_at']),
            'is_sample'              => (bool) $row['is_sample'],
            'top_article'            => $row['top_article'] ?? null,
            'created_at'             => self::isoDate($row['created_at']),
        ];
    }

    public static function isoDate(?string $value): ?string
    {
        return $value ? (new DateTimeImmutable($value))->format(DATE_ATOM) : null;
    }
}
