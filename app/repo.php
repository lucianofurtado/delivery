<?php
declare(strict_types=1);

// ============================================================
// Plataformas, categorias e configurações
// ============================================================

/** Plataformas ativas, na ordem de exibição. */
function plataformas(): array
{
    static $cache = null;
    if ($cache === null) {
        $cache = q('SELECT id, slug, nome, cor, ordem FROM plataformas WHERE ativo = 1 ORDER BY ordem, nome');
    }

    return $cache;
}

function plataforma_por_slug(string $slug): ?array
{
    foreach (plataformas() as $p) {
        if ($p['slug'] === $slug) {
            return $p;
        }
    }

    return null;
}

function plataforma_por_id(int $id): ?array
{
    foreach (plataformas() as $p) {
        if ((int) $p['id'] === $id) {
            return $p;
        }
    }

    return null;
}

/**
 * Slots físicos da tabela `lancamentos` que um campo pode ocupar,
 * e como cada um se comporta. Os de 'dinheiro' somam no total do dia.
 */
function slots_lancamento(): array
{
    return [
        'rotas'        => 'inteiro',
        'solicitacoes' => 'inteiro',
        'valor_base'   => 'dinheiro',
        'promos'       => 'dinheiro',
        'gorjetas'     => 'dinheiro',
        'adicional'    => 'dinheiro',
        'outros'       => 'dinheiro',
    ];
}

/**
 * Campos do formulário de uma plataforma, na ordem de exibição.
 * Cada item: ['coluna' => ..., 'rotulo' => ..., 'tipo' => 'dinheiro'|'inteiro'].
 */
function campos_da_plataforma(int $plataformaId): array
{
    static $cache = [];
    if (isset($cache[$plataformaId])) {
        return $cache[$plataformaId];
    }

    $slots  = slots_lancamento();
    $campos = [];
    $linhas = q(
        'SELECT coluna, rotulo FROM plataforma_campos
          WHERE plataforma_id = ? AND ativo = 1 ORDER BY ordem, id',
        [$plataformaId]
    );

    foreach ($linhas as $r) {
        // Ignora slot desconhecido em vez de quebrar a tela.
        if (!isset($slots[$r['coluna']])) {
            continue;
        }
        $campos[] = [
            'coluna' => $r['coluna'],
            'rotulo' => $r['rotulo'],
            'tipo'   => $slots[$r['coluna']],
        ];
    }

    return $cache[$plataformaId] = $campos;
}

/** A plataforma usa este slot? */
function tem_campo(array $campos, string $coluna): bool
{
    foreach ($campos as $c) {
        if ($c['coluna'] === $coluna) {
            return true;
        }
    }

    return false;
}

/** Quantos campos de dinheiro a plataforma tem. */
function campos_de_dinheiro(array $campos): array
{
    return array_values(array_filter($campos, static function (array $c) {
        return $c['tipo'] === 'dinheiro';
    }));
}

/** Categorias de despesa ativas. */
function categorias(): array
{
    static $cache = null;
    if ($cache === null) {
        $cache = q('SELECT id, nome, cor FROM categorias_despesa WHERE ativo = 1 ORDER BY ordem, nome');
    }

    return $cache;
}

function config_get(string $chave, string $padrao = ''): string
{
    static $cache = null;
    if ($cache === null) {
        $cache = [];
        foreach (q('SELECT chave, valor FROM configuracoes') as $r) {
            $cache[$r['chave']] = $r['valor'];
        }
    }

    return $cache[$chave] ?? $padrao;
}

function config_set(string $chave, string $valor): void
{
    exec_sql(
        'INSERT INTO configuracoes (chave, valor) VALUES (?, ?) ON DUPLICATE KEY UPDATE valor = VALUES(valor)',
        [$chave, $valor]
    );
}

function meta_diaria(): float
{
    return n(config_get('meta_diaria', '150'));
}

