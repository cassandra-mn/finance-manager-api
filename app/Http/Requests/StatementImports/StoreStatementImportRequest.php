<?php

namespace App\Http\Requests\StatementImports;

use App\Enum\StatementImportFormat;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class StoreStatementImportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'max:'.(int) config('statement_imports.max_file_size_kb'), 'extensions:ofx,csv,txt'],
            'format' => ['required', new Enum(StatementImportFormat::class)],

            // Obrigatórios apenas quando format = csv: cada banco exporta as
            // colunas em posições diferentes, então o mapeamento é informado
            // a cada importação. Colunas são sempre 0-indexed.
            'delimiter' => ['sometimes', 'string', 'max:1'],
            'has_header' => ['sometimes', 'boolean'],
            'date_column' => ['required_if:format,csv', 'integer', 'min:0'],
            'description_column' => ['required_if:format,csv', 'integer', 'min:0'],
            'amount_column' => ['required_if:format,csv', 'integer', 'min:0'],
            'date_format' => ['required_if:format,csv', 'string', 'max:20'],
        ];
    }
}
