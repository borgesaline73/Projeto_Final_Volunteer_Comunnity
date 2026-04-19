-- ===========================================
-- SCRIPT SQL POSTGRESQL - SISTEMA DE DOAÇÕES PIX
-- ===========================================

-- 1. Adicionar coluna chave_pix na tabela ongs (se não existir)
ALTER TABLE ongs 
ADD COLUMN IF NOT EXISTS chave_pix VARCHAR(150) DEFAULT NULL;

-- Adicionar comentário explicativo
COMMENT ON COLUMN ongs.chave_pix IS 'Chave PIX para recebimento de doações (CPF, CNPJ, Email, Telefone ou UUID)';

-- 2. Criar índice para melhor performance
CREATE INDEX IF NOT EXISTS idx_ongs_chave_pix ON ongs(chave_pix);

-- 3. Adicionar coluna metodo_pagamento na tabela doacoes (se não existir)
ALTER TABLE doacoes 
ADD COLUMN IF NOT EXISTS metodo_pagamento VARCHAR(50) DEFAULT 'DIRETO';

-- Adicionar constraint para metodo_pagamento
DO $$
BEGIN
    IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'check_metodo_pagamento') THEN
        ALTER TABLE doacoes 
        ADD CONSTRAINT check_metodo_pagamento 
        CHECK (metodo_pagamento IN ('PIX', 'DIRETO', 'DEPOSITO', 'OUTRO'));
    END IF;
END $$;

COMMENT ON COLUMN doacoes.metodo_pagamento IS 'Método de pagamento: PIX, DIRETO, DEPOSITO, OUTRO';

-- 4. Adicionar coluna status_pagamento na tabela doacoes (se não existir)
ALTER TABLE doacoes 
ADD COLUMN IF NOT EXISTS status_pagamento VARCHAR(50) DEFAULT 'PENDENTE';

-- Adicionar constraint para status_pagamento
DO $$
BEGIN
    IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'check_status_pagamento') THEN
        ALTER TABLE doacoes 
        ADD CONSTRAINT check_status_pagamento 
        CHECK (status_pagamento IN ('PENDENTE', 'CONFIRMADO', 'CANCELADO', 'REEMBOLSADO'));
    END IF;
END $$;

COMMENT ON COLUMN doacoes.status_pagamento IS 'Status do pagamento: PENDENTE, CONFIRMADO, CANCELADO, REEMBOLSADO';

-- 5. Adicionar coluna data_criacao se não existir (para melhor rastreamento)
ALTER TABLE doacoes 
ADD COLUMN IF NOT EXISTS data_criacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP;

-- 6. Verificar e ajustar coluna data_doacao (se existir)
DO $$
BEGIN
    IF EXISTS (SELECT 1 FROM information_schema.columns 
               WHERE table_name = 'doacoes' AND column_name = 'data_doacao') THEN
        -- Copiar dados existentes
        UPDATE doacoes SET data_criacao = data_doacao WHERE data_criacao IS NULL AND data_doacao IS NOT NULL;
    END IF;
END $$;

-- ===========================================
-- ÍNDICES ADICIONAIS PARA PERFORMANCE
-- ===========================================

CREATE INDEX IF NOT EXISTS idx_doacoes_metodo_pagamento ON doacoes(metodo_pagamento);
CREATE INDEX IF NOT EXISTS idx_doacoes_status_pagamento ON doacoes(status_pagamento);
CREATE INDEX IF NOT EXISTS idx_doacoes_ong_tipo ON doacoes(id_ong, tipo);
CREATE INDEX IF NOT EXISTS idx_doacoes_data_criacao ON doacoes(data_criacao);

-- ===========================================
-- FUNÇÕES ÚTEIS PARA RELATÓRIOS PIX
-- ===========================================

-- Função para contar doações em PIX confirmadas
CREATE OR REPLACE FUNCTION contar_doacoes_pix_confirmadas(p_id_ong INTEGER)
RETURNS TABLE(
    total_doacoes BIGINT,
    valor_total NUMERIC,
    valor_confirmado NUMERIC,
    taxa_confirmacao NUMERIC
) AS $$
BEGIN
    RETURN QUERY
    SELECT 
        COUNT(*)::BIGINT as total_doacoes,
        COALESCE(SUM(CASE WHEN tipo = 'DINHEIRO' THEN valor ELSE 0 END), 0)::NUMERIC as valor_total,
        COALESCE(SUM(CASE WHEN tipo = 'DINHEIRO' AND status_pagamento = 'CONFIRMADO' THEN valor ELSE 0 END), 0)::NUMERIC as valor_confirmado,
        CASE 
            WHEN COUNT(*) > 0 THEN (COUNT(CASE WHEN status_pagamento = 'CONFIRMADO' THEN 1 END)::NUMERIC / COUNT(*)::NUMERIC * 100)::NUMERIC
            ELSE 0::NUMERIC
        END as taxa_confirmacao
    FROM doacoes
    WHERE id_ong = p_id_ong AND tipo = 'DINHEIRO';