function meta_mensal(): float
{
    return n(config_get('meta_mensal', '3000'));
}

// ============================================================
// Gravação
// ============================================================

/**
 * Grava (ou regrava) o lançamento do dia de uma plataforma.
 * Slots que a plataforma não usa são gravados como 0, para que um
 * campo removido não continue somando no total do dia.
 */
function salvar_lancamento(int $plataformaId, string $data, array $v): void
{
    $valores = normalizar_slots($v);

    exec_sql(
        'INSERT INTO lancamentos
            (plataforma_id, data, rotas, solicitacoes, valor_base, promos, gorjetas, adicional, outros)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE
            rotas = VALUES(rotas), solicitacoes = VALUES(solicitacoes), valor_base = VALUES(valor_base),
            promos = VALUES(promos), gorjetas = VALUES(gorjetas), adicional = VALUES(adicional),
            outros = VALUES(outros)',
        [
            $plataformaId,
            $data,
            $valores['rotas'],
            $valores['solicitacoes'],
            $valores['valor_base'],
            $valores['promos'],
            $valores['gorjetas'],
            $valores['adicional'],
            $valores['outros'],
        ]
    );
}

/**
 * Regrava um lançamento já existente, inclusive a data.
 * Quem chama garante que a nova data não colide com outro dia da plataforma.
 */
function atualizar_lancamento(int $id, int $plataformaId, string $data, array $v): void
{
    $valores = normalizar_slots($v);

    exec_sql(
        'UPDATE lancamentos
            SET data = ?, rotas = ?, solicitacoes = ?, valor_base = ?, promos = ?,
                gorjetas = ?, adicional = ?, outros = ?
          WHERE id = ? AND plataforma_id = ?',
        [
            $data,
            $valores['rotas'],
            $valores['solicitacoes'],
            $valores['valor_base'],
            $valores['promos'],
            $valores['gorjetas'],
            $valores['adicional'],
            $valores['outros'],
            $id,
            $plataformaId,
        ]
    );
}

/** Todos os slots com o tipo certo; os ausentes viram 0. */
function normalizar_slots(array $v): array
{
    $valores = [];
    foreach (slots_lancamento() as $coluna => $tipo) {
        $valores[$coluna] = $tipo === 'inteiro'
            ? (int) n($v[$coluna] ?? 0)
            : n($v[$coluna] ?? 0);
    }

    return $valores;
}

/** Lançamento de uma plataforma pelo id; null se não existir ou for de outra plataforma. */
function lancamento_por_id(int $id, int $plataformaId): ?array
{
    $r = q1(
        'SELECT id, data, rotas, solicitacoes, valor_base, promos, gorjetas, adicional, outros, total
           FROM lancamentos WHERE id = ? AND plataforma_id = ?',
        [$id, $plataformaId]
    );

    return $r ?: null;
}

/** Já existe outro lançamento da plataforma nesta data? */
function lancamento_ocupa_data(int $plataformaId, string $data, int $excetoId): bool
{
    return (bool) q1(
        'SELECT id FROM lancamentos WHERE plataforma_id = ? AND data = ? AND id <> ?',
        [$plataformaId, $data, $excetoId]
    );
}

function excluir_lancamento(int $id): void
{
    exec_sql('DELETE FROM lancamentos WHERE id = ?', [$id]);
}

/** Grava (ou regrava) o odômetro do dia. */
function salvar_km(string $data, int $inicial, int $final): void
{
    exec_sql(
        'INSERT INTO km_diario (data, km_inicial, km_final) VALUES (?, ?, ?)
         ON DUPLICATE KEY UPDATE km_inicial = VALUES(km_inicial), km_final = VALUES(km_final)',
        [$data, $inicial, $final]
    );
}

/** Regrava um registro de km já existente, inclusive a data. */
function atualizar_km(int $id, string $data, int $inicial, int $final): void
{
    exec_sql(
        'UPDATE km_diario SET data = ?, km_inicial = ?, km_final = ? WHERE id = ?',
        [$data, $inicial, $final, $id]
    );
}

