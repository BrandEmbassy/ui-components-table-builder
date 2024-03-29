<?php declare(strict_types = 1);

namespace BrandEmbassy\UiComponents\Table;

use BrandEmbassy\Components\Controls\Link\Link;
use BrandEmbassy\Components\EmptyComponent;
use BrandEmbassy\Components\SnapshotAssertTrait;
use BrandEmbassy\Components\Table\Model\ArrayDataProvider;
use BrandEmbassy\Components\Table\Model\CellData;
use BrandEmbassy\Components\Table\Model\ColumnDefinition;
use BrandEmbassy\Components\Table\Model\RowData;
use BrandEmbassy\Components\Table\Model\TableIterator;
use BrandEmbassy\Components\Table\Ui\Cell;
use BrandEmbassy\Components\Typography\Paragraph;
use BrandEmbassy\Components\UiComponent;
use BrandEmbassy\Router\UrlGenerator;
use GuzzleHttp\Psr7\Uri;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\UriInterface;
use function sprintf;

/**
 * @final
 */
class CrudTableComponentBuilderTest extends TestCase
{
    use SnapshotAssertTrait;


    public function testSmoke(): void
    {
        $urlGenerator = $this->createUrlGeneratorMock();
        $builder = new CrudTableComponentBuilder($urlGenerator);

        $builder->addColumn(new ColumnDefinition('yolo', 'Yolo'))
            ->addCellRenderCallback(
                'name',
                static fn(
                    CellData $cellData,
                    RowData $rowData,
                    ColumnDefinition $columnDefinition,
                    TableIterator $tableIterator
                ): Cell => new Cell(new Paragraph('Yolo'), $columnDefinition),
            )
            ->addLinkFactory(
                static fn(string $rowIdentifier, RowData $rowData): UiComponent => new Link('Link Text', new Uri('https://google.com')),
            )
            ->addLinkFactory(
                static fn(string $rowIdentifier, RowData $rowData): UiComponent => new EmptyComponent(),
            )
            ->addDeleteLink('deleteLink')
            ->addEditLink('editLink');

        $rowsData = [new RowData('1', ['yolo' => new CellData('yolo', 'foo-bar-baz')])];
        $dataProvider = new ArrayDataProvider($rowsData);

        $table = $builder->build($dataProvider);

        $this->assertSnapshot(__DIR__ . '/__snapshot__/render.html', $table);
    }


    public function testRenderingEmptyTable(): void
    {
        $urlGenerator = $this->createUrlGeneratorMock();

        $builder = new CrudTableComponentBuilder($urlGenerator);
        $builder->addColumn(new ColumnDefinition('yolo', 'Yolo'));

        $dataProvider = new ArrayDataProvider([]);

        $table = $builder->build($dataProvider);

        $this->assertSnapshot(__DIR__ . '/__snapshot__/emptyTable.html', $table);
    }


    public function testRenderingEmptyTableWithEmptyTableComponentSet(): void
    {
        $urlGenerator = $this->createUrlGeneratorMock();

        $builder = new CrudTableComponentBuilder($urlGenerator);
        $builder->addColumn(new ColumnDefinition('yolo', 'Yolo'));
        $builder->setEmptyTableComponent(new Paragraph('Empty table'));

        $dataProvider = new ArrayDataProvider([]);

        $table = $builder->build($dataProvider);

        $this->assertSnapshot(__DIR__ . '/__snapshot__/emptyTableWithEmptyTableComponentSet.html', $table);
    }


    /**
     * @dataProvider getPagesDataProvider
     */
    public function testSortingOfPaginatedTables(
        string $expectedSnapshot,
        bool $isFirstPage,
        bool $isLastPage,
        bool $withNumberDisplayed
    ): void {
        $urlGenerator = $this->createUrlGeneratorMock();

        $builder = new CrudTableComponentBuilder($urlGenerator);
        $builder->addColumn(new ColumnDefinition('yolo', 'Yolo'));
        $builder->addColumn(new ColumnDefinition('order', 'Order'));
        $builder->addSorting(
            $this->addSortingCallable(1),
            $this->addSortingCallable(-1),
            $isFirstPage,
            $isLastPage,
            $withNumberDisplayed,
        );

        $rowsData = $this->getRowsDataForSorting();

        $dataProvider = new ArrayDataProvider($rowsData);

        $table = $builder->build($dataProvider);

        $this->assertSnapshot(__DIR__ . '/__snapshot__/' . $expectedSnapshot, $table);
    }


    /**
     * @return mixed[]
     */
    public function getPagesDataProvider(): array
    {
        return [
            'single page' => [
                'expectedSnapshot'    => 'paginationSinglePage.html',
                'isFirstPage'         => true,
                'isLastPage'          => true,
                'withNumberDisplayed' => false,
            ],
            'Middle page' => [
                'expectedSnapshot'    => 'paginationMiddlePage.html',
                'isFirstPage'         => false,
                'isLastPage'          => false,
                'withNumberDisplayed' => false,
            ],
            'Last page'   => [
                'expectedSnapshot'    => 'paginationLastPage.html',
                'isFirstPage'         => false,
                'isLastPage'          => true,
                'withNumberDisplayed' => false,
            ],
            'with display number' => [
                'expectedSnapshot'    => 'withSortNumber.html',
                'isFirstPage'         => true,
                'isLastPage'          => true,
                'withNumberDisplayed' => true,
            ],
        ];
    }


    private function addSortingCallable(int $direction): callable
    {
        return static fn(string $rowIdentifier): UriInterface => new Uri(sprintf('someUrl/%s/direction/%d', $rowIdentifier, $direction));
    }


    /**
     * @return RowData[]
     */
    private function getRowsDataForSorting(): array
    {
        return [
            new RowData(
                '1',
                [
                    'yolo'  => new CellData('row1', 'foo-bar-baz-1'),
                    'order' => new CellData('order', 1),
                ],
            ),
            new RowData(
                '2',
                [
                    'yolo'  => new CellData('row2', 'foo-bar-baz-2'),
                    'order' => new CellData('order', 2),
                ],
            ),
            new RowData(
                '3',
                [
                    'yolo'  => new CellData('row3', 'foo-bar-baz-3'),
                    'order' => new CellData('order', 3),
                ],
            ),
        ];
    }


    /**
     * @return UrlGenerator&MockInterface
     */
    public function createUrlGeneratorMock(): UrlGenerator
    {
        /** @var UrlGenerator&MockInterface $mock */
        $mock = Mockery::mock(UrlGenerator::class);
        $mock->shouldReceive('pathFor')->andReturn(new Uri(''));

        return $mock;
    }
}
