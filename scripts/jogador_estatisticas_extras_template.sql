-- Manual insert for extra stats (Pelada 06/01/26)
-- NOTE: gols/assistencias set to 0. Update if you have values later.
-- Mappings used:
-- Kim=1, Diego=8, Darlan=20, Nacho=11, Carlos=2, Fernando=15, Joao=21,
-- Santao=10, Marcelo=13, Mosci=19, Pedrin=27, Bruno=16,
-- Arthur=29, Matheus=23, Vagina=28, Igor=14, Sergio=22, Michael=4.

INSERT INTO jogador_estatisticas_extras
  (jogador_id, data_referencia, gols, assistencias, vitorias, origem, observacao, created_at, updated_at)
VALUES
  (1,  '2026-01-06', 1, 0, 1, 'manual', 'Pelada 06/01/26', NOW(), NOW()),
  (8,  '2026-01-06', 0, 0, 1, 'manual', 'Pelada 06/01/26', NOW(), NOW()),
  (20, '2026-01-06', 1, 1, 1, 'manual', 'Pelada 06/01/26', NOW(), NOW()),
  (11, '2026-01-06', 0, 0, 1, 'manual', 'Pelada 06/01/26', NOW(), NOW()),
  (2,  '2026-01-06', 1, 0, 1, 'manual', 'Pelada 06/01/26', NOW(), NOW()),
  (15, '2026-01-06', 0, 3, 1, 'manual', 'Pelada 06/01/26', NOW(), NOW()),
  (21, '2026-01-06', 0, 2, 1, 'manual', 'Pelada 06/01/26', NOW(), NOW()),
  (10, '2026-01-06', 0, 0, 1, 'manual', 'Pelada 06/01/26', NOW(), NOW()),
  (13, '2026-01-06', 1, 0, 1, 'manual', 'Pelada 06/01/26', NOW(), NOW()),
  (19, '2026-01-06', 3, 0, 1, 'manual', 'Pelada 06/01/26', NOW(), NOW()),
  (27, '2026-01-06', 1, 0, 1, 'manual', 'Pelada 06/01/26', NOW(), NOW()),
  (16, '2026-01-06', 0, 3, 1, 'manual', 'Pelada 06/01/26', NOW(), NOW()),
  (29, '2026-01-06', 1, 0, 4, 'manual', 'Pelada 06/01/26', NOW(), NOW()),
  (23, '2026-01-06', 0, 2, 4, 'manual', 'Pelada 06/01/26', NOW(), NOW()),
  (28, '2026-01-06', 2, 0, 4, 'manual', 'Pelada 06/01/26', NOW(), NOW()),
  (14, '2026-01-06', 1, 4, 4, 'manual', 'Pelada 06/01/26', NOW(), NOW()),
  (22, '2026-01-06', 0, 0, 4, 'manual', 'Pelada 06/01/26', NOW(), NOW()),
  (4,  '2026-01-06', 6, 3, 4, 'manual', 'Pelada 06/01/26', NOW(), NOW());

-- Check results
-- SELECT jogador_id, data_referencia, gols, assistencias, vitorias
-- FROM jogador_estatisticas_extras
-- WHERE data_referencia BETWEEN '2026-01-01' AND '2026-01-31';
