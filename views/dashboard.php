<?php
/** @var array $m métricas consolidadas */
$titulo      = 'Dashboard';
$rota_ativa  = 'dashboard';

$plataformas = $m['plataformas'];
// O dashboard é a foto do mês corrente. O histórico completo fica em Mensal.
$datas       = $m['datas_mes'];

// Escala do gráfico: 12% de folga acima do melhor dia do mês.
$maxDia = 1.0;
foreach ($datas as $d) {
    $maxDia = max($maxDia, $m['receita_dia'][$d]);
}
$escala = $maxDia * 1.12;

$ultimas = array_slice(array_reverse($datas), 0, 8);
?>

<div class="pilha">

  <div class="pagina-topo">
    <div>
      <h1 class="pagina-titulo grande">Dashboard</h1>
      <p class="pagina-sub"><?= e($m['mes_nome']) ?> · <?= e($m['dias_mes']) ?>
        <?= $m['dias_mes'] === 1 ? 'dia rodado' : 'dias rodados' ?></p>
    </div>
    <div style="display:flex;gap:8px;flex-wrap:wrap">
      <?php foreach ($plataformas as $i => $p): ?>
        <a class="btn <?= $i === 0 ? 'btn-principal' : 'btn-secundario' ?>"
           href="<?= e(url('plataforma', ['slug' => $p['slug']])) ?>">+ Lançar <?= e($p['nome']) ?></a>
      <?php endforeach; ?>
    </div>
  </div>

  <?php $alertasManut = manutencoes_alerta(); ?>
  <?php if ($alertasManut !== []): ?>
    <?php $temVencida = $alertasManut[0]['status'] === 'vencida'; ?>
    <div class="aviso-manut<?= $temVencida ? ' vencida' : '' ?>">
      <div>
        <strong><?= $temVencida ? 'Manutenção vencida' : 'Manutenção chegando' ?></strong>
        <ul>
          <?php foreach ($alertasManut as $a): ?>
            <li>
              <?= e($a['nome']) ?> —
              <?= $a['falta_km'] > 0
                    ? 'faltam ' . e(num($a['falta_km'])) . ' km'
                    : e(num(abs($a['falta_km']))) . ' km além do limite' ?>
            </li>
          <?php endforeach; ?>
        </ul>
      </div>
      <a class="btn btn-secundario" href="<?= e(url('manutencao')) ?>">Ver manutenção</a>
    </div>
  <?php endif; ?>

  <!-- KPIs -->
  <div class="grade grade-kpi">
    <div class="kpi destaque">
      <div class="rotulo">Lucro líquido</div>
      <div class="kpi-valor verde"><?= e(brl($m['lucro_mes'])) ?></div>
      <div class="kpi-nota"><?= e(brl($m['receita_mes'])) ?> − <?= e(brl($m['despesas_mes'])) ?> de despesas</div>
    </div>

    <div class="kpi">
      <div class="rotulo">Receita bruta</div>
      <div class="kpi-valor"><?= e(brl($m['receita_mes'])) ?></div>
      <div class="kpi-linha">
        <?php foreach ($plataformas as $p): ?>
          <span style="color:<?= e($p['cor']) ?>">
            <?= e($p['nome']) ?> <?= e(brl($m['total_plat_mes'][(int) $p['id']] ?? 0)) ?>
          </span>
        <?php endforeach; ?>
      </div>
    </div>

    <div class="kpi">
      <div class="rotulo">Ganho por km</div>
      <div class="kpi-valor"><?= e(brl($m['por_km_mes'])) ?></div>
      <div class="kpi-nota"><?= e(num($m['km_mes'], 1)) ?> km rodados no mês</div>
    </div>

    <div class="kpi">
      <div class="rotulo">Média por dia</div>
      <div class="kpi-valor"><?= e(brl($m['media_dia_mes'])) ?></div>
      <div class="kpi-nota">
        Melhor dia:
        <?= $m['melhor_dia_mes'] ? e(dm($m['melhor_dia_mes']) . ' · ' . brl($m['melhor_valor_mes'])) : '—' ?>
      </div>
    </div>

    <div class="kpi">
      <div class="rotulo">Ganho na semana</div>
      <div class="kpi-valor <?= $m['lucro_semana'] >= 0 ? 'verde' : 'vermelho' ?>">
        <?= e(brl($m['lucro_semana'])) ?>
      </div>
      <div class="kpi-nota">
        <?= e(brl($m['receita_semana'])) ?> brutos ·
        <?= e($m['dias_semana']) ?> <?= $m['dias_semana'] === 1 ? 'dia rodado' : 'dias rodados' ?>
        (<?= e(dm($m['semana_inicio'])) ?> a <?= e(dm($m['semana_fim'])) ?>)
      </div>
      <div class="kpi-nota">
        <?php if ($m['variacao_semana'] === null): ?>
          Sem semana anterior para comparar.
        <?php else: ?>
          <?php $varSem = $m['variacao_semana']; ?>
          <span class="<?= $varSem >= 0 ? 'verde' : 'vermelho' ?>">
            <?= $varSem >= 0 ? '+' : '−' ?><?= e(number_format(abs($varSem), 0, ',', '.')) ?>%
          </span>
          vs. semana passada (<?= e(brl($m['lucro_semana_anterior'])) ?>)
        <?php endif; ?>
      </div>
    </div>
  </div>

  <!-- Gráfico + metas -->
  <div class="grade grade-2">
    <div class="card grade-larga">
      <div style="display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap">
        <div>
          <h2>Ganhos por dia</h2>
          <p class="pagina-sub" style="font-size:13px;color:var(--ink3)">
            Comparativo por plataforma em <?= e($m['mes_nome']) ?>
          </p>
        </div>
        <div class="legenda">
          <?php foreach ($plataformas as $p): ?>
            <span><i style="background:<?= e($p['cor']) ?>"></i><?= e($p['nome']) ?></span>
          <?php endforeach; ?>
          <span><i class="linha"></i>meta diária</span>
        </div>
      </div>

      <?php if ($datas === []): ?>
        <p class="vazio">Nenhum lançamento neste mês. Comece registrando um dia em uma das plataformas.</p>
      <?php else: ?>
        <div class="grafico">
          <div class="grafico-meta"
               style="bottom:<?= e(number_format(min(98.0, pct($m['meta_diaria'], $escala)), 2, '.', '')) ?>%"></div>

          <?php foreach ($datas as $d): ?>
            <?php $total = $m['receita_dia'][$d]; ?>
            <div class="grafico-col">
              <div class="grafico-rotulo"><?= e(brl_curto($total)) ?></div>
              <div class="grafico-pilha"
                   style="height:<?= e(number_format(pct($total, $escala), 2, '.', '')) ?>%">
                <?php foreach (array_reverse($plataformas) as $p): ?>
                  <?php $v = $m['por_data'][$d][(int) $p['id']] ?? 0.0; ?>
                  <?php if ($v > 0): ?>
                    <div style="height:<?= e(number_format(pct($v, $total), 2, '.', '')) ?>%;background:<?= e($p['cor']) ?>"></div>
                  <?php endif; ?>
                <?php endforeach; ?>
              </div>
              <div class="grafico-rotulo"><?= e(dm($d)) ?></div>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>

    <div class="card" style="display:flex;flex-direction:column;gap:16px">
      <h2>Metas</h2>

      <?php $pctMes = pct($m['lucro_mes'], $m['meta_mensal']); ?>
      <div>
        <div class="linha-entre">
          <span class="suave">Lucro do mês</span>
          <span class="forte"><?= e(brl($m['lucro_mes'])) ?></span>
        </div>
        <div class="trilho">
          <div class="trilho-barra"
               style="width:<?= e(number_format($pctMes, 1, '.', '')) ?>%;background:<?= $pctMes >= 100 ? 'var(--green)' : 'var(--red)' ?>"></div>
        </div>
        <div class="kpi-nota">
          <?= e(number_format($m['meta_mensal'] > 0 ? ($m['lucro_mes'] / $m['meta_mensal']) * 100 : 0, 0, ',', '.')) ?>%
          da meta de <?= e(brl($m['meta_mensal'])) ?>
        </div>
        <div class="kpi-nota" style="margin-top:4px">
          <?php if ($m['meta_mes_batida']): ?>
            <span class="verde">Meta do mês batida.</span>
          <?php else: ?>
            Faltam <strong class="verde"><?= e(brl($m['por_dia_necessario'])) ?>/dia</strong>
            em <?= e($m['dias_restantes']) ?>
            <?= $m['dias_restantes'] === 1 ? 'dia' : 'dias' ?>
          <?php endif; ?>
        </div>
      </div>

      <?php $pctDia = pct($m['lucro_ultimo'], $m['meta_diaria']); ?>
      <div>
        <div class="linha-entre">
          <span class="suave">Último dia (<?= e(dm($m['ultimo_dia'])) ?>)</span>
          <span class="forte"><?= e(brl($m['lucro_ultimo'])) ?></span>
        </div>
        <div class="trilho">
          <div class="trilho-barra"
               style="width:<?= e(number_format($pctDia, 1, '.', '')) ?>%;background:<?= $pctDia >= 100 ? 'var(--green)' : 'var(--red)' ?>"></div>
        </div>
        <div class="kpi-nota">
          <?= e(number_format($m['meta_diaria'] > 0 ? ($m['lucro_ultimo'] / $m['meta_diaria']) * 100 : 0, 0, ',', '.')) ?>%
          da meta de <?= e(brl($m['meta_diaria'])) ?>
        </div>
      </div>

      <div class="rodape-card">
        <span class="suave">Dias acima da meta</span>
        <span class="forte"><?= e($m['dias_na_meta_mes']) ?> de <?= e($m['dias_mes']) ?></span>
      </div>
    </div>
  </div>

  <!-- Últimos dias + despesas por categoria -->
  <div class="grade grade-2">
    <div class="card grade-larga">
      <h2 style="margin-bottom:14px">Últimos dias</h2>
      <div class="rolagem">
        <table class="tabela" style="min-width:<?= 300 + (count($plataformas) * 100) ?>px">
          <thead>
            <tr>
              <th>Data</th>
              <?php foreach ($plataformas as $p): ?>
                <th><?= e($p['nome']) ?></th>
              <?php endforeach; ?>
              <th>Km</th>
              <th>R$/km</th>
              <th>Despesas</th>
              <th>Lucro</th>
            </tr>
          </thead>
          <tbody>
            <?php if ($ultimas === []): ?>
              <tr><td class="vazio" colspan="<?= 5 + count($plataformas) ?>">Sem lançamentos neste mês.</td></tr>
            <?php endif; ?>

            <?php foreach ($ultimas as $d): ?>
              <?php
                $receita = $m['receita_dia'][$d];
                $km      = $m['km_dia'][$d] ?? 0.0;
                $desp    = $m['despesa_dia'][$d] ?? 0.0;
                $lucro   = $m['lucro_dia'][$d];
                $classe  = $lucro >= $m['meta_diaria'] ? 'verde' : ($lucro >= 0 ? '' : 'vermelho');
              ?>
              <tr>
                <td><?= e(dm($d)) ?></td>
                <?php foreach ($plataformas as $p): ?>
                  <?php $v = $m['por_data'][$d][(int) $p['id']] ?? 0.0; ?>
                  <td style="color:<?= e($p['cor']) ?>"><?= $v > 0 ? e(brl($v)) : '—' ?></td>
                <?php endforeach; ?>
                <td class="suave"><?= $km > 0 ? e(num($km)) : '—' ?></td>
                <td class="suave"><?= $km > 0 ? e(brl($receita / $km)) : '—' ?></td>
                <td class="suave"><?= $desp > 0 ? e(brl($desp)) : '—' ?></td>
                <td class="forte <?= $classe ?>"><?= e(brl($lucro)) ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>

    <div class="card">
      <h2 style="margin-bottom:4px">Despesas por categoria</h2>
      <p class="pagina-sub" style="font-size:13px;color:var(--ink3);margin:0 0 16px">
        Total <?= e(brl($m['despesas_mes'])) ?> em <?= e($m['mes_nome']) ?>
      </p>
      <div style="display:flex;flex-direction:column;gap:13px">
        <?php foreach ($m['categorias_mes'] as $c): ?>
          <div>
            <div class="linha-entre" style="margin-bottom:6px">
              <span style="color:var(--ink4)"><?= e($c['nome']) ?></span>
              <span style="font-weight:600"><?= e(brl($c['valor'])) ?></span>
            </div>
            <div class="trilho fina">
              <div class="trilho-barra"
                   style="width:<?= e(number_format(pct($c['valor'], $m['max_categoria_mes']), 1, '.', '')) ?>%;background:<?= e($c['cor']) ?>"></div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>

</div>
