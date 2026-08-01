<?php

namespace App\Data\StatementImports;

use App\Enum\StatementImportFormat;
use App\Http\Requests\StatementImports\StoreStatementImportRequest;

final readonly class CreateStatementImportData
{
    public function __construct(
        public StatementImportFormat $format,
        public string $originalFilename,
        public ?CsvImportMapping $csvMapping,
    ) {}

    public static function fromRequest(StoreStatementImportRequest $request): self
    {
        $format = StatementImportFormat::from($request->string('format')->toString());

        return new self(
            format: $format,
            originalFilename: (string) $request->file('file')?->getClientOriginalName(),
            csvMapping: $format === StatementImportFormat::CSV ? CsvImportMapping::fromRequest($request) : null,
        );
    }
}
