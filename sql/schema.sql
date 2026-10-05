-- ============================================================
-- GIRO - Controle de Delivery
-- Schema completo (arquivo único) - MySQL 5.7+ / MariaDB 10.2+
-- Pode ser rodado de novo num banco existente: só cria o que falta.
-- ============================================================

SET NAMES utf8mb4;
SET time_zone = '-03:00';

-- ------------------------------------------------------------
-- Usuários (sistema pessoal: um único usuário)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS usuarios (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  usuario       VARCHAR(60)  NOT NULL,
  nome          VARCHAR(120) NOT NULL,
  senha_hash    VARCHAR(255) NOT NULL,
  ultimo_login  DATETIME     NULL,
  criado_em     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_usuarios_usuario (usuario)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Plataformas / empresas de delivery
-- Novas empresas entram aqui, sem mexer no código.
-- Os campos do formulário de cada uma ficam em `plataforma_campos`.
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS plataformas (
  id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  slug       VARCHAR(40)  NOT NULL,
  nome       VARCHAR(60)  NOT NULL,
  cor        VARCHAR(9)   NOT NULL DEFAULT '#E11B22',
  ordem      SMALLINT     NOT NULL DEFAULT 0,
  ativo      TINYINT(1)   NOT NULL DEFAULT 1,
  criado_em  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_plataformas_slug (slug),
  KEY ix_plataformas_ativo_ordem (ativo, ordem)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Campos do formulário de cada plataforma
-- `coluna` diz em qual slot de `lancamentos` o campo grava.
-- Slots de dinheiro (valor_base, promos, gorjetas, adicional, outros)
-- somam no total do dia; `rotas` e `solicitacoes` são contadores.
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS plataforma_campos (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  plataforma_id INT UNSIGNED NOT NULL,
  coluna        ENUM('rotas','solicitacoes','valor_base','promos','gorjetas','adicional','outros') NOT NULL,
  rotulo        VARCHAR(60)  NOT NULL,
  ordem         SMALLINT     NOT NULL DEFAULT 0,
  ativo         TINYINT(1)   NOT NULL DEFAULT 1,
  PRIMARY KEY (id),
  UNIQUE KEY uq_campo_plataforma_coluna (plataforma_id, coluna),
  KEY ix_campos_plataforma_ordem (plataforma_id, ativo, ordem),
  CONSTRAINT fk_campos_plataforma FOREIGN KEY (plataforma_id)
    REFERENCES plataformas (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Lançamentos diários por plataforma
-- Um lançamento por plataforma/dia (regravado ao salvar de novo).
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS lancamentos (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  plataforma_id INT UNSIGNED NOT NULL,
  data          DATE         NOT NULL,
  rotas         SMALLINT     NOT NULL DEFAULT 0,
  solicitacoes  SMALLINT     NOT NULL DEFAULT 0,
  valor_base    DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  promos        DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  gorjetas      DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  adicional     DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  outros        DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  total         DECIMAL(10,2) AS (valor_base + promos + gorjetas + adicional + outros) STORED,
  criado_em     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  atualizado_em DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_lancamento_plataforma_data (plataforma_id, data),
  KEY ix_lancamentos_data (data),
  CONSTRAINT fk_lancamentos_plataforma FOREIGN KEY (plataforma_id)
    REFERENCES plataformas (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Odômetro diário
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS km_diario (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  data          DATE         NOT NULL,
  km_inicial    INT UNSIGNED NOT NULL DEFAULT 0,
  km_final      INT UNSIGNED NOT NULL DEFAULT 0,
  km_rodados    INT AS (GREATEST(CAST(km_final AS SIGNED) - CAST(km_inicial AS SIGNED), 0)) STORED,
  criado_em     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  atualizado_em DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_km_data (data)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Categorias de despesa
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS categorias_despesa (
  id     INT UNSIGNED NOT NULL AUTO_INCREMENT,
  nome   VARCHAR(60)  NOT NULL,
  cor    VARCHAR(9)   NOT NULL DEFAULT '#7B818A',
  ordem  SMALLINT     NOT NULL DEFAULT 0,
  ativo  TINYINT(1)   NOT NULL DEFAULT 1,
  PRIMARY KEY (id),
  UNIQUE KEY uq_categoria_nome (nome)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Despesas
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS despesas (
  id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  data         DATE         NOT NULL,
  categoria_id INT UNSIGNED NOT NULL,
  descricao    VARCHAR(120) NOT NULL DEFAULT '',
  valor        DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  criado_em    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY ix_despesas_data (data),
  CONSTRAINT fk_despesas_categoria FOREIGN KEY (categoria_id)
    REFERENCES categorias_despesa (id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Eventos (metas pontuais com recompensa, ex.: "60 entregas
-- em 15 dias = R$ 3000")
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS eventos (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  titulo        VARCHAR(120)  NOT NULL,
  plataforma_id INT UNSIGNED  NULL,
  meta_qtd      SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  meta_unidade  VARCHAR(30)   NOT NULL DEFAULT 'entregas',
  prazo_dias    SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  recompensa    DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  data_inicio   DATE          NOT NULL,
  data_fim      DATE AS (DATE_ADD(data_inicio, INTERVAL GREATEST(prazo_dias, 1) - 1 DAY)) STORED,
  concluido     TINYINT(1)    NOT NULL DEFAULT 0,
  resultado_qtd SMALLINT UNSIGNED NULL,
  criado_em     DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  atualizado_em DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY ix_eventos_data_inicio (data_inicio),
  CONSTRAINT fk_eventos_plataforma FOREIGN KEY (plataforma_id)
    REFERENCES plataformas (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Manutenção (itens trocados a cada X km, ex.: pneu, óleo)
-- km_ultima é o odômetro da última troca; a próxima vence em
-- km_ultima + intervalo_km.
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS manutencoes (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  nome          VARCHAR(80)   NOT NULL,
  intervalo_km  INT UNSIGNED  NOT NULL,
  km_ultima     INT UNSIGNED  NOT NULL DEFAULT 0,
  data_ultima   DATE          NULL,
  aviso_km      INT UNSIGNED  NOT NULL DEFAULT 500,
  criado_em     DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  atualizado_em DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS manutencao_trocas (
  id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  manutencao_id  INT UNSIGNED NOT NULL,
  data           DATE         NOT NULL,
  km             INT UNSIGNED NOT NULL,
  criado_em      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY ix_trocas_manutencao (manutencao_id, data),
  CONSTRAINT fk_trocas_manutencao FOREIGN KEY (manutencao_id)
    REFERENCES manutencoes (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Configurações (metas, tema, etc.)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS configuracoes (
  chave VARCHAR(50)  NOT NULL,
  valor VARCHAR(255) NOT NULL DEFAULT '',
  PRIMARY KEY (chave)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Dados iniciais
-- ------------------------------------------------------------
INSERT INTO plataformas (slug, nome, cor, ordem) VALUES
  ('ifood',  'iFood',  '#E11B22', 1),
  ('99food', '99Food', '#F2A312', 2),
  ('keeta',  'Keeta',  '#FFD100', 3)
ON DUPLICATE KEY UPDATE nome = VALUES(nome);

-- Campos do iFood
INSERT INTO plataforma_campos (plataforma_id, coluna, rotulo, ordem)
SELECT p.id, c.coluna, c.rotulo, c.ordem
FROM plataformas p
JOIN (
  SELECT 'rotas'        AS coluna, 'Rotas'            AS rotulo, 1 AS ordem UNION ALL
  SELECT 'valor_base',        'Rotas completas',        2 UNION ALL
  SELECT 'promos',            'Promos',                 3 UNION ALL
  SELECT 'gorjetas',          'Gorjetas',               4 UNION ALL
  SELECT 'adicional',         'Adicional super',        5 UNION ALL
  SELECT 'outros',            'Outros',                 6
) c
WHERE p.slug = 'ifood'
ON DUPLICATE KEY UPDATE rotulo = VALUES(rotulo), ordem = VALUES(ordem), ativo = 1;

-- Campos do 99Food
INSERT INTO plataforma_campos (plataforma_id, coluna, rotulo, ordem)
SELECT p.id, c.coluna, c.rotulo, c.ordem
FROM plataformas p
JOIN (
  SELECT 'valor_base'   AS coluna, 'Valor do pedido'  AS rotulo, 1 AS ordem UNION ALL
  SELECT 'promos',            'Recompensa',             2 UNION ALL
  SELECT 'gorjetas',          'Gorjeta',                3 UNION ALL
  SELECT 'adicional',         'Compensação',            4 UNION ALL
  SELECT 'outros',            'Outro',                  5 UNION ALL
  SELECT 'solicitacoes',      'Solicitações',           6
) c
WHERE p.slug = '99food'
ON DUPLICATE KEY UPDATE rotulo = VALUES(rotulo), ordem = VALUES(ordem), ativo = 1;

-- Campos da Keeta
INSERT INTO plataforma_campos (plataforma_id, coluna, rotulo, ordem)
SELECT p.id, c.coluna, c.rotulo, c.ordem
FROM plataformas p
JOIN (
  SELECT 'valor_base'   AS coluna, 'Taxa de entrega'  AS rotulo, 1 AS ordem UNION ALL
  SELECT 'promos',            'Recompensas e extras',   2 UNION ALL
  SELECT 'gorjetas',          'Gorjetas',               3 UNION ALL
  SELECT 'outros',            'Outros',                 4 UNION ALL
  SELECT 'solicitacoes',      'Pedidos',                5
) c
WHERE p.slug = 'keeta'
ON DUPLICATE KEY UPDATE rotulo = VALUES(rotulo), ordem = VALUES(ordem), ativo = 1;

INSERT INTO categorias_despesa (nome, cor, ordem) VALUES
  ('Combustível',   '#E11B22', 1),
  ('Manutenção',    '#F2A312', 2),
  ('Plano Celular', '#5B8DEF', 3),
  ('Seguro',        '#8B72E8', 4),
  ('Outros',        '#7B818A', 5)
ON DUPLICATE KEY UPDATE cor = VALUES(cor);

INSERT INTO configuracoes (chave, valor) VALUES
  ('meta_diaria', '150'),
  ('meta_mensal', '3000')
ON DUPLICATE KEY UPDATE valor = valor;
