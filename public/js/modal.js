// Sistema de Modal para Gestão Orçamentária
console.log('=== MODAL.JS CARREGADO COM SUCESSO ===');

// Função de teste simples
window.testeModal = function() {
    console.log('Teste executado com sucesso!');
    alert('Modal.js está funcionando!');
};

// Função principal para abrir modais
function openModal(modalId, clearForm = true) {
    console.log('openModal chamado para:', modalId);
    const modal = document.getElementById(modalId);
    if (modal) {
        if (clearForm) {
            const form = modal.querySelector('form');
            if (form) {
                form.reset();
            }
        }
        modal.classList.add('show');
        modal.style.display = 'flex';
        document.body.style.overflow = 'hidden';
        console.log('Modal aberto:', modalId);
    } else {
        console.error('Modal não encontrado:', modalId);
    }
}

// Função para fechar modais
function closeModal(modalId) {
    console.log('closeModal chamado para:', modalId);
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.classList.remove('show');
        modal.style.display = 'none';
        document.body.style.overflow = 'auto';
        console.log('Modal fechado:', modalId);
    } else {
        console.error('Modal não encontrado para fechar:', modalId);
    }
}

// Função para toggle campos de distribuição (modal principal)
function toggleDistribuicaoFields(select) {
    const campoAnual = document.getElementById('campo_anual');
    const camposMensal = document.getElementById('campos_mensal');
    
    // Reset
    if (campoAnual) campoAnual.classList.remove('show');
    if (camposMensal) camposMensal.classList.remove('show');
    
    if (select.value === 'Anual' && campoAnual) {
        campoAnual.classList.add('show');
    } else if (select.value === 'Mensal' && camposMensal) {
        camposMensal.classList.add('show');
    }
}

// Função para toggle campos de distribuição (modal de edição de receita)
function toggleEditDistribuicaoFieldsReceita(select) {
    const campoAnual = document.getElementById('edit-campo-anual-receita');
    const camposMensal = document.getElementById('edit-campos-mensal-receita');
    
    if (campoAnual) campoAnual.style.display = 'none';
    if (camposMensal) camposMensal.style.display = 'none';
    
    if (select.value === 'Anual' && campoAnual) {
        campoAnual.style.display = 'block';
    } else if (select.value === 'Mensal' && camposMensal) {
        camposMensal.style.display = 'block';
    }
}

// Função para toggle campos de distribuição (modal de edição de despesa)
function toggleEditDistribuicaoFieldsDespesa(select) {
    const campoAnual = document.getElementById('edit-campo-anual-despesa');
    const camposMensal = document.getElementById('edit-campos-mensal-despesa');
    
    if (campoAnual) campoAnual.style.display = 'none';
    if (camposMensal) camposMensal.style.display = 'none';
    
    if (select.value === 'Anual' && campoAnual) {
        campoAnual.style.display = 'block';
    } else if (select.value === 'Mensal' && camposMensal) {
        camposMensal.style.display = 'block';
    }
}

// Função para atualizar informações do item
function updateItemInfo(select) {
    const infoDiv = document.getElementById('item-info');
    const quantidadeInput = document.querySelector('input[name="quantidade"]');
    
    if (select.value && infoDiv) {
        const option = select.selectedOptions[0];
        const valor = parseFloat(option.dataset.valor);
        
        infoDiv.innerHTML = `Valor unitário: R$ ${valor.toFixed(2).replace('.', ',')}`;
        infoDiv.style.display = 'block';
        
        if (quantidadeInput && quantidadeInput.value) {
            updateTotal();
        }
    } else if (infoDiv) {
        infoDiv.style.display = 'none';
    }
}

// Função para atualizar total
function updateTotal() {
    const select = document.querySelector('select[name="id_item_catalogo"]');
    const quantidadeInput = document.querySelector('input[name="quantidade"]');
    const infoDiv = document.getElementById('item-info');
    
    if (select && select.value && quantidadeInput && quantidadeInput.value && infoDiv) {
        const option = select.selectedOptions[0];
        const valor = parseFloat(option.dataset.valor);
        const quantidade = parseInt(quantidadeInput.value);
        const total = valor * quantidade;
        
        infoDiv.innerHTML = `
            Valor unitário: R$ ${valor.toFixed(2).replace('.', ',')}<br>
            <strong>Total: R$ ${total.toFixed(2).replace('.', ',')}</strong>
        `;
    }
}

