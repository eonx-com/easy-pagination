<?php
declare(strict_types=1);

namespace EonX\EasyPagination\Traits;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Query\QueryBuilder;

trait DoctrineDbalPaginatorTrait
{
    use DoctrineCommonPaginatorTrait;

    private Connection $conn;

    private function createQueryBuilder(): QueryBuilder
    {
        return $this->conn->createQueryBuilder()
            ->from($this->from, $this->fromAlias);
    }

    /**
     * @throws \Doctrine\DBAL\Exception
     */
    private function fetchResults(QueryBuilder $queryBuilder): array
    {
        return $this->conn->fetchAllAssociative(
            $queryBuilder->getSQL(),
            $queryBuilder->getParameters(),
            $queryBuilder->getParameterTypes()
        );
    }

    private function getConnection(): Connection
    {
        return $this->conn;
    }

    private function resolveSelect(): mixed
    {
        if ($this->select !== null) {
            return $this->select;
        }

        if ($this->fromAlias !== null) {
            return \sprintf('%s.*', $this->fromAlias);
        }

        return '*';
    }
}
