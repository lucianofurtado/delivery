<?php
/**
 * Eventos: metas pontuais com recompensa (ex.: "60 entregas em 15 dias = R$ 3000").
 * @var array $eventos     lista de eventos
 * @var array $plataformas plataformas ativas
 * @var float $ganhoTotal  soma das recompensas de eventos concluídos
 */
$titulo     = 'Eventos';
$rota_ativa = 'eventos';

$hoje = date('Y-m-d');

function evento_status(array $ev, string $hoje): array
{
    if ((int) $ev['concluido'] === 1) {
        return ['rotulo' => 'Concluído', 'classe' => 'ok'];
    }
    if ($ev['data_fim'] < $hoje) {
        return ['rotulo' => 'Não batido', 'classe' => 'abaixo'];
    }

    return ['rotulo' => 'Em andamento', 'classe' => 'pendente'];
}
?>

<div class="pilha-md">

  <div>
    <h1 class="pagina-titulo">Eventos</h1>
    <p class="pagina-sub">
      Metas pontuais com recompensa das plataformas —
      <span class="forte verde"><?= e(brl($ganhoTotal)) ?></span> ganhos até agora
    </p>
  </div>

  <div class="card">
    <h2 class="card-titulo">Novo evento</h2>
    <form method="post" action="index.php">
      <?= csrf_campo() ?>
      <input type="hidden" name="acao" value="salvar_evento">

      <div class="form-grade larga">
        <label class="campo">Título
          <input type="text" name="titulo" maxlength="120" placeholder="60 entregas em 15 dias" required>
        </label>
        <label class="campo">Plataforma
          <select name="plataforma_id">
            <option value="">Geral</option>
            <?php foreach ($plataformas as $p): ?>
              <option value="<?= e($p['id']) ?>"><?= e($p['nome']) ?></option>
            <?php endforeach; ?>
          </select>
        </label>
        <label class="campo">Meta
          <input type="number" name="meta_qtd" min="0" step="1" placeholder="60" required>
        </label>
        <label class="campo">Unidade
          <input type="text" name="meta_unidade" maxlength="30" value="entregas">
        </label>
        <label class="campo">Prazo (dias)
          <input type="number" name="prazo_dias" min="1" step="1" placeholder="15" required>
        </label>
        <label class="campo">Recompensa R$
          <input type="number" name="recompensa" step="0.01" min="0" placeholder="3000,00" required>
        </label>
        <label class="campo">Início
          <input type="date" name="data_inicio" value="<?= e($hoje) ?>" required>
        </label>
        <button type="submit" class="btn btn-principal">Salvar evento</button>
      </div>
    </form>
  </div>

  <div class="card">
    <div class="rolagem">
      <table class="tabela" style="min-width:720px">
        <thead>
          <tr style="text-align:left">
            <th>Evento</th>
            <th>Plataforma</th>
            <th>Período</th>
            <th style="text-align:right">Recompensa</th>
            <th>Status</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <?php if ($eventos === []): ?>
            <tr><td class="vazio" colspan="6">Nenhum evento registrado.</td></tr>
          <?php endif; ?>

          <?php foreach ($eventos as $ev): ?>
            <?php $st = evento_status($ev, $hoje); ?>
            <tr style="text-align:left">
              <td>
                <div class="forte"><?= e($ev['titulo']) ?></div>
                <div class="suave" style="font-size:12px">
                  Meta: <?= e(num($ev['meta_qtd'])) ?> <?= e($ev['meta_unidade']) ?> em <?= e($ev['prazo_dias']) ?> dias
                  <?php if ($ev['resultado_qtd'] !== null): ?>
                    · fez <?= e(num($ev['resultado_qtd'])) ?>
                  <?php endif; ?>
                </div>
              </td>
              <td>
                <?php if ($ev['plataforma']): ?>
                  <span class="tag" style="border-color:<?= e($ev['cor']) ?>"><?= e($ev['plataforma']) ?></span>
                <?php else: ?>
                  <span class="suave">Geral</span>
                <?php endif; ?>
              </td>
              <td class="suave"><?= e(dm($ev['data_inicio'])) ?> – <?= e(dm($ev['data_fim'])) ?></td>
              <td class="forte verde" style="text-align:right"><?= e(brl($ev['recompensa'])) ?></td>
              <td><span class="selo <?= e($st['classe']) ?>"><?= e($st['rotulo']) ?></span></td>
              <td style="text-align:right;white-space:nowrap">
                <form method="post" action="index.php" style="display:inline">
                  <?= csrf_campo() ?>
                  <input type="hidden" name="id" value="<?= e($ev['id']) ?>">
                  <?php if ((int) $ev['concluido'] === 1): ?>
                    <input type="hidden" name="acao" value="reabrir_evento">
                    <button type="submit" class="btn-chip">Reabrir</button>
                  <?php else: ?>
                    <input type="hidden" name="acao" value="concluir_evento">
                    <input type="number" name="resultado_qtd" min="0" step="1"
                           placeholder="qtd." title="Quantidade batida (opcional)"
                           style="width:64px;display:inline-block;margin-right:6px">
                    <button type="submit" class="btn-chip">Marcar concluído</button>
                  <?php endif; ?>
                </form>
                <form method="post" action="index.php" style="display:inline"
                      data-confirmar="Excluir o evento &quot;<?= e($ev['titulo']) ?>&quot;?">
                  <?= csrf_campo() ?>
                  <input type="hidden" name="acao" value="excluir_evento">
                  <input type="hidden" name="id" value="<?= e($ev['id']) ?>">
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
