<?php

declare(strict_types=1);

namespace Moves\Services\Platform;

use Moves\Core\Config;

/**
 * Tenant-scoped sample content for the ERP visual foundation.
 *
 * The provider never writes to the database. Demo content is available only
 * when APP_ENV=development and ERP_DEMO_DATA_ENABLED is true.
 */
final class ErpDemoDataProvider
{
    public static function enabled(): bool
    {
        return Config::isDevelopment()
            && filter_var(Config::get('ERP_DEMO_DATA_ENABLED', true), FILTER_VALIDATE_BOOL);
    }

    /** @param list<array<string,mixed>> $condominiums
     *  @return array{balance:string,receivable:string,payable:string,delinquency:string,due_today:int,inflows:string,outflows:string,projected:string,period:string,period_label:string,cashflow:list<array{label:string,in:int,out:int}>,activity:list<array{title:string,detail:string,time:string}>}
     */
    public static function dashboard(array $condominiums, string $period = '7'): array
    {
        $first = self::condominiumName($condominiums, 0, 'Edifício Unique');
        $second = self::condominiumName($condominiums, 1, 'Residencial Central');
        $period = in_array($period, ['7', '30', 'month'], true) ? $period : '7';
        $cashflowByPeriod = [
            '7' => [
                ['label'=>'Seg','in'=>12500,'out'=>6800], ['label'=>'Ter','in'=>18200,'out'=>9400],
                ['label'=>'Qua','in'=>9600,'out'=>13200], ['label'=>'Qui','in'=>22400,'out'=>11200],
                ['label'=>'Sex','in'=>16800,'out'=>8300], ['label'=>'Sáb','in'=>4200,'out'=>2500],
                ['label'=>'Dom','in'=>6100,'out'=>3700],
            ],
            '30' => [
                ['label'=>'01–05','in'=>51000,'out'=>32000], ['label'=>'06–10','in'=>45000,'out'=>27000],
                ['label'=>'11–15','in'=>62000,'out'=>34000], ['label'=>'16–20','in'=>48000,'out'=>29000],
                ['label'=>'21–25','in'=>57000,'out'=>31000], ['label'=>'26–30','in'=>39000,'out'=>18000],
            ],
            'month' => [
                ['label'=>'01–05','in'=>51000,'out'=>32000], ['label'=>'06–10','in'=>45000,'out'=>27000],
                ['label'=>'11–15','in'=>62000,'out'=>34000], ['label'=>'16–20','in'=>48000,'out'=>29000],
                ['label'=>'21–25','in'=>57000,'out'=>31000], ['label'=>'26–31','in'=>46000,'out'=>21000],
            ],
        ];
        $cashflow = $cashflowByPeriod[$period];
        $inflows = array_sum(array_column($cashflow, 'in'));
        $outflows = array_sum(array_column($cashflow, 'out'));
        $periodLabel = match ($period) {
            '30' => '30 dias', 'month' => 'este mês', default => '7 dias',
        };

        return [
            'balance' => 'R$ 842.430,25',
            'receivable' => 'R$ 186.240,00',
            'payable' => 'R$ 94.780,50',
            'delinquency' => 'R$ 38.420,00',
            'due_today' => 12,
            'inflows' => self::currency($inflows * 100),
            'outflows' => self::currency($outflows * 100),
            'projected' => self::currency(84243025 + (($inflows - $outflows) * 100)),
            'period' => $period,
            'period_label' => $periodLabel,
            'cashflow' => $cashflow,
            'activity' => [
                ['title' => 'Pagamento confirmado', 'detail' => 'CEMIG · ' . $first, 'time' => 'Hoje, 10:42'],
                ['title' => 'Cobrança recebida', 'detail' => 'Unidade 301 · ' . $second, 'time' => 'Hoje, 09:18'],
                ['title' => 'Conta cadastrada', 'detail' => 'Elevadores Atlas · ' . $first, 'time' => 'Ontem, 16:05'],
                ['title' => 'Conciliação concluída', 'detail' => 'Conta demonstrativa · Banco Horizonte', 'time' => 'Ontem, 14:31'],
            ],
        ];
    }

