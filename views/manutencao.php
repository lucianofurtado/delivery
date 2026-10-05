<?php
/**
 * Manutenção: peças e serviços trocados a cada X km (pneu, óleo, relação...).
 * A contagem usa o odômetro do KM diário (último km final registrado).
 * @var array  $itens    itens com a situação calculada (manutencoes_situacao)
 * @var array  $trocas   histórico de trocas por item [id => [...]]
 * @var int    $kmAtual  odômetro atual
 * @var ?array $editando item em edição, ou null para um novo
 */
$titulo     = 'Manutenção';
$rota_ativa = 'manutencao';

$hoje     = date('Y-m-d');
$editando = $editando ?? null;

$selos = [
    'vencida' => ['rotulo' => 'Trocar agora', 'classe' => 'abaixo', 'cor' => 'var(--red)'],
    'proxima' => ['rotulo' => 'Trocar em breve', 'classe' => 'alerta', 'cor' => 'var(--amber)'],
    'ok'      => ['rotulo' => 'Em dia', 'classe' => 'ok', 'cor' => 'var(--green)'],
];
?>

<div class="pilha-md">

  <div>
    <h1 class="pagina-titulo">Manutenção</h1>
    <p class="pagina-sub">
      <?php if ($kmAtual > 0): ?>
        Odômetro atual: <span class="forte"><?= e(num($kmAtual)) ?> km</span>, pelo último registro do KM diário
      <?php else: ?>
        Registre o odômetro no <a href="<?= e(url('km')) ?>">KM diário</a> para acompanhar os km rodados desde cada troca
      <?php endif; ?>
    </p>
  </div>

  <div class="card<?= $editando ? ' card-editando' : '' ?>" id="formulario">
    <h2 class="card-titulo"><?= $editando ? 'Editar ' . e($editando['nome']) : 'Nova peça ou serviço' ?></h2>
    <form method="post" action="index.php">
      <?= csrf_campo() ?>
      <input type="hidden" name="acao" value="salvar_manutencao">
      <?php if ($editando): ?>
        <input type="hidden" name="id" value="<?= e($editando['id']) ?>">
      <?php endif; ?>

      <div class="form-grade larga">
        <label class="campo">Peça / serviço
          <input type="text" name="nome" maxlength="80" placeholder="Troca de óleo"
                 value="<?= e($editando['nome'] ?? '') ?>" required>
        </label>
        <label class="campo">Trocar a cada (km)
          <input type="number" name="intervalo_km" min="1" step="1" placeholder="20000"
                 value="<?= e($editando['intervalo_km'] ?? '') ?>" required>
        </label>
        <label class="campo">KM da última troca
          <input type="number" name="km_ultima" min="0" step="1" placeholder="57205"
                 value="<?= $editando ? e($editando['km_ultima']) : ($kmAtual > 0 ? e($kmAtual) : '') ?>" required>
        </label>
        <label class="campo">Data da última troca
          <input type="date" name="data_ultima" value="<?= e($editando['data_ultima'] ?? $hoje) ?>">
        </label>
        <label class="campo">Avisar faltando (km)
          <input type="number" name="aviso_km" min="0" step="1"
                 value="<?= e($editando['aviso_km'] ?? 500) ?>">
        </label>
        <button type="submit" class="btn btn-principal"><?= $editando ? 'Salvar alterações' : 'Cadastrar' ?></button>
        <?php if ($editando): ?>
          <a class="btn btn-secundario" href="<?= e(url('manutencao')) ?>">Cancelar</a>
        <?php endif; ?>
      </div>

      <div class="form-nota">
        Ex.: <strong>Pneu traseiro</strong> a cada 20.000 km, <strong>Óleo</strong> a cada 1.000 km.
        O km da última troca já vem com o odômetro atual — ajuste se a troca foi antes.
      </div>
    </form>
  </div>

  <?php if ($itens === []): ?>
    <div class="card"><p class="vazio">Nenhuma peça ou serviço cadastrado.</p></div>
  <?php endif; ?>

  <div class="grade grade-2">
    <?php foreach ($itens as $it): ?>
      <?php
        $sel       = $selos[$it['status']];
        $historico = $trocas[(int) $it['id']] ?? [];
      ?>
      <div class="card manut-card" style="border-left:3px solid <?= $sel['cor'] ?>">
        <div class="linha-entre" style="align-items:flex-start;margin-bottom:0">
          <div>
            <h2><?= e($it['nome']) ?></h2>
            <div class="kpi-nota" style="margin-top:4px">
              A cada <?= e(num($it['intervalo_km'])) ?> km · última troca em
              <?= e(num($it['km_ultima'])) ?> km<?= $it['data_ultima'] ? ' (' . e(dmy($it['data_ultima'])) . ')' : '' ?>
            </div>
          </div>
          <span class="selo <?= e($sel['classe']) ?>"><?= e($sel['rotulo']) ?></span>
        </div>

        <div>
          <div class="linha-entre">
            <span class="suave"><?= e(num($it['rodados'])) ?> km rodados</span>
            <?php if ($it['falta_km'] > 0): ?>
              <span class="forte">faltam <?= e(num($it['falta_km'])) ?> km</span>
            <?php else: ?>
              <span class="forte vermelho"><?= e(num(abs($it['falta_km']))) ?> km além do limite</span>
            <?php endif; ?>
          </div>
          <div class="trilho">
            <div class="trilho-barra"
                 style="width:<?= e(number_format($it['progresso'], 1, '.', '')) ?>%;background:<?= $sel['cor'] ?>"></div>
          </div>
          <div class="kpi-nota">Próxima troca em <?= e(num($it['proxima_km'])) ?> km</div>
        </div>

        <form method="post" action="index.php" class="manut-troca"
              data-confirmar="Registrar a troca de &quot;<?= e($it['nome']) ?>&quot;? A contagem recomeça do km informado.">
          <?= csrf_campo() ?>
          <input type="hidden" name="acao" value="registrar_troca">
          <input type="hidden" name="id" value="<?= e($it['id']) ?>">
          <label class="campo">Data
            <input type="date" name="data" value="<?= e($hoje) ?>" required>
          </label>
          <label class="campo">KM na troca
            <input type="number" name="km" min="1" step="1"
                   value="<?= $kmAtual > 0 ? e($kmAtual) : '' ?>" placeholder="57205" required>
          </label>
          <button type="submit" class="btn btn-secundario">Registrar troca</button>
        </form>

        <div class="rodape-card" style="align-items:center">
          <?php if (count($historico) > 1): ?>
            <details class="manut-historico">
              <summary><?= e(count($historico)) ?> trocas registradas</summary>
              <ul>
                <?php foreach ($historico as $h): ?>
                  <li><?= e(dmy($h['data'])) ?> · <?= e(num($h['km'])) ?> km</li>
                <?php endforeach; ?>
              </ul>
            </details>
          <?php else: ?>
            <span class="suave" style="font-size:12px">Sem trocas anteriores</span>
          <?php endif; ?>
          <span style="white-space:nowrap">
            <a class="btn-editar" title="Editar" href="<?= e(url('manutencao', ['editar' => $it['id']])) ?>#formulario">&#9998;</a>
            <form method="post" action="index.php" style="display:inline"
                  data-confirmar="Excluir &quot;<?= e($it['nome']) ?>&quot; e todo o histórico de trocas?">
              <?= csrf_campo() ?>
              <input type="hidden" name="acao" value="excluir_manutencao">
              <input type="hidden" name="id" value="<?= e($it['id']) ?>">
              <button type="submit" class="btn-excluir" title="Excluir">&times;</button>
            </form>
          </span>
        </div>
      </div>
    <?php endforeach; ?>
  </div>

</div>
