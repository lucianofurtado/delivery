# GIRO — Controle de Delivery

Sistema pessoal em PHP + MySQL para controlar rotas de delivery: lançamentos diários
por plataforma, km rodado, despesas, lucro líquido, fechamento mensal e metas.

Uso pessoal, **um único usuário** (cadastrado pelo `setup.php`).

---

## Requisitos

- PHP 8.1 ou superior, com as extensões `pdo_mysql` e `session` (`mbstring` recomendada)
- MySQL 5.7+ ou MariaDB 10.2+ (o schema usa colunas geradas)
- Apache com `mod_rewrite` não é necessário; os `.htaccess` só bloqueiam acesso direto às pastas

## Instalação

1. Copie os arquivos para a pasta pública do site.
2. Copie `config/config.example.php` para `config/config.php` e preencha os dados do banco:

   ```php
   'host'    => 'localhost',
   'nome'    => 'seu_banco',
   'usuario' => 'seu_usuario',
   'senha'   => 'sua_senha',
   ```

3. Abra `setup.php` no navegador. Ele cria as tabelas, cadastra o usuário de login
   e a senha que você digitar e, se você marcar a opção,
   carrega os dados de exemplo de ago/set 2026.
4. **Apague `setup.php`** depois de instalar.
5. Acesse `login.php`.

Se preferir instalar pelo terminal, os arquivos SQL estão prontos:

```bash
mysql -u seu_usuario -p seu_banco < sql/schema.sql
```

Nesse caso você ainda precisa criar o usuário — o `setup.php` faz isso com o hash
correto da senha.

### Banco de dados

Todo o banco está em um único arquivo, `sql/schema.sql`: tabelas, plataformas
(iFood, 99Food, Keeta), campos de cada uma, categorias de despesa, eventos,
manutenção e metas padrão. Ele usa `CREATE TABLE IF NOT EXISTS` e
`ON DUPLICATE KEY UPDATE`, então rodar de novo num banco existente cria só o que
falta, sem apagar dados.

`sql/seed_demo.sql` é opcional: carrega lançamentos de exemplo.

## Estrutura

```
index.php          front controller: trata as ações (POST) e escolhe a tela (GET)
login.php          tela de login
logout.php         encerra a sessão
setup.php          instalador (apagar depois de usar)

app/
  bootstrap.php    carrega tudo, abre a sessão, define render()
  helpers.php      formatação pt-BR (brl, num, dm), CSRF, flash, url
  db.php           conexão PDO e atalhos q() / q1() / exec_sql()
  auth.php         login, logout, proteção de rota, freio de força bruta
  repo.php         acesso ao banco e cálculo das métricas

views/             uma tela por arquivo + layout.php
assets/            app.css, app.js e os ícones (favicon.svg/.ico, apple-touch-icon)
favicon.ico        cópia na raiz, para o pedido automático do navegador
config/            config.php (dados do banco)
sql/               schema.sql (banco completo) e seed_demo.sql (exemplo opcional)
```

## Telas

| Tela | O que faz |
|---|---|
| Dashboard | KPIs do período, gráfico por dia, últimos dias, despesas por categoria |
| iFood / 99Food | Lançamento diário e histórico da plataforma |
| KM diário | Odômetro do dia; o km inicial já vem do último km final |
| Despesas | Registro por categoria, com totais |
| Resumo mensal | Fechamento mês a mês, com R$/km e lucro líquido |
| Metas | Meta diária e mensal, e o dia a dia comparado com a meta |
| Eventos | Metas pontuais com recompensa das plataformas (ex.: "60 entregas em 15 dias = R$ 3000") |
| Manutenção | Peças/serviços trocados a cada X km (pneu, óleo), com lembrete pelo odômetro do KM diário |

## Trabalhar com outras empresas

Empresas **e os campos de cada uma** são dados, não código. Nada de PHP muda para
adicionar uma plataforma nova.

**1. Cadastre a empresa:**

```sql
INSERT INTO plataformas (slug, nome, cor, ordem) VALUES
  ('rappi', 'Rappi', '#FF3008', 3);
```

**2. Defina os campos do formulário dela:**

