<?php
declare(strict_types=1);

/**
 * GIRO — Controle de Delivery
 * Front controller: trata as ações (POST) e escolhe a tela (GET).
 */

require_once __DIR__ . '/app/bootstrap.php';

exigir_login();

// ------------------------------------------------------------
// Ações (POST)
// ------------------------------------------------------------
if (is_post()) {
    csrf_verificar();
    $acao = post('acao');

    switch ($acao) {
        case 'salvar_lancamento':
            acao_salvar_lancamento();
            break;

        case 'excluir_lancamento':
            $slug = post('slug');
            excluir_lancamento((int) post('id'));
            flash('Lançamento excluído.');
            redirect(url('plataforma', ['slug' => $slug]));

        case 'salvar_km':
            acao_salvar_km();
            break;

        case 'excluir_km':
            excluir_km((int) post('id'));
            flash('Registro de km excluído.');
            redirect(url('km'));

        case 'salvar_despesa':
            acao_salvar_despesa();
            break;

        case 'excluir_despesa':
            excluir_despesa((int) post('id'));
            flash('Despesa excluída.');
            redirect(url('despesas'));

        case 'salvar_metas':
            acao_salvar_metas();
            break;

        case 'salvar_evento':
            acao_salvar_evento();
            break;

        case 'concluir_evento':
            acao_marcar_evento(true);
            break;

        case 'reabrir_evento':
            acao_marcar_evento(false);
            break;

        case 'excluir_evento':
            excluir_evento((int) post('id'));
            flash('Evento excluído.');
            redirect(url('eventos'));

        case 'salvar_manutencao':
            acao_salvar_manutencao();
            break;

        case 'registrar_troca':
            acao_registrar_troca();
            break;

        case 'excluir_manutencao':
            excluir_manutencao((int) post('id'));
            flash('Item de manutenção excluído.');
            redirect(url('manutencao'));

        default:
            flash('Ação desconhecida.', 'erro');
            redirect(url('dashboard'));
    }
}

// ------------------------------------------------------------
// Telas (GET)
// ------------------------------------------------------------
$rota = get('r', 'dashboard');

switch ($rota) {
    case 'plataforma':
        $plataforma = plataforma_por_slug(get('slug'));
        if ($plataforma === null) {
            flash('Plataforma não encontrada.', 'erro');
            redirect(url('dashboard'));
        }
        $editando = null;
        if (get('editar') !== '') {
            $editando = lancamento_por_id((int) get('editar'), (int) $plataforma['id']);
            if ($editando === null) {
                flash('Lançamento não encontrado.', 'erro');
                redirect(url('plataforma', ['slug' => $plataforma['slug']]));
            }
        }
        render('plataforma', [
            'plataforma'  => $plataforma,
            'lancamentos' => lancamentos_da_plataforma((int) $plataforma['id']),
            'editando'    => $editando,
            'm'           => metricas(),
        ]);
        break;

    case 'km':
        $editando = null;
        if (get('editar') !== '') {
            $editando = km_por_id((int) get('editar'));
            if ($editando === null) {
                flash('Registro de km não encontrado.', 'erro');
                redirect(url('km'));
            }
        }
        render('km', [
            'registros' => km_lista(),
            'editando'  => $editando,
            'm'         => metricas(),
        ]);
        break;

    case 'despesas':
        render('despesas', [
            'despesas'   => despesas_lista(),
            'categorias' => categorias(),
            'm'          => metricas(),
        ]);
        break;

    case 'mensal':
        render('mensal', ['m' => metricas()]);
        break;

    case 'metas':
        render('metas', ['m' => metricas()]);
        break;

    case 'eventos':
        render('eventos', [
            'eventos'     => eventos_lista(),
            'plataformas' => plataformas(),
            'ganhoTotal'  => eventos_ganho_total(),
        ]);
        break;

    case 'manutencao':
        $editando = null;
        if (get('editar') !== '') {
            $editando = manutencao_por_id((int) get('editar'));
            if ($editando === null) {
                flash('Item de manutenção não encontrado.', 'erro');
                redirect(url('manutencao'));
            }
        }
        render('manutencao', [
            'itens'    => manutencoes_situacao(),
            'trocas'   => manutencao_trocas(),
            'kmAtual'  => ultimo_km_final(),
            'editando' => $editando,
        ]);
        break;

    case 'dashboard':
    default:
        render('dashboard', ['m' => metricas()]);
        break;
}

// ------------------------------------------------------------
// Implementação das ações
// ------------------------------------------------------------

