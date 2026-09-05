<?php

namespace App\Support\Exports;

use Illuminate\Support\Str;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\CSV\Options as CsvOptions;
use OpenSpout\Writer\CSV\Writer as CsvWriter;
use OpenSpout\Writer\XLSX\Writer as XlsxWriter;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Schrijft een Export weg als CSV of Excel, rechtstreeks naar de browser.
 *
 * Streamend: de rijen worden een voor een geschreven, dus een groot overzicht
 * hoeft nooit helemaal in het geheugen.
 *
 * CSV met puntkomma en een BOM: zo opent Nederlands Excel het bestand meteen
 * goed, met de kolommen los en de accenten heel. Met een komma als scheider
 * zou Excel alles in één kolom proppen, want de komma is hier het decimaalteken.
 */
class ExportWriter
{
    public const FORMATS = ['csv', 'xlsx'];

    /** @param  array{from?: string|null, to?: string|null}  $filters */
    public function download(Export $export, string $format, array $filters): StreamedResponse
    {
        $format = in_array($format, self::FORMATS, strict: true) ? $format : 'xlsx';

        $bestandsnaam = Str::slug($export->title()).'-'.now()->format('Y-m-d').'.'.$format;

        return response()->streamDownload(function () use ($export, $format, $filters) {
            $writer = $format === 'csv' ? $this->csv() : new XlsxWriter;

            $writer->openToFile('php://output');

            // Excel krijgt alle tabbladen; CSV kent er maar één en krijgt het hoofdtabblad.
            $sheets = ($format === 'xlsx' && $export instanceof WorkbookExport)
                ? $export->sheets($filters)
                : [new Sheet($export->title(), $export->headings(), $export->rows($filters))];

            foreach ($sheets as $index => $sheet) {
                if ($index > 0) {
                    $writer->addNewSheetAndMakeItCurrent();
                }

                if ($writer instanceof XlsxWriter) {
                    $writer->getCurrentSheet()->setName(mb_substr($sheet->title, 0, 31));
                }

                $writer->addRow(Row::fromValues($sheet->headings));

                foreach ($sheet->rows as $rij) {
                    $writer->addRow(Row::fromValues(array_map(
                        fn ($waarde) => $waarde ?? '',
                        array_values($rij),
                    )));
                }
            }

            $writer->close();
        }, $bestandsnaam, [
            'Content-Type' => $format === 'csv'
                ? 'text/csv; charset=UTF-8'
                : 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    protected function csv(): CsvWriter
    {
        return new CsvWriter(new CsvOptions(FIELD_DELIMITER: ';', SHOULD_ADD_BOM: true));
    }
}
