<?php

declare(strict_types=1);

namespace smpp;

use ArrayIterator;
use IteratorIterator;
use smpp\exceptions\InvalidCollectionItem;

/**
 * Class Collection
 * @package smpp
 *
 * all item classes must implements ItemInterface
 */
class Collection extends IteratorIterator
{
    /** @var class-string class name of items */
    public const CLASSNAME = ItemInterface::class;

    /** @var ItemInterface[] */
    public array $items = [];

    /**
     * Collection constructor.
     *
     * @param bool $strict in strict mode on invalid collection item will be thrown an Exception
     * @param ItemInterface ...$items
     *
     * @throws InvalidCollectionItem
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
     *
     * @param ItemInterface $item
     *
     * @throws InvalidCollectionItem
     */
    public function addItem(ItemInterface $item): void
    {
        /** @var class-string $classname */
        $classname = static::CLASSNAME;
        if ($item instanceof $classname) {
            $this->items[] = $item;
        } else {
            if ($this->strict) {
                throw new InvalidCollectionItem('Invalid item of collection');
            }
        }
    }

    /**
     * @param ItemInterface[] $items
     * @throws InvalidCollectionItem
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
     * Shuffle items in collection
     * @return void
     */
    public function shuffle(): void
    {
        shuffle($this->items);
    }

    /**
     * @return string
     */
    public function __toString(): string
    {
        return serialize($this->items);
    }
}