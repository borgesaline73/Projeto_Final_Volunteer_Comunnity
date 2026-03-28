-- ===========================================
-- TABELA DE USUÁRIOS
-- ===========================================
CREATE TABLE usuarios (
    id_usuario SERIAL PRIMARY KEY,
    nome VARCHAR(100) NOT NULL,
    email VARCHAR(150) UNIQUE NOT NULL,
    senha VARCHAR(200) NOT NULL,
    cpf_cnpj VARCHAR(20) UNIQUE NOT NULL,
    tipo_usuario VARCHAR(50) NOT NULL, -- 'doador' ou 'instituicao'
    data_cadastro TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    ativo BOOLEAN DEFAULT TRUE
);

-- ===========================================
-- TABELA DE ONGS
-- ===========================================
CREATE TABLE ongs (
    id_ong INT PRIMARY KEY REFERENCES usuarios(id_usuario) ON DELETE CASCADE,
    autenticada BOOLEAN DEFAULT FALSE,
    descricao TEXT,
    endereco VARCHAR(255),
    categoria VARCHAR(100),
    latitude NUMERIC(9,6),
    longitude NUMERIC(9,6)
);

-- ===========================================
-- TABELA DE DOADORES
-- ===========================================
CREATE TABLE doadores (
    id_doador INT PRIMARY KEY REFERENCES usuarios(id_usuario) ON DELETE CASCADE,
    preferencias TEXT
);

