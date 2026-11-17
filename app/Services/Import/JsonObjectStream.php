<?php

namespace App\Services\Import;

class JsonObjectStream
{
    public function __construct(public int $blockSize = 8 * 1024 * 1024) {}

    /**
     * @param  callable(string): void  $emit
     */
    public function each(string $path, callable $emit): int
    {
        $handle = fopen($path, 'rb');

        if ($handle === false) {
            return 0;
        }

        $count = 0;
        $current = '';
        $depth = 0;
        $inString = false;
        $escaped = false;
        $first = true;

        try {
            while (! feof($handle)) {
                $block = (string) fread($handle, $this->blockSize);

                if ($first) {
                    $block = (string) preg_replace('/^\xEF\xBB\xBF/', '', $block);
                    $first = false;
                }

                $length = strlen($block);
                $start = $depth > 0 ? 0 : null;
                $i = 0;

                while ($i < $length) {
                    if ($escaped) {
                        $escaped = false;
                        $i++;

                        continue;
                    }

                    if ($inString) {
                        $i += strcspn($block, '"\\', $i);

                        if ($i >= $length) {
                            break;
                        }

                        if ($block[$i] === '\\') {
                            $escaped = true;
                        } else {
                            $inString = false;
                        }

                        $i++;

                        continue;
                    }

                    $i += strcspn($block, '"{}', $i);

                    if ($i >= $length) {
                        break;
                    }

                    $char = $block[$i];

                    if ($char === '"') {
                        $inString = true;
                    } elseif ($char === '{') {
                        if ($depth === 0) {
                            $start = $i;
                        }

                        $depth++;
                    } elseif ($depth > 0) {
                        $depth--;

                        if ($depth === 0) {
                            $emit($current.substr($block, (int) $start, $i - (int) $start + 1));
                            $current = '';
                            $start = null;
                            $count++;
                        }
                    }

                    $i++;
                }

                if ($depth > 0 && $start !== null) {
                    $current .= substr($block, $start);
                }
            }
        } finally {
            fclose($handle);
        }

        return $count;
    }
}
