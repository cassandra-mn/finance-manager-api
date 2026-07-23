<?php

namespace Database\Seeders;

use App\Actions\Recurrences\GenerateRecurringTransactionsAction;
use App\Enum\AccountType;
use App\Enum\RecurrenceFrequency;
use App\Enum\TransactionEntryType;
use App\Enum\TransactionStatus;
use App\Enum\TransactionType;
use App\Models\Account;
use App\Models\Budget;
use App\Models\Category;
use App\Models\Recurrence;
use App\Models\Transaction;
use App\Models\User;
use App\Support\RecurrenceDateResolver;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Massa de dados realista para o usuário demo (demo@demo.com / demo), usada
 * para exibição no front. Reexecutável: apaga (force delete) todos os dados
 * do usuário demo antes de recriá-los, para nunca duplicar em re-seeds.
 */
class DemoSeeder extends Seeder
{
    private const EMAIL = 'demo@demo.com';

    public function run(): void
    {
        DB::transaction(function (): void {
            $user = $this->resetDemoUser();

            $accounts = $this->createAccounts($user);
            $categories = $this->createCategories($user);

            $this->createRecurrences($user, $accounts, $categories);
            $this->createOneOffTransactions($user, $accounts, $categories);
            $this->createBudgets($user, $categories);

            app(GenerateRecurringTransactionsAction::class)->execute();
        });
    }

    private function resetDemoUser(): User
    {
        User::withoutEvents(function (): void {
            User::query()->where('email', self::EMAIL)->forceDelete();
        });

        return User::factory()->create([
            'name' => 'Usuário Demo',
            'email' => self::EMAIL,
            'password' => 'demo',
            'email_verified_at' => now(),
        ]);
    }

    /** @return array<string, Account> */
    private function createAccounts(User $user): array
    {
        return [
            'checking' => Account::factory()->for($user)->create([
                'name' => 'Conta Corrente',
                'type' => AccountType::CHECKING,
                'initial_balance_cents' => 250000,
                'color' => '#2563eb',
            ]),
            'savings' => Account::factory()->for($user)->create([
                'name' => 'Poupança',
                'type' => AccountType::SAVINGS,
                'initial_balance_cents' => 1000000,
                'color' => '#16a34a',
            ]),
            'wallet' => Account::factory()->for($user)->create([
                'name' => 'Carteira',
                'type' => AccountType::WALLET,
                'initial_balance_cents' => 15000,
                'color' => '#f59e0b',
            ]),
            'credit_card' => Account::factory()->for($user)->create([
                'name' => 'Cartão de Crédito',
                'type' => AccountType::CREDIT_CARD,
                'initial_balance_cents' => 0,
                'color' => '#dc2626',
            ]),
        ];
    }

    /** @return array<string, Category> */
    private function createCategories(User $user): array
    {
        $income = [
            'Salário' => ['color' => '#16a34a', 'icon' => 'wallet'],
            'Freelance' => ['color' => '#0ea5e9', 'icon' => 'briefcase'],
            'Investimentos' => ['color' => '#22c55e', 'icon' => 'trending-up'],
        ];

        $expense = [
            'Moradia' => ['color' => '#b45309', 'icon' => 'home'],
            'Alimentação' => ['color' => '#ea580c', 'icon' => 'utensils'],
            'Transporte' => ['color' => '#0891b2', 'icon' => 'car'],
            'Saúde' => ['color' => '#e11d48', 'icon' => 'heart-pulse'],
            'Educação' => ['color' => '#7c3aed', 'icon' => 'graduation-cap'],
            'Lazer' => ['color' => '#db2777', 'icon' => 'popcorn'],
            'Assinaturas' => ['color' => '#4f46e5', 'icon' => 'repeat'],
            'Compras' => ['color' => '#65a30d', 'icon' => 'shopping-bag'],
        ];

        $categories = [];

        foreach ($income as $name => $meta) {
            $categories[$name] = Category::factory()->for($user)->income()->create([
                'name' => $name,
                'color' => $meta['color'],
                'icon' => $meta['icon'],
            ]);
        }

        foreach ($expense as $name => $meta) {
            $categories[$name] = Category::factory()->for($user)->expense()->create([
                'name' => $name,
                'color' => $meta['color'],
                'icon' => $meta['icon'],
            ]);
        }

        return $categories;
    }