END;
$$ LANGUAGE plpgsql;

-- Função para buscar últimas doações em PIX de uma ONG
CREATE OR REPLACE FUNCTION obter_ultimas_doacoes_pix(p_id_ong INTEGER, p_limite INTEGER DEFAULT 10)
RETURNS TABLE(
    id_doacao INTEGER,
    valor NUMERIC,
    status_pagamento VARCHAR,
    metodo_pagamento VARCHAR,
    data_doacao TIMESTAMP,
    nome_doador VARCHAR
) AS $$
BEGIN
    RETURN QUERY
    SELECT 
        d.id_doacao,
        d.valor,
        d.status_pagamento::VARCHAR,
        d.metodo_pagamento::VARCHAR,
        COALESCE(d.data_criacao, d.data_doacao, NOW()) as data_doacao,
        u.nome::VARCHAR
    FROM doacoes d
    LEFT JOIN doadores doa ON d.id_doador = doa.id_doador
    LEFT JOIN usuarios u ON doa.id_doador = u.id_usuario
    WHERE d.id_ong = p_id_ong AND d.tipo = 'DINHEIRO'
    ORDER BY COALESCE(d.data_criacao, d.data_doacao, NOW()) DESC
    LIMIT p_limite;
END;
$$ LANGUAGE plpgsql;

-- ===========================================
-- PROCEDURE PARA ATUALIZAR STATUS DE PAGAMENTO
-- ===========================================

CREATE OR REPLACE FUNCTION atualizar_status_pagamento_pix(
    p_id_doacao INTEGER,
    p_novo_status VARCHAR,
    p_observacoes TEXT DEFAULT NULL
)
RETURNS TABLE(
    sucesso BOOLEAN,
    mensagem VARCHAR
) AS $$
DECLARE
    v_status_anterior VARCHAR;
    v_id_doador INTEGER;
    v_id_ong INTEGER;
BEGIN
    -- Buscar status anterior e informações da doação
    SELECT d.status_pagamento, d.id_doador, d.id_ong
    INTO v_status_anterior, v_id_doador, v_id_ong
    FROM doacoes
    WHERE id_doacao = p_id_doacao;
    
    -- Verificar se doação existe
    IF v_status_anterior IS NULL THEN
        RETURN QUERY SELECT FALSE::BOOLEAN, 'Doação não encontrada'::VARCHAR;
        RETURN;
    END IF;
    
    -- Validar transição de status
    IF v_status_anterior = 'CONFIRMADO' AND p_novo_status != 'REEMBOLSADO' THEN
        RETURN QUERY SELECT FALSE::BOOLEAN, 'Doação já confirmada só pode ser reembolsada'::VARCHAR;
        RETURN;
    END IF;
    
    -- Atualizar status
    UPDATE doacoes
    SET status_pagamento = p_novo_status,
        data_criacao = CURRENT_TIMESTAMP
    WHERE id_doacao = p_id_doacao;
    
    -- Criar notificação se status mudou para CONFIRMADO
    IF p_novo_status = 'CONFIRMADO' AND v_status_anterior != 'CONFIRMADO' THEN
        INSERT INTO notificacoes (id_usuario, mensagem, tipo)
        VALUES (
            v_id_doador,
            'Sua doação de PIX foi confirmada pela ONG!',
            'PAGAMENTO_CONFIRMADO'
        );
        
        INSERT INTO notificacoes (id_usuario, mensagem, tipo)
        VALUES (
            v_id_ong,
            'Novo pagamento PIX confirmado!',
            'PAGAMENTO_RECEBIDO'
        );
    END IF;
    
    RETURN QUERY SELECT TRUE::BOOLEAN, 'Status atualizado com sucesso'::VARCHAR;
    
EXCEPTION WHEN OTHERS THEN
    RETURN QUERY SELECT FALSE::BOOLEAN, ('Erro ao atualizar: ' || SQLERRM)::VARCHAR;
END;
$$ LANGUAGE plpgsql;

-- ===========================================
-- VIEW PARA RESUMO DE DOACOES EM PIX
-- ===========================================

CREATE OR REPLACE VIEW vw_resumo_doacoes_pix AS
SELECT 
    d.id_ong,
    u.nome as nome_ong,
    COUNT(*) FILTER (WHERE d.tipo = 'DINHEIRO') as total_doacoes_dinheiro,
    COUNT(*) FILTER (WHERE d.tipo = 'DINHEIRO' AND d.status_pagamento = 'CONFIRMADO') as doacoes_confirmadas,
    COUNT(*) FILTER (WHERE d.tipo = 'DINHEIRO' AND d.status_pagamento = 'PENDENTE') as doacoes_pendentes,
    COUNT(*) FILTER (WHERE d.tipo = 'DINHEIRO' AND d.status_pagamento = 'CANCELADO') as doacoes_canceladas,
    COALESCE(SUM(d.valor) FILTER (WHERE d.tipo = 'DINHEIRO'), 0) as valor_total,
    COALESCE(SUM(d.valor) FILTER (WHERE d.tipo = 'DINHEIRO' AND d.status_pagamento = 'CONFIRMADO'), 0) as valor_confirmado,
    COALESCE(SUM(d.valor) FILTER (WHERE d.tipo = 'DINHEIRO' AND d.status_pagamento = 'PENDENTE'), 0) as valor_pendente,
    CASE 
        WHEN COUNT(*) FILTER (WHERE d.tipo = 'DINHEIRO') > 0 
        THEN ROUND(100.0 * COUNT(*) FILTER (WHERE d.tipo = 'DINHEIRO' AND d.status_pagamento = 'CONFIRMADO') / COUNT(*) FILTER (WHERE d.tipo = 'DINHEIRO'), 2)
        ELSE 0
    END as taxa_confirmacao_percentual
