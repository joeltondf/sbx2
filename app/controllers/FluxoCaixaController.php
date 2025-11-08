<?php
// /app/controllers/FluxoCaixaController.php

require_once __DIR__ . '/../models/LancamentoFinanceiro.php';
require_once __DIR__ . '/../models/CategoriaFinanceira.php';
require_once __DIR__ . '/../models/Processo.php';
require_once __DIR__ . '/../models/Venda.php';

class FluxoCaixaController {
    private $pdo;
    private $lancamentoFinanceiroModel;
    private $categoriaModel;

    public function __construct($pdo) {
        $this->pdo = $pdo;
        $this->lancamentoFinanceiroModel = new LancamentoFinanceiro($pdo);
        $this->categoriaModel = new CategoriaFinanceira($pdo);
    }

    /**
     * Verifica se o usuário está logado. Se não, redireciona para a página de login.
     */
    private function auth_check() {
        if (!isset($_SESSION['user_id'])) {
            header('Location: /login.php');
            exit();
        }
    }

    /**
     * Renderiza uma view com os dados passados.
     */
    protected function render($view, $data = []) {
        extract($data);
        // Garante que o header e footer sejam incluídos no contexto da view
        require_once __DIR__ . '/../views/layouts/header.php';
        require_once __DIR__ . '/../views/' . $view . '.php';
        require_once __DIR__ . '/../views/layouts/footer.php';
    }

    /**
     * Exibe a página principal do Fluxo de Caixa.
     */
public function index() {
    $this->auth_check();

    // ... (código de filtros existente) ...
    $page = $_GET['page'] ?? 1;
    $search = $_GET['search'] ?? '';
    $filters = [
        'start_date' => $_GET['start_date'] ?? null,
        'end_date' => $_GET['end_date'] ?? null,
        'type' => $_GET['type'] ?? null,
        'status' => $_GET['status'] ?? null,
        'category' => $_GET['category'] ?? null,
    ];

    $lancamentos = $this->lancamentoFinanceiroModel->getAllPaginated($page, 20, $search, $filters);
    $totalLancamentos = $this->lancamentoFinanceiroModel->countAll($search, $filters);
    $categorias = $this->categoriaModel->getExpenseCategories();

    // --- CORREÇÃO: Lógica para buscar os totais ---
    $totals = $this->lancamentoFinanceiroModel->getTotals($search, $filters);
    $receitas = $totals['receitas'] ?? 0;
    $despesas = $totals['despesas'] ?? 0;
    $resultado = $receitas - $despesas;
    // --- FIM DA CORREÇÃO ---

    // ... (lógica do relatório de serviços existente) ...
    $processoModel = new Processo($this->pdo);
    $hoje = new DateTime();
    $data_inicio_relatorio = !empty($filters['start_date']) ? $filters['start_date'] : $hoje->format('Y-m-01');
    $data_fim_relatorio = !empty($filters['end_date']) ? $filters['end_date'] : $hoje->format('Y-m-t');
    $relatorioServicos = $processoModel->getRelatorioServicosPorPeriodo($data_inicio_relatorio, $data_fim_relatorio);
    $mesRelatorio = (new DateTime($data_inicio_relatorio))->format('m/Y');

    $this->render('fluxo_caixa/painel', [
        'lancamentos' => $lancamentos,
        'totalLancamentos' => $totalLancamentos,
        'categorias' => $categorias,
        'currentPage' => $page,
        'totalPages' => ceil($totalLancamentos / 20),
        'search' => $search,
        'filters' => $filters,
        'relatorioServicos' => $relatorioServicos,
        'mesRelatorio' => $mesRelatorio,
        // --- CORREÇÃO: Enviando as variáveis para a view ---
        'receitas' => $receitas,
        'despesas' => $despesas,
        'resultado' => $resultado
    ]);
}

    /**
     * Persiste uma despesa manual registrada pelo formulário da tela.
     */
    public function store(): void
    {
        $this->auth_check();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: /fluxo_caixa.php');
            exit();
        }

        $categoriaId = isset($_POST['categoria_id']) ? (int) $_POST['categoria_id'] : null;
        $descricao = trim($_POST['descricao'] ?? '');
        $valorBruto = $_POST['valor'] ?? '';
        $dataLancamento = $_POST['data_lancamento'] ?? date('Y-m-d');
        $dataVencimento = $_POST['data_vencimento'] ?? null;
        $statusPagamento = strtoupper($_POST['status_pagamento'] ?? 'PENDENTE');
        $metodoPagamento = trim($_POST['metodo_pagamento'] ?? '');
        $metodoPagamento = $metodoPagamento !== '' ? $metodoPagamento : null;

