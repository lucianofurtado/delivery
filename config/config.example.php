<?php
/**
 * Configuração do GIRO.
 * Copie para config/config.php e preencha com os dados do seu banco.
 * O config/config.php fica fora do Git — nunca versione a senha.
 */

return [
    'db' => [
        'host'    => '',
        'port'    => 3306,
        'nome'    => '',
        'usuario' => '',
        'senha'   => '',
        'charset' => 'utf8mb4',
    ],

    // Nome do app exibido no topo
    'app_nome' => 'GIRO',

    // Fuso usado nas datas
    'timezone' => 'America/Sao_Paulo',

    // true = mostra erros na tela (use só em desenvolvimento)
    'debug' => false,
];