function km_por_id(int $id): ?array
{
    $r = q1('SELECT id, data, km_inicial, km_final, km_rodados FROM km_diario WHERE id = ?', [$id]);

    return $r ?: null;
}

/** Já existe outro registro de km nesta data? */
function km_ocupa_data(string $data, int $excetoId): bool
{
    return (bool) q1('SELECT id FROM km_diario WHERE data = ? AND id <> ?', [$data, $excetoId]);
}

function excluir_km(int $id): void
{
    exec_sql('DELETE FROM km_diario WHERE id = ?', [$id]);
}

function salvar_despesa(string $data, int $categoriaId, string $descricao, float $valor): void
{
    exec_sql(
        'INSERT INTO despesas (data, categoria_id, descricao, valor) VALUES (?, ?, ?, ?)',
        [$data, $categoriaId, $descricao, $valor]
    );
}

function excluir_despesa(int $id): void
{
    exec_sql('DELETE FROM despesas WHERE id = ?', [$id]);
}

function salvar_evento(
    string $titulo,
    ?int $plataformaId,
    int $metaQtd,
    string $metaUnidade,
    int $prazoDias,
    float $recompensa,
    string $dataInicio
): void {
    exec_sql(
        'INSERT INTO eventos (titulo, plataforma_id, meta_qtd, meta_unidade, prazo_dias, recompensa, data_inicio)
         VALUES (?, ?, ?, ?, ?, ?, ?)',
        [$titulo, $plataformaId, $metaQtd, $metaUnidade, $prazoDias, $recompensa, $dataInicio]
    );
}

/** Marca um evento como concluído (ou reabre), com a quantidade batida opcional. */
function evento_marcar(int $id, bool $concluido, ?int $resultadoQtd): void
{
    exec_sql(
        'UPDATE eventos SET concluido = ?, resultado_qtd = ? WHERE id = ?',
        [$concluido ? 1 : 0, $resultadoQtd, $id]
    );
}

function excluir_evento(int $id): void
{
    exec_sql('DELETE FROM eventos WHERE id = ?', [$id]);
}

function evento_por_id(int $id): ?array
{
    return q1('SELECT * FROM eventos WHERE id = ?', [$id]);
}

function salvar_manutencao(string $nome, int $intervaloKm, int $kmUltima, ?string $dataUltima, int $avisoKm): void
{
    exec_sql(
        'INSERT INTO manutencoes (nome, intervalo_km, km_ultima, data_ultima, aviso_km) VALUES (?, ?, ?, ?, ?)',
        [$nome, $intervaloKm, $kmUltima, $dataUltima, $avisoKm]
    );
    $id = (int) db()->lastInsertId();
    exec_sql(
        'INSERT INTO manutencao_trocas (manutencao_id, data, km) VALUES (?, ?, ?)',
        [$id, $dataUltima ?? date('Y-m-d'), $kmUltima]
    );
}

function atualizar_manutencao(int $id, string $nome, int $intervaloKm, int $kmUltima, ?string $dataUltima, int $avisoKm): void
{
    exec_sql(
        'UPDATE manutencoes SET nome = ?, intervalo_km = ?, km_ultima = ?, data_ultima = ?, aviso_km = ? WHERE id = ?',
        [$nome, $intervaloKm, $kmUltima, $dataUltima, $avisoKm, $id]
    );
}

/** Registra uma troca: zera a contagem a partir do km informado e guarda no histórico. */
function registrar_troca(int $id, string $data, int $km): void
{
    exec_sql('UPDATE manutencoes SET km_ultima = ?, data_ultima = ? WHERE id = ?', [$km, $data, $id]);
    exec_sql('INSERT INTO manutencao_trocas (manutencao_id, data, km) VALUES (?, ?, ?)', [$id, $data, $km]);
}

