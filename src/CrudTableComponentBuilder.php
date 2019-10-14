<?php declare(strict_types = 1);

namespace BrandEmbassy\UiComponents\Table;

use BrandEmbassy\Components\Align;
use BrandEmbassy\Components\Controls\Link\Link;
use BrandEmbassy\Components\Controls\Link\LinkColor;
use BrandEmbassy\Components\Controls\Link\LinkList;
use BrandEmbassy\Components\Icon\IconType;
use BrandEmbassy\Components\Table\Model\CellData;
use BrandEmbassy\Components\Table\Model\ColumnDefinition;
use BrandEmbassy\Components\Table\Model\DataProvider;
use BrandEmbassy\Components\Table\Model\RowData;
use BrandEmbassy\Components\Table\Model\TableDefinition;
use BrandEmbassy\Components\Table\Model\TableIterator;
use BrandEmbassy\Components\Table\Model\TableRowDivider;
use BrandEmbassy\Components\Table\Ui\Cell;
use BrandEmbassy\Components\Table\Ui\Table;
use BrandEmbassy\Components\UiComponent;
use BrandEmbassy\Router\UrlGenerator;

final class CrudTableComponentBuilder
{
    private const DEFAULT_QUERY_KEY = 'id';

    /**
     * @var UrlGenerator
     */
    private $urlGenerator;

    /**
     * @var ColumnDefinition[]
     */
    private $columnDefinition = [];

    /**
     * @var (callable(string $rowIdentifier): Link)[]
     * @phpcsSuppress SlevomatCodingStandard.TypeHints.TypeHintDeclaration.MissingTraversablePropertyTypeHintSpecification
     */
    private $linkFactories = [];

    /**
     * @var string[]
     */
    private $queryParams = [];

    /**
     * @var (callable(CellData $cellData, RowData $rowData, ColumnDefinition $columnDefinition): Cell)[]
     * @phpcsSuppress SlevomatCodingStandard.TypeHints.TypeHintDeclaration.MissingTraversablePropertyTypeHintSpecification
     */
    private $cellRenderCallbacks = [];

    /**
     * @var bool
     */
    private $hasHover = false;

    /**
     * @var UiComponent|null
     */
    private $emptyTableComponent;

    /**
     * @var TableRowDivider|null
     */
    private $tableRowDivider;


    public function __construct(UrlGenerator $urlGenerator)
    {
        $this->urlGenerator = $urlGenerator;
    }


    public function setHasHover(bool $hasHover): void
    {
        $this->hasHover = $hasHover;
    }


    public function setTableRowDivider(?TableRowDivider $tableRowDivider): self
    {
        $this->tableRowDivider = $tableRowDivider;

        return $this;
    }


    public function setEmptyTableComponent(UiComponent $emptyTableComponent): void
    {
        $this->emptyTableComponent = $emptyTableComponent;
    }


    public function addColumn(ColumnDefinition $columnDefinition): self
    {
        $this->columnDefinition[$columnDefinition->getKey()] = $columnDefinition;

        return $this;
    }


    public function addQueryParam(string $key, string $value): self
    {
        $this->queryParams[$key] = $value;

        return $this;
    }


    public function addEditLink(string $editRoutePath, string $keyQueryName = self::DEFAULT_QUERY_KEY): self
    {
        $this->linkFactories[] = function (string $rowIdentifier) use ($editRoutePath, $keyQueryName): Link {
            $linkQueryParams = $this->queryParams;
            $linkQueryParams[$keyQueryName] = $rowIdentifier;

            return new Link(
                'Edit',
                $this->urlGenerator->pathFor($editRoutePath, $linkQueryParams),
                LinkColor::get(LinkColor::BLUE),
                IconType::get(IconType::PENCIL)
            );
        };

        return $this;
    }


    public function addDeleteLink(string $deleteRoutePath, string $keyQueryName = self::DEFAULT_QUERY_KEY): self
    {
        $this->linkFactories[] = function (string $rowIdentifier) use ($deleteRoutePath, $keyQueryName): Link {
            $linkQueryParams = $this->queryParams;
            $linkQueryParams[$keyQueryName] = $rowIdentifier;

            return new Link(
                'Delete',
                $this->urlGenerator->pathFor($deleteRoutePath, $linkQueryParams),
                LinkColor::get(LinkColor::DEFAULT),
                IconType::get(IconType::TRASH),
                'return confirm(\'Are you sure you want to remove this item?\')'
            );
        };

        return $this;
    }


