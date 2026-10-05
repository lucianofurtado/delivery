<?php
/**
 * KM diário (odômetro).
 * @var array $registros linhas de km_diario (mais recente primeiro)
 * @var ?array $editando  registro em edição, ou null para um novo
 * @var array $m         métricas consolidadas
 */
$titulo     = 'KM diário';
$rota_ativa = 'km';

$somaKm  = 0;
foreach ($registros as $r) {
    $somaKm += (int) $r['km_rodados'];
}
$qtd    = count($registros);
$media  = $qtd > 0 ? $somaKm / $qtd : 0;
$kmIni  = ultimo_km_final();
$editando = $editando ?? null;
?>

<div class="pilha-md">

  <div>
    <h1 class="pagina-titulo">KM diário</h1>
    <p class="pagina-sub">
      <?= e(num($somaKm)) ?> km rodados em <?= e($qtd) ?> dias · média <?= e(num($media)) ?> km/dia
    </p>
  </div>

  <div class="card<?= $editando ? ' card-editando' : '' ?>" id="formulario">
    <h2 class="card-titulo">
      <?= $editando ? 'Editar km de ' . e(dmy($editando['data'])) : 'Registrar odômetro do dia' ?>
    </h2>
    <form method="post" action="index.php">
      <?= csrf_campo() ?>
      <input type="hidden" name="acao" value="salvar_km">
      <?php if ($editando): ?>
        <input type="hidden" name="id" value="<?= e($editando['id']) ?>">
      <?php endif; ?>

      <div class="form-grade">
        <label class="campo">Data
          <input type="date" name="data" value="<?= e($editando['data'] ?? date('Y-m-d')) ?>" required>
        </label>
        <label class="campo">KM inicial
          <input type="number" name="km_inicial" id="km_inicial" min="0" step="1"
                 value="<?= $editando ? e($editando['km_inicial']) : ($kmIni > 0 ? e($kmIni) : '') ?>" placeholder="57205" required>
        </label>
        <label class="campo">KM final
          <input type="number" name="km_final" id="km_final" min="0" step="1"
                 value="<?= $editando ? e($editando['km_final']) : '' ?>" placeholder="57290" required>
        </label>
        <div class="campo">KM rodados
          <div class="campo-calculado" id="previa-km">0 km</div>
        </div>
        <button type="submit" class="btn btn-principal"><?= $editando ? 'Salvar alterações' : 'Salvar km' ?></button>
        <?php if ($editando): ?>
          <a class="btn btn-secundario" href="<?= e(url('km')) ?>">Cancelar</a>
        <?php endif; ?>
      </div>

      <div class="form-nota">
        <?= $editando
              ? 'Você pode corrigir a data também, desde que não haja outro registro de km naquele dia.'
              : 'O KM inicial vem automático do KM final do último dia registrado.' ?>
      </div>
    </form>
  </div>

  <div class="card">
    <div class="rolagem">
      <table class="tabela" style="min-width:660px">
        <thead>
          <tr>
            <th>Data</th>
            <th>KM inicial</th>
            <th>KM final</th>
            <th>KM rodados</th>
            <th>Receita do dia</th>
            <th>R$/km</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <?php if ($registros === []): ?>
            <tr><td class="vazio" colspan="7">Nenhum km registrado ainda.</td></tr>
          <?php endif; ?>

          <?php foreach ($registros as $r): ?>
            <?php
              $km      = (int) $r['km_rodados'];
              $receita = $m['receita_dia'][$r['data']] ?? 0.0;
            ?>
            <tr<?= ($editando && (int) $editando['id'] === (int) $r['id']) ? ' class="linha-editando"' : '' ?>>
              <td><?= e(dm($r['data'])) ?></td>
              <td class="suave"><?= e(num($r['km_inicial'])) ?></td>
              <td class="suave"><?= e(num($r['km_final'])) ?></td>
              <td class="forte"><?= e(num($km)) ?> km</td>
              <td class="suave"><?= $receita > 0 ? e(brl($receita)) : '—' ?></td>
              <td class="verde forte"><?= ($km > 0 && $receita > 0) ? e(brl($receita / $km)) : '—' ?></td>
              <td class="acoes">
                <a class="btn-editar" title="Editar" href="<?= e(url('km', ['editar' => $r['id']])) ?>#formulario">&#9998;</a>
                <form method="post" action="index.php" style="display:inline"
                      data-confirmar="Excluir o km de <?= e(dmy($r['data'])) ?>?">
                  <?= csrf_campo() ?>
                  <input type="hidden" name="acao" value="excluir_km">
                  <input type="hidden" name="id" value="<?= e($r['id']) ?>">
                  <button type="submit" class="btn-excluir" title="Excluir">&times;</button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

</div>