// Função para editar pacote de receita
function editPacoteReceita(button) {
    console.log('editPacoteReceita chamado');
    
    const id = button.dataset.id;
    const unidade = button.dataset.unidade;
    const ano = button.dataset.ano;
    const data = button.dataset.data;
    const sei = button.dataset.sei;
    const descricao = button.dataset.descricao;
    
    document.getElementById('edit-receita-id').value = id;
    document.getElementById('edit-receita-unidade').value = unidade;
    document.getElementById('edit-receita-ano').value = ano;
    document.getElementById('edit-receita-data').value = data;
    document.getElementById('edit-receita-sei').value = sei || '';
    document.getElementById('edit-receita-descricao').value = descricao || '';
    
    openModal('modal-edit-receita', false);
}

// Função para editar pacote de despesa
function editPacoteDespesa(button) {
    console.log('editPacoteDespesa chamado');
    
    const id = button.dataset.id;
    const unidade = button.dataset.unidade;
    const data = button.dataset.data;
    const sei = button.dataset.sei;
    const descricao = button.dataset.descricao;
    
    document.getElementById('edit-despesa-id').value = id;
    document.getElementById('edit-despesa-unidade').value = unidade;
    document.getElementById('edit-despesa-data').value = data;
    document.getElementById('edit-despesa-sei').value = sei || '';
    document.getElementById('edit-despesa-descricao').value = descricao || '';
    
    openModal('modal-edit-despesa', false);
}

// Função para editar item de receita
function editItemReceita(button) {
    console.log('editItemReceita chamado');
    
    const itemId = button.dataset.itemId;
    const pacoteId = button.dataset.pacoteId;
    const catalogoId = button.dataset.catalogoId;
    const quantidade = button.dataset.quantidade;
    const valorUnitario = button.dataset.valorUnitario;
    const acao = button.dataset.acao;
    const grupo = button.dataset.grupo;
    const elemento = button.dataset.elemento;
    const tipoDistribuicao = button.dataset.tipoDistribuicao;
    const mesAlocacao = button.dataset.mesAlocacao;
    const mesInicial = button.dataset.mesInicial;
    const mesFinal = button.dataset.mesFinal;
    
    document.getElementById('edit-item-receita-id').value = itemId;
    document.getElementById('edit-item-receita-pacote-id').value = pacoteId;
    document.getElementById('edit-item-receita-acao').value = `${acao}.${grupo}.${elemento}`;
    document.getElementById('edit-item-receita-valor').value = valorUnitario;
    document.getElementById('edit-item-receita-quantidade').value = quantidade;
    document.getElementById('edit-item-receita-distribuicao').value = tipoDistribuicao;
    
    if (tipoDistribuicao === 'Anual') {
        document.getElementById('edit-mes-alocacao-receita').value = mesAlocacao;
    } else {
        document.getElementById('edit-mes-inicial-receita').value = mesInicial;
        document.getElementById('edit-mes-final-receita').value = mesFinal;
    }
    
    // Trigger change event para mostrar campos corretos
    toggleEditDistribuicaoFieldsReceita(document.getElementById('edit-item-receita-distribuicao'));
    
    openModal('modal-edit-item-receita', false);
}

