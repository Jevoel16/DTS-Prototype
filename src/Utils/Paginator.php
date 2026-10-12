<?php

namespace App\Utils;

class Paginator
{
    private $totalItems;
    private $perPage;
    private $currentPage;
    private $urlPattern;
    private $totalPages;

    public function __construct($totalItems, $perPage, $currentPage, $urlPattern)
    {
        $this->totalItems = (int) $totalItems;
        $this->perPage = (int) $perPage;
        $this->currentPage = (int) max(1, $currentPage);
        $this->urlPattern = $urlPattern;
        $this->totalPages = ceil($this->totalItems / $this->perPage);
        
        if ($this->currentPage > $this->totalPages && $this->totalPages > 0) {
            $this->currentPage = $this->totalPages;
        }
    }

    public function getTotalPages()
    {
        return $this->totalPages;
    }

    public function getLimit()
    {
        return $this->perPage;
    }

    public function getOffset()
    {
        return max(0, ($this->currentPage - 1) * $this->perPage);
    }

    public function getCurrentPage()
    {
        return $this->currentPage;
    }

    public function getTotalItems()
    {
        return $this->totalItems;
    }

    public function getUrl($page)
    {
        return str_replace('(:num)', $page, $this->urlPattern);
    }

    public function __toString()
    {
        return (string) $this->links();
    }

    public function links()
    {
        if ($this->totalPages <= 1) {
            return '';
        }

        ob_start();
        $paginator = $this;
        require BASE_PATH . '/src/Views/components/pagination.php';
        return ob_get_clean();
    }
}