    /** @param list<array<string,mixed>> $condominiums
     *  @return array{title:string,description:string,columns:list<array{key:string,label:string}>,rows:list<array<string,string>>,filters:list<string>,action:?string}
     */
    public static function page(string $section, array $condominiums): array
    {
        $first = self::condominiumName($condominiums, 0, 'Edifício Unique');
        $second = self::condominiumName($condominiums, 1, 'Edifício Torres');
        $third = self::condominiumName($condominiums, 2, 'Residencial Central');

        $pages = [
            'payables' => [
                'title' => 'Contas a pagar', 'description' => 'Obrigações e vencimentos da administradora.',
                'columns' => [['key'=>'due','label'=>'Vencimento'],['key'=>'description','label'=>'Descrição'],['key'=>'supplier','label'=>'Fornecedor'],['key'=>'condominium','label'=>'Condomínio'],['key'=>'category','label'=>'Categoria'],['key'=>'amount','label'=>'Valor'],['key'=>'status','label'=>'Status']],
                'filters' => ['period','condominium','supplier','status'], 'action' => 'Nova conta',
                'rows' => [
                    ['due'=>'Hoje, 06 out','description'=>'Energia elétrica','supplier'=>'CEMIG','condominium'=>$first,'category'=>'Utilidades','amount'=>'R$ 4.820,35','status'=>'Vencendo'],
                    ['due'=>'08 out','description'=>'Manutenção mensal','supplier'=>'Elevadores Atlas','condominium'=>$second,'category'=>'Manutenção','amount'=>'R$ 1.950,00','status'=>'Pendente'],
                    ['due'=>'10 out','description'=>'Serviços mensais','supplier'=>'Limpeza Urbana','condominium'=>$third,'category'=>'Serviços','amount'=>'R$ 7.400,00','status'=>'Pendente'],
                    ['due'=>'02 out','description'=>'Abastecimento de água','supplier'=>'Companhia de Água','condominium'=>$first,'category'=>'Utilidades','amount'=>'R$ 3.184,70','status'=>'Vencida'],
                ],
            ],
            'receivables' => [
                'title' => 'Contas a receber', 'description' => 'Valores previstos e recebidos por unidade.',
                'columns' => [['key'=>'due','label'=>'Vencimento'],['key'=>'payer','label'=>'Pagador'],['key'=>'unit','label'=>'Unidade'],['key'=>'condominium','label'=>'Condomínio'],['key'=>'description','label'=>'Descrição'],['key'=>'amount','label'=>'Valor'],['key'=>'status','label'=>'Status']],
                'filters' => ['period','condominium','status'], 'action' => null,
                'rows' => [
                    ['due'=>'10 out','payer'=>'Pessoa demonstrativa 01','unit'=>'301','condominium'=>$first,'description'=>'Cota condominial · outubro','amount'=>'R$ 1.248,00','status'=>'Aberto'],
                    ['due'=>'05 out','payer'=>'Pessoa demonstrativa 02','unit'=>'502','condominium'=>$second,'description'=>'Cota condominial · outubro','amount'=>'R$ 986,50','status'=>'Pago'],
                    ['due'=>'01 out','payer'=>'Pessoa demonstrativa 03','unit'=>'104','condominium'=>$third,'description'=>'Cota + fundo de reserva','amount'=>'R$ 1.532,00','status'=>'Vencido'],
                    ['due'=>'15 out','payer'=>'Pessoa demonstrativa 04','unit'=>'201','condominium'=>$first,'description'=>'Consumo de água','amount'=>'R$ 184,90','status'=>'Negociado'],
                ],
            ],
            'billing' => [
                'title' => 'Cobranças', 'description' => 'Acompanhamento demonstrativo das cobranças por unidade.',
                'columns' => [['key'=>'competence','label'=>'Competência'],['key'=>'condominium','label'=>'Condomínio'],['key'=>'unit','label'=>'Unidade'],['key'=>'responsible','label'=>'Responsável'],['key'=>'due','label'=>'Vencimento'],['key'=>'amount','label'=>'Valor'],['key'=>'status','label'=>'Status'],['key'=>'slip','label'=>'Boleto']],
                'filters' => ['period','condominium','status'], 'action' => null,
                'rows' => [
                    ['competence'=>'Out/2026','condominium'=>$first,'unit'=>'301','responsible'=>'Pessoa demonstrativa 01','due'=>'10 out','amount'=>'R$ 1.248,00','status'=>'Aberta','slip'=>'—'],
                    ['competence'=>'Out/2026','condominium'=>$second,'unit'=>'502','responsible'=>'Pessoa demonstrativa 02','due'=>'05 out','amount'=>'R$ 986,50','status'=>'Paga','slip'=>'Liquidado'],
                    ['competence'=>'Out/2026','condominium'=>$third,'unit'=>'104','responsible'=>'Pessoa demonstrativa 03','due'=>'01 out','amount'=>'R$ 1.532,00','status'=>'Vencida','slip'=>'Disponível'],
                    ['competence'=>'Out/2026','condominium'=>$first,'unit'=>'201','responsible'=>'Pessoa demonstrativa 04','due'=>'15 out','amount'=>'R$ 1.410,00','status'=>'Negociada','slip'=>'—'],
                ],
            ],
            'condominiums' => [
                'title' => 'Condomínios', 'description' => 'Cadastros vinculados à administradora atual.',
                'columns' => [['key'=>'name','label'=>'Condomínio'],['key'=>'tax_id','label'=>'CNPJ'],['key'=>'units','label'=>'Unidades'],['key'=>'manager','label'=>'Síndico'],['key'=>'status','label'=>'Situação']],
                'filters' => [], 'action' => null,
                'rows' => self::condominiumRows($condominiums),
            ],
            'people' => [
                'title' => 'Pessoas', 'description' => 'Pessoas e seus vínculos com condomínios e unidades.',
                'columns' => [['key'=>'name','label'=>'Nome'],['key'=>'document','label'=>'CPF/CNPJ'],['key'=>'roles','label'=>'Vínculos'],['key'=>'condominium','label'=>'Condomínio'],['key'=>'unit','label'=>'Unidade'],['key'=>'contact','label'=>'Contato'],['key'=>'status','label'=>'Status']],
                'filters' => ['condominium','status'], 'action' => null,
                'rows' => [
                    ['name'=>'Pessoa demonstrativa 01','document'=>'Dado demonstrativo','roles'=>'Proprietário · Morador','condominium'=>$first,'unit'=>'301','contact'=>'pessoa01@example.test','status'=>'Ativo'],
                    ['name'=>'Pessoa demonstrativa 02','document'=>'Dado demonstrativo','roles'=>'Síndico','condominium'=>$second,'unit'=>'—','contact'=>'pessoa02@example.test','status'=>'Ativo'],
                    ['name'=>'Pessoa demonstrativa 03','document'=>'Dado demonstrativo','roles'=>'Proprietário · Conselheiro','condominium'=>$third,'unit'=>'104','contact'=>'pessoa03@example.test','status'=>'Ativo'],
                    ['name'=>'Pessoa demonstrativa 04','document'=>'Dado demonstrativo','roles'=>'Proprietário','condominium'=>$first,'unit'=>'201 · 402','contact'=>'pessoa04@example.test','status'=>'Ativo'],
                ],
            ],
            'units' => [
                'title' => 'Unidades', 'description' => 'Unidades e vínculos de propriedade e moradia.',
                'columns' => [['key'=>'condominium','label'=>'Condomínio'],['key'=>'block','label'=>'Bloco'],['key'=>'unit','label'=>'Unidade'],['key'=>'owner','label'=>'Proprietário'],['key'=>'resident','label'=>'Morador'],['key'=>'finance','label'=>'Situação financeira']],
                'filters' => ['condominium','status'], 'action' => null,
                'rows' => [
                    ['condominium'=>$first,'block'=>'Torre A','unit'=>'201','owner'=>'Pessoa demonstrativa 04','resident'=>'Pessoa demonstrativa 04','finance'=>'Em dia'],
                    ['condominium'=>$first,'block'=>'Torre A','unit'=>'301','owner'=>'Pessoa demonstrativa 01','resident'=>'Pessoa demonstrativa 01','finance'=>'Em aberto'],
                    ['condominium'=>$first,'block'=>'Torre B','unit'=>'402','owner'=>'Pessoa demonstrativa 04','resident'=>'—','finance'=>'Em dia'],
                    ['condominium'=>$second,'block'=>'Torre única','unit'=>'502','owner'=>'Pessoa demonstrativa 02','resident'=>'Pessoa demonstrativa 02','finance'=>'Pago'],
                    ['condominium'=>$third,'block'=>'Bloco Central','unit'=>'104','owner'=>'Pessoa demonstrativa 03','resident'=>'Pessoa demonstrativa 03','finance'=>'Vencido'],
                ],
            ],
            'bank-accounts' => [
                'title' => 'Contas bancárias', 'description' => 'Visão demonstrativa das contas por condomínio.',
                'columns' => [['key'=>'bank','label'=>'Banco'],['key'=>'branch','label'=>'Agência'],['key'=>'account','label'=>'Conta'],['key'=>'condominium','label'=>'Condomínio'],['key'=>'balance','label'=>'Saldo demonstrativo'],['key'=>'reconciled','label'=>'Última conciliação']],
                'filters' => ['condominium'], 'action' => null,
                'rows' => [
                    ['bank'=>'Banco Horizonte','branch'=>'0001','account'=>'•••• 4821','condominium'=>$first,'balance'=>'R$ 412.820,45','reconciled'=>'05 out 2026'],
                    ['bank'=>'Cooperativa Sul','branch'=>'0132','account'=>'•••• 9074','condominium'=>$second,'balance'=>'R$ 286.410,00','reconciled'=>'04 out 2026'],
                    ['bank'=>'Banco Horizonte','branch'=>'0001','account'=>'•••• 1158','condominium'=>$third,'balance'=>'R$ 143.199,80','reconciled'=>'05 out 2026'],
                ],
            ],
            'reconciliation' => [
                'title' => 'Conciliação', 'description' => 'Comparação demonstrativa entre movimentos e lançamentos.',
                'columns' => [['key'=>'date','label'=>'Data'],['key'=>'bank_movement','label'=>'Movimento bancário'],['key'=>'erp_entry','label'=>'Lançamento ERP'],['key'=>'condominium','label'=>'Condomínio'],['key'=>'bank_amount','label'=>'Valor banco'],['key'=>'erp_amount','label'=>'Valor ERP'],['key'=>'status','label'=>'Status']],
                'filters' => ['period','condominium','status'], 'action' => null,
                'rows' => [
                    ['date'=>'06 out','bank_movement'=>'TED · CEMIG','erp_entry'=>'Conta de energia · outubro','condominium'=>$first,'bank_amount'=>'− R$ 4.820,35','erp_amount'=>'− R$ 4.820,35','status'=>'Conciliado'],
                    ['date'=>'06 out','bank_movement'=>'PIX recebido · unidade 301','erp_entry'=>'Cota condominial','condominium'=>$second,'bank_amount'=>'R$ 986,50','erp_amount'=>'R$ 986,50','status'=>'Pendente'],
                    ['date'=>'05 out','bank_movement'=>'Débito · manutenção','erp_entry'=>'Manutenção elevadores','condominium'=>$third,'bank_amount'=>'− R$ 1.950,00','erp_amount'=>'− R$ 1.850,00','status'=>'Divergente'],
                ],
            ],
        ];

        return $pages[$section] ?? $pages['payables'];
    }

