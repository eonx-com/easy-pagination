<?php
declare(strict_types=1);

namespace EonX\EasyPagination\Pagination;

use Closure;
use Spatie\Url\Url;

final class Pagination implements PaginationInterface
{
    private const DEFAULT_PAGE_ATTRIBUTE = 'page';

    private const DEFAULT_PER_PAGE_ATTRIBUTE = 'perPage';

    private const DEFAULT_URL = '/';

    private readonly string $pageAttribute;

    private readonly string $perPageAttribute;

    private readonly string $url;

    private ?Closure $urlResolver = null;

    public function __construct(
        private readonly int $page,
        private readonly int $perPage,
        ?string $pageAttribute = null,
        ?string $perPageAttribute = null,
        ?string $url = null,
    ) {
        $this->pageAttribute = $pageAttribute ?? self::DEFAULT_PAGE_ATTRIBUTE;
        $this->perPageAttribute = $perPageAttribute ?? self::DEFAULT_PER_PAGE_ATTRIBUTE;
        $this->url = $url ?? self::DEFAULT_URL;
    }

    public static function create(
        int $page,
        int $perPage,
        ?string $pageAttribute = null,
        ?string $perPageAttribute = null,
        ?string $url = null,
    ): self {
        return new self($page, $perPage, $pageAttribute, $perPageAttribute, $url);
    }

    public function getPage(): int
    {
        return $this->page;
    }

    public function getPageAttribute(): string
    {
        return $this->pageAttribute;
    }

    public function getPerPage(): int
    {
        return $this->perPage;
    }

    public function getPerPageAttribute(): string
    {
        return $this->perPageAttribute;
    }

    public function getUrl(int $page): string
    {
        $urlResolver = $this->urlResolver ?? $this->getDefaultUrlResolver();

        return (string)$urlResolver(Url::fromString($this->url), $this, $page);
    }

    public function setUrlResolver(?callable $urlResolver = null): PaginationInterface
    {
        $this->urlResolver = $urlResolver === null ? null : $urlResolver(...);

        return $this;
    }

    private function getDefaultUrlResolver(): callable
    {
        return static function (Url $url, PaginationInterface $pagination, int $page): Url {
            $query = $url->getAllQueryParameters();

            $query[$pagination->getPageAttribute()] = $page > 0 ? $page : 1;
            $query[$pagination->getPerPageAttribute()] = $pagination->getPerPage();

            return $url->withQueryParameters($query);
        };
    }
}
