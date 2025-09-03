<?php

namespace App\Exports;

use App\Models\Note;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithCustomStartCell;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;
use Illuminate\Support\Collection;

class NotesExport implements
    FromCollection,
    WithHeadings,
    WithMapping,
    WithStyles,
    WithTitle,
    WithCustomStartCell,
    WithEvents
{
    protected $filter;

    public function __construct($filter = 'all')
    {
        $this->filter = $filter;
    }

    /**
     * Get the collection of data to export
     */
    public function collection(): Collection
    {
        $query = Note::query()->orderBy('created_at', 'desc');

        // Apply filters
        match ($this->filter) {
            'active' => $query->where('active', true),
            'inactive' => $query->where('active', false),
            'today' => $query->whereDate('created_at', today()),
            'week' => $query->where('created_at', '>=', now()->subWeek()),
            'month' => $query->where('created_at', '>=', now()->subMonth()),
            default => $query, // All notes
        };

        return $query->get();
    }

    /**
     * Define the headings for the Excel file
     */
    public function headings(): array
    {
        return [
            'ID',
            'Title',
            'Content',
            'Status',
            'Created Date',
            'Updated Date',
        ];
    }

    /**
     * Map the data for each row
     */
    public function map($note): array
    {
        return [
            $note->id,
            $note->title,
            $note->content ?? 'No content',
            $note->active ? 'Active' : 'Inactive',
            $note->created_at?->format('Y-m-d H:i:s'),
            $note->updated_at?->format('Y-m-d H:i:s'),
        ];
    }

    /**
     * Apply styles to the worksheet
     */
    public function styles(Worksheet $sheet): array
    {
        return [
            // Style the header row
            1 => [
                'font' => [
                    'bold' => true,
                    'size' => 12,
                ],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => [
                        'argb' => 'FF4CAF50', // Green background
                    ],
                ],
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_CENTER,
                ],
            ],
        ];
    }

    /**
     * Set the title of the worksheet
     */
    public function title(): string
    {
        return 'Notes Report';
    }

    /**
     * Set the starting cell for the data
     */
    public function startCell(): string
    {
        return 'A1';
    }

    /**
     * Register events for additional formatting
     */
    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function(AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();

                // Auto-size columns
                foreach (range('A', 'F') as $column) {
                    $sheet->getColumnDimension($column)->setAutoSize(true);
                }

                // Add borders to all data
                $highestRow = $sheet->getHighestRow();
                $highestColumn = $sheet->getHighestColumn();

                $sheet->getStyle('A1:' . $highestColumn . $highestRow)
                    ->getBorders()
                    ->getAllBorders()
                    ->setBorderStyle(Border::BORDER_THIN);

                // Set white font color for header
                $sheet->getStyle('A1:F1')->getFont()->getColor()->setARGB('FFFFFFFF');

                // Add title above the table
                $sheet->insertNewRowBefore(1, 2);
                $sheet->mergeCells('A1:F1');
                $sheet->setCellValue('A1', 'Notes Export Report - ' . now()->format('Y-m-d H:i:s'));
                $sheet->getStyle('A1')->applyFromArray([
                    'font' => [
                        'bold' => true,
                        'size' => 14,
                    ],
                    'alignment' => [
                        'horizontal' => Alignment::HORIZONTAL_CENTER,
                    ],
                ]);

                // Add summary row
                $sheet->insertNewRowBefore(2, 1);
                $totalNotes = $this->collection()->count();
                $activeNotes = $this->collection()->where('active', true)->count();
                $inactiveNotes = $this->collection()->where('active', false)->count();

                $sheet->setCellValue('A2', "Summary: Total: {$totalNotes} | Active: {$activeNotes} | Inactive: {$inactiveNotes}");
                $sheet->mergeCells('A2:F2');
                $sheet->getStyle('A2')->applyFromArray([
                    'font' => [
                        'italic' => true,
                        'size' => 10,
                    ],
                    'alignment' => [
                        'horizontal' => Alignment::HORIZONTAL_CENTER,
                    ],
                ]);
            },
        ];
    }
}
