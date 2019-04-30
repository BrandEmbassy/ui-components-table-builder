<?php declare(strict_types = 1);

namespace BrandEmbassy\UiComponents\Table;

use BrandEmbassy\Components\Controls\Link\Link;
use BrandEmbassy\Components\SnapshotAssertTrait;
use BrandEmbassy\Components\Table\Model\ArrayDataProvider;
use BrandEmbassy\Components\Table\Model\CellData;
use BrandEmbassy\Components\Table\Model\ColumnDefinition;
use BrandEmbassy\Components\Table\Model\RowData;
use BrandEmbassy\Components\Table\Model\TableIterator;
use BrandEmbassy\Components\Table\Ui\Cell;
use BrandEmbassy\Components\Typography\Paragraph;
use BrandEmbassy\Router\UrlGenerator;
use GuzzleHttp\Psr7\Uri;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\TestCase;

final class CrudTableComponentBuilderTest extends TestCase
{
    use SnapshotAssertTrait;


    public function testSmoke(): void
    {
        $urlGenerator = $this->createUrlGeneratorMock();
        $builder = new CrudTableComponentBuilder($urlGenerator);

        $builder->addColumn(new ColumnDefinition('yolo', 'Yolo'))
            ->addCellRenderCallback(
                'name',
                function (
                    CellData $cellData,
                    RowData $rowData,
                    ColumnDefinition $columnDefinition,
                    TableIterator $tableIterator
                ): Cell {
                    return new Cell(new Paragraph('Yolo'), $columnDefinition);
                }
            )
            ->addLinkFactory(
                function (string $rowIdentifier, RowData $rowData): Link {
                    return new Link('Link Text', new Uri('https://google.com'));
                }
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
     * @return MockInterface&UrlGenerator
     */
    public function createUrlGeneratorMock(): UrlGenerator
    {
        /** @var UrlGenerator&MockInterface $mock */
        $mock = Mockery::mock(UrlGenerator::class);
        $mock->shouldReceive('pathFor')->andReturn(new Uri(''));

        return $mock;
    }
}
