<?php

namespace Tests\Unit;

use App\Support\StandardAxes;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class StandardAxesTest extends TestCase
{
    /**
     * @return array<string, array{0: string, 1: string, 2: ?string}>
     */
    public static function values(): array
    {
        return [
            'storage spaced' => [StandardAxes::STORAGE, '256 GB', '256 GB'],
            'storage glued' => [StandardAxes::STORAGE, '128GB', '128 GB'],
            'storage with notes' => [StandardAxes::STORAGE, '64GB (eMMC ფლეშ მეხსიერება)', '64 GB'],
            'storage ssd' => [StandardAxes::STORAGE, '512GB SSD M.2 2242 PCIe 4.0x4 NVMe', '512 GB'],
            'storage terabyte' => [StandardAxes::STORAGE, '1 TB', '1 TB'],
            'storage decimal terabytes' => [StandardAxes::STORAGE, '1000 GB', '1 TB'],
            'storage microsd support' => [StandardAxes::STORAGE, 'microSD, 256 GB-მდე', null],
            'storage ram stick' => [StandardAxes::STORAGE, '1x 16GB SODIMM DDR5-4800', null],
            'storage in megabytes' => [StandardAxes::STORAGE, '1024 MB', null],
            'screen quote' => [StandardAxes::SCREEN, '55"', '55"'],
            'screen curly quote' => [StandardAxes::SCREEN, '86”', '86"'],
            'screen inches word' => [StandardAxes::SCREEN, '6.70 inches', '6.7"'],
            'screen with resolution' => [StandardAxes::SCREEN, '16" WUXGA', '16"'],
            'screen bare number' => [StandardAxes::SCREEN, '65', '65"'],
            'screen range' => [StandardAxes::SCREEN, '60-120"', null],
            'screen up to' => [StandardAxes::SCREEN, '4.7–7.9 ინჩამდე', null],
            'screen dimensions' => [StandardAxes::SCREEN, '203*114 სმ', null],
            'screen centimetres' => [StandardAxes::SCREEN, '6.1სმ', null],
            'capacity litres' => [StandardAxes::CAPACITY, '411 ლ', '411 ლ'],
            'capacity glued comma' => [StandardAxes::CAPACITY, '1,5ლ', '1.5 ლ'],
            'capacity latin' => [StandardAxes::CAPACITY, '11 L', '11 ლ'],
            'capacity small litres' => [StandardAxes::CAPACITY, '0.25 ლ', '250 მლ'],
            'capacity millilitres' => [StandardAxes::CAPACITY, '127,00 მლ', '127 მლ'],
            'capacity kilograms' => [StandardAxes::CAPACITY, '8 კგ', '8 კგ'],
            'capacity two volumes' => [StandardAxes::CAPACITY, '2.1 ლ, 0.7ლ', null],
            'capacity slash' => [StandardAxes::CAPACITY, '1.4 / 3.8 ლიტრი', null],
            'capacity tonnes' => [StandardAxes::CAPACITY, '4 ტ', null],
            'capacity storage unit' => [StandardAxes::CAPACITY, '128 GB', null],
        ];
    }

    #[DataProvider('values')]
    public function test_values_are_read_into_one_standard(string $axis, string $value, ?string $expected): void
    {
        $this->assertSame($expected, StandardAxes::parse($axis, $value)['ka'] ?? null);
    }

    public function test_names_give_storage_and_screen_size_only_when_clear(): void
    {
        $this->assertSame('256 GB', StandardAxes::fromName(StandardAxes::STORAGE, 'Apple iPhone 18 Pro 256GB Black')['ka']);
        $this->assertSame('256 GB', StandardAxes::fromName(StandardAxes::STORAGE, 'Samsung Galaxy A55 8/256GB Blue')['ka']);
        $this->assertSame('512 GB', StandardAxes::fromName(StandardAxes::STORAGE, 'Lenovo IdeaPad 16GB RAM 512GB SSD')['ka']);
        $this->assertNull(StandardAxes::fromName(StandardAxes::STORAGE, 'Kingston 8GB DDR4'));
        $this->assertSame('55"', StandardAxes::fromName(StandardAxes::SCREEN, 'Samsung 55" QLED Q60D')['ka']);
        $this->assertNull(StandardAxes::fromName(StandardAxes::SCREEN, 'Samsung UE55CU7100 Crystal UHD'));
        $this->assertNull(StandardAxes::fromName(StandardAxes::CAPACITY, 'Bosch 350L fridge'));
    }

    public function test_values_sort_in_their_natural_order(): void
    {
        $sorts = array_map(fn (string $value) => StandardAxes::parse(StandardAxes::STORAGE, $value)['sort'], ['64 GB', '128 GB', '512 GB', '1 TB', '2 TB']);

        $this->assertSame($sorts, array_values(array_unique($sorts)));
        $this->assertSame($sorts, collect($sorts)->sort()->values()->all());
    }

    public function test_only_the_listed_attributes_feed_an_axis(): void
    {
        $this->assertTrue(StandardAxes::isSource(StandardAxes::SCREEN, 'diagonali'));
        $this->assertFalse(StandardAxes::isSource(StandardAxes::SCREEN, 'televizoris-diagonali'));
        $this->assertFalse(StandardAxes::isSource(StandardAxes::SCREEN, StandardAxes::SCREEN));
        $this->assertTrue(StandardAxes::isSource(StandardAxes::CAPACITY, 'maksimaluri-chatvirtva'));
        $this->assertFalse(StandardAxes::isSource(StandardAxes::CAPACITY, 'maksimaluri-datvirtva'));
    }
}
