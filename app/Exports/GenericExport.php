<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

abstract class GenericExport implements FromCollection, WithHeadings, WithMapping, WithStyles, WithTitle
{
    protected $collection;
    protected string $title;
    protected array $headings;
    protected array $columnFormats = [];

    /**
     * Конструктор
     */
    public function __construct($collection, string $title, array $headings)
    {
        $this->collection = $collection;
        $this->title = $title;
        $this->headings = $headings;
    }

    /**
     * @return \Illuminate\Support\Collection
     */
    public function collection()
    {
        return $this->collection;
    }

    /**
     * Заголовки столбцов
     */
    public function headings(): array
    {
        return $this->headings;
    }

    /**
     * Преобразование данных для экспорта
     */
    public function map($row): array
    {
        // Базовое преобразование - переопределите в дочерних классах
        return (array) $row;
    }

    /**
     * Название листа
     */
    public function title(): string
    {
        return $this->title;
    }

    /**
     * Стили для Excel
     */
    public function styles(Worksheet $sheet)
    {
        // Стиль для заголовков
        $sheet->getStyle('A1:' . $sheet->getHighestColumn() . '1')->applyFromArray([
            'font' => [
                'bold' => true,
                'color' => ['rgb' => 'FFFFFF'],
            ],
            'fill' => [
                'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                'startColor' => ['rgb' => '2C3E50'],
            ],
            'alignment' => [
                'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
                'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER,
            ],
            'borders' => [
                'allBorders' => [
                    'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                    'color' => ['rgb' => '000000'],
                ],
            ],
        ]);

        // Автоматическая ширина столбцов
        foreach (range('A', $sheet->getHighestColumn()) as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }

        // Чередование цветов строк
        $lastRow = $sheet->getHighestRow();
        for ($row = 2; $row <= $lastRow; $row++) {
            $color = $row % 2 == 0 ? 'F8F9FA' : 'FFFFFF';
            $sheet->getStyle("A{$row}:{$sheet->getHighestColumn()}{$row}")
                ->getFill()
                ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                ->getStartColor()
                ->setARGB($color);
        }

        // Форматы для столбцов с датами и числами
        foreach ($this->columnFormats as $column => $format) {
            $sheet->getStyle($column . '2:' . $column . $lastRow)
                ->getNumberFormat()
                ->setFormatCode($format);
        }

        return [];
    }

    /**
     * Установить форматы для столбцов
     */
    public function setColumnFormats(array $formats): self
    {
        $this->columnFormats = $formats;
        return $this;
    }
}
