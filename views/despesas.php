<?php
/**
 * Despesas.
 * @var array $despesas   lista de despesas
 * @var array $categorias categorias ativas
 * @var array $m          métricas consolidadas
 */
$titulo     = 'Despesas';
$rota_ativa = 'despesas';

$pctReceita = $m['receita'] > 0 ? ($m['despesas'] / $m['receita']) * 100 : 0.0;
?>

<div class="pilha-md">

  <div>
    <h1 class="pagina-titulo">Despesas</h1>
    <p class="pagina-sub">
      Total de <?= e(brl($m['despesas'])) ?> no período —
      <?= e(number_format($pctReceita, 1, ',', '.')) ?>% da receita bruta
    </p>
  </div>

  <div class="card">
    <h2 class="card-titulo">Nova despesa</h2>
    <form method="post" action="index.php">
      <?= csrf_campo() ?>
      <input type="hidden" name="acao" value="salvar_despesa">

      <div class="form-grade larga">
        <label class="campo">Data
          <input type="date" name="data" value="<?= e(date('Y-m-d')) ?>" required>
        </label>
        <label class="campo">Categoria
          <select name="categoria_id" required>
            <?php foreach ($categorias as $c): ?>
              <option value="<?= e($c['id']) ?>"><?= e($c['nome']) ?></option>
            <?php endforeach; ?>
          </select>
        </label>
        <label class="campo">Descrição
          <input type="text" name="descricao" maxlength="120" placeholder="Gasolina">
        </label>
        <label class="campo">Valor R$
          <input type="number" name="valor" step="0.01" min="0" placeholder="0,00" required>
        </label>
        <button type="submit" class="btn btn-principal">Salvar despesa</button>
      </div>
    </form>
  </div>

  <div class="grade grade-2">
    <div class="card grade-larga">
      <div class="rolagem">
        <table class="tabela" style="min-width:480px">
          <thead>
            <tr style="text-align:left">
              <th>Data</th>
              <th>Categoria</th>
              <th>Descrição</th>
              <th style="text-align:right">Valor</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            <?php if ($despesas === []): ?>
              <tr><td class="vazio" colspan="5">Nenhuma despesa registrada.</td></tr>
            <?php endif; ?>

            <?php foreach ($despesas as $d): ?>
              <tr style="text-align:left">
                <td><?= e(dm($d['data'])) ?></td>
                <td><span class="tag" style="border-color:<?= e($d['cor']) ?>"><?= e($d['categoria']) ?></span></td>
                <td class="suave"><?= e($d['descricao']) ?></td>
                <td class="forte vermelho" style="text-align:right"><?= e(brl($d['valor'])) ?></td>
                <td style="text-align:right">
                  <form method="post" action="index.php" style="display:inline"
                        data-confirmar="Excluir a despesa de <?= e(brl($d['valor'])) ?>?">
                    <?= csrf_campo() ?>
                    <input type="hidden" name="acao" value="excluir_despesa">
                    <input type="hidden" name="id" value="<?= e($d['id']) ?>">
                    <button type="submit" class="btn-excluir" title="Excluir">&times;</button>
                  </form>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>

    <div class="card">
      <h2 style="margin-bottom:16px">Por categoria</h2>
      <div style="display:flex;flex-direction:column;gap:13px">
        <?php foreach ($m['categorias'] as $c): ?>
          <div>
            <div class="linha-entre" style="margin-bottom:6px">
              <span style="color:var(--ink4)"><?= e($c['nome']) ?></span>
              <span style="font-weight:600"><?= e(brl($c['valor'])) ?></span>
            </div>
            <div class="trilho fina">
              <div class="trilho-barra"
                   style="width:<?= e(number_format(pct($c['valor'], $m['max_categoria']), 1, '.', '')) ?>%;background:<?= e($c['cor']) ?>"></div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>

</div>
