<?php

namespace App\Services\Orders;

use App\Filament\Resources\Orders\OrderResource;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Database\Eloquent\Builder;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class OrdersExport
{
    public function headings(): array
    {
        return [
            __('admin.orders.number'),
            __('admin.common.created_at'),
            __('admin.common.status'),
            __('admin.orders.customer'),
            __('admin.orders.phone'),
            __('admin.orders.city'),
            __('admin.orders.address'),
            __('admin.orders.apartment'),
            __('admin.orders.note'),
            __('admin.orders.items'),
            __('admin.orders.quantity'),
            __('admin.orders.shipping_fee'),
            __('admin.orders.total'),
            __('admin.orders.export.to_collect'),
            __('admin.orders.payment_method'),
            __('admin.orders.payment_status'),
        ];
    }

    public function row(Order $order): array
    {
        return [
            $order->number,
            $order->created_at?->format('d.m.Y H:i'),
            OrderResource::statusOptions()[$order->status] ?? $order->status,
            trim($order->first_name.' '.$order->last_name),
            $order->phone,
            $order->city ? __("checkout.cities.{$order->city}") : null,
            $order->address,
            $order->apartment,
            $order->note,
            $order->items->map(fn (OrderItem $item): string => $item->name
                .($item->options ? ' ('.implode(', ', (array) $item->options).')' : '')
                .' × '.$item->quantity)->implode("\n"),
            (int) $order->items->sum('quantity'),
            (float) $order->shipping,
            (float) $order->total,
            $order->isPaid() ? 0.0 : (float) $order->total,
            $order->paymentLabel(),
            OrderResource::paymentStatusOptions()[$order->payment_status] ?? $order->payment_status,
        ];
    }

    public function spreadsheet(Builder $orders): Spreadsheet
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle(__('admin.orders.plural'));
        $sheet->fromArray($this->headings());
        $columns = count($this->headings());
        $last = Coordinate::stringFromColumnIndex($columns);
        $sheet->getStyle("A1:{$last}1")->getFont()->setBold(true);
        $sheet->freezePane('A2');

        $line = 1;

        $orders->with('items')->lazyById(200)->each(function (Order $order) use ($sheet, &$line): void {
            $line++;

            foreach (array_values($this->row($order)) as $index => $value) {
                $cell = Coordinate::stringFromColumnIndex($index + 1).$line;

                if (is_float($value) || is_int($value)) {
                    $sheet->setCellValue($cell, $value);
                } else {
                    $sheet->setCellValueExplicit($cell, (string) $value, DataType::TYPE_STRING);
                }
            }
        });

        foreach (range(1, $columns) as $index) {
            $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($index))->setAutoSize(true);
        }

        $sheet->getColumnDimension('J')->setAutoSize(false)->setWidth(60);
        $sheet->getStyle("A1:{$last}{$line}")->getAlignment()->setVertical(Alignment::VERTICAL_TOP);
        $sheet->getStyle("J2:J{$line}")->getAlignment()->setWrapText(true);
        $sheet->getStyle("L2:N{$line}")->getNumberFormat()->setFormatCode('#,##0.00');

        if ($line > 1) {
            $sheet->setCellValue('M'.($line + 1), "=SUM(M2:M{$line})");
            $sheet->setCellValue('N'.($line + 1), "=SUM(N2:N{$line})");
            $sheet->getStyle('M'.($line + 1).':N'.($line + 1))->getFont()->setBold(true);
            $sheet->getStyle('M'.($line + 1).':N'.($line + 1))->getNumberFormat()->setFormatCode('#,##0.00');
        }

        return $spreadsheet;
    }

    public function download(Builder $orders): StreamedResponse
    {
        $spreadsheet = $this->spreadsheet($orders);

        return response()->streamDownload(
            fn () => (new Xlsx($spreadsheet))->save('php://output'),
            'orders-'.now()->format('Y-m-d-His').'.xlsx',
            ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
        );
    }
}
