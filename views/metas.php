<?php
/**
 * Metas diária e mensal.
 * @var array $m métricas consolidadas
 */
$titulo     = 'Metas';
$rota_ativa = 'metas';

$pctDia = pct($m['lucro_ultimo'], $m['meta_diaria']);
$pctMes = pct($m['lucro_mes'], $m['meta_mensal']);

$diasDesc = array_reverse($m['datas']);
?>

<div class="pilha-md">

  <div>
    <h1 class="pagina-titulo">Metas</h1>
    <p class="pagina-sub">Defina quanto precisa girar por dia e por mês</p>
  </div>

  <form method="post" action="index.php">
    <?= csrf_campo() ?>
    <input type="hidden" name="acao" value="salvar_metas">

    <div class="grade grade-2">
      <div class="card" style="padding:22px;display:flex;flex-direction:column;gap:16px">
        <h2>Meta diária</h2>
        <label class="campo">Lucro alvo por dia (R$)
          <input type="number" name="meta_diaria" step="0.01" min="0"
                 value="<?= e(number_format($m['meta_diaria'], 2, '.', '')) ?>"
                 style="font-size:16px;font-weight:700;min-height:46px">
        </label>
        <div>
          <div class="linha-entre">
            <span class="suave">Último dia</span>
            <span class="forte"><?= e(brl($m['lucro_ultimo'])) ?></span>
          </div>
          <div class="trilho">
            <div class="trilho-barra"
                 style="width:<?= e(number_format($pctDia, 1, '.', '')) ?>%;background:<?= $pctDia >= 100 ? 'var(--green)' : 'var(--red)' ?>"></div>
          </div>
        </div>
        <div style="font-size:13px;color:var(--ink2)">
          <?php if ($pctDia >= 100): ?>
            O último dia fechou acima da meta.
          <?php else: ?>
            No último dia faltaram <?= e(brl($m['meta_diaria'] - $m['lucro_ultimo'])) ?> para a meta diária.
          <?php endif; ?>
        </div>
      </div>

      <div class="card" style="padding:22px;display:flex;flex-direction:column;gap:16px">
        <h2>Meta mensal</h2>
        <label class="campo">Lucro alvo no mês (R$)
          <input type="number" name="meta_mensal" step="0.01" min="0"
                 value="<?= e(number_format($m['meta_mensal'], 2, '.', '')) ?>"
                 style="font-size:16px;font-weight:700;min-height:46px">
        </label>
        <div>
          <div class="linha-entre">
            <span class="suave">Mês atual</span>
            <span class="forte"><?= e(brl($m['lucro_mes'])) ?></span>
          </div>
          <div class="trilho">
            <div class="trilho-barra"
                 style="width:<?= e(number_format($pctMes, 1, '.', '')) ?>%;background:<?= $pctMes >= 100 ? 'var(--green)' : 'var(--red)' ?>"></div>
          </div>
        </div>
        <?php if ($m['meta_mes_batida']): ?>
          <div style="font-size:13px;color:var(--ink2)">
            Meta de <?= e($m['mes_nome']) ?> batida. Sobrou
            <?= e(brl($m['lucro_mes'] - $m['meta_mensal'])) ?>.
          </div>
        <?php else: ?>
          <div style="font-size:13px;color:var(--ink2);text-wrap:pretty">
            Faltam <strong style="color:var(--ink)"><?= e(brl($m['falta_mes'])) ?></strong>
            para fechar <?= e($m['mes_nome']) ?> na meta.
          </div>

          <!-- Ritmo necessário até o fim do mês -->
          <div style="background:var(--sunk);border:1px solid var(--line);border-radius:10px;padding:14px">
            <div class="rotulo">Precisa ganhar por dia</div>
            <div class="display verde" style="font-size:26px;margin-top:6px">
              <?= e(brl($m['por_dia_necessario'])) ?>
            </div>
            <div class="kpi-nota">
              <?= e($m['dias_restantes']) ?>
              <?= $m['dias_restantes'] === 1 ? 'dia restante' : 'dias restantes' ?>
              (hoje até <?= e(dm($m['ultimo_dia_do_mes'])) ?>)
            </div>

            <?php if ($m['meta_diaria'] > 0): ?>
              <?php $folga = $m['meta_diaria'] - $m['por_dia_necessario']; ?>
              <div style="margin-top:10px;padding-top:10px;border-top:1px solid var(--line);font-size:12px">
                <?php if ($folga >= 0): ?>
                  <span class="verde">
                    <?= e(brl($folga)) ?> abaixo da sua meta diária — mantendo o ritmo, o mês fecha.
                  </span>
                <?php else: ?>
                  <span class="vermelho">
                    <?= e(brl(-$folga)) ?> acima da sua meta diária — precisa apertar o passo.
                  </span>
                <?php endif; ?>
              </div>
            <?php endif; ?>
          </div>
        <?php endif; ?>
      </div>
    </div>

    <div style="margin-top:14px">
      <button type="submit" class="btn btn-principal">Salvar metas</button>
    </div>
  </form>

  <div class="card" style="padding:22px">
    <h2 style="margin-bottom:16px">Dia a dia vs meta</h2>
    <div style="display:flex;flex-direction:column;gap:10px">
      <?php if ($diasDesc === []): ?>
        <p class="vazio">Sem dias registrados ainda.</p>
      <?php endif; ?>

      <?php foreach ($diasDesc as $d): ?>
        <?php
          $lucro = $m['lucro_dia'][$d];
          $ok    = $lucro >= $m['meta_diaria'];
          $largura = $m['meta_diaria'] > 0 ? pct($lucro, $m['meta_diaria']) : 0.0;
        ?>
        <div class="meta-linha">
          <span class="data"><?= e(dm($d)) ?></span>
          <div class="trilho media">
            <div class="trilho-barra"
                 style="width:<?= e(number_format($largura, 1, '.', '')) ?>%;background:<?= $ok ? 'var(--green)' : 'var(--red)' ?>"></div>
          </div>
          <span class="valor"><?= e(brl($lucro)) ?></span>
          <span class="selo <?= $ok ? 'ok' : 'abaixo' ?>"><?= $ok ? 'na meta' : 'abaixo' ?></span>
        </div>
      <?php endforeach; ?>
    </div>
  </div>

</div>