    /** @param list<array<string,mixed>> $condominiums
     *  @return array{title:string,description:string,columns:list<array{key:string,label:string}>,rows:list<array<string,string>>,filters:list<string>,action:?string}
     */
    public static function realCondominiums(array $condominiums): array
    {
        $page = [
            'title' => 'Condomínios',
            'description' => 'Cadastros vinculados à administradora atual.',
            'columns' => [['key'=>'name','label'=>'Condomínio'],['key'=>'tax_id','label'=>'CNPJ'],['key'=>'units','label'=>'Unidades'],['key'=>'manager','label'=>'Síndico'],['key'=>'status','label'=>'Situação']],
            'filters' => [],
            'action' => null,
            'rows' => [],
        ];
        foreach ($condominiums as $condominium) {
            $page['rows'][] = [
                'name' => (string) (($condominium['trade_name'] ?? '') ?: ($condominium['legal_name'] ?? '')),
                'tax_id' => (string) (($condominium['tax_id'] ?? '') ?: '—'),
                'units' => '—',
                'manager' => 'Não informado',
                'status' => (string) ($condominium['status'] ?? 'active'),
            ];
        }
        return $page;
    }

    /** @param list<array<string,mixed>> $condominiums
     *  @return list<array<string,string>>
     */
    private static function condominiumRows(array $condominiums): array
    {
        if ($condominiums === []) {
            return [
                ['name'=>'Edifício Unique','tax_id'=>'00.000.000/0000-00','units'=>'128','manager'=>'Síndico demonstrativo','status'=>'Ativo'],
                ['name'=>'Edifício Torres','tax_id'=>'00.000.000/0000-01','units'=>'84','manager'=>'Síndico demonstrativo','status'=>'Ativo'],
                ['name'=>'Residencial Central','tax_id'=>'00.000.000/0000-02','units'=>'56','manager'=>'Síndico demonstrativo','status'=>'Ativo'],
            ];
        }
        $rows = [];
        foreach ($condominiums as $condominium) {
            $rows[] = [
                'name' => (string) ($condominium['trade_name'] ?: $condominium['legal_name']),
                'tax_id' => (string) ($condominium['tax_id'] ?: '—'),
                'units' => '—', 'manager' => 'Não informado',
                'status' => (string) ($condominium['status'] ?? 'Ativo'),
            ];
        }
        return $rows;
    }

    /** @param list<array<string,mixed>> $condominiums */
    private static function condominiumName(array $condominiums, int $index, string $fallback): string
    {
        if (!isset($condominiums[$index])) {
            return $fallback;
        }
        $row = $condominiums[$index];
        return (string) (($row['trade_name'] ?? '') ?: ($row['legal_name'] ?? $fallback));
    }

    private static function currency(int $amountInCents): string
    {
        return 'R$ ' . number_format($amountInCents / 100, 2, ',', '.');
    }
}