function excluir_manutencao(int $id): void
{
    exec_sql('DELETE FROM manutencoes WHERE id = ?', [$id]);
}

function manutencao_por_id(int $id): ?array
{
    return q1('SELECT * FROM manutencoes WHERE id = ?', [$id]);
}

/** Histórico de trocas de todos os itens, mais recente primeiro. */
function manutencao_trocas(): array
{
    $porItem = [];
    foreach (q('SELECT manutencao_id, data, km FROM manutencao_trocas ORDER BY data DESC, id DESC') as $r) {
        $porItem[(int) $r['manutencao_id']][] = $r;
    }

    return $porItem;
}

/**
 * Itens de manutenção com a situação calculada a partir do odômetro atual
 * (último km final do KM diário). Os vencidos e próximos vêm primeiro.
 * Cada item ganha: km_atual, rodados, proxima_km, falta_km, progresso (%) e status.
 */
function manutencoes_situacao(): array
{
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }

    $kmAtual = ultimo_km_final();
    $itens   = [];
    foreach (q('SELECT * FROM manutencoes ORDER BY nome') as $r) {
        $intervalo = max(1, (int) $r['intervalo_km']);
        $ultima    = (int) $r['km_ultima'];
        // Odômetro abaixo do km da troca (troca lançada à frente do KM diário): nada rodado ainda.
        $rodados   = max(0, $kmAtual - $ultima);
        $proxima   = $ultima + $intervalo;
        $falta     = $proxima - max($kmAtual, $ultima);

        if ($falta <= 0) {
            $status = 'vencida';
        } elseif ($falta <= (int) $r['aviso_km']) {
            $status = 'proxima';
        } else {
            $status = 'ok';
        }

        $r['km_atual']   = $kmAtual;
        $r['rodados']    = $rodados;
        $r['proxima_km'] = $proxima;
        $r['falta_km']   = $falta;
        $r['progresso']  = pct((float) $rodados, (float) $intervalo);
        $r['status']     = $status;
        $itens[]         = $r;
    }

    $peso = ['vencida' => 0, 'proxima' => 1, 'ok' => 2];
    usort($itens, static function (array $a, array $b) use ($peso) {
        return [$peso[$a['status']], $a['falta_km']] <=> [$peso[$b['status']], $b['falta_km']];
    });

    return $cache = $itens;
}

/**
 * Itens vencidos ou perto de vencer — o lembrete do dashboard e do menu.
 * Tolera o banco sem a migração 004: sem tabela, sem lembrete.
 */
function manutencoes_alerta(): array
{
    try {
        return array_values(array_filter(manutencoes_situacao(), static function (array $r) {
            return $r['status'] !== 'ok';
        }));
    } catch (PDOException $e) {
        return [];
    }
}

/** Último km final registrado — vira o km inicial sugerido do próximo dia. */
function ultimo_km_final(): int
{
    $r = q1('SELECT km_final FROM km_diario ORDER BY data DESC, id DESC LIMIT 1');

    return $r ? (int) $r['km_final'] : 0;
}

// ============================================================
// Listagens por tela
// ============================================================

/** Lançamentos de uma plataforma, do mais recente para o mais antigo. */
function lancamentos_da_plataforma(int $plataformaId): array
{
    return q(
        'SELECT id, data, rotas, solicitacoes, valor_base, promos, gorjetas, adicional, outros, total
           FROM lancamentos WHERE plataforma_id = ? ORDER BY data DESC',
        [$plataformaId]
    );
}

function km_lista(): array
{
    return q('SELECT id, data, km_inicial, km_final, km_rodados FROM km_diario ORDER BY data DESC');
}

function despesas_lista(): array
{
    return q(
        'SELECT d.id, d.data, d.descricao, d.valor, c.nome AS categoria, c.cor
           FROM despesas d
           JOIN categorias_despesa c ON c.id = d.categoria_id
          ORDER BY d.data DESC, d.id DESC'
    );
}

