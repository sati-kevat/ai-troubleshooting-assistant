<?php

declare(strict_types=1);

final class ArticleRepository
{
    private const SELECT = 'SELECT a.id, a.article_code, a.title, a.symptoms, a.causes, a.steps,
                                   a.escalation_conditions, a.escalate_to, a.keywords, a.is_fictional,
                                   a.updated_at, c.slug AS category_slug, c.name AS category_name
                            FROM kb_articles a
                            JOIN categories c ON c.id = a.category_id';

    public function __construct(private PDO $db)
    {
    }

    /** All active articles, formatted, used by the retriever. */
    public function allActive(): array
    {
        $rows = $this->db->query(self::SELECT . ' WHERE a.is_active = 1 ORDER BY a.article_code')->fetchAll();
        return array_map([self::class, 'format'], $rows);
    }

    public function search(?string $search, ?string $categorySlug): array
    {
        $sql = self::SELECT . ' WHERE a.is_active = 1';
        $params = [];
        if ($categorySlug !== null) {
            $sql .= ' AND c.slug = :category';
            $params['category'] = $categorySlug;
        }
        if ($search !== null) {
            $sql .= ' AND (a.title LIKE :s1 OR a.symptoms LIKE :s2 OR a.keywords LIKE :s3 OR a.article_code LIKE :s4)';
            $like = '%' . addcslashes($search, '%_\\') . '%';
            $params += ['s1' => $like, 's2' => $like, 's3' => $like, 's4' => $like];
        }
        $statement = $this->db->prepare($sql . ' ORDER BY a.article_code');
        $statement->execute($params);
        return array_map([self::class, 'format'], $statement->fetchAll());
    }

    public function findByCode(string $code): ?array
    {
        $statement = $this->db->prepare(self::SELECT . ' WHERE a.article_code = :code AND a.is_active = 1');
        $statement->execute(['code' => $code]);
        $row = $statement->fetch();
        return $row ? self::format($row) : null;
    }

    public function categories(): array
    {
        return $this->db->query(
            'SELECT c.slug, c.name, c.description, COUNT(a.id) AS article_count
             FROM categories c
             LEFT JOIN kb_articles a ON a.category_id = c.id AND a.is_active = 1
             GROUP BY c.id, c.slug, c.name, c.description
             ORDER BY c.name'
        )->fetchAll();
    }

    public function categoryIdBySlug(string $slug): ?int
    {
        $statement = $this->db->prepare('SELECT id FROM categories WHERE slug = :slug');
        $statement->execute(['slug' => $slug]);
        $id = $statement->fetchColumn();
        return $id === false ? null : (int) $id;
    }

    public function count(): int
    {
        return (int) $this->db->query('SELECT COUNT(*) FROM kb_articles WHERE is_active = 1')->fetchColumn();
    }

    private static function format(array $row): array
    {
        return [
            'id'                    => (int) $row['id'],
            'code'                  => $row['article_code'],
            'title'                 => $row['title'],
            'category'              => ['slug' => $row['category_slug'], 'name' => $row['category_name']],
            'symptoms'              => self::lines($row['symptoms']),
            'causes'                => self::lines($row['causes']),
            'steps'                 => self::lines($row['steps']),
            'escalation_conditions' => self::lines($row['escalation_conditions']),
            'escalate_to'           => $row['escalate_to'],
            'keywords'              => array_values(array_filter(array_map('trim', explode(',', $row['keywords'])))),
            'is_fictional'          => (bool) $row['is_fictional'],
            'updated_at'            => $row['updated_at'],
        ];
    }

    private static function lines(string $text): array
    {
        return array_values(array_filter(array_map('trim', preg_split('/\r?\n/', $text) ?: [])));
    }
}