function acao_salvar_lancamento(): never
{
    $slug       = post('slug');
    $plataforma = plataforma_por_slug($slug);
    $data       = data_valida(post('data'));
    $id         = (int) post('id');

    if ($plataforma === null) {
        flash('Plataforma inválida.', 'erro');
        redirect(url('dashboard'));
    }

    // Em edição, erros voltam para o mesmo formulário de edição.
    $volta = $id > 0
        ? url('plataforma', ['slug' => $slug, 'editar' => $id])
        : url('plataforma', ['slug' => $slug]);

    if ($data === null) {
        flash('Informe uma data válida.', 'erro');
        redirect($volta);
    }
    if ($id > 0 && lancamento_por_id($id, (int) $plataforma['id']) === null) {
        flash('Lançamento não encontrado.', 'erro');
        redirect(url('plataforma', ['slug' => $slug]));
    }
    if ($id > 0 && lancamento_ocupa_data((int) $plataforma['id'], $data, $id)) {
        flash('Já existe um lançamento em ' . dmy($data) . '. Edite ou exclua aquele dia primeiro.', 'erro');
        redirect($volta);
    }

    // Só aceita os campos que a plataforma realmente usa.
    $valores = [];
    foreach (campos_da_plataforma((int) $plataforma['id']) as $campo) {
        $valores[$campo['coluna']] = post($campo['coluna']);
    }

    if ($id > 0) {
        atualizar_lancamento($id, (int) $plataforma['id'], $data, $valores);
        flash('Lançamento de ' . dmy($data) . ' atualizado em ' . $plataforma['nome'] . '.');
    } else {
        salvar_lancamento((int) $plataforma['id'], $data, $valores);
        flash('Dia ' . dmy($data) . ' salvo em ' . $plataforma['nome'] . '.');
    }
    redirect(url('plataforma', ['slug' => $slug]));
}

function acao_salvar_km(): never
{
    $data    = data_valida(post('data'));
    $inicial = (int) n(post('km_inicial'));
    $final   = (int) n(post('km_final'));
    $id      = (int) post('id');
    $volta   = $id > 0 ? url('km', ['editar' => $id]) : url('km');

    if ($data === null) {
        flash('Informe uma data válida.', 'erro');
        redirect($volta);
    }
    if ($final < $inicial) {
        flash('O km final não pode ser menor que o km inicial.', 'erro');
        redirect($volta);
    }
    if ($id > 0 && km_por_id($id) === null) {
        flash('Registro de km não encontrado.', 'erro');
        redirect(url('km'));
    }
    if ($id > 0 && km_ocupa_data($data, $id)) {
        flash('Já existe km registrado em ' . dmy($data) . '. Edite ou exclua aquele dia primeiro.', 'erro');
        redirect($volta);
    }

    if ($id > 0) {
        atualizar_km($id, $data, $inicial, $final);
        flash('Km de ' . dmy($data) . ' atualizado: ' . num($final - $inicial) . ' km.');
    } else {
        salvar_km($data, $inicial, $final);
        flash('Km de ' . dmy($data) . ' salvo: ' . num($final - $inicial) . ' km.');
    }
    redirect(url('km'));
}

function acao_salvar_despesa(): never
{
    $data      = data_valida(post('data'));
    $categoria = (int) post('categoria_id');
    $valor     = n(post('valor'));
    $descricao = post('descricao');

    if ($data === null) {
        flash('Informe uma data válida.', 'erro');
        redirect(url('despesas'));
    }
    if ($valor <= 0) {
        flash('Informe um valor maior que zero.', 'erro');
        redirect(url('despesas'));
    }

    $existe = false;
    $nomeCategoria = '';
    foreach (categorias() as $c) {
        if ((int) $c['id'] === $categoria) {
            $existe = true;
            $nomeCategoria = $c['nome'];
            break;
        }
    }
    if (!$existe) {
        flash('Categoria inválida.', 'erro');
        redirect(url('despesas'));
    }

    salvar_despesa($data, $categoria, $descricao !== '' ? $descricao : $nomeCategoria, $valor);
    flash('Despesa de ' . brl($valor) . ' registrada.');
    redirect(url('despesas'));
}

function acao_salvar_metas(): never
{
    $diaria = max(0.0, n(post('meta_diaria')));
    $mensal = max(0.0, n(post('meta_mensal')));

    config_set('meta_diaria', (string) $diaria);
    config_set('meta_mensal', (string) $mensal);

    flash('Metas atualizadas.');
    redirect(url('metas'));
}