/** Eventos (metas pontuais com recompensa), do mais recente para o mais antigo. */
function eventos_lista(): array
{
    return q(
        'SELECT ev.id, ev.titulo, ev.plataforma_id, ev.meta_qtd, ev.meta_unidade,
                ev.prazo_dias, ev.recompensa, ev.data_inicio, ev.data_fim,
                ev.concluido, ev.resultado_qtd, p.nome AS plataforma, p.cor
           FROM eventos ev
           LEFT JOIN plataformas p ON p.id = ev.plataforma_id
          ORDER BY ev.data_inicio DESC, ev.id DESC'
    );
}

/** Total já ganho em eventos concluídos. */
function eventos_ganho_total(): float
{
    $r = q1('SELECT SUM(recompensa) AS t FROM eventos WHERE concluido = 1');

    return $r ? (float) $r['t'] : 0.0;
}

// ============================================================
// Métricas consolidadas (usadas por dashboard, mensal e metas)
// ============================================================

/**
 * Calcula tudo de uma vez: receita por dia/plataforma, km, despesas,
 * totais do período, séries do gráfico, fechamento mensal e metas.
 */
function metricas(): array
{
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }

    $plataformas = plataformas();

    // Receita por dia e por plataforma
    $porData   = []; // [data][plataforma_id] => total
    $totalPlat = []; // [plataforma_id] => total do período
    foreach ($plataformas as $p) {
        $totalPlat[(int) $p['id']] = 0.0;
    }
    foreach (q('SELECT data, plataforma_id, SUM(total) AS t FROM lancamentos GROUP BY data, plataforma_id') as $r) {
        $d   = $r['data'];
        $pid = (int) $r['plataforma_id'];
        $t   = (float) $r['t'];
        $porData[$d][$pid] = ($porData[$d][$pid] ?? 0.0) + $t;
        $totalPlat[$pid]   = ($totalPlat[$pid] ?? 0.0) + $t;
    }

    // Km e despesas por dia
    $kmDia = [];
    foreach (q('SELECT data, SUM(km_rodados) AS km FROM km_diario GROUP BY data') as $r) {
        $kmDia[$r['data']] = (float) $r['km'];
    }
    $despDia = [];
    foreach (q('SELECT data, SUM(valor) AS v FROM despesas GROUP BY data') as $r) {
        $despDia[$r['data']] = (float) $r['v'];
    }

    // Dias com receita lançada, em ordem crescente
    $datas = array_keys($porData);
    sort($datas);

    $receitaDia = [];
    foreach ($datas as $d) {
        $receitaDia[$d] = array_sum($porData[$d]);
    }

    $receita  = array_sum($receitaDia);
    $despesas = array_sum($despDia);
    $lucro    = $receita - $despesas;
    $totalKm  = array_sum($kmDia);
    $dias     = count($datas);
    $ultimo   = $dias > 0 ? $datas[$dias - 1] : null;
    $primeiro = $dias > 0 ? $datas[0] : null;

    // Melhor dia por receita
    $melhorDia = null;
    $melhorVal = 0.0;
    foreach ($receitaDia as $d => $v) {
        if ($v > $melhorVal) {
            $melhorDia = $d;
            $melhorVal = $v;
        }
    }

    $metaDia = meta_diaria();
    $metaMes = meta_mensal();

    // Lucro por dia (receita do dia menos despesas do dia)
    $lucroDia = [];
    foreach ($datas as $d) {
        $lucroDia[$d] = $receitaDia[$d] - ($despDia[$d] ?? 0.0);
    }

    // ------------------------------------------------------------
    // Mês corrente — é o recorte do dashboard e a base das metas.
    // Vale o mês de verdade, não o do último lançamento: "quanto falta
    // até o fim do mês" só faz sentido a partir de hoje.
    // ------------------------------------------------------------
    $hoje     = date('Y-m-d');
    $mesAtual = substr($hoje, 0, 7);
    $doMes    = static function ($d) use ($mesAtual) {
        return substr((string) $d, 0, 7) === $mesAtual;
    };

    $datasMes     = array_values(array_filter($datas, $doMes));
    $diasMes      = count($datasMes);
    $receitaMes   = 0.0;
    $totalPlatMes = [];
    foreach ($plataformas as $p) {
        $totalPlatMes[(int) $p['id']] = 0.0;
    }
    foreach ($datasMes as $d) {
        $receitaMes += $receitaDia[$d];
        foreach ($porData[$d] as $pid => $v) {
            $totalPlatMes[$pid] = ($totalPlatMes[$pid] ?? 0.0) + $v;
        }
    }

    // Despesa e km do mês varrem os próprios dias: há despesa e km em dias
    // sem receita lançada, e eles contam no fechamento do mês.
    $despesasMes = 0.0;
    foreach ($despDia as $d => $v) {
        if ($doMes($d)) {
            $despesasMes += $v;
        }
    }
    $kmMes = 0.0;
    foreach ($kmDia as $d => $v) {
        if ($doMes($d)) {
            $kmMes += $v;
        }
    }
    $lucroMes = $receitaMes - $despesasMes;

    // Melhor dia do mês, por receita
    $melhorDiaMes = null;
    $melhorValMes = 0.0;
    foreach ($datasMes as $d) {
        if ($receitaDia[$d] > $melhorValMes) {
            $melhorDiaMes = $d;
            $melhorValMes = $receitaDia[$d];
        }
    }

    $diasNaMetaMes = 0;
    foreach ($datasMes as $d) {
        if ($lucroDia[$d] >= $metaDia) {
            $diasNaMetaMes++;
        }
    }

    // Despesas do mês por categoria
    $categoriasMes = [];
    foreach (categorias() as $c) {
        $categoriasMes[$c['nome']] = ['nome' => $c['nome'], 'cor' => $c['cor'], 'valor' => 0.0];
    }
    foreach (q('SELECT c.nome, c.cor, SUM(d.valor) AS v
                  FROM despesas d JOIN categorias_despesa c ON c.id = d.categoria_id
                 WHERE d.data LIKE ?
                 GROUP BY c.nome, c.cor', [$mesAtual . '%']) as $r) {
        $categoriasMes[$r['nome']] = [
            'nome'  => $r['nome'],
            'cor'   => $r['cor'],
            'valor' => (float) $r['v'],
        ];
    }
    $valoresCatMes   = array_column($categoriasMes, 'valor');
    $maxCategoriaMes = $valoresCatMes === [] ? 1.0 : max(1.0, max($valoresCatMes));

    $lucroUltimo = $ultimo !== null ? $lucroDia[$ultimo] : 0.0;

    // Semana corrente (segunda a domingo), ancorada em hoje como o mês acima:
    // "quanto já entrou nesta semana" só faz sentido a partir da semana que corre.
    $hojeDt        = new DateTimeImmutable($hoje);
    $semanaInicio  = $hojeDt->modify('monday this week')->format('Y-m-d');
    $semanaFim     = $hojeDt->modify('monday this week')->modify('+6 days')->format('Y-m-d');
    $anteriorIni   = $hojeDt->modify('monday this week')->modify('-7 days')->format('Y-m-d');

    $receitaSemana   = 0.0;
    $diasSemana      = 0;
    $receitaAnterior = 0.0;
    foreach ($datas as $d) {
        if ($d >= $semanaInicio && $d <= $semanaFim) {
            $receitaSemana += $receitaDia[$d];
            $diasSemana++;
        } elseif ($d >= $anteriorIni && $d < $semanaInicio) {
            $receitaAnterior += $receitaDia[$d];
        }
    }
    // Despesa varre os próprios dias, como no mês: gasto em dia sem receita
    // lançada também derruba o líquido da semana.
    $despesaSemana   = 0.0;
    $despesaAnterior = 0.0;
    foreach ($despDia as $d => $v) {
        if ($d >= $semanaInicio && $d <= $semanaFim) {
            $despesaSemana += $v;
        } elseif ($d >= $anteriorIni && $d < $semanaInicio) {
            $despesaAnterior += $v;
        }
    }
    $lucroSemana   = $receitaSemana - $despesaSemana;
    $lucroAnterior = $receitaAnterior - $despesaAnterior;

    // Comparação sobre o líquido, que é o número em destaque no card.
    // null quando a semana anterior não fechou no positivo: % sobre base
    // zero ou negativa não diz nada.
    $variacaoSemana = $lucroAnterior > 0
        ? (($lucroSemana - $lucroAnterior) / $lucroAnterior) * 100
        : null;

    // Quanto ainda falta para a meta mensal, e o ritmo diário necessário.
    // O dia de hoje conta: ainda dá para rodar.
    $diasNoMes      = (int) date('t');
    $diasRestantes  = $diasNoMes - (int) date('j') + 1;
    $faltaMes       = max(0.0, $metaMes - $lucroMes);
    $porDiaNecessario = $diasRestantes > 0 ? $faltaMes / $diasRestantes : $faltaMes;
    $diasNaMeta  = count(array_filter($lucroDia, static function ($v) use ($metaDia) {
        return $v >= $metaDia;
    }));

    // Despesas por categoria
    $porCategoria = [];
    foreach (categorias() as $c) {
        $porCategoria[$c['nome']] = ['nome' => $c['nome'], 'cor' => $c['cor'], 'valor' => 0.0];
    }
    foreach (q('SELECT c.nome, c.cor, SUM(d.valor) AS v
                  FROM despesas d JOIN categorias_despesa c ON c.id = d.categoria_id
                 GROUP BY c.nome, c.cor') as $r) {
        $porCategoria[$r['nome']] = [
            'nome'  => $r['nome'],
            'cor'   => $r['cor'],
            'valor' => (float) $r['v'],
        ];
    }
    $valoresCat   = array_column($porCategoria, 'valor');
    $maxCategoria = $valoresCat === [] ? 1.0 : max(1.0, max($valoresCat));

    // Fechamento mensal (inclui meses que só têm km ou evento lançado)
    $meses    = [];
    $mesVazio = static function () use ($plataformas) {
        $base = ['plataformas' => [], 'eventos' => 0.0, 'qtd_eventos' => 0, 'despesas' => 0.0, 'km' => 0.0, 'dias' => 0];
        foreach ($plataformas as $p) {
            $base['plataformas'][(int) $p['id']] = 0.0;
        }

        return $base;
    };
    foreach ($datas as $d) {
        $k = substr($d, 0, 7);
        if (!isset($meses[$k])) {
            $meses[$k] = $mesVazio();
        }
        foreach ($porData[$d] as $pid => $v) {
            $meses[$k]['plataformas'][$pid] = ($meses[$k]['plataformas'][$pid] ?? 0.0) + $v;
        }
        $meses[$k]['despesas'] += $despDia[$d] ?? 0.0;
        $meses[$k]['dias']++;
    }
    foreach ($kmDia as $d => $km) {
        $k = substr($d, 0, 7);
        if (!isset($meses[$k])) {
            $meses[$k] = $mesVazio();
        }
        $meses[$k]['km'] += $km;
    }
    // Recompensa de evento concluído entra no mês em que o evento termina.
    foreach (q('SELECT DATE_FORMAT(data_fim, \'%Y-%m\') AS ym, SUM(recompensa) AS v, COUNT(*) AS qtd
                  FROM eventos WHERE concluido = 1 GROUP BY ym') as $r) {
        $k = $r['ym'];
        if (!isset($meses[$k])) {
            $meses[$k] = $mesVazio();
        }
        $meses[$k]['eventos']     += (float) $r['v'];
        $meses[$k]['qtd_eventos'] += (int) $r['qtd'];
    }
    krsort($meses);
    foreach ($meses as $k => $m) {
        $m['ym']        = $k;
        $m['nome']      = mes_extenso($k);
        $m['receita']   = array_sum($m['plataformas']) + $m['eventos'];
        $m['lucro']     = $m['receita'] - $m['despesas'];
        // R$/km e média por dia olham só as corridas: o bônus de evento
        // não sai de um dia ou de um km específico.
        $corridas       = array_sum($m['plataformas']);
        $m['por_km']    = $m['km'] > 0 ? $corridas / $m['km'] : 0.0;
        $m['media_dia'] = $m['dias'] > 0 ? $corridas / $m['dias'] : 0.0;
        $meses[$k]      = $m;
    }

    $cache = [
        'plataformas'   => $plataformas,
        'datas'         => $datas,
        'por_data'      => $porData,
        'receita_dia'   => $receitaDia,
        'lucro_dia'     => $lucroDia,
        'km_dia'        => $kmDia,
        'despesa_dia'   => $despDia,
        'total_plat'    => $totalPlat,
        'receita'       => $receita,
        'despesas'      => $despesas,
        'lucro'         => $lucro,
        'total_km'      => $totalKm,
        'dias'          => $dias,
        'primeiro_dia'  => $primeiro,
        'ultimo_dia'    => $ultimo,
        'melhor_dia'    => $melhorDia,
        'melhor_valor'  => $melhorVal,
        'por_km'        => $totalKm > 0 ? $receita / $totalKm : 0.0,
        'media_dia'     => $dias > 0 ? $receita / $dias : 0.0,
        'meta_diaria'   => $metaDia,
        'meta_mensal'   => $metaMes,
        'lucro_mes'     => $lucroMes,
        'lucro_ultimo'  => $lucroUltimo,
        'receita_semana'   => $receitaSemana,
        'lucro_semana'     => $lucroSemana,
        'dias_semana'      => $diasSemana,
        'semana_inicio'    => $semanaInicio,
        'semana_fim'       => $semanaFim,
        'receita_semana_anterior' => $receitaAnterior,
        'lucro_semana_anterior'   => $lucroAnterior,
        'variacao_semana'  => $variacaoSemana,
        'dias_na_meta'  => $diasNaMeta,
        'mes_atual'     => $mesAtual,
        'mes_nome'          => mes_extenso($mesAtual),
        'datas_mes'         => $datasMes,
        'receita_mes'       => $receitaMes,
        'despesas_mes'      => $despesasMes,
        'km_mes'            => $kmMes,
        'dias_mes'          => $diasMes,
        'total_plat_mes'    => $totalPlatMes,
        'por_km_mes'        => $kmMes > 0 ? $receitaMes / $kmMes : 0.0,
        'media_dia_mes'     => $diasMes > 0 ? $receitaMes / $diasMes : 0.0,
        'melhor_dia_mes'    => $melhorDiaMes,
        'melhor_valor_mes'  => $melhorValMes,
        'dias_na_meta_mes'  => $diasNaMetaMes,
        'categorias_mes'    => array_values($categoriasMes),
        'max_categoria_mes' => $maxCategoriaMes,
        'dias_no_mes'       => $diasNoMes,
        'dias_restantes'    => $diasRestantes,
        'ultimo_dia_do_mes' => date('Y-m-t'),
        'falta_mes'         => $faltaMes,
        'por_dia_necessario' => $porDiaNecessario,
        'meta_mes_batida'   => $lucroMes >= $metaMes,
        'categorias'    => array_values($porCategoria),
        'max_categoria' => $maxCategoria,
        'meses'         => $meses,
    ];

    return $cache;
}
