<?php

return [
    /*
     * Tamanho máximo (em KB) de um arquivo de extrato (OFX/CSV) aceito pelo
     * endpoint de importação.
     */
    'max_file_size_kb' => (int) env('STATEMENT_IMPORTS_MAX_FILE_SIZE_KB', 5120),
];
