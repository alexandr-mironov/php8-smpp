<?php


namespace smpp;


use ArrayIterator;
use Exception;
use IteratorIterator;

/**
 * Class Collection
 * @package smpp
 */
abstract class Collection extends IteratorIterator
{
    /** @var array */
    public array $items = [];

    /**
     * Collection constructor.
     * @param string $class Name of class
     * @param bool $strict in strict mode on invalid collection item will be thrown an Exception
     * @param ItemInterface ...$items
     * @throws Exception
     */
    public function __construct(
        protected string $class,
        public bool $strict = false,
        ItemInterface ...$items,
    )
    {
        foreach ($items as $item) {
            $this->addItem($item);
        }
        parent::__construct(new ArrayIterator($this->items));
    }

    /**
     * Add item to collection
     * @param ItemInterface $item
     * @throws Exception
     */
    public function addItem(ItemInterface $item): void
    {
        if ($item instanceof $this->class) {
            $this->items[] = $item;
        } else {
            if ($this->strict) {
                throw new Exception('Invalid item of collection');
            }
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
     *
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