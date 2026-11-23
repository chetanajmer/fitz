<?php

namespace App\Services;

use App\Models\Member;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use OpenSpout\Reader\CSV\Reader as CsvReader;
use OpenSpout\Reader\XLSX\Reader as XlsxReader;
use Throwable;

/**
 * Bulk import members from CSV or XLSX. The first row must be a header row.
 * Rows are imported one by one; invalid or duplicate rows are skipped and reported.
 */
class MemberImportService
{
    public const COLUMNS = [
        'name', 'phone', 'email', 'gender', 'date_of_birth', 'joining_date', 'address', 'city',
        'fitness_goal', 'emergency_contact_name', 'emergency_contact_phone', 'blood_group',
    ];

    public function __construct(protected MemberService $members) {}

    /** @return array{imported:int, skipped:int, errors:list<string>} */
    public function import(string $path, string $extension): array
    {
        $reader = strtolower($extension) === 'xlsx' ? new XlsxReader : new CsvReader;
        $reader->open($path);

        $imported = 0;
        $skipped = 0;
        $errors = [];
        $headers = null;
        $line = 0;

        try {
            foreach ($reader->getSheetIterator() as $sheet) {
                foreach ($sheet->getRowIterator() as $row) {
                    $line++;
                    $cells = array_map(fn ($v) => $v instanceof \DateTimeInterface ? $v->format('Y-m-d') : trim((string) $v), $row->toArray());

                    if ($headers === null) {
                        $headers = array_map(fn ($h) => Str::snake(Str::lower(trim((string) $h))), $cells);
                        if (! in_array('name', $headers, true) || ! in_array('phone', $headers, true)) {
                            return ['imported' => 0, 'skipped' => 0, 'errors' => ['Header row must include at least "name" and "phone" columns.']];
                        }

                        continue;
                    }

                    if (count(array_filter($cells, fn ($c) => $c !== '')) === 0) {
                        continue; // blank line
                    }

                    $data = [];
                    foreach ($headers as $i => $header) {
                        if (in_array($header, self::COLUMNS, true)) {
                            $data[$header] = ($cells[$i] ?? '') !== '' ? $cells[$i] : null;
                        }
                    }

                    $result = $this->importRow($data, $line);
                    if ($result === true) {
                        $imported++;
                    } else {
                        $skipped++;
                        $errors[] = $result;
                    }
                }

                break; // first sheet only
            }
        } finally {
            $reader->close();
        }

        return ['imported' => $imported, 'skipped' => $skipped, 'errors' => array_values(array_unique($errors))];
    }

    protected function importRow(array $data, int $line): true|string
    {
        foreach (['date_of_birth', 'joining_date'] as $dateField) {
            if (! empty($data[$dateField])) {
                try {
                    $data[$dateField] = Carbon::parse(str_replace('/', '-', $data[$dateField]))->toDateString();
                } catch (Throwable) {
                    return "Row {$line}: invalid {$dateField} \"{$data[$dateField]}\".";
                }
            }
        }

        if (! empty($data['gender'])) {
            $data['gender'] = match (Str::lower(substr($data['gender'], 0, 1))) {
                'm' => 'male', 'f' => 'female', default => 'other',
            };
        }

        $validator = Validator::make($data, [
            'name' => 'required|string|max:255',
            'phone' => 'required|string|max:20',
            'email' => 'nullable|email|max:255',
            'date_of_birth' => 'nullable|date|before:today',
            'joining_date' => 'nullable|date',
            'blood_group' => 'nullable|string|max:5',
        ]);

        if ($validator->fails()) {
            return "Row {$line}: ".$validator->errors()->first();
        }

        if (Member::where('phone', $data['phone'])->exists()) {
            return "Row {$line}: a member with phone {$data['phone']} already exists.";
        }

        $data['joining_date'] ??= Carbon::today()->toDateString();
        $data['status'] = 'active';

        $this->members->create(array_filter($data, fn ($v) => $v !== null));

        return true;
    }

    public function sampleCsv(): string
    {
        $rows = [
            self::COLUMNS,
            ['Rahul Sharma', '9876543210', 'rahul@example.com', 'male', '1995-04-12', now()->toDateString(), '12 MG Road', 'Pune', 'Weight loss', 'Priya Sharma', '9876500000', 'B+'],
        ];

        return collect($rows)->map(fn ($r) => implode(',', array_map(fn ($c) => '"'.str_replace('"', '""', $c).'"', $r)))->implode("\n")."\n";
    }
}