    /**
     * @param callable(string $rowIdentifier, RowData $rowData): Link $linkFactory
     *
     * @return CrudTableComponentBuilder
     */
    public function addLinkFactory(callable $linkFactory): self
    {
        $this->linkFactories[] = $linkFactory;

        return $this;
    }


    public function build(DataProvider $tableDataProvider): UiComponent
    {
        $tableIsEmpty = $tableDataProvider->count() === 0;
        $emptyTableComponentIsSet = $this->emptyTableComponent !== null;
        if ($tableIsEmpty && $emptyTableComponentIsSet) {
            return $this->emptyTableComponent;
        }

        $this->columnDefinition['actions'] = new ColumnDefinition('actions', '', Align::get(Align::RIGHT));

        $tableDefinition = new TableDefinition($this->columnDefinition);
        if ($this->tableRowDivider !== NULL) {
            $tableDefinition->setRowDivider($this->tableRowDivider);
        }
        $table = new Table($tableDefinition, $tableDataProvider, $this->hasHover);
        $table->setColumnsNotInDataSet(['actions']);

        $table->setCellRenderCallback(
            'actions',
            function (CellData $cellData, RowData $rowData, ColumnDefinition $columnDefinition): Cell {
                $links = \array_map(
                    static function (callable $linkFactory) use ($rowData): UiComponent {
                        return $linkFactory($rowData->getRowIdentifier(), $rowData);
                    },
                    $this->linkFactories
                );

                return new Cell(new LinkList($links), $columnDefinition);
            }
        );

        foreach ($this->cellRenderCallbacks as $key => $cellRenderCallback) {
            $table->setCellRenderCallback($key, $cellRenderCallback);
        }

        return $table;
    }


    // phpcs:disable

    /**
     * @param string $column
     * @param callable(CellData $cellData, RowData $rowData, ColumnDefinition $columnDefinition, TableIterator $tableIterator): Cell $callback
     *
     * @return self
     */
    public function addCellRenderCallback(string $column, $callback): self
    {
        $this->cellRenderCallbacks[$column] = $callback;

        return $this;
    }
    // phpcs:enable


    /**
     * @param callable(string $rowIdentifier): UriInterface $upUrlFactory
     * @param callable(string $rowIdentifier): UriInterface $downUrlFactory
     *
     * @return CrudTableComponentBuilder
     */
    public function addSorting(callable $upUrlFactory, callable $downUrlFactory): self
    {
        $this->addCellRenderCallback(
            'order',
            function (
                CellData $cellData,
                RowData $rowData,
                ColumnDefinition $columnDefinition,
                TableIterator $tableIterator
            ) use (
                $upUrlFactory,
                $downUrlFactory
            ): Cell {
                $rowIdentifier = $rowData->getRowIdentifier();
                $upUrl = $upUrlFactory($rowIdentifier);
                $downUrl = $downUrlFactory($rowIdentifier);

                $children = [];
                if (!$tableIterator->isFirst()) {
                    $children[] = new Link(
                        '',
                        $upUrl,
                        LinkColor::get(LinkColor::BLUE),
                        IconType::get(IconType::TRIANGLE_UP)
                    );
                } else {
                    $children[] = $this->createSpanner();
                }

                if (!$tableIterator->isLast()) {
                    $children[] = new Link(
                        '',
                        $downUrl,
                        LinkColor::get(LinkColor::BLUE),
                        IconType::get(IconType::TRIANGLE_DOWN)
                    );
                }

                return new Cell($children, $columnDefinition);
            }
        );

        return $this;
    }


    private function createSpanner(): UiComponent
    {
        return new class implements UiComponent
        {
            public function render(): string
            {
                return '<div style="display: inline-flex; width: 17px; height: 19px;">&nbsp;</div>';
            }
        };
    }
}
