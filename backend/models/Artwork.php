<?php
declare(strict_types=1);

/**
 * Acces BDD pour les oeuvres (artwork). Inclut recherche, filtres, tri, pagination.
 */
final class ArtworkModel
{
    private const SORTS = [
        'recent'   => 'a.created_at DESC',
        'price_asc'  => 'a.price ASC',
        'price_desc' => 'a.price DESC',
        'popular'  => 'a.views_count DESC',
    ];

    /**
     * Liste paginee + filtres. Toutes les valeurs utilisateur sont injectees
     * via des parametres lies (prepared statements) pour bloquer les injections.
     *
     * @param array{
     *   q?:string, sale_mode?:string, category?:string,
     *   min_price?:float, max_price?:float,
     *   sort?:string, page?:int, per_page?:int,
     *   seller_id?:int, status?:string
     * } $filters
     */
    public static function search(array $filters = []): array
    {
        $where  = ['1=1'];
        $params = [];

        $status = $filters['status'] ?? 'active';
        $where[] = 'a.status = :status';
        $params[':status'] = $status;

        if (!empty($filters['q'])) {
            // LIKE simple pour eviter les contraintes du fulltext en dev
            $where[] = '(a.title LIKE :q OR a.description LIKE :q OR u.display_name LIKE :q)';
            $params[':q'] = '%' . $filters['q'] . '%';
        }
        if (!empty($filters['sale_mode'])) {
            $where[] = 'a.sale_mode = :sale_mode';
            $params[':sale_mode'] = $filters['sale_mode'];
        }
        if (!empty($filters['category'])) {
            $where[] = 'c.slug = :cat';
            $params[':cat'] = $filters['category'];
        }
        if (isset($filters['min_price'])) {
            $where[] = 'COALESCE(a.price, a.start_price) >= :pmin';
            $params[':pmin'] = (float) $filters['min_price'];
        }
        if (isset($filters['max_price'])) {
            $where[] = 'COALESCE(a.price, a.start_price) <= :pmax';
            $params[':pmax'] = (float) $filters['max_price'];
        }
        if (!empty($filters['seller_id'])) {
            $where[] = 'a.seller_id = :sid';
            $params[':sid'] = (int) $filters['seller_id'];
        }

        $sortKey  = $filters['sort'] ?? 'recent';
        $orderBy  = self::SORTS[$sortKey] ?? self::SORTS['recent'];

        $page     = max(1, (int) ($filters['page'] ?? 1));
        $perPage  = min(48, max(1, (int) ($filters['per_page'] ?? 12)));
        $offset   = ($page - 1) * $perPage;

        $sql = "SELECT
                    a.id, a.title, a.description, a.sale_mode,
                    a.price, a.start_price, a.min_increment, a.ends_at,
                    a.views_count, a.favorites_count, a.status, a.created_at,
                    u.id AS seller_id, u.display_name AS seller_name,
                    c.slug AS category_slug, c.label AS category_label
                FROM artwork a
                JOIN user u ON u.id = a.seller_id
                LEFT JOIN category c ON c.id = a.category_id
                WHERE " . implode(' AND ', $where) . "
                ORDER BY $orderBy
                LIMIT $perPage OFFSET $offset";

        $stmt = Database::pdo()->prepare($sql);
        $stmt->execute($params);
        $items = $stmt->fetchAll();

        // Total pour pagination
        $countSql = "SELECT COUNT(*) FROM artwork a
                     JOIN user u ON u.id = a.seller_id
                     LEFT JOIN category c ON c.id = a.category_id
                     WHERE " . implode(' AND ', $where);
        $countStmt = Database::pdo()->prepare($countSql);
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        return [
            'items'    => $items,
            'page'     => $page,
            'per_page' => $perPage,
            'total'    => $total,
            'pages'    => (int) ceil($total / $perPage),
        ];
    }

    public static function findById(int $id): ?array
    {
        $stmt = Database::pdo()->prepare(
            "SELECT a.*, u.display_name AS seller_name, c.slug AS category_slug, c.label AS category_label
               FROM artwork a
               JOIN user u ON u.id = a.seller_id
               LEFT JOIN category c ON c.id = a.category_id
              WHERE a.id = :id LIMIT 1"
        );
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function incrementViews(int $id): void
    {
        $stmt = Database::pdo()->prepare(
            'UPDATE artwork SET views_count = views_count + 1 WHERE id = :id'
        );
        $stmt->execute([':id' => $id]);
    }

    public static function listCategories(): array
    {
        return Database::pdo()
            ->query('SELECT id, slug, label FROM category ORDER BY label')
            ->fetchAll();
    }
}