FROM doacoes d
LEFT JOIN usuarios u ON d.id_ong = u.id_usuario
WHERE d.tipo = 'DINHEIRO'
GROUP BY d.id_ong, u.nome
ORDER BY valor_confirmado DESC;

-- ===========================================
-- QUERIES ÚTEIS PARA RELATÓRIOS
-- ===========================================

-- Total de doações em PIX por status
-- SELECT status_pagamento, COUNT(*), SUM(valor) FROM doacoes WHERE tipo = 'DINHEIRO' GROUP BY status_pagamento;

-- Doações em PIX por ONG (últimos 30 dias)
-- SELECT 
--     (SELECT nome FROM usuarios WHERE id_usuario = d.id_ong) as ong,
--     COUNT(*) as total,
--     SUM(valor) as total_valor,
--     COUNT(*) FILTER (WHERE status_pagamento = 'CONFIRMADO') as confirmadas
-- FROM doacoes d
-- WHERE tipo = 'DINHEIRO' AND COALESCE(data_criacao, data_doacao, NOW()) >= CURRENT_DATE - INTERVAL '30 days'
-- GROUP BY d.id_ong;

-- Ver todas as ONGs com chave PIX
-- SELECT id_ong, chave_pix FROM ongs WHERE chave_pix IS NOT NULL;

-- Ver todas as doacoes em dinheiro
-- SELECT id_doacao, id_ong, tipo, metodo_pagamento, status_pagamento, valor FROM doacoes WHERE tipo = 'DINHEIRO';

-- ===========================================
-- VERIFICAÇÕES DAS ESTRUTURAS
-- ===========================================

-- Ver estrutura da tabela ongs
-- \d ongs;

-- Ver estrutura da tabela doacoes
-- \d doacoes;

-- Verificar se as colunas foram adicionadas corretamente
SELECT column_name, data_type, is_nullable, column_default
FROM information_schema.columns
WHERE table_name IN ('ongs', 'doacoes')
ORDER BY table_name, ordinal_position;

select * from usuarios
select * from doadores

SELECT column_name, data_type 
FROM information_schema.columns 
WHERE table_name = 'doadores'
ORDER BY ordinal_position;

SELECT column_name, data_type 
FROM information_schema.columns 
WHERE table_name = 'doacoes'
ORDER BY ordinal_position;

ALTER TABLE doadores ADD COLUMN IF NOT EXISTS data_cadastro DATE DEFAULT CURRENT_DATE;

INSERT INTO doadores (id_doador, data_cadastro)
SELECT u.id_usuario, CURRENT_DATE
FROM usuarios u
LEFT JOIN doadores d ON u.id_usuario = d.id_doador
WHERE u.tipo_usuario = 'doador'
AND d.id_doador IS NULL;

SELECT 
    u.id_usuario,
    u.nome,
    u.tipo_usuario,
    d.id_doador,
    d.data_cadastro
FROM usuarios u
LEFT JOIN doadores d ON u.id_usuario = d.id_doador
WHERE u.tipo_usuario = 'doador';

-- Substitua 999 pelo ID real do usuário que está logado
SELECT id_usuario, nome, tipo_usuario 
FROM usuarios 
WHERE id_usuario = 9;

-- Ver todos os doadores cadastrados
SELECT id_usuario, nome, tipo_usuario 
FROM usuarios 
WHERE tipo_usuario = 'doador';

-- Ver o que tem na tabela doadores
SELECT * FROM doadores;

-- 1. Ver a estrutura REAL da tabela doadores
SELECT column_name, data_type 
FROM information_schema.columns 
WHERE table_name = 'doadores' 
ORDER BY ordinal_position;

-- 2. Ver se existe alguma VIEW chamada doadores
SELECT table_name, table_type 
FROM information_schema.tables 
WHERE table_name = 'doadores';

-- 3. Verificar o search_path atual
SHOW search_path;

-- 4. Ver em qual schema a tabela doadores está
SELECT schemaname, tablename 
FROM pg_tables 
WHERE tablename = 'doadores';

select * from usuarios

SELECT * FROM usuarios WHERE id_usuario = 7;

-- Verificar o tipo da coluna id_usuario
SELECT column_name, data_type 
FROM information_schema.columns 
WHERE table_name = 'usuarios' AND column_name = 'id_usuario';