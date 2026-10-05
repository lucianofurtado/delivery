-- ============================================================
-- Dados de exemplo (planilha de ago/set 2026)
-- Opcional: carregue só para ver o sistema populado.
-- ============================================================
SET NAMES utf8mb4;

INSERT INTO lancamentos (plataforma_id, data, rotas, valor_base, promos, gorjetas, adicional, outros)
SELECT p.id, d.data, d.rotas, d.rc, d.promos, d.gorj, d.adic, d.outros
FROM plataformas p
JOIN (
  SELECT '2026-08-28' AS data, 15 AS rotas, 167.60 AS rc,  30.00 AS promos,  0.00 AS gorj, 0.00 AS adic,  1.40 AS outros UNION ALL
  SELECT '2026-08-29', 13, 155.05,  52.00, 0.00, 0.00,  3.40 UNION ALL
  SELECT '2026-08-30', 14, 135.24,  61.00, 0.00, 0.00,  0.00 UNION ALL
  SELECT '2026-08-31',  4,  37.98,  48.00, 0.00, 0.00,  0.00 UNION ALL
  SELECT '2026-09-01', 18, 135.98, 123.00, 0.00, 0.00, 18.68 UNION ALL
  SELECT '2026-09-02',  5,  30.72,   0.00, 0.00, 0.00,  9.34 UNION ALL
  SELECT '2026-09-03',  9,  45.03,   0.00, 0.00, 0.00, 16.21 UNION ALL
  SELECT '2026-09-04',  5,  57.89,  56.00, 10.00, 0.00, 2.60 UNION ALL
  SELECT '2026-09-05',  3,  37.21,  20.00, 0.00, 0.00,  6.20 UNION ALL
  SELECT '2026-09-06', 11, 100.20,  58.00, 0.00, 0.00,  0.80 UNION ALL
  SELECT '2026-09-07', 11,  26.46,  16.00, 0.00, 0.00,  0.00
) d
WHERE p.slug = 'ifood'
ON DUPLICATE KEY UPDATE valor_base = VALUES(valor_base);

-- 99Food: valor do pedido, recompensa, gorjeta, compensação, outro e solicitações
INSERT INTO lancamentos (plataforma_id, data, solicitacoes, valor_base, promos, gorjetas, adicional, outros)
SELECT p.id, d.data, d.sol, d.pedido, d.recompensa, d.gorjeta, d.compensacao, d.outro
FROM plataformas p
JOIN (
  SELECT '2026-09-04' AS data,  5 AS sol,  60.41 AS pedido,  8.00 AS recompensa,  4.00 AS gorjeta, 2.00 AS compensacao, 0.00 AS outro UNION ALL
  SELECT '2026-09-05',  5,  80.26, 12.00,  5.00, 0.00, 0.00 UNION ALL
  SELECT '2026-09-06', 10, 130.42, 20.00, 10.00, 2.00, 0.00 UNION ALL
  SELECT '2026-09-07',  9, 100.96, 14.00,  6.00, 0.00, 4.00
) d
WHERE p.slug = '99food'
ON DUPLICATE KEY UPDATE valor_base = VALUES(valor_base);

INSERT INTO km_diario (data, km_inicial, km_final) VALUES
  ('2026-08-29', 56289, 56408),
  ('2026-08-30', 56408, 56500),
  ('2026-08-31', 56500, 56526),
  ('2026-09-01', 56526, 56585),
  ('2026-09-02', 56636, 56726),
  ('2026-09-03', 56726, 56797),
  ('2026-09-04', 56797, 56875),
  ('2026-09-05', 56875, 56955),
  ('2026-09-06', 56955, 57037),
  ('2026-09-07', 57037, 57170),
  ('2026-09-08', 57170, 57205)
ON DUPLICATE KEY UPDATE km_final = VALUES(km_final);

INSERT INTO despesas (data, categoria_id, descricao, valor)
SELECT d.data, c.id, d.descricao, d.valor
FROM (
  SELECT '2026-08-30' AS data, 'Combustível'   AS cat, 'Gasolina'    AS descricao, 38.92 AS valor UNION ALL
  SELECT '2026-08-31', 'Manutenção',    'Óleo',        60.00 UNION ALL
  SELECT '2026-09-02', 'Combustível',   'Gasolina',    39.95 UNION ALL
  SELECT '2026-09-05', 'Combustível',   'Gasolina',    39.40 UNION ALL
  SELECT '2026-09-07', 'Combustível',   'Gasolina',    36.98 UNION ALL
  SELECT '2026-09-07', 'Plano Celular', 'Mensalidade', 20.00
) d
JOIN categorias_despesa c ON c.nome = d.cat;
