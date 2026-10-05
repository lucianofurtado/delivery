<?php
/**
 * Resumo mensal.
 * @var array $m métricas consolidadas
 */
$titulo     = 'Resumo mensal';
$rota_ativa = 'mensal';

$plataformas = $m['plataformas'];
?>

<div class="pilha-md">

  <div>
    <h1 class="pagina-titulo">Resumo mensal</h1>
    <p class="pagina-sub">
      Fechamento por mês, com lucro líquido e eficiência por km.
      Eventos concluídos entram no mês em que terminam.
    </p>
  </div>

  <?php if ($m['meses'] === []): ?>
    <div class="card"><p class="vazio">Nenhum mês fechado ainda.</p></div>
  <?php endif; ?>

  <?php foreach ($m['meses'] as $mes): ?>
    <div class="card" style="padding:22px">
      <div class="mes-topo">
        <h2 class="display" style="margin:0;font-size:20px"><?= e($mes['nome']) ?></h2>
        <span style="font-size:13px;color:var(--ink3)"><?= e($mes['dias']) ?> dias trabalhados</span>
      </div>

      <div class="mes-grade">
        <?php foreach ($plataformas as $p): ?>
          <div>
            <div class="rotulo"><?= e($p['nome']) ?></div>
            <div class="mes-valor" style="color:<?= e($p['cor']) ?>">
              <?= e(brl($mes['plataformas'][(int) $p['id']] ?? 0)) ?>
            </div>
          </div>
        <?php endforeach; ?>

        <div>
          <div class="rotulo">Eventos</div>
          <div class="mes-valor ambar"><?= e(brl($mes['eventos'])) ?></div>
          <?php if ($mes['qtd_eventos'] > 0): ?>
            <div class="kpi-nota"><?= e($mes['qtd_eventos']) ?> <?= $mes['qtd_eventos'] === 1 ? 'concluído' : 'concluídos' ?></div>
          <?php endif; ?>
        </div>

        <div>
          <div class="rotulo">Receita</div>
          <div class="mes-valor"><?= e(brl($mes['receita'])) ?></div>
        </div>
        <div>
          <div class="rotulo">Despesas</div>
          <div class="mes-valor" style="color:var(--ink4)"><?= e(brl($mes['despesas'])) ?></div>
        </div>
        <div>
          <div class="rotulo">Lucro líquido</div>
          <div class="mes-valor verde"><?= e(brl($mes['lucro'])) ?></div>
        </div>
        <div>
          <div class="rotulo">Km rodados</div>
          <div class="mes-valor"><?= e(num($mes['km'])) ?> km</div>
        </div>
        <div>
          <div class="rotulo">R$/km</div>
          <div class="mes-valor"><?= e(brl($mes['por_km'])) ?></div>
        </div>
      </div>

      <?php if ($mes['receita'] > 0): ?>
        <div style="margin-top:18px">
          <div class="mes-split">
            <?php foreach ($plataformas as $p): ?>
              <?php $fatia = pct($mes['plataformas'][(int) $p['id']] ?? 0, $mes['receita']); ?>
              <?php if ($fatia > 0): ?>
                <div style="width:<?= e(number_format($fatia, 1, '.', '')) ?>%;background:<?= e($p['cor']) ?>"></div>
              <?php endif; ?>
            <?php endforeach; ?>
            <?php $fatia = pct($mes['eventos'], $mes['receita']); ?>
            <?php if ($fatia > 0): ?>
              <div style="width:<?= e(number_format($fatia, 1, '.', '')) ?>%;background:var(--amber);opacity:.55"></div>
            <?php endif; ?>
          </div>
          <div class="kpi-nota">
            Média de <?= e(brl($mes['media_dia'])) ?> por dia trabalhado ·
            meta mensal <?= e(brl($m['meta_mensal'])) ?>
          </div>
        </div>
      <?php endif; ?>
    </div>
  <?php endforeach; ?>

</div>