function acao_salvar_evento(): never
{
    $titulo      = post('titulo');
    $plataformaP = post('plataforma_id');
    $plataformaId = $plataformaP !== '' ? (int) $plataformaP : null;
    $metaQtd     = (int) n(post('meta_qtd'));
    $metaUnidade = post('meta_unidade', 'entregas');
    $prazoDias   = (int) n(post('prazo_dias'));
    $recompensa  = n(post('recompensa'));
    $dataInicio  = data_valida(post('data_inicio'));

    if ($titulo === '') {
        flash('Informe um título para o evento.', 'erro');
        redirect(url('eventos'));
    }
    if ($dataInicio === null) {
        flash('Informe uma data de início válida.', 'erro');
        redirect(url('eventos'));
    }
    if ($prazoDias < 1) {
        flash('O prazo precisa ser de pelo menos 1 dia.', 'erro');
        redirect(url('eventos'));
    }
    if ($plataformaId !== null && plataforma_por_id($plataformaId) === null) {
        flash('Plataforma inválida.', 'erro');
        redirect(url('eventos'));
    }

    salvar_evento($titulo, $plataformaId, $metaQtd, $metaUnidade !== '' ? $metaUnidade : 'entregas', $prazoDias, $recompensa, $dataInicio);
    flash('Evento "' . $titulo . '" registrado.');
    redirect(url('eventos'));
}

function acao_marcar_evento(bool $concluido): never
{
    $id = (int) post('id');
    if (evento_por_id($id) === null) {
        flash('Evento não encontrado.', 'erro');
        redirect(url('eventos'));
    }

    $resultadoQtd = null;
    if ($concluido) {
        $qtd = post('resultado_qtd');
        $resultadoQtd = $qtd !== '' ? (int) n($qtd) : null;
    }

    evento_marcar($id, $concluido, $resultadoQtd);
    flash($concluido ? 'Evento marcado como concluído.' : 'Evento reaberto.');
    redirect(url('eventos'));
}

function acao_salvar_manutencao(): never
{
    $id        = (int) post('id');
    $nome      = post('nome');
    $intervalo = (int) n(post('intervalo_km'));
    $kmUltima  = (int) n(post('km_ultima'));
    $aviso     = max(0, (int) n(post('aviso_km')));
    $dataP     = post('data_ultima');
    $data      = $dataP !== '' ? data_valida($dataP) : null;
    $volta     = $id > 0 ? url('manutencao', ['editar' => $id]) : url('manutencao');

    if ($nome === '') {
        flash('Informe o nome da peça ou serviço.', 'erro');
        redirect($volta);
    }
    if ($intervalo < 1) {
        flash('Informe de quantos em quantos km a troca deve ser feita.', 'erro');
        redirect($volta);
    }
    if ($kmUltima < 0) {
        flash('Informe o km da última troca.', 'erro');
        redirect($volta);
    }
    if ($dataP !== '' && $data === null) {
        flash('Informe uma data válida.', 'erro');
        redirect($volta);
    }
    if ($id > 0 && manutencao_por_id($id) === null) {
        flash('Item de manutenção não encontrado.', 'erro');
        redirect(url('manutencao'));
    }

    if ($id > 0) {
        atualizar_manutencao($id, $nome, $intervalo, $kmUltima, $data, $aviso);
        flash('"' . $nome . '" atualizado.');
    } else {
        salvar_manutencao($nome, $intervalo, $kmUltima, $data, $aviso);
        flash('"' . $nome . '" cadastrado: próxima troca em ' . num($kmUltima + $intervalo) . ' km.');
    }
    redirect(url('manutencao'));
}

function acao_registrar_troca(): never
{
    $id   = (int) post('id');
    $item = manutencao_por_id($id);
    $data = data_valida(post('data'));
    $km   = (int) n(post('km'));

    if ($item === null) {
        flash('Item de manutenção não encontrado.', 'erro');
        redirect(url('manutencao'));
    }
    if ($data === null) {
        flash('Informe uma data válida para a troca.', 'erro');
        redirect(url('manutencao'));
    }
    if ($km <= 0) {
        flash('Informe o km do odômetro na troca.', 'erro');
        redirect(url('manutencao'));
    }

    registrar_troca($id, $data, $km);
    flash('Troca de "' . $item['nome'] . '" registrada. Próxima em ' . num($km + (int) $item['intervalo_km']) . ' km.');
    redirect(url('manutencao'));
}