        $allowedStatus = ['PAGO', 'PENDENTE', 'VENCIDO'];
        if (!$categoriaId || $descricao === '' || $valorBruto === '' || !in_array($statusPagamento, $allowedStatus, true)) {
            $_SESSION['error_message'] = 'Preencha todos os campos obrigatórios da despesa manual.';
            header('Location: /fluxo_caixa.php');
            exit();
        }

        $valorNormalizado = is_string($valorBruto)
            ? str_replace(['.', ','], ['', '.'], preg_replace('/[^0-9,.-]/', '', $valorBruto))
            : $valorBruto;

        $dataLancamentoValida = DateTime::createFromFormat('Y-m-d', $dataLancamento) !== false;
        $dataVencimentoValida = !$dataVencimento || DateTime::createFromFormat('Y-m-d', $dataVencimento) !== false;

        if (!$dataLancamentoValida || !$dataVencimentoValida) {
            $_SESSION['error_message'] = 'Datas inválidas informadas para a despesa.';
            header('Location: /fluxo_caixa.php');
            exit();
        }

        $comprovanteUrl = null;
        if (!empty($_FILES['comprovante']) && $_FILES['comprovante']['error'] !== UPLOAD_ERR_NO_FILE) {
            if ($_FILES['comprovante']['error'] !== UPLOAD_ERR_OK) {
                $_SESSION['error_message'] = 'Falha ao enviar o comprovante da despesa.';
                header('Location: /fluxo_caixa.php');
                exit();
            }

            $uploadDir = __DIR__ . '/../../uploads/financeiro/comprovantes/';
            if (!is_dir($uploadDir) && !mkdir($uploadDir, 0775, true)) {
                $_SESSION['error_message'] = 'Não foi possível preparar o diretório de comprovantes.';
                header('Location: /fluxo_caixa.php');
                exit();
            }

            $originalName = $_FILES['comprovante']['name'] ?? 'comprovante';
            $extension = pathinfo($originalName, PATHINFO_EXTENSION);
            $safeExtension = $extension ? '.' . strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $extension)) : '';
            $fileName = uniqid('despesa_', true) . $safeExtension;
            $destination = $uploadDir . $fileName;

            if (!move_uploaded_file($_FILES['comprovante']['tmp_name'], $destination)) {
                $_SESSION['error_message'] = 'Não foi possível salvar o comprovante enviado.';
                header('Location: /fluxo_caixa.php');
                exit();
            }

            $comprovanteUrl = 'uploads/financeiro/comprovantes/' . $fileName;
        }

        $dadosDespesa = [
            'categoria_id' => $categoriaId,
            'descricao' => $descricao,
            'valor' => $valorNormalizado,
            'data_lancamento' => $dataLancamento,
            'data_vencimento' => $dataVencimento ?: null,
            'status_pagamento' => $statusPagamento,
            'metodo_pagamento' => $metodoPagamento,
            'comprovante_url' => $comprovanteUrl,
            'userid' => $_SESSION['user_id'] ?? null,
        ];

        if ($this->lancamentoFinanceiroModel->createManualExpense($dadosDespesa)) {
            $_SESSION['success_message'] = 'Despesa manual registrada com sucesso!';
        } else {
            if ($comprovanteUrl) {
                @unlink(__DIR__ . '/../../' . $comprovanteUrl);
            }
            $_SESSION['error_message'] = 'Não foi possível salvar a despesa manual.';
        }

        header('Location: /fluxo_caixa.php');
        exit();
    }

    /**
     * API para buscar detalhes de um lançamento agregado.
     */
    public function get_detalhes_lancamento_agregado($lancamento_id) {
        $this->auth_check();
        header('Content-Type: application/json');
        
        $lancamento = $this->lancamentoFinanceiroModel->getById($lancamento_id);
        
        if (!$lancamento || !$lancamento['eh_agregado'] || empty($lancamento['itens_agregados_ids'])) {
            echo json_encode(['success' => false, 'message' => 'Lançamento não encontrado ou não é agregado.']);
            return;
        }
        
        $ids_dos_itens = json_decode($lancamento['itens_agregados_ids'], true);
        
        if (empty($ids_dos_itens)) {
            echo json_encode(['success' => false, 'message' => 'Nenhum item detalhado encontrado.']);
            return;
        }
        
        $vendaModel = new Venda($this->pdo);
        $itens_detalhados = $vendaModel->getItensByIds($ids_dos_itens);
        
        echo json_encode(['success' => true, 'itens' => $itens_detalhados]);
    }
    
    /**
     * Apaga um lançamento financeiro.
     */
    public function delete() {
        $this->auth_check();
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id'])) {
            $id = $_POST['id'];
            if ($this->lancamentoFinanceiroModel->delete($id)) {
                $_SESSION['success_message'] = 'Lançamento apagado com sucesso!';
            } else {
                $_SESSION['error_message'] = 'Erro ao apagar o lançamento.';
            }
        }
        header('Location: /fluxo_caixa.php');
        exit();
    }
}