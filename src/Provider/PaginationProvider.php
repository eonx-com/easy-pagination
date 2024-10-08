<?php
declare(strict_types=1);

namespace EonX\EasyPagination\Provider;

use Closure;
use EonX\EasyPagination\Exception\NoPaginationResolverSetException;
use EonX\EasyPagination\Pagination\PaginationInterface;

final class PaginationProvider implements PaginationProviderInterface
{
    private ?PaginationInterface $pagination = null;

    private ?Closure $resolver = null;

    public function __construct(
        private readonly PaginationConfigProviderInterface $paginationConfigProvider,
    ) {
    }

    public function getPagination(): PaginationInterface
    {
        if ($this->pagination !== null) {
            return $this->pagination;
        }

        if ($this->resolver !== null) {
            return $this->pagination = \call_user_func($this->resolver);
        }

        throw new NoPaginationResolverSetException(\sprintf(
            'No pagination resolver set on provider. Use %s::setResolver().',
            PaginationProviderInterface::class
        ));
    }

    public function getPaginationConfigProvider(): PaginationConfigProviderInterface
    {
        return $this->paginationConfigProvider;
    }

    public function setResolver(callable $resolver): PaginationProviderInterface
    {
        $this->resolver = $resolver(...);
        $this->pagination = null;

        return $this;
    }
}
