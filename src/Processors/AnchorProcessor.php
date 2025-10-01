<?php

declare(strict_types=1);

namespace Ineersa\Html2text\Processors;

use Ineersa\Html2text\Utilities\AnchorUtilities;

/**
 * AnchorProcessor encapsulates all anchor-depth tracking and closure logic
 * that was previously split between AnchorDepthTracker and TagProcessor.
 */
final class AnchorProcessor
{
    /** @var list<int> */
    private array $anchorStartDepths;
    /** @var list<int> */
    private array $anchorCloseDepths;
    /** @var list<array{text: string, depth: int}> */
    private array $anchorTextDepths;

    /** @var list<int> */
    private array $anchorDepthStack = [];
    /** @var list<int> */
    private array $pendingAnchorClosures = [];

    private int $anchorStartPointer = 0;
    private int $anchorClosePointer = 0;
    private int $anchorTextPointer = 0;

    /** @param list<int> $anchorStartDepths
     * @param list<int>                             $anchorCloseDepths
     * @param list<array{text: string, depth: int}> $anchorTextDepths
     */
    private function __construct(array $anchorStartDepths, array $anchorCloseDepths, array $anchorTextDepths)
    {
        $this->anchorStartDepths = $anchorStartDepths;
        $this->anchorCloseDepths = $anchorCloseDepths;
        $this->anchorTextDepths = $anchorTextDepths;
    }

    public static function fromHtml(string $html): self
    {
        return new self(...AnchorUtilities::compute($html));
    }

    public function pushStartDepth(): int
    {
        $depth = $this->nextAnchorStartDepth();
        $this->anchorDepthStack[] = $depth;

        return $depth;
    }

    public function popOnExplicitClose(): void
    {
        array_pop($this->anchorDepthStack);
        ++$this->anchorClosePointer;
    }

    public function addPendingClosureForCurrentDepth(): void
    {
        $currentDepth = $this->anchorDepthStack ? (int) end($this->anchorDepthStack) : 0;
        $this->pendingAnchorClosures[] = $currentDepth;
    }

    public function expectedCloseDepth(): ?int
    {
        return $this->anchorCloseDepths[$this->anchorClosePointer] ?? null;
    }

    public function currentDepth(): int
    {
        return $this->anchorDepthStack ? (int) end($this->anchorDepthStack) : 0;
    }

    public function flushPending(?int $triggerDepth, callable $onClose): void
    {
        while ($this->pendingAnchorClosures) {
            $expectedDepth = $this->expectedCloseDepth();
            if (null === $expectedDepth) {
                break;
            }

            $pendingDepth = (int) end($this->pendingAnchorClosures);
            if (null !== $triggerDepth && $pendingDepth < $triggerDepth) {
                break;
            }
        }
    }

    public function flushForText(int $depth, ?int $nextDepth, callable $onClose): void
    {
        while ($this->pendingAnchorClosures) {
            $expectedDepth = $this->expectedCloseDepth();
            if (null === $expectedDepth) {
                break;
            }

            $pendingDepth = (int) end($this->pendingAnchorClosures);
            if ($pendingDepth > $depth) {
                break;
            }
            if (null !== $nextDepth && $nextDepth >= $pendingDepth) {
                break;
            }
            $this->flushSingle($onClose);
        }
    }

    public function consumeTextDepth(string $text): ?int
    {
        $targetLength = mb_strlen($text);
        $buffer = '';
        $depth = null;
        $total = \count($this->anchorTextDepths);
        while ($this->anchorTextPointer < $total) {
            $entry = $this->anchorTextDepths[$this->anchorTextPointer];
            $buffer .= $entry['text'];
            $depth = $entry['depth'];
            ++$this->anchorTextPointer;
            if (mb_strlen($buffer) >= $targetLength) {
                break;
            }
        }

        return $depth;
    }

    public function peekNextTextDepth(): ?int
    {
        return $this->anchorTextDepths[$this->anchorTextPointer]['depth'] ?? null;
    }

    private function flushSingle(callable $onClose): void
    {
        array_pop($this->pendingAnchorClosures);
        // Pop the current depth being closed
        array_pop($this->anchorDepthStack);
        $onClose();
        ++$this->anchorClosePointer;
    }

    private function nextAnchorStartDepth(): int
    {
        if (isset($this->anchorStartDepths[$this->anchorStartPointer])) {
            return $this->anchorStartDepths[$this->anchorStartPointer++];
        }

        throw new \LogicException('Anchor depth calculation failed.');
    }
}
