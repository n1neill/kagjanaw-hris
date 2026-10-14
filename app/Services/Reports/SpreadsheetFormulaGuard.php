<?php

namespace App\Services\Reports;

use PhpOffice\PhpSpreadsheet\Cell\IValueBinder;
use PhpOffice\PhpSpreadsheet\Cell\StringValueBinder;

/**
 * Keeps names and other typed-in text from running as formulas when an
 * export is opened in Excel or LibreOffice (CSV / formula injection).
 */
class SpreadsheetFormulaGuard
{
    /**
     * A CSV cell starting with one of these is read as a formula.
     *
     * @var list<string>
     */
    private const FORMULA_PREFIXES = ['=', '+', '-', '@', "\t", "\r"];

    /**
     * Prefix text that would be read as a formula with an apostrophe, which
     * spreadsheets show as plain text. Numbers, negative ones included, are kept.
     *
     * @param  array<int, string|int|float|null>  $values
     * @return array<int, string|int|float|null>
     */
    public static function csvRow(array $values): array
    {
        return array_map(
            fn (string|int|float|null $value): string|int|float|null => is_string($value)
                && $value !== ''
                && ! is_numeric($value)
                && in_array($value[0], self::FORMULA_PREFIXES, true)
                    ? "'".$value
                    : $value,
            $values,
        );
    }

    /**
     * For .xlsx: text is stored as text, never as a formula; numbers stay numbers.
     */
    public static function valueBinder(): IValueBinder
    {
        return (new StringValueBinder)
            ->setNullConversion(false)
            ->setBooleanConversion(false)
            ->setNumericConversion(false);
    }
}