    /**
     * @param  array<string, Account>  $accounts
     * @param  array<string, Category>  $categories
     */
    private function createRecurrences(User $user, array $accounts, array $categories): void
    {
        $today = Carbon::today();

        $rules = [
            [
                'account' => 'checking',
                'category' => 'Salário',
                'type' => TransactionType::INCOME,
                'entry_type' => TransactionEntryType::FIXED,
                'description' => 'Salário mensal',
                'amount_cents' => 550000,
                'frequency' => RecurrenceFrequency::MONTHLY,
                'start_date' => $today->copy()->subMonths(5)->day(5),
                'end_date' => null,
                'paused' => false,
            ],
            [
                'account' => 'checking',
                'category' => 'Moradia',
                'type' => TransactionType::EXPENSE,
                'entry_type' => TransactionEntryType::FIXED,
                'description' => 'Aluguel',
                'amount_cents' => 180000,
                'frequency' => RecurrenceFrequency::MONTHLY,
                'start_date' => $today->copy()->subMonths(5)->day(10),
                'end_date' => null,
                'paused' => false,
            ],
            [
                'account' => 'wallet',
                'category' => 'Saúde',
                'type' => TransactionType::EXPENSE,
                'entry_type' => TransactionEntryType::FIXED,
                'description' => 'Academia',
                'amount_cents' => 9900,
                'frequency' => RecurrenceFrequency::WEEKLY,
                'start_date' => $today->copy()->subWeeks(10),
                'end_date' => null,
                'paused' => false,
            ],
            [
                'account' => 'checking',
                'category' => 'Freelance',
                'type' => TransactionType::INCOME,
                'entry_type' => TransactionEntryType::VARIABLE,
                'description' => 'Pagamento quinzenal — projeto freelance',
                'amount_cents' => 90000,
                'frequency' => RecurrenceFrequency::FORTNIGHTLY,
                'start_date' => $today->copy()->subMonths(3),
                'end_date' => null,
                'paused' => false,
            ],
            [
                'account' => 'checking',
                'category' => 'Transporte',
                'type' => TransactionType::EXPENSE,
                'entry_type' => TransactionEntryType::FIXED,
                'description' => 'Seguro do carro',
                'amount_cents' => 120000,
                'frequency' => RecurrenceFrequency::YEARLY,
                'start_date' => $today->copy()->subYear()->addDays(18),
                'end_date' => null,
                'paused' => false,
            ],
            [
                'account' => 'credit_card',
                'category' => 'Assinaturas',
                'type' => TransactionType::EXPENSE,
                'entry_type' => TransactionEntryType::FIXED,
                'description' => 'Assinatura streaming',
                'amount_cents' => 3990,
                'frequency' => RecurrenceFrequency::MONTHLY,
                'start_date' => $today->copy()->subMonths(4)->day(15),
                'end_date' => null,
                'paused' => true,
            ],
        ];

        foreach ($rules as $rule) {
            $account = $accounts[$rule['account']];
            $category = $categories[$rule['category']];
            $startDate = $rule['start_date']->copy()->startOfDay();

            // Anda a régua de ocorrências a partir de start_date, gravando cada
            // uma como transação paga no passado, até alcançar a primeira
            // ocorrência de hoje em diante (que fica como next_due_date da
            // regra — o gerador de ocorrências cuida do resto a partir daí).
            $occurrence = $startDate->copy();
            $recurrence = Recurrence::factory()->for($user)->for($account)->for($category)->create([
                'type' => $rule['type'],
                'entry_type' => $rule['entry_type'],
                'description' => $rule['description'],
                'amount_cents' => $rule['amount_cents'],
                'frequency' => $rule['frequency'],
                'start_date' => $startDate,
                'next_due_date' => $startDate,
                'end_date' => $rule['end_date'],
                'notes' => null,
                'is_active' => ! $rule['paused'],
            ]);

            while ($occurrence->lt($today)) {
                Transaction::create([
                    'user_id' => $user->id,
                    'account_id' => $account->id,
                    'category_id' => $category->id,
                    'recurrence_id' => $recurrence->id,
                    'type' => $rule['type'],
                    'entry_type' => $rule['entry_type'],
                    'status' => TransactionStatus::PAID,
                    'description' => $rule['description'],
                    'amount_cents' => $rule['amount_cents'],
                    'due_date' => $occurrence->toDateString(),
                    'paid_at' => $occurrence->copy()->addHours(9),
                    'notes' => null,
                ]);

                $occurrence = RecurrenceDateResolver::next($rule['frequency'], $startDate, $occurrence);
            }

            $recurrence->update(['next_due_date' => $occurrence]);

            if ($rule['paused']) {
                // A regra foi pausada com o histórico já registrado acima;
                // não deve avançar mais nem ser processada pelo gerador.
                $recurrence->update(['is_active' => false]);
            }
        }
    }

