<?php

namespace App\Services\Assistant\Commands;

use App\Models\User;

/**
 * Uma ação já proposta pelo assistente de IA (AiAssistantService::quickAdd)
 * e aceita pelo usuário, pronta para ser persistida. Cada implementação
 * encapsula tudo que precisa pra se executar sozinha — o executor
 * (AssistantActionExecutorService) só percorre a lista e chama execute(),
 * sem saber o que cada tipo de ação faz por dentro.
 */
interface AssistantCommand
{
    /** @return array{kind: string, summary: string, id: int} */
    public function execute(User $user): array;
}
