<?php

namespace Tests\Unit;

use App\Services\Import\JsonObjectStream;
use PHPUnit\Framework\TestCase;

class JsonObjectStreamTest extends TestCase
{
    public function test_objects_are_found_whole_whatever_the_block_size(): void
    {
        $objects = [
            ['a' => 'x}{"\\', 'nested' => ['b' => [1, 2, ['c' => '\\"}']]]],
            ['photo' => str_repeat('QUJD', 50), 'name' => 'ქართული {ტექსტი}'],
            ['empty' => []],
        ];
        $path = tempnam(sys_get_temp_dir(), 'json');
        file_put_contents($path, "\u{FEFF}[\n".implode(",\n", array_map(fn (array $object) => json_encode($object, JSON_UNESCAPED_UNICODE), $objects))."\n]");

        foreach ([1, 2, 3, 5, 7, 64, 8192] as $blockSize) {
            $found = [];
            $count = (new JsonObjectStream($blockSize))->each($path, function (string $object) use (&$found): void {
                $found[] = json_decode($object, true);
            });

            $this->assertSame(3, $count, "block size $blockSize");
            $this->assertSame($objects, $found, "block size $blockSize");
        }

        unlink($path);
    }
}
