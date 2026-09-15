<?php

namespace Nexor\PageBuilder\Blocks;

use Nexor\PageBuilder\Enums\Surface;

/**
 * Все типы блоков: встроенные и добавленные сайтом или другими модулями.
 */
class BlockRegistry
{
    /** @var array<string, Block> */
    protected array $blocks = [];

    public function register(Block $block): void
    {
        $this->blocks[$block->type()] = $block;
    }

    public function find(string $type): ?Block
    {
        return $this->blocks[$type] ?? null;
    }

    /**
     * @return array<string, Block>
     */
    public function all(): array
    {
        return $this->blocks;
    }

    /**
     * Блоки, доступные на поверхности, в порядке регистрации.
     *
     * @return array<string, Block>
     */
    public function for(Surface $surface): array
    {
        return array_filter($this->blocks, fn (Block $block) => in_array($surface, $block->surfaces(), true));
    }
}
