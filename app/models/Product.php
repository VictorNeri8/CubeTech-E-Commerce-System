
<?php

class Product
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    // Get active products for the storefront
    public function getActiveProducts(
        string $search = '',
        ?int $categoryId = null,
        string $sort = 'newest'
    ): array {
        $sql = "
            SELECT p.*, c.name AS category_name
            FROM products p
            JOIN categories c ON p.category_id = c.id
            WHERE p.is_active = 1
        ";

        $params = [];

        if ($search !== '') {
            $sql .= " AND p.name LIKE :search";
            $params['search'] = "%$search%";
        }

        if ($categoryId !== null && $categoryId > 0) {
            $sql .= " AND p.category_id = :category_id";
            $params['category_id'] = $categoryId;
        }

        // Only allow predefined sorting options
        $sortOptions = [
            'newest' => 'p.created_at DESC',
            'price_asc' => 'p.price ASC',
            'price_desc' => 'p.price DESC',
            'name_asc' => 'p.name ASC'
        ];

        $orderBy = $sortOptions[$sort] ?? $sortOptions['newest'];

        $sql .= " ORDER BY (p.stock <= 0) ASC, $orderBy";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    // Get one active product
    public function getProductById(int $id): ?array
    {
        $sql = "
            SELECT p.*, c.name AS category_name
            FROM products p
            JOIN categories c ON p.category_id = c.id
            WHERE p.id = :id
            AND p.is_active = 1
            LIMIT 1
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['id' => $id]);

        $product = $stmt->fetch();

        return $product ?: null;
    }

    // Get related products from the same category
    public function getRelatedProducts(
        int $categoryId,
        int $currentProductId
    ): array {
        $sql = "
            SELECT *
            FROM products
            WHERE category_id = :category_id
            AND id != :current_id
            AND is_active = 1
            ORDER BY created_at DESC
            LIMIT 3
        ";

        $stmt = $this->pdo->prepare($sql);

        $stmt->execute([
            'category_id' => $categoryId,
            'current_id' => $currentProductId
        ]);

        return $stmt->fetchAll();
    }
}
