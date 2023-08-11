<?php
declare(strict_types=1);

namespace EonX\EasyPagination\Tests\Paginators;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use EonX\EasyPagination\Interfaces\PaginationInterface;
use EonX\EasyPagination\Pagination;
use EonX\EasyPagination\Paginators\DoctrineOrmLengthAwarePaginator;
use EonX\EasyPagination\Tests\AbstractDoctrineOrmTestCase;
use EonX\EasyPagination\Tests\Stubs\Entity\ChildItem;
use EonX\EasyPagination\Tests\Stubs\Entity\Item;
use PHPUnit\Framework\Attributes\DataProvider;

final class DoctrineOrmLengthAwarePaginatorTest extends AbstractDoctrineOrmTestCase
{
    /**
     * @see testPaginator
     */
    public static function providerTestPaginator(): iterable
    {
        yield 'Default 0 items' => [
            Pagination::create(1, 15),
            Item::class,
            'i',
            null,
            function (EntityManagerInterface $manager): void {
                self::createItemsTable($manager);
            },
            static function (DoctrineOrmLengthAwarePaginator $paginator): void {
                self::assertEmpty($paginator->getItems());
                self::assertEquals(0, $paginator->getTotalItems());
            },
        ];

        yield 'High pagination when no items in db' => [
            Pagination::create(10, 15),
            Item::class,
            'i',
            null,
            function (EntityManagerInterface $manager): void {
                self::createItemsTable($manager);
            },
            static function (DoctrineOrmLengthAwarePaginator $paginator): void {
                self::assertEmpty($paginator->getItems());
                self::assertEquals(0, $paginator->getTotalItems());
            },
        ];

        yield 'Default 1 item' => [
            Pagination::create(1, 15),
            Item::class,
            'i',
            null,
            function (EntityManagerInterface $manager): void {
                self::createItemsTable($manager);
                self::addItemToTable($manager, 'my-title');
            },
            static function (DoctrineOrmLengthAwarePaginator $paginator): void {
                self::assertCount(1, $paginator->getItems());
                self::assertEquals(1, $paginator->getTotalItems());
            },
        ];

        yield '2 items filter 1' => [
            Pagination::create(1, 15),
            Item::class,
            'i',
            null,
            function (EntityManagerInterface $manager, DoctrineOrmLengthAwarePaginator $paginator): void {
                self::createItemsTable($manager);
                self::addItemToTable($manager, 'my-title');
                self::addItemToTable($manager, 'my-title-1');

                $paginator->setFilterCriteria(static function (QueryBuilder $queryBuilder): void {
                    $queryBuilder
                        ->where('i.title = :title')
                        ->setParameter('title', 'my-title-1');
                });
            },
            static function (DoctrineOrmLengthAwarePaginator $paginator): void {
                self::assertCount(1, $paginator->getItems());
                self::assertEquals(1, $paginator->getTotalItems());
            },
        ];

        yield '1 item select everything by default' => [
            Pagination::create(1, 15),
            Item::class,
            'i',
            null,
            function (EntityManagerInterface $manager): void {
                self::createItemsTable($manager);
                self::addItemToTable($manager, 'my-title');
            },
            static function (DoctrineOrmLengthAwarePaginator $paginator): void {
                $item = $paginator->getItems()[0] ?? null;

                self::assertCount(1, $paginator->getItems());
                self::assertEquals(1, $paginator->getTotalItems());
                self::assertInstanceOf(Item::class, $item);
                self::assertEquals(1, $item->getId());
                self::assertEquals('my-title', $item->getTitle());
            },
        ];

        yield '1 item select everything explicitly' => [
            Pagination::create(1, 15),
            Item::class,
            'i',
            null,
            function (EntityManagerInterface $manager, DoctrineOrmLengthAwarePaginator $paginator): void {
                self::createItemsTable($manager);
                self::addItemToTable($manager, 'my-title');

                $paginator->setSelect('*');
            },
            static function (DoctrineOrmLengthAwarePaginator $paginator): void {
                $item = $paginator->getItems()[0] ?? null;

                self::assertCount(1, $paginator->getItems());
                self::assertEquals(1, $paginator->getTotalItems());
                self::assertInstanceOf(Item::class, $item);
                self::assertEquals(1, $item->getId());
                self::assertEquals('my-title', $item->getTitle());
            },
        ];

        yield '1 item select only title' => [
            Pagination::create(1, 15),
            Item::class,
            'i',
            null,
            function (EntityManagerInterface $manager, DoctrineOrmLengthAwarePaginator $paginator): void {
                self::createItemsTable($manager);
                self::addItemToTable($manager, 'my-title');

                $paginator->setSelect('i.title');
            },
            static function (DoctrineOrmLengthAwarePaginator $paginator): void {
                $item = $paginator->getItems()[0] ?? null;

                self::assertCount(1, $paginator->getItems());
                self::assertEquals(1, $paginator->getTotalItems());
                self::assertIsArray($item);
                self::assertArrayNotHasKey('id', $item);
                self::assertArrayHasKey('title', $item);
            },
        ];

        yield '1 item transform entity to array' => [
            Pagination::create(1, 15),
            Item::class,
            'i',
            null,
            function (EntityManagerInterface $manager, DoctrineOrmLengthAwarePaginator $paginator): void {
                self::createItemsTable($manager);
                self::addItemToTable($manager, 'my-title');

                $paginator->setTransformer(static fn (Item $item): array => [
                    'id' => $item->getId(),
                    'title' => $item->getTitle(),
                ]);
            },
            static function (DoctrineOrmLengthAwarePaginator $paginator): void {
                $item = $paginator->getItems()[0] ?? null;

                self::assertCount(1, $paginator->getItems());
                self::assertEquals(1, $paginator->getTotalItems());
                self::assertIsArray($item);
                self::assertEquals(1, $item['id']);
                self::assertEquals('my-title', $item['title']);
            },
        ];

        yield 'Paginate children of item by title' => [
            Pagination::create(1, 15),
            ChildItem::class,
            'ci',
            null,
            function (EntityManagerInterface $manager, DoctrineOrmLengthAwarePaginator $paginator): void {
                self::createItemsTable($manager);
                self::createParentsTable($manager);
                $item = self::addItemToTable($manager, 'my-parent');
                self::addChildItemToTable($manager, 'my-child', $item);

                $paginator->hasJoinsInQuery();
                $paginator->setCommonCriteria(static function (QueryBuilder $queryBuilder): void {
                    $queryBuilder
                        ->join('ci.item', 'i', 'WITH', 'i.title = :title')
                        ->setParameter('title', 'my-parent');
                });
                $paginator->setGetItemsCriteria(static function (QueryBuilder $queryBuilder): void {
                    $queryBuilder->addSelect('i');
                });
            },
            static function (DoctrineOrmLengthAwarePaginator $paginator): void {
                $childItem = $paginator->getItems()[0] ?? null;

                self::assertCount(1, $paginator->getItems());
                self::assertEquals(1, $paginator->getTotalItems());
                self::assertInstanceOf(ChildItem::class, $childItem);
                self::assertInstanceOf(Item::class, $childItem->getItem());
                self::assertEquals(1, $childItem->getId());
                self::assertEquals(1, $childItem->getItem()->getId());
                self::assertEquals('my-parent', $childItem->getItem()->getTitle());
                self::assertEquals('my-child', $childItem->getTitle());
            },
        ];

        yield '2 items, 1 perPage' => [
            Pagination::create(1, 1),
            Item::class,
            'i',
            null,
            function (EntityManagerInterface $manager): void {
                self::createItemsTable($manager);
                self::addItemToTable($manager, 'my-title');
                self::addItemToTable($manager, 'my-title-1');
            },
            static function (DoctrineOrmLengthAwarePaginator $paginator): void {
                self::assertCount(1, $paginator->getItems());
                self::assertEquals(2, $paginator->getTotalItems());
            },
        ];
    }

    /**
     * @throws \Doctrine\ORM\ORMException
     */
    #[DataProvider('providerTestPaginator')]
    public function testPaginator(
        PaginationInterface $pagination,
        string $from,
        string $fromAlias,
        ?string $indexBy,
        callable $setup,
        callable $assert,
    ): void {
        $entityManager = $this->getEntityManager();
        $paginator = new DoctrineOrmLengthAwarePaginator($pagination, $entityManager, $from, $fromAlias, $indexBy);

        $setup($entityManager, $paginator);
        $assert($paginator);
    }
}