```sql
INSERT INTO plataforma_campos (plataforma_id, coluna, rotulo, ordem)
SELECT p.id, c.coluna, c.rotulo, c.ordem
FROM plataformas p
JOIN (
  SELECT 'valor_base' AS coluna, 'Valor da corrida' AS rotulo, 1 AS ordem UNION ALL
  SELECT 'promos',      'Bônus',                        2 UNION ALL
  SELECT 'gorjetas',    'Gorjeta',                      3
) c
WHERE p.slug = 'rappi';
```

A empresa aparece sozinha no menu, no dashboard, no gráfico e no resumo mensal.

### Como funcionam os campos

`plataforma_campos.coluna` diz em qual slot da tabela `lancamentos` o campo grava.
São 7 slots disponíveis:

| Slot | Tipo | Entra no total do dia? |
|---|---|---|
| `valor_base`, `promos`, `gorjetas`, `adicional`, `outros` | dinheiro | sim |
| `rotas`, `solicitacoes` | contador | não |

- `rotulo` é o nome que aparece na tela — use o termo da empresa.
- A ordem das colunas no formulário e na tabela vem de `ordem`.
- Se a plataforma tiver o campo `solicitacoes`, a última coluna da tabela vira
  **Ganho por solicitação**; senão, vira **R$/km**.
- Para tirar um campo sem perder o histórico:
  `UPDATE plataforma_campos SET ativo = 0 WHERE ...` — o slot passa a ser gravado
  como zero e para de somar no total.
- Para desativar uma empresa inteira:
  `UPDATE plataformas SET ativo = 0 WHERE slug = '...'`

### Campos hoje

| iFood | 99Food | Keeta |
|---|---|---|
| Rotas (contador) | Valor do pedido | Taxa de entrega |
| Rotas completas | Recompensa | Recompensas e extras |
| Promos | Gorjeta | Gorjetas |
| Gorjetas | Compensação | Outros |
| Adicional super | Outro | Pedidos (contador) |
| Outros | Solicitações (contador) | — |

Categorias de despesa funcionam do mesmo jeito, na tabela `categorias_despesa`.

## Regras de negócio

- **Um lançamento por plataforma por dia.** Salvar a mesma data de novo substitui
  o lançamento daquele dia (`UNIQUE (plataforma_id, data)`).
- **Total do dia** = soma dos campos de dinheiro da plataforma
  (`valor_base + promos + gorjetas + adicional + outros`, coluna gerada no banco).
- **Km rodados** = km final − km inicial, nunca negativo (coluna gerada).
- **Lucro do dia** = receita de todas as plataformas − despesas daquele dia.
- **R$/km** usa a receita bruta do dia dividida pelos km daquele dia.
- O período do dashboard vai do primeiro ao último dia **com lançamento**.
- **Metas do mês** usam o mês corrente do calendário (a data de hoje no servidor),
  não o mês do último lançamento.
- **Eventos no resumo mensal**: a recompensa de um evento concluído entra na receita
  e no lucro do mês em que o evento termina (`data_fim`). R$/km e média por dia
  continuam olhando só as corridas.
- **Manutenção**: km rodados desde a troca = último km final do KM diário − km da
  última troca. O item fica "trocar em breve" quando faltam `aviso_km` ou menos, e
  "trocar agora" quando passa do intervalo. Os dois aparecem no dashboard e no menu.
- **Quanto falta por dia** = (meta mensal − lucro do mês) ÷ dias restantes, contando
  hoje. No dia 09 de um mês de 30 dias, são 22 dias.

## Segurança

- Senha guardada com `password_hash()` (bcrypt), com re-hash automático
- Todas as consultas usam prepared statements
- Token CSRF em todo formulário POST
- Cookie de sessão `HttpOnly`, `SameSite=Lax` e `Secure` sob HTTPS
- Bloqueio de 10 minutos após 5 tentativas de login erradas
- `.htaccess` bloqueia acesso direto a `app/`, `config/`, `sql/`, `views/` e a arquivos `.sql`/`.md`
- `config/config.php` está no `.gitignore` — não suba a senha do banco para o Git

Em produção, mantenha `'debug' => false` no `config/config.php`.
