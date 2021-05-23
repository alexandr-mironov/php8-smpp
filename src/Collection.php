<?php


namespace smpp;


use ArrayIterator;
use Exception;
use IteratorIterator;

/**
 * Class Collection
 * @package smpp
 */
class Collection extends IteratorIterator
{
    /** @var string class name of items */
    public const CLASSNAME = ItemInterface::class;

    /** @var array */
    public array $items = [];

    /**
     * Collection constructor.
     * @param bool $strict in strict mode on invalid collection item will be thrown an Exception
     * @param ItemInterface ...$items
     * @throws Exception
     */
    public function __construct(
        public bool $strict = false,
        ItemInterface ...$items,
    )
    {
        $this->addItems($items);
        parent::__construct(new ArrayIterator($this->items));
    }

    /**
     * Add item to collection
     * @param ItemInterface $item
     * @throws Exception
     */
    public function addItem(ItemInterface $item): void
    {
        if ($item instanceof static::CLASSNAME) {
            $this->items[] = $item;
        } else {
            if ($this->strict) {
                throw new Exception('Invalid item of collection');
            }
        }
    }

    /**
     * @param ItemInterface[] $items
     * @throws Exception
     */
    public function addItems(array $items): void
    {
        foreach ($items as $item) {
            $this->addItem($item);
        }
    }

    /**
     * remove last element from collection
     */
    public function pop(): void
    {
        unset($this->items[$this->count() - 1]);
    }

    /**
     * count items in collection
     * @return int
     */
    public function count(): int
    {
        return count($this->items);
    }

    /**
     * cleanup collection (remove all elements)
     */
    public function clear(): void
    {
        $this->items = [];
    }

    /**
     *
     */
    public function __toString(): string
    {
        return serialize($this->items);
    }
}