// Função para editar item de despesa
function editItemDespesa(button) {
    console.log('editItemDespesa chamado');
    
    const itemId = button.dataset.itemId;
    const pacoteId = button.dataset.pacoteId;
    const catalogoId = button.dataset.catalogoId;
    const quantidade = button.dataset.quantidade;
    const valorUnitario = button.dataset.valorUnitario;
    const grupo = button.dataset.grupo;
    const elemento = button.dataset.elemento;
    const tipoDistribuicao = button.dataset.tipoDistribuicao;
    const mesAlocacao = button.dataset.mesAlocacao;
    const mesInicial = button.dataset.mesInicial;
    const mesFinal = button.dataset.mesFinal;
    
    document.getElementById('edit-item-despesa-id').value = itemId;
    document.getElementById('edit-item-despesa-pacote-id').value = pacoteId;
    document.getElementById('edit-item-despesa-grupo').value = `${grupo}.${elemento}`;
    document.getElementById('edit-item-despesa-valor').value = valorUnitario;
    document.getElementById('edit-item-despesa-quantidade').value = quantidade;
    document.getElementById('edit-item-despesa-distribuicao').value = tipoDistribuicao;
    
    if (tipoDistribuicao === 'Anual') {
        document.getElementById('edit-mes-alocacao-despesa').value = mesAlocacao;
    } else {
        document.getElementById('edit-mes-inicial-despesa').value = mesInicial;
        document.getElementById('edit-mes-final-despesa').value = mesFinal;
    }
    
    // Trigger change event para mostrar campos corretos
    toggleEditDistribuicaoFieldsDespesa(document.getElementById('edit-item-despesa-distribuicao'));
    
    openModal('modal-edit-item-despesa', false);
}

// Event listeners quando o DOM for carregado
document.addEventListener('DOMContentLoaded', function() {
    console.log('DOM carregado - configurando event listeners');
    
    // Event listeners para os botões de editar receita
    const botoesEditReceita = document.querySelectorAll('.btn-edit-receita');
    botoesEditReceita.forEach(botao => {
        botao.addEventListener('click', function(e) {
            e.preventDefault();
            editPacoteReceita(this);
        });
    });
    
    // Event listeners para os botões de editar despesa
    const botoesEditDespesa = document.querySelectorAll('.btn-edit-despesa');
    botoesEditDespesa.forEach(botao => {
        botao.addEventListener('click', function(e) {
            e.preventDefault();
            editPacoteDespesa(this);
        });
    });
    
    // Event listeners para os botões de editar item de receita
    const botoesEditItemReceita = document.querySelectorAll('.btn-edit-item-receita');
    botoesEditItemReceita.forEach(botao => {
        botao.addEventListener('click', function(e) {
            e.preventDefault();
            editItemReceita(this);
        });
    });
    
    // Event listeners para os botões de editar item de despesa
    const botoesEditItemDespesa = document.querySelectorAll('.btn-edit-item-despesa');
    botoesEditItemDespesa.forEach(botao => {
        botao.addEventListener('click', function(e) {
            e.preventDefault();
            editItemDespesa(this);
        });
    });
    
    // Event listener para fechar modal clicando fora do conteúdo
    document.addEventListener('click', function(e) {
        if (e.target.classList.contains('modal') && e.target.classList.contains('show')) {
            closeModal(e.target.id);
        }
    });
    
    // Event listener para fechar modal com tecla ESC
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            const openModal = document.querySelector('.modal.show');
            if (openModal) {
                closeModal(openModal.id);
            }
        }
    });
    
    // Auto-hide feedback messages
    const feedback = document.getElementById('feedback');
    if (feedback) {
        setTimeout(() => {
            feedback.style.opacity = '0';
            setTimeout(() => {
                feedback.style.display = 'none';
            }, 300);
        }, 5000);
    }
    
    // Add event listeners para quantidade input
    const quantidadeInput = document.querySelector('input[name="quantidade"]');
    if (quantidadeInput) {
        quantidadeInput.addEventListener('input', updateTotal);
    }
    
    console.log('Event listeners configurados!');
});

// Teste de disponibilidade das funções
window.addEventListener('load', function() {
    console.log('=== TESTE DE FUNÇÕES MODAIS ===');
    console.log('openModal disponível:', typeof window.openModal);
    console.log('closeModal disponível:', typeof window.closeModal);
    console.log('Todas as funções:', Object.getOwnPropertyNames(window).filter(name => name.includes('Modal')));
});

// Tornar as funções globalmente disponíveis
window.openModal = openModal;
window.closeModal = closeModal;
window.toggleDistribuicaoFields = toggleDistribuicaoFields;
window.updateItemInfo = updateItemInfo;
window.updateTotal = updateTotal;