    /**
     * @param  array<string, Account>  $accounts
     * @param  array<string, Category>  $categories
     */
    private function createOneOffTransactions(User $user, array $accounts, array $categories): void
    {
        $today = Carbon::today();

        $expenseSamples = [
            'Alimentação' => ['Supermercado Pão de Açúcar', 'iFood', 'Padaria do Bairro', 'Restaurante Sabor Caseiro'],
            'Transporte' => ['Uber', 'Posto Ipiranga', 'Estacionamento Shopping', '99 Táxi'],
            'Lazer' => ['Cinema Cinemark', 'Ingresso de show', 'Parque de diversões'],
            'Compras' => ['Loja Renner', 'Amazon', 'Livraria Cultura'],
            'Saúde' => ['Farmácia São Paulo', 'Consulta médica', 'Exames laboratoriais'],
            'Educação' => ['Curso online', 'Livro técnico', 'Material escolar'],
        ];

        $incomeSamples = [
            'Freelance' => ['Projeto de design', 'Consultoria pontual'],
            'Investimentos' => ['Dividendos', 'Rendimento da poupança'],
        ];

        foreach ($expenseSamples as $categoryName => $descriptions) {
            foreach ($descriptions as $description) {
                $this->createOneOffTransaction($user, $accounts, $categories[$categoryName], TransactionType::EXPENSE, $description);
            }
        }

        foreach ($incomeSamples as $categoryName => $descriptions) {
            foreach ($descriptions as $description) {
                $this->createOneOffTransaction($user, $accounts, $categories[$categoryName], TransactionType::INCOME, $description);
            }
        }

        // Casos específicos para exibir os demais estados possíveis no front.
        Transaction::create([
            'user_id' => $user->id,
            'account_id' => $accounts['checking']->id,
            'category_id' => $categories['Compras']->id,
            'type' => TransactionType::EXPENSE,
            'entry_type' => TransactionEntryType::SINGLE,
            'status' => TransactionStatus::CANCELLED,
            'description' => 'Compra online cancelada',
            'amount_cents' => 25990,
            'due_date' => $today->copy()->subDays(6)->toDateString(),
            'notes' => 'Pedido cancelado pelo vendedor.',
        ]);

        Transaction::create([
            'user_id' => $user->id,
            'account_id' => $accounts['credit_card']->id,
            'category_id' => $categories['Saúde']->id,
            'type' => TransactionType::EXPENSE,
            'entry_type' => TransactionEntryType::SINGLE,
            'status' => TransactionStatus::PENDING,
            'description' => 'Consulta odontológica (atrasada)',
            'amount_cents' => 35000,
            'due_date' => $today->copy()->subDays(4)->toDateString(),
            'notes' => null,
        ]);
    }

    /** @param array<string, Account> $accounts */
    private function createOneOffTransaction(User $user, array $accounts, Category $category, TransactionType $type, string $description): void
    {
        $today = Carbon::today();
        $account = $type === TransactionType::INCOME ? $accounts['checking'] : $accounts[fake()->randomElement(['checking', 'wallet', 'credit_card'])];
        $dueDate = $today->copy()->subDays(fake()->numberBetween(-15, 90));
        $amountCents = $type === TransactionType::INCOME
            ? fake()->numberBetween(20000, 150000)
            : fake()->numberBetween(2000, 60000);

        $status = match (true) {
            $dueDate->gt($today) => TransactionStatus::PENDING,
            default => fake()->randomElement([
                TransactionStatus::PAID,
                TransactionStatus::PAID,
                TransactionStatus::PAID,
                TransactionStatus::PENDING,
            ]),
        };

        Transaction::create([
            'user_id' => $user->id,
            'account_id' => $account->id,
            'category_id' => $category->id,
            'type' => $type,
            'entry_type' => TransactionEntryType::SINGLE,
            'status' => $status,
            'description' => $description,
            'amount_cents' => $amountCents,
            'due_date' => $dueDate->toDateString(),
            'paid_at' => $status === TransactionStatus::PAID ? $dueDate->copy()->addHours(fake()->numberBetween(8, 20)) : null,
            'notes' => null,
        ]);
    }

    /** @param array<string, Category> $categories */
    private function createBudgets(User $user, array $categories): void
    {
        $budgeted = [
            'Moradia' => 200000,
            'Alimentação' => 120000,
            'Transporte' => 60000,
            'Lazer' => 40000,
        ];

        $currentMonth = Carbon::today();
        $previousMonth = $currentMonth->copy()->subMonthNoOverflow();

        foreach ([$currentMonth, $previousMonth] as $reference) {
            foreach ($budgeted as $categoryName => $amountCents) {
                Budget::factory()->for($user)->for($categories[$categoryName])->forPeriod($reference->month, $reference->year)->create([
                    'amount_cents' => $amountCents,
                ]);
            }
        }
    }
}
