<?php

namespace App\Utils;

class CursorPaginator
{
    private $items;
    private $perPage;
    private $hasMore;
    private $nextCursor;
    private $totalItems;
    private $urlPattern;
    private $cursorParam;

    public function __construct(array $items, $perPage, $nextCursor, $totalItems, $urlPattern, $cursorParam = 'cursor')
    {
        $this->perPage = (int) $perPage;
        
        if (count($items) > $this->perPage) {
            $this->hasMore = true;
            $this->items = array_slice($items, 0, $this->perPage);
        } else {
            $this->hasMore = false;
            $this->items = $items;
        }
        
        $this->nextCursor = $nextCursor;
        $this->totalItems = $totalItems;
        $this->urlPattern = $urlPattern;
        $this->cursorParam = $cursorParam;
    }

    public function getItems()
    {
        return $this->items;
    }

    public function hasMore()
    {
        return $this->hasMore;
    }

    public function getNextCursor()
    {
        return $this->nextCursor;
    }

    public function getTotalItems()
    {
        return $this->totalItems;
    }

    public function getNextUrl()
    {
        if (!$this->hasMore || !$this->nextCursor) return null;
        
        // Append or replace the cursor parameter in the URL pattern
        $url = $this->urlPattern;
        if (strpos($url, '?') !== false) {
            $url .= '&' . $this->cursorParam . '=' . urlencode($this->nextCursor);
        } else {
            $url .= '?' . $this->cursorParam . '=' . urlencode($this->nextCursor);
        }
        return $url;
    }

    public function getLimit()
    {
        return $this->perPage;
    }

    public function __toString()
    {
        return (string) $this->links();
    }

    public function links()
    {
        ob_start();
        $paginator = $this;
        require BASE_PATH . '/src/Views/components/cursor-pagination.php';
        return ob_get_clean();
    }
}
