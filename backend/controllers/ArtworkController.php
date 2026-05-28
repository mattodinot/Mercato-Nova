<?php
declare(strict_types=1);

final class ArtworkController extends Controller
{
    /**
     * GET /api/artworks
     * Filtres : q, sale_mode, category, min_price, max_price, sort, page, per_page
     */
    public function index(): void
    {
        $filters = [
            'q'         => $this->query('q'),
            'sale_mode' => $this->query('sale_mode'),
            'category'  => $this->query('category'),
            'sort'      => $this->query('sort', 'recent'),
            'page'      => (int) ($this->query('page', '1')),
            'per_page'  => (int) ($this->query('per_page', '12')),
        ];
        if ($v = $this->query('min_price')) $filters['min_price'] = (float) $v;
        if ($v = $this->query('max_price')) $filters['max_price'] = (float) $v;

        Response::ok(ArtworkModel::search($filters));
    }

    /**
     * GET /api/artworks/{id}
     */
    public function show(array $params): void
    {
        $id = (int) $params['id'];
        $a  = ArtworkModel::findById($id);
        if (!$a || $a['status'] === 'brouillon' || $a['status'] === 'rejetee') {
            Response::error('Oeuvre introuvable.', 404);
        }
        ArtworkModel::incrementViews($id);
        Response::ok(['artwork' => $a]);
    }

    /**
     * GET /api/categories
     */
    public function categories(): void
    {
        Response::ok(['categories' => ArtworkModel::listCategories()]);
    }
}
