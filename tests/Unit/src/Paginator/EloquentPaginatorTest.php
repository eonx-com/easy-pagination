<?php
declare(strict_types=1);

namespace EonX\EasyPagination\Tests\Unit\Paginator;

use EonX\EasyPagination\Paginator\EloquentPaginator;
use EonX\EasyPagination\Tests\Stub\Model\ChildItemModel;
use EonX\EasyPagination\Tests\Stub\Model\ItemModel;
use EonX\EasyPagination\ValueObject\Pagination;
use EonX\EasyPagination\ValueObject\PaginationInterface;
use Illuminate\Database\ConnectionResolver;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use PHPUnit\Framework\Attributes\DataProvider;

final class EloquentPaginatorTest extends AbstractEloquentPaginatorTestCase
{
    /**
     * @see testPaginator
     */
    public static function providePaginatorData(): iterable
    {
        yield 'Default 0 items' => [
            Pagination::create(1, 15),
            new ItemModel(),
            function (Model $model): void {
                self::createItemsTable($model);
            },
            static function (EloquentPaginator $paginator): void {
                self::assertEmpty($paginator->getItems());
            },
        ];

        yield 'High pagination when no items in db' => [
            Pagination::create(10, 15),
            new ItemModel(),
            function (Model $model): void {
                self::createItemsTable($model);
            },
            static function (EloquentPaginator $paginator): void {
                self::assertEmpty($paginator->getItems());
            },
        ];

        yield 'Default 1 item' => [
            Pagination::create(1, 15),
            new ItemModel(),
            function (Model $model): void {
                self::createItemsTable($model);

                (new ItemModel(['title' => 'my-title']))->save();
            },
            static function (EloquentPaginator $paginator): void {
                self::assertCount(1, $paginator->getItems());
            },
        ];

        yield '2 items filter 1' => [
            Pagination::create(1, 15),
            new ItemModel(),
            function (Model $model, EloquentPaginator $paginator): void {
                self::createItemsTable($model);

                (new ItemModel(['title' => 'my-title']))->save();
                (new ItemModel(['title' => 'my-title-1']))->save();

                $paginator->setFilterCriteria(static function (Builder $queryBuilder): void {
                    $queryBuilder->where('title', 'my-title-1');
                });
            },
            static function (EloquentPaginator $paginator): void {
                self::assertCount(1, $paginator->getItems());
            },
        ];

        yield '1 item select everything by default' => [
            Pagination::create(1, 15),
            new ItemModel(),
            function (Model $model): void {
                self::createItemsTable($model);

                (new ItemModel(['title' => 'my-title']))->save();
            },
            static function (EloquentPaginator $paginator): void {
                $item = $paginator->getItems()[0] ?? null;

                self::assertCount(1, $paginator->getItems());
                self::assertInstanceOf(ItemModel::class, $item);
                self::assertEquals(1, $item->id);
                self::assertEquals('my-title', $item->title);
            },
        ];

        yield '1 item select everything explicitly' => [
            Pagination::create(1, 15),
            new ItemModel(),
            function (Model $model, EloquentPaginator $paginator): void {
                self::createItemsTable($model);

                (new ItemModel(['title' => 'my-title']))->save();

                $paginator->setSelect('*');
            },
            static function (EloquentPaginator $paginator): void {
                $item = $paginator->getItems()[0] ?? null;

                self::assertCount(1, $paginator->getItems());
                self::assertInstanceOf(ItemModel::class, $item);
                self::assertEquals(1, $item->id);
                self::assertEquals('my-title', $item->title);
            },
        ];

        yield '1 item select only title' => [
            Pagination::create(1, 15),
            new ItemModel(),
            function (Model $model, EloquentPaginator $paginator): void {
                self::createItemsTable($model);

                (new ItemModel(['title' => 'my-title']))->save();

                $paginator->setSelect('title');
            },
            static function (EloquentPaginator $paginator): void {
                $item = $paginator->getItems()[0] ?? null;

                self::assertCount(1, $paginator->getItems());
                self::assertInstanceOf(ItemModel::class, $item);
                self::assertNull($item->id);
                self::assertEquals('my-title', $item->title);
            },
        ];

        yield '1 item transform entity to array' => [
            Pagination::create(1, 15),
            new ItemModel(),
            function (Model $model, EloquentPaginator $paginator): void {
                self::createItemsTable($model);

                (new ItemModel(['title' => 'my-title']))->save();

                $paginator->setTransformer(static fn (ItemModel $item): array => [
                    'id' => $item->id,
                    'title' => $item->title,
                ]);
            },
            static function (EloquentPaginator $paginator): void {
                $item = $paginator->getItems()[0] ?? null;

                self::assertCount(1, $paginator->getItems());
                self::assertIsArray($item);
                self::assertEquals(1, $item['id']);
                self::assertEquals('my-title', $item['title']);
            },
        ];

        yield 'Paginate children of item by title' => [
            Pagination::create(1, 15),
            new ChildItemModel(),
            function (Model $model, EloquentPaginator $paginator): void {
                self::createItemsTable($model);
                self::createChildItemsTable($model);

                (new ItemModel(['title' => 'my-parent']))->save();
                (new ChildItemModel([
                    'child_title' => 'my-child',
                    'item_id' => 1,
                ]))->save();

                $paginator->hasJoinsInQuery();
                $paginator->setCommonCriteria(static function (Builder $queryBuilder): void {
                    $queryBuilder->join('items', 'items.title', '=', 'my-parent');
                });
                $paginator->setGetItemsCriteria(static function (Builder $queryBuilder): void {
                    $queryBuilder->with('item');
                });
            },
            static function (EloquentPaginator $paginator): void {
                $childItem = $paginator->getItems()[0] ?? null;

                self::assertCount(1, $paginator->getItems());
                self::assertInstanceOf(ChildItemModel::class, $childItem);
                self::assertInstanceOf(ItemModel::class, $childItem->item);
                self::assertEquals(1, $childItem->id);
                self::assertEquals(1, $childItem->item->id);
                self::assertEquals('my-parent', $childItem->item->title);
                self::assertEquals('my-child', $childItem->child_title);
            },
        ];
    }

    /**
     * @throws \Doctrine\DBAL\Exception
     */
    #[DataProvider('providePaginatorData')]
    public function testPaginator(
        PaginationInterface $pagination,
        Model $model,
        callable $setup,
        callable $assert,
    ): void {
        $connectionResolver = new ConnectionResolver([
            'default' => $this->getEloquentConnection(),
        ]);
        $connectionResolver->setDefaultConnection('default');

        Model::setConnectionResolver($connectionResolver);

        $paginator = new EloquentPaginator($pagination, $model);

        $setup($model, $paginator);
        $assert($paginator);
    }
}