-- ===========================================
-- TABELA DE POSTS (publicações das ONGs)
-- ===========================================
CREATE TABLE posts (
    id_post SERIAL PRIMARY KEY,
    id_usuario INT NOT NULL REFERENCES usuarios(id_usuario) ON DELETE CASCADE,
    titulo VARCHAR(255) NOT NULL,
    categoria VARCHAR(50) NOT NULL,
    descricao TEXT NOT NULL,
    imagem VARCHAR(255),
    data_post TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ===========================================
-- TABELA DE DOAÇÕES
-- ===========================================
CREATE TABLE doacoes (
    id_doacao SERIAL PRIMARY KEY,
    id_doador INT NOT NULL REFERENCES doadores(id_doador) ON DELETE CASCADE,
    id_ong INT NOT NULL REFERENCES ongs(id_ong) ON DELETE CASCADE,
    tipo VARCHAR(20) NOT NULL CHECK (tipo IN ('ITEM', 'DINHEIRO')),
    status VARCHAR(20) DEFAULT 'AGENDADA' CHECK (status IN ('AGENDADA', 'RECEBIDA', 'CANCELADA')),
    data_doacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    valor NUMERIC(10,2),
    descricao_item TEXT
);

-- ===========================================
-- TABELA DE COLETAS
-- ===========================================
CREATE TABLE coletas (
    id_coleta SERIAL PRIMARY KEY,
    id_doacao INT NOT NULL REFERENCES doacoes(id_doacao) ON DELETE CASCADE,
    tipo VARCHAR(20) NOT NULL DEFAULT 'COLETA',
    endereco VARCHAR(255) NOT NULL,
    data_agendada TIMESTAMP NOT NULL,
    confirmado BOOLEAN DEFAULT FALSE
);

-- ===========================================
-- TABELA DE AVALIAÇÕES
-- ===========================================
CREATE TABLE avaliacoes (
    id_avaliacao SERIAL PRIMARY KEY,
    id_doador INT NOT NULL REFERENCES doadores(id_doador) ON DELETE CASCADE,
    id_ong INT NOT NULL REFERENCES ongs(id_ong) ON DELETE CASCADE,
    nota INT CHECK (nota >= 1 AND nota <= 5),
    comentario TEXT,
    data_avaliacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(id_doador, id_ong)
);

-- ===========================================
-- TABELA DE NOTIFICAÇÕES
-- ===========================================
CREATE TABLE notificacoes (
    id_notificacao SERIAL PRIMARY KEY,
    id_usuario INT NOT NULL REFERENCES usuarios(id_usuario) ON DELETE CASCADE,
    mensagem TEXT NOT NULL,
    tipo VARCHAR(50) DEFAULT 'GERAL',
    lida BOOLEAN DEFAULT FALSE,
    data_envio TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ===========================================
-- TABELA DE COLETAS VISUALIZADAS (para ONGs)
-- ===========================================
CREATE TABLE coletas_visualizadas (
    id_visualizacao SERIAL PRIMARY KEY,
    id_ong INT NOT NULL REFERENCES usuarios(id_usuario) ON DELETE CASCADE,
    id_doacao INT NOT NULL REFERENCES doacoes(id_doacao) ON DELETE CASCADE,
    visualizada BOOLEAN DEFAULT FALSE,
    data_visualizacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE (id_ong, id_doacao)
);

-- ===========================================
-- CRIAR ÍNDICES PARA PERFORMANCE
-- ===========================================

-- Índices para usuários
CREATE INDEX idx_usuarios_email ON usuarios(email);
CREATE INDEX idx_usuarios_tipo ON usuarios(tipo_usuario);

-- Índices para posts
CREATE INDEX idx_posts_usuario ON posts(id_usuario);
CREATE INDEX idx_posts_categoria ON posts(categoria);
CREATE INDEX idx_posts_data ON posts(data_post DESC);

-- Índices para doações
CREATE INDEX idx_doacoes_doador ON doacoes(id_doador);
CREATE INDEX idx_doacoes_ong ON doacoes(id_ong);
CREATE INDEX idx_doacoes_status ON doacoes(status);
CREATE INDEX idx_doacoes_data ON doacoes(data_doacao DESC);

-- Índices para coletas
CREATE INDEX idx_coletas_doacao ON coletas(id_doacao);
CREATE INDEX idx_coletas_data ON coletas(data_agendada);

-- Índices para notificações
CREATE INDEX idx_notificacoes_usuario ON notificacoes(id_usuario, data_envio DESC);
CREATE INDEX idx_notificacoes_nao_lidas ON notificacoes(id_usuario) WHERE lida = FALSE;

-- Índices para coletas visualizadas
CREATE INDEX idx_coletas_visualizadas_ong ON coletas_visualizadas(id_ong);
CREATE INDEX idx_coletas_visualizadas_doacao ON coletas_visualizadas(id_doacao);

-- ===========================================
-- FUNÇÕES ÚTEIS
-- ===========================================

-- Função para contar notificações não lidas de um usuário
CREATE OR REPLACE FUNCTION contar_notificacoes_nao_lidas(p_id_usuario INTEGER)
RETURNS INTEGER AS $$
DECLARE
    total INTEGER;
BEGIN
    SELECT COUNT(*) INTO total
    FROM notificacoes
    WHERE id_usuario = p_id_usuario AND lida = FALSE;
    
    RETURN total;
END;
$$ LANGUAGE plpgsql;

-- Função para contar coletas não visualizadas de uma ONG
CREATE OR REPLACE FUNCTION contar_coletas_nao_visualizadas(p_id_ong INTEGER)
RETURNS INTEGER AS $$
DECLARE
    total INTEGER;
BEGIN
    SELECT COUNT(*) INTO total
    FROM doacoes d
    JOIN coletas c ON d.id_doacao = c.id_doacao
    LEFT JOIN coletas_visualizadas cv ON d.id_doacao = cv.id_doacao AND cv.id_ong = p_id_ong
    WHERE d.id_ong = p_id_ong 
    AND d.status = 'AGENDADA'
    AND c.data_agendada >= CURRENT_DATE
    AND (cv.visualizada IS NULL OR cv.visualizada = FALSE);
    
    RETURN total;
END;
$$ LANGUAGE plpgsql;

-- ===========================================
-- TRIGGERS
-- ===========================================

-- Trigger para criar notificação automática quando uma doação é recebida
CREATE OR REPLACE FUNCTION notificar_doacao_recebida()
RETURNS TRIGGER AS $$
BEGIN
    IF NEW.status = 'RECEBIDA' AND OLD.status != 'RECEBIDA' THEN
        -- Notificar o doador
        INSERT INTO notificacoes (id_usuario, mensagem, tipo)
        SELECT NEW.id_doador, 
               'Sua doação foi recebida e confirmada pela ONG!', 
               'DOACAO_RECEBIDA'
        FROM usuarios 
        WHERE id_usuario = NEW.id_doador;
    END IF;
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

CREATE TRIGGER trigger_notificar_doacao_recebida
AFTER UPDATE ON doacoes
FOR EACH ROW
EXECUTE FUNCTION notificar_doacao_recebida();

-- Trigger para criar notificação automática quando uma coleta é agendada
CREATE OR REPLACE FUNCTION notificar_coleta_agendada()
RETURNS TRIGGER AS $$
DECLARE
    v_id_ong INTEGER;
    v_nome_doador VARCHAR(100);
    v_tipo_doacao VARCHAR(20);
BEGIN
    -- Buscar informações da doação
    SELECT d.id_ong, d.tipo, u.nome INTO v_id_ong, v_tipo_doacao, v_nome_doador
    FROM doacoes d
    JOIN usuarios u ON d.id_doador = u.id_usuario
    WHERE d.id_doacao = NEW.id_doacao;
    
    -- Notificar a ONG sobre a nova coleta agendada
    INSERT INTO notificacoes (id_usuario, mensagem, tipo)
    VALUES (
        v_id_ong,
        v_nome_doador || ' agendou uma coleta de ' || v_tipo_doacao || 
        ' para ' || TO_CHAR(NEW.data_agendada, 'DD/MM/YYYY HH24:MI') || 
        ' no local: ' || NEW.endereco,
        'COLETA_AGENDADA'
    );
    
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

CREATE TRIGGER trigger_notificar_coleta_agendada
AFTER INSERT ON coletas
FOR EACH ROW
EXECUTE FUNCTION notificar_coleta_agendada();