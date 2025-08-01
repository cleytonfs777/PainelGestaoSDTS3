SET NAMES 'utf8mb4';
-- Create the contratos table
CREATE TABLE IF NOT EXISTS contratos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    objeto VARCHAR(255),
    detalhamento VARCHAR(200),
    el_item INT,
    valor_anual_estimado DECIMAL(15,2),
    valor_ppag DECIMAL(15,2),
    valor_empenhado DECIMAL(15,2),
    nr_contrato VARCHAR(50),
    meses INT,
    servico_continuado BOOLEAN,
    anos_limite_contratual INT,
    data_inicio DATE,
    data_final DATE,
    empenho BOOLEAN,
    liquidacao BOOLEAN,
    nr_termo_aditivo INT,
    quantidade DECIMAL(10,2),
    distribuicao TEXT,
    processo_sei_sdts VARCHAR(100),
    processo_sei_csm VARCHAR(100),
    status_aditamento VARCHAR(255),
    situacao_aditamento_ano_corrente VARCHAR(255),
    razao_social VARCHAR(255),
    email VARCHAR(255),
    responsavel VARCHAR(255),
    telefone VARCHAR(50),
    situacao VARCHAR(50) DEFAULT 'Não informado',
    observacoes TEXT,
    contrato_documento VARCHAR(255)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Insert sample data
INSERT INTO contratos (
    objeto, detalhamento, el_item, valor_anual_estimado, valor_ppag, valor_empenhado, nr_contrato, meses, servico_continuado, anos_limite_contratual, data_inicio, data_final, empenho, liquidacao, nr_termo_aditivo, quantidade, distribuicao, processo_sei_sdts, processo_sei_csm, status_aditamento, situacao_aditamento_ano_corrente, razao_social, email, responsavel, telefone, situacao, observacoes, contrato_documento
) VALUES
('Desenvolvimento de Sistema Web', 'Sistema de gestão de contratos', 101, 150000.00, 120000.00, 100000.00, 'CONTR-2024-001', 12, 1, 5, '2024-01-01', '2024-12-31', 1, 0, 2, 1.00, 'Distribuição A', 'SEI12345', 'CSM54321', 'Em andamento', 'Assinado e publicado', 'Tech Solutions SA', 'contato@techsolutions.com', 'João Silva', '(11) 99999-9999', 'Ativo', 'Contrato para desenvolvimento de sistema web', 'exemplo1.pdf'),
('Manutenção de Infraestrutura', 'Manutenção preventiva e corretiva', 202, 50000.00, 40000.00, 35000.00, 'CONTR-2024-002', 6, 0, 3, '2024-02-01', '2024-08-01', 1, 1, 1, 2.00, 'Distribuição B', 'SEI67890', 'CSM09876', 'Aguardando aditamento', 'Montagem do processo', 'Infra Services Ltda', 'contato@infraservices.com', 'Maria Santos', '(11) 98888-8888', 'Pendente', 'Contrato de manutenção de infraestrutura', 'exemplo2.pdf'),
('Manutenção de Infraestrutura', 'Manutenção preventiva e corretiva', 202, 50000.00, 40000.00, 35000.00, 'CONTR-2024-002', 6, 0, 3, '2024-02-01', '2024-08-01', 1, 1, 1, 2.00, 'Distribuição B', 'SEI67890', 'CSM09876', 'Aguardando aditamento', 'Montagem do processo', 'Infra Services Ltda', 'contato@infraservices.com', 'Maria Santos', '(11) 98888-8888', 'Pendente', 'Contrato de manutenção de infraestrutura', 'exemplo2.pdf'),
('Manutenção de Infraestrutura', 'Manutenção preventiva e corretiva', 202, 50000.00, 40000.00, 35000.00, 'CONTR-2024-002', 6, 0, 3, '2024-02-01', '2024-08-01', 1, 1, 1, 2.00, 'Distribuição B', 'SEI67890', 'CSM09876', 'Aguardando aditamento', 'Montagem do processo', 'Infra Services Ltda', 'contato@infraservices.com', 'Maria Santos', '(11) 98888-8888', 'Pendente', 'Contrato de manutenção de infraestrutura', 'exemplo2.pdf'),
('Manutenção de Infraestrutura', 'Manutenção preventiva e corretiva', 202, 50000.00, 40000.00, 35000.00, 'CONTR-2024-002', 6, 0, 3, '2024-02-01', '2024-08-01', 1, 1, 1, 2.00, 'Distribuição B', 'SEI67890', 'CSM09876', 'Aguardando aditamento', 'Montagem do processo', 'Infra Services Ltda', 'contato@infraservices.com', 'Maria Santos', '(11) 98888-8888', 'Pendente', 'Contrato de manutenção de infraestrutura', 'exemplo2.pdf'),
('Manutenção de Infraestrutura', 'Manutenção preventiva e corretiva', 202, 50000.00, 40000.00, 35000.00, 'CONTR-2024-002', 6, 0, 3, '2024-02-01', '2024-08-01', 1, 1, 1, 2.00, 'Distribuição B', 'SEI67890', 'CSM09876', 'Aguardando aditamento', 'Montagem do processo', 'Infra Services Ltda', 'contato@infraservices.com', 'Maria Santos', '(11) 98888-8888', 'Pendente', 'Contrato de manutenção de infraestrutura', 'exemplo2.pdf'),
('Manutenção de Infraestrutura', 'Manutenção preventiva e corretiva', 202, 50000.00, 40000.00, 35000.00, 'CONTR-2024-002', 6, 0, 3, '2024-02-01', '2024-08-01', 1, 1, 1, 2.00, 'Distribuição B', 'SEI67890', 'CSM09876', 'Aguardando aditamento', 'Montagem do processo', 'Infra Services Ltda', 'contato@infraservices.com', 'Maria Santos', '(11) 98888-8888', 'Pendente', 'Contrato de manutenção de infraestrutura', 'exemplo2.pdf'),
('Manutenção de Infraestrutura', 'Manutenção preventiva e corretiva', 202, 50000.00, 40000.00, 35000.00, 'CONTR-2024-002', 6, 0, 3, '2024-02-01', '2024-08-01', 1, 1, 1, 2.00, 'Distribuição B', 'SEI67890', 'CSM09876', 'Aguardando aditamento', 'Montagem do processo', 'Infra Services Ltda', 'contato@infraservices.com', 'Maria Santos', '(11) 98888-8888', 'Pendente', 'Contrato de manutenção de infraestrutura', 'exemplo2.pdf'),
('Manutenção de Infraestrutura', 'Manutenção preventiva e corretiva', 202, 50000.00, 40000.00, 35000.00, 'CONTR-2024-002', 6, 0, 3, '2024-02-01', '2024-08-01', 1, 1, 1, 2.00, 'Distribuição B', 'SEI67890', 'CSM09876', 'Aguardando aditamento', 'Montagem do processo', 'Infra Services Ltda', 'contato@infraservices.com', 'Maria Santos', '(11) 98888-8888', 'Pendente', 'Contrato de manutenção de infraestrutura', 'exemplo2.pdf'); 