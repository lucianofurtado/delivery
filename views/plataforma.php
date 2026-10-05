<?php
/**
 * Registro diário de uma plataforma.
 * Formulário e tabela são montados a partir de `plataforma_campos`,
 * então cada empresa tem os seus próprios campos sem mexer no código.
 *
 * @var array $plataforma  linha de `plataformas`
 * @var array $lancamentos lançamentos da plataforma (mais recente primeiro)
 * @var ?array $editando   lançamento em edição, ou null para um novo
 * @var array $m           métricas consolidadas
 */
$titulo     = 'Registro diário — ' . $plataforma['nome'];
$rota_ativa = 'plataforma';

$campos    = campos_da_plataforma((int) $plataforma['id']);
$dinheiro  = campos_de_dinheiro($campos);
$porSolic  = tem_campo($campos, 'solicitacoes');

$total = 0.0;
foreach ($lancamentos as $r) {
    $total += (float) $r['total'];
}

// Larguras: rótulo + valor por coluna, com folga para data e total.
$colunas    = count($campos) + 3;
$larguraMin = 300 + (count($campos) * 95);
$hoje       = date('Y-m-d');
$editando   = $editando ?? null;

/** Valor do campo no formulário: vazio para lançamento novo, o gravado em edição. */
$valorCampo = static function (array $campo) use ($editando): string {
    if ($editando === null) {
        return '';
    }
    $v = $editando[$campo['coluna']];

    return $campo['tipo'] === 'dinheiro'
        ? number_format((float) $v, 2, '.', '')
        : (string) (int) $v;
};
?>

<div class="pilha-md">

  <div class="titulo-com-barra">
    <div class="barra-cor" style="background:<?= e($plataforma['cor']) ?>"></div>
    <div>
      <h1 class="pagina-titulo">Registro diário — <?= e($plataforma['nome']) ?></h1>
      <p class="pagina-sub"><?= e(count($lancamentos)) ?> dias lançados · total <?= e(brl($total)) ?></p>
    </div>
  </div>

  <!-- Formulário -->
  <div class="card<?= $editando ? ' card-editando' : '' ?>" id="formulario">
    <h2 class="card-titulo">
      <?= $editando ? 'Editar lançamento de ' . e(dmy($editando['data'])) : 'Novo lançamento' ?>
    </h2>

    <?php if ($campos === []): ?>
      <p class="vazio">
        Esta plataforma ainda não tem campos configurados.
        Cadastre-os na tabela <code>plataforma_campos</code>.
      </p>
    <?php else: ?>
      <form method="post" action="index.php">
        <?= csrf_campo() ?>
        <input type="hidden" name="acao" value="salvar_lancamento">
        <input type="hidden" name="slug" value="<?= e($plataforma['slug']) ?>">
        <?php if ($editando): ?>
          <input type="hidden" name="id" value="<?= e($editando['id']) ?>">
        <?php endif; ?>

        <div class="form-grade<?= count($campos) <= 4 ? ' larga' : '' ?>">
          <label class="campo">Data
            <input type="date" name="data" value="<?= e($editando['data'] ?? $hoje) ?>" required>
          </label>

          <?php foreach ($campos as $campo): ?>
            <?php $ehDinheiro = $campo['tipo'] === 'dinheiro'; ?>
            <label class="campo">
              <?= e($campo['rotulo']) ?><?= $ehDinheiro ? ' R$' : '' ?>
              <input type="number"
                     name="<?= e($campo['coluna']) ?>"
                     id="<?= e($campo['coluna']) ?>"
                     step="<?= $ehDinheiro ? '0.01' : '1' ?>"
                     <?= $ehDinheiro ? '' : 'min="0"' ?>
                     value="<?= e($valorCampo($campo)) ?>"
                     placeholder="<?= $ehDinheiro ? '0,00' : '0' ?>">
            </label>
          <?php endforeach; ?>

          <button type="submit" class="btn"
                  style="background:<?= e($plataforma['cor']) ?>;color:<?= e(cor_texto($plataforma['cor'])) ?>"><?= $editando ? 'Salvar alterações' : 'Salvar dia' ?></button>
          <?php if ($editando): ?>
            <a class="btn btn-secundario" href="<?= e(url('plataforma', ['slug' => $plataforma['slug']])) ?>">Cancelar</a>
          <?php endif; ?>
        </div>

        <?php if (count($dinheiro) > 1): ?>
          <div class="form-nota">
            Total do dia calculado: <strong id="previa-total">R$ 0,00</strong>
          </div>
        <?php endif; ?>

        <div class="form-nota" style="border-top:none;padding-top:0;margin-top:8px">
          <?= $editando
                ? 'Você pode corrigir a data também, desde que não haja outro lançamento naquele dia.'
                : 'Salvar duas vezes a mesma data substitui o lançamento daquele dia.' ?>
        </div>
      </form>
    <?php endif; ?>
  </div>

  <!-- Lançamentos -->
  <div class="card">
    <div class="rolagem">
      <table class="tabela" style="min-width:<?= e($larguraMin) ?>px">
        <thead>
          <tr>
            <th>Data</th>
            <?php foreach ($campos as $campo): ?>
              <th><?= e($campo['rotulo']) ?></th>
            <?php endforeach; ?>
            <th>Total do dia</th>
            <th><?= $porSolic ? 'Ganho por solicitação' : 'R$/km' ?></th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <?php if ($lancamentos === []): ?>
            <tr><td class="vazio" colspan="<?= e($colunas) ?>">Nenhum lançamento registrado.</td></tr>
          <?php endif; ?>

          <?php foreach ($lancamentos as $r): ?>
            <?php
              $km        = $m['km_dia'][$r['data']] ?? 0.0;
              $solic     = (int) $r['solicitacoes'];
              $totalDia  = (float) $r['total'];

              if ($porSolic) {
                  $derivado = $solic > 0 ? brl($totalDia / $solic) : '—';
              } else {
                  $derivado = $km > 0 ? brl($totalDia / $km) : '—';
              }
            ?>
            <tr<?= ($editando && (int) $editando['id'] === (int) $r['id']) ? ' class="linha-editando"' : '' ?>>
              <td><?= e(dm($r['data'])) ?></td>

              <?php foreach ($campos as $campo): ?>
                <td class="suave">
                  <?= $campo['tipo'] === 'dinheiro'
                        ? e(brl($r[$campo['coluna']]))
                        : e(num($r[$campo['coluna']])) ?>
                </td>
              <?php endforeach; ?>

              <td class="forte" style="color:<?= e($plataforma['cor']) ?>"><?= e(brl($totalDia)) ?></td>
              <td class="suave"><?= e($derivado) ?></td>

              <td class="acoes">
                <a class="btn-editar" title="Editar"
                   href="<?= e(url('plataforma', ['slug' => $plataforma['slug'], 'editar' => $r['id']])) ?>#formulario">&#9998;</a>
                <form method="post" action="index.php" style="display:inline"
                      data-confirmar="Excluir o lançamento de <?= e(dmy($r['data'])) ?>?">
                  <?= csrf_campo() ?>
                  <input type="hidden" name="acao" value="excluir_lancamento">
                  <input type="hidden" name="slug" value="<?= e($plataforma['slug']) ?>">
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
