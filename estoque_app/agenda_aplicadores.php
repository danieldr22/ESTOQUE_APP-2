<?php
// agenda_aplicadores.php
require_once 'config.php';

// Verifica a conexão com o banco de dados
if (!$link) {
    die("Erro: Conexão com o banco de dados não estabelecida em agenda_aplicadores.php. Verifique config.php.");
}

// Lista fixa de aplicadores
$aplicadores_disponiveis = ['Toinho', 'Marcelino', 'Rafael', 'Samuel'];
$status_agendamento = ['Agendado', 'Concluído', 'Cancelado', 'Reagendado'];

// Opções de serviços agregados
$servicos_agregados_opcoes = [
    'vitrificacao' => 'Vitrificação',
    'vitrificacao_bancos' => 'Vitrificação de Bancos',
    'impermeabilizacao' => 'Impermeabilização',
    'ppf' => 'PPF'
];

// --- Lógica para filtros ---
$filter_aplicador = $_GET['filter_aplicador'] ?? '';
$filter_status = $_GET['filter_status'] ?? '';
$filter_data_inicio = $_GET['filter_data_inicio'] ?? '';
$filter_data_fim = $_GET['filter_data_fim'] ?? '';
$filter_concessionaria_id = $_GET['filter_concessionaria_id'] ?? '';


$filter_conditions = [];
if (!empty($filter_aplicador)) {
    $filter_conditions[] = "aplicador = '" . mysqli_real_escape_string($link, $filter_aplicador) . "'";
}
if (!empty($filter_status)) {
    $filter_conditions[] = "status = '" . mysqli_real_escape_string($link, $filter_status) . "'";
}
if (!empty($filter_data_inicio)) {
    $filter_conditions[] = "data_agendamento >= '" . mysqli_real_escape_string($link, $filter_data_inicio) . "'";
}
if (!empty($filter_data_fim)) {
    $filter_conditions[] = "data_agendamento <= '" . mysqli_real_escape_string($link, $filter_data_fim) . "'";
}
if (!empty($filter_concessionaria_id)) {
    $filter_conditions[] = "concessionaria_id = " . intval($filter_concessionaria_id);
}

$where_clause = '';
if (!empty($filter_conditions)) {
    $where_clause = " WHERE " . implode(' AND ', $filter_conditions);
}

// --- Lógica para buscar concessionárias para os dropdowns (tanto formulário de adição quanto filtro) ---
$concessionarias_dropdown = [];
$sql_concessionarias = "SELECT id, nome FROM concessionarias ORDER BY nome ASC";
$result_concessionarias = mysqli_query($link, $sql_concessionarias);
if ($result_concessionarias) {
    while ($row = mysqli_fetch_assoc($result_concessionarias)) {
        $concessionarias_dropdown[$row['id']] = $row['nome'];
    }
    mysqli_free_result($result_concessionarias);
}

// --- Lógica para processar mudança de status (AÇÃO) ---
if (isset($_GET['action']) && $_GET['action'] == 'change_status' && isset($_GET['id']) && isset($_GET['new_status'])) {
    $agendamento_id = filter_var($_GET['id'], FILTER_VALIDATE_INT);
    $new_status = mysqli_real_escape_string($link, $_GET['new_status']);

    if ($agendamento_id && in_array($new_status, $status_agendamento)) {
        $sql_update_status = "UPDATE agendamentos_aplicadores SET status = ? WHERE id = ?";
        if ($stmt = mysqli_prepare($link, $sql_update_status)) {
            mysqli_stmt_bind_param($stmt, "si", $new_status, $agendamento_id);
            if (mysqli_stmt_execute($stmt)) {
                $message = "Status do agendamento ID " . $agendamento_id . " atualizado para '" . htmlspecialchars($new_status) . "' com sucesso!";
                $type = "success";
            } else {
                $message = "Erro ao atualizar status: " . mysqli_error($link);
                $type = "error";
            }
            mysqli_stmt_close($stmt);
        } else {
            $message = "Erro ao preparar a atualização de status: " . mysqli_error($link);
            $type = "error";
        }
    } else {
        $message = "Dados inválidos para atualizar o status.";
        $type = "error";
    }
    // Redireciona para a mesma página com a mensagem (removendo action/id/new_status da URL)
    $redirect_params = $_GET;
    unset($redirect_params['action'], $redirect_params['id'], $redirect_params['new_status']);
    $redirect_params['message'] = $message;
    $redirect_params['type'] = $type;
    header('Location: agenda_aplicadores.php?' . http_build_query($redirect_params));
    exit();
}

// Lógica para processar o formulário de adição (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data_agendamento = trim($_POST['data_agendamento'] ?? '');
    $tipo_pelicula = trim($_POST['tipo_pelicula'] ?? ''); // Novo campo
    $concessionaria_id = filter_var($_POST['concessionaria_id'] ?? null, FILTER_VALIDATE_INT);
    $aplicador = trim($_POST['aplicador'] ?? '');
    $servico = trim($_POST['servico'] ?? '');
    $servicos_agregados_array = $_POST['servicos_agregados'] ?? []; // Novo campo (array)
    $observacoes = trim($_POST['observacoes'] ?? '');

    // Codifica os serviços agregados para JSON para armazenar no banco de dados
    $servicos_agregados_json = json_encode($servicos_agregados_array);
    if ($servicos_agregados_json === false) { // Verifica se houve erro na codificação JSON
        $servicos_agregados_json = '[]'; // Garante que seja um JSON vazio em caso de erro
    }

    if (empty($data_agendamento) || $concessionaria_id === false || $concessionaria_id <= 0 || empty($aplicador) || empty($servico) || !in_array($aplicador, $aplicadores_disponiveis)) {
        $message = "Erro: Por favor, preencha todos os campos obrigatórios corretamente e selecione um aplicador válido.";
        $type = "error";
    } else {
        // Ajuste o INSERT SQL para incluir os novos campos
        $sql_insert = "INSERT INTO agendamentos_aplicadores (data_agendamento, tipo_pelicula, concessionaria_id, aplicador, servico, servicos_agregados, observacoes) VALUES (?, ?, ?, ?, ?, ?, ?)";
        if ($stmt = mysqli_prepare($link, $sql_insert)) {
            // Ajuste os tipos para bind_param: sssisss -> ssissss (data, tipo, id_con, aplicador, servico, servicos_json, obs)
            mysqli_stmt_bind_param($stmt, "ssissss", $data_agendamento, $tipo_pelicula, $concessionaria_id, $aplicador, $servico, $servicos_agregados_json, $observacoes);
            if (mysqli_stmt_execute($stmt)) {
                $message = "Agendamento registrado com sucesso!";
                $type = "success";
            } else {
                $message = "Erro ao registrar agendamento: " . mysqli_error($link);
                $type = "error";
            }
            mysqli_stmt_close($stmt);
        } else {
            $message = "Erro ao preparar a inserção do agendamento: " . mysqli_error($link);
            $type = "error";
        }
    }
    // Redireciona para a mesma página com a mensagem (mantendo filtros se existirem)
    $redirect_params = $_GET; // Mantém os filtros da URL atual
    unset($redirect_params['action'], $redirect_params['id'], $redirect_params['new_status']); // Limpa parâmetros de ação
    $redirect_params['message'] = $message;
    $redirect_params['type'] = $type;
    header('Location: agenda_aplicadores.php?' . http_build_query($redirect_params));
    exit();
}

?>
<!DOCTYPE html>
<html lang="pt-BR" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Agenda de Aplicadores</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        /* Custom Colors based on logo */
        :root {
            --color-primary-dark-teal: #006B70;
            --color-primary-indigo: #4f46e5;
            --color-primary-indigo-dark: #4338ca;
            --color-footer-bg: #1a202c;
            --color-text-light: #e2e8f0;
        }

        html, body {
            height: 100%;
            margin: 0;
            padding: 0;
            overflow-x: hidden;
        }

        body {
            font-family: 'Inter', sans-serif;
            color: #333;
            display: flex;
            flex-direction: column;
            min-height: 100vh;
            background-color: #f3f4f6;
            background-image: url('imagens/background_art.jpg');
            background-size: cover;
            background-repeat: no-repeat;
            background-position: center center;
            background-attachment: fixed;
            padding-top: 150px; /* Espaço para o cabeçalho fixo */
        }
        
        .main-header {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            z-index: 1000;
            background-color: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(8px);
            -webkit-backdrop-filter: blur(8px);
            padding: 1rem 0;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
        }

        .container {
            max-width: 960px; /* Alinhado com o index para consistência */
            margin: 0 auto;
            padding: 3rem;
            background-color: #ffffff;
            border-radius: 1.5rem;
            box-shadow:
                0 25px 50px -12px rgba(0, 0, 0, 0.3),
                0 8px 15px -3px rgba(0, 0, 0, 0.15);
            flex-grow: 1;
            border: 1px solid rgba(224, 224, 224, 0.6);
        }
        h1 { /* Este H1 é do cabeçalho fixo principal */
            color: var(--color-primary-dark-teal);
            font-size: 2.8rem;
            font-weight: 700;
            text-align: center;
            margin-bottom: 0.5rem;
            letter-spacing: -0.03em;
            line-height: 1.2;
            padding-top: 0.5rem;
        }
        h2 { /* Títulos dentro dos containers */
            color: #4a5568;
            font-weight: 700;
            margin-bottom: 1.5rem;
        }
        .nav-category-container {
            display: flex;
            justify-content: center;
            gap: 1rem; /* Espaço entre as categorias principais */
            width: 100%;
            max-width: 800px; /* Limita a largura do container das categorias */
            padding: 0 1rem;
            flex-wrap: wrap; /* Permite quebrar linha em telas menores */
        }
        .nav-category {
            position: relative; /* Para posicionar o submenu */
            flex-grow: 1;
            flex-basis: 150px; /* Base para o tamanho das categorias */
            text-align: center;
        }
        .nav-category:hover .submenu {
            opacity: 1;
            visibility: visible;
            transform: translateY(0);
        }

        .nav-button { /* Estilo base para todos os botões (categoria e submenu) */
            display: flex;
            align-items: center;
            justify-content: center;
            width: 100%;
            padding: 0.8rem 1rem;
            background-color: var(--color-primary-indigo);
            color: white;
            text-align: center;
            border-radius: 9999px; /* Botões em formato de pílula */
            font-weight: 500;
            font-size: 0.95rem;
            transition: all 0.4s cubic-bezier(0.25, 0.8, 0.25, 1);
            text-decoration: none;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            border: none;
            cursor: pointer;
            outline: none;
            position: relative;
            overflow: hidden;
            white-space: nowrap; /* Evita que o texto quebre a linha no botão */
        }
        .nav-button::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: rgba(255, 255, 255, 0.15);
            transform: skewX(-30deg);
            transition: all 0.5s cubic-bezier(0.25, 0.8, 0.25, 1);
        }
        .nav-button:hover::before {
            left: 100%;
        }
        .nav-button:hover {
            background-color: var(--color-primary-indigo-dark);
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.15);
        }
        
        .submenu {
            position: absolute;
            top: calc(100% + 5px); /* Posiciona abaixo do botão pai */
            left: 50%; /* Centraliza o submenu */
            transform: translateX(-50%) translateY(10px); /* Ajusta para centralizar e ter um leve deslocamento inicial */
            min-width: 200px; /* Largura mínima para o submenu */
            background-color: rgba(255, 255, 255, 0.95);
            border-radius: 0.75rem;
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.2);
            padding: 0.75rem;
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s cubic-bezier(0.25, 0.8, 0.25, 1);
            z-index: 1005; /* Acima do header, mas abaixo de tooltips se houver */
            display: flex;
            flex-direction: column;
            gap: 0.5rem; /* Espaço entre os itens do submenu */
            align-items: stretch; /* Estica os botões para preencher a largura */
            border: 1px solid rgba(224, 224, 224, 0.6);
        }

        .submenu-item { /* Estilo específico para botões do submenu */
            font-size: 0.9rem;
            padding: 0.6rem 0.8rem;
            box-shadow: none; /* Remove sombra extra dos submenus */
            transition: background-color 0.2s ease-in-out, color 0.2s ease-in-out;
            background-color: transparent; /* Fundo transparente */
            color: #333; /* Cor de texto escura */
            border: 1px solid transparent; /* Borda transparente */
        }
        .submenu-item:hover {
            background-color: #f0f0f0; /* Fundo cinza claro no hover */
            color: var(--color-primary-indigo); /* Cor do texto ao passar o mouse */
            transform: translateY(0); /* Remove efeito de levantar */
            box-shadow: none; /* Remove sombra do hover */
            border-color: #ddd; /* Borda no hover */
        }

        .message-box {
            padding: 1rem;
            border-radius: 0.75rem;
            margin-bottom: 1.5rem;
            font-weight: 600;
            border-width: 1px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.08);
        }
        .message-success { background-color: #dcfce7; color: #065f46; border-color: #22c55e; }
        .message-error { background-color: #fee2e2; color: #991b1b; border-color: #ef4444; }
        
        .form-input, .form-select, .form-textarea { width: 100%; padding: 0.75rem; border: 1px solid #d1d5db; border-radius: 0.5rem; font-size: 1rem; background-color: #f9fafb; }
        .btn-primary { background-color: #4f46e5; color: white; padding: 0.75rem 1.5rem; border-radius: 0.5rem; font-weight: 600; transition: background-color 0.2s ease-in-out; }
        .btn-primary:hover { background-color: #4338ca; }
        .btn-secondary { background-color: #6b7280; color: white; padding: 0.75rem 1.5rem; border-radius: 0.5rem; font-weight: 600; transition: background-color 0.2s ease-in-out; text-decoration: none; }
        .btn-secondary:hover { background-color: #4b5563; }
        .btn-danger { background-color: #dc2626; color: white; padding: 0.75rem 1.5rem; border-radius: 0.5rem; font-weight: 600; transition: background-color 0.2s ease-in-out; text-decoration: none; }
        .btn-danger:hover { background-color: #b91c1c; }

        .table-header th { background-color: #e5e7eb; padding: 0.75rem; text-align: left; font-weight: 600; border-bottom: 2px solid #d1d5db; }
        .table-row td { padding: 0.75rem; border-bottom: 1px solid #e5e7eb; }
        .table-row:nth-child(even) { background-color: #f9fafb; }
        .app-footer { margin-top: 4rem; padding: 2rem; background-color: var(--color-footer-bg); color: var(--color-text-light); text-align: center; font-size: 1rem; box-shadow: 0 -8px 20px rgba(0, 0, 0, 0.3); border-top: 1px solid rgba(255, 255, 255, 0.1); }
        .app-footer p { margin-bottom: 0.5rem; line-height: 1.5; }
        .app-footer p:last-child { margin-bottom: 0; }

        /* Estilos específicos para páginas de formulário/tabela */
        .highlight-today {
            background-color: #fffacd;
            font-weight: 600;
            border-left: 5px solid #f59e0b;
        }
        .highlight-today:nth-child(even) {
            background-color: #fef08a;
        }
        .completed-appointment {
            background-color: #dcfce7;
            font-weight: 600;
            border-left: 5px solid #22c55e;
        }
        .completed-appointment:nth-child(even) {
            background-color: #bbf7d0;
        }
        .expired-appointment {
            background-color: #fce7e7;
            font-weight: 600;
            border-left: 5px solid #ef4444;
        }
        .expired-appointment:nth-child(even) {
            background-color: #fecaca;
        }
        .daily-panel {
            background-color: #ecfdf5;
            border: 1px solid #a7f3d0;
            padding: 1rem;
            border-radius: 0.5rem;
            margin-bottom: 2rem;
        }
        .daily-panel h3 {
            font-size: 1.25rem;
            font-weight: 700;
            color: #047857;
            margin-bottom: 0.75rem;
        }
        .daily-panel ul {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        .daily-panel ul li {
            background-color: #d1fae5;
            padding: 0.5rem 0.75rem;
            border-radius: 0.375rem;
            margin-bottom: 0.5rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 0.95rem;
            color: #065f46;
        }
        .daily-panel ul li:last-child {
            margin-bottom: 0;
        }
        .daily-panel ul li .status-badge {
            padding: 0.25rem 0.5rem;
            border-radius: 0.375rem;
            font-size: 0.8rem;
            font-weight: 600;
        }
        .status-badge.Agendado { background-color: #bfdbfe; color: #1e40af; }
        .status-badge.Concluído { background-color: #a7f3d0; color: #065f46; }
        .status-badge.Cancelado { background-color: #fecaca; color: #991b1b; }
        .status-badge.Reagendado { background-color: #fde68a; color: #92400e; }
    </style>
</head>
<body class="h-full">
    <header class="main-header">
        <h1 class="text-3xl font-bold">Sistema de Gestão de Estoque</h1>
        
        <nav class="nav-category-container">
            <div class="nav-category">
                <a href="#" class="nav-button category-toggle">PRODUTOS</a>
                <div class="submenu">
                    <a href="cadastro_produto.php" class="nav-button submenu-item">Cadastrar Produto</a>
                    <a href="entrada_produto.php" class="nav-button submenu-item">Registrar Entrada</a>
                    <a href="saida_produto.php" class="nav-button submenu-item">Registrar Saída</a>
                </div>
            </div>

            <div class="nav-category">
                <a href="#" class="nav-button category-toggle">SERVIÇOS</a>
                <div class="submenu">
                    <a href="agenda_aplicadores.php" class="nav-button submenu-item">Agenda Aplicadores</a>
                    <a href="agenda_compras.php" class="nav-button submenu-item">Agenda Compras</a>
                </div>
            </div>

            <div class="nav-category">
                <a href="#" class="nav-button category-toggle">RELATÓRIOS</a>
                <div class="submenu">
                    <a href="relatorio.php?type=estoque_atual" class="nav-button submenu-item">Estoque Atual</a>
                    <a href="relatorio_comissoes.php" class="nav-button submenu-item">Comissões Aplicadores</a>
                    <a href="relatorio.php?type=saida_total_por_produto" class="nav-button submenu-item">Saída por Produto</a>
                    <a href="relatorio.php?type=relatorio_lavagens" class="nav-button submenu-item">Serviços Executados</a>
                    </div>
            </div>
        </nav>
    </header>

    <div class="container">
        <?php
        // Exibe mensagens de sucesso ou erro (agora abaixo do cabeçalho fixo)
        if (isset($_GET['message']) && !isset($_GET['display_in_footer'])) {
            $message = htmlspecialchars($_GET['message']);
            $type = isset($_GET['type']) ? htmlspecialchars($_GET['type']) : 'success';
            echo "<div class='message-box message-$type'>$message</div>";
        }
        ?>

        <h1 class="text-3xl font-bold text-center mb-6">Agenda de Aplicadores</h1> <h2 class="text-2xl font-semibold mb-4 text-gray-700">Agendamentos de Hoje (<?php echo date('d/m/Y'); ?>)</h2>
        <div class="daily-panel">
            <?php
            $today_date_sql = date('Y-m-d');
            $sql_today_agendamentos = "SELECT aa.id, aa.aplicador, aa.servico, aa.status, c.nome AS concessionaria_nome, aa.observacoes, aa.tipo_pelicula, aa.servicos_agregados
                                       FROM agendamentos_aplicadores aa
                                       JOIN concessionarias c ON aa.concessionaria_id = c.id
                                       WHERE aa.data_agendamento = ?
                                       ORDER BY aa.id ASC"; // Ordena por ID para consistência

            $stmt_today = mysqli_prepare($link, $sql_today_agendamentos);
            mysqli_stmt_bind_param($stmt_today, "s", $today_date_sql);
            mysqli_stmt_execute($stmt_today);
            $result_today = mysqli_stmt_get_result($stmt_today);

            if ($result_today && mysqli_num_rows($result_today) > 0) {
                echo "<ul>";
                while ($agendamento_today = mysqli_fetch_assoc($result_today)) {
                    // Decodifica os serviços agregados para exibição no painel
                    $servicos_agregados_exibicao_today = [];
                    $decoded_services_today = json_decode($agendamento_today['servicos_agregados'], true);
                    if (is_array($decoded_services_today)) {
                        foreach ($decoded_services_today as $service_key) {
                            $servicos_agregados_exibicao_today[] = $servicos_agregados_opcoes[$service_key] ?? $service_key;
                        }
                    }
                    $servicos_agregados_str_today = !empty($servicos_agregados_exibicao_today) ? " (" . implode(', ', $servicos_agregados_exibicao_today) . ")" : '';

                    echo "<li>";
                    echo "<span>";
                    echo "<strong>" . htmlspecialchars($agendamento_today['concessionaria_nome']) . "</strong> - ";
                    echo htmlspecialchars($agendamento_today['servico']);
                    if (!empty($agendamento_today['tipo_pelicula'])) {
                        echo " (Película: " . htmlspecialchars($agendamento_today['tipo_pelicula']) . ")";
                    }
                    echo htmlspecialchars($servicos_agregados_str_today); // Adiciona os serviços agregados aqui
                    echo " (Aplicador: " . htmlspecialchars($agendamento_today['aplicador']) . ")";
                    if (!empty($agendamento_today['observacoes'])) {
                        echo " - Obs: " . htmlspecialchars($agendamento_today['observacoes']); // Adiciona observações
                    }
                    echo "</span>";
                    echo "<span class='status-badge " . htmlspecialchars($agendamento_today['status']) . "'>" . htmlspecialchars($agendamento_today['status']) . "</span>";
                    echo "</li>";
                }
                echo "</ul>";
            } else {
                echo "<p class='text-gray-600'>Nenhum agendamento para hoje.</p>";
            }
            mysqli_stmt_close($stmt_today);
            ?>
        </div>

        <hr class="my-8">

        <h2 class="text-2xl font-semibold mb-4 text-gray-700">Adicionar Novo Agendamento</h2>
        <form action="agenda_aplicadores.php" method="POST" class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-8 p-6 bg-white rounded-lg shadow-md">
            <div>
                <label for="data_agendamento" class="block text-sm font-medium text-gray-700 mb-1">Data:</label>
                <input type="date" id="data_agendamento" name="data_agendamento" class="form-input" value="<?php echo date('Y-m-d'); ?>" required>
            </div>
            <div>
                <label for="tipo_pelicula" class="block text-sm font-medium text-gray-700 mb-1">Tipo de Película:</label>
                <input type="text" id="tipo_pelicula" name="tipo_pelicula" class="form-input" placeholder="Ex: G5, G20, Window Blue" required>
            </div>
            <div>
                <label for="concessionaria_id" class="block text-sm font-medium text-gray-700 mb-1">Concessionária:</label>
                <select id="concessionaria_id" name="concessionaria_id" class="form-select" required>
                    <option value="">Selecione uma concessionária</option>
                    <?php foreach ($concessionarias_dropdown as $id => $nome): ?>
                        <option value="<?php echo htmlspecialchars($id); ?>"><?php echo htmlspecialchars($nome); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label for="aplicador" class="block text-sm font-medium text-gray-700 mb-1">Aplicador:</label>
                <select id="aplicador" name="aplicador" class="form-select" required>
                    <option value="">Selecione um aplicador</option>
                    <?php foreach ($aplicadores_disponiveis as $aplicador_nome): ?>
                        <option value="<?php echo htmlspecialchars($aplicador_nome); ?>"><?php echo htmlspecialchars($aplicador_nome); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="md:col-span-2">
                <label for="servico" class="block text-sm font-medium text-gray-700 mb-1">Serviço Principal:</label>
                <input type="text" id="servico" name="servico" class="form-input" placeholder="Ex: Instalação de Insulfilm" required>
            </div>

            <div class="md:col-span-2">
                <label class="block text-sm font-medium text-gray-700 mb-2">Serviços Agregados:</label>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                    <?php foreach ($servicos_agregados_opcoes as $key => $label): ?>
                        <div class="flex items-center">
                            <input type="checkbox" id="servico_<?php echo $key; ?>" name="servicos_agregados[]" value="<?php echo htmlspecialchars($key); ?>" class="h-4 w-4 text-indigo-600 border-gray-300 rounded focus:ring-indigo-500">
                            <label for="servico_<?php echo $key; ?>" class="ml-2 block text-sm text-gray-900"><?php echo htmlspecialchars($label); ?></label>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="md:col-span-2">
                <label for="observacoes" class="block text-sm font-medium text-gray-700 mb-1">Observações (Opcional):</label>
                <textarea id="observacoes" name="observacoes" class="form-textarea" rows="3" placeholder="Detalhes adicionais..."></textarea>
            </div>
            <div class="md:col-span-2 flex justify-end space-x-4">
                <button type="submit" class="btn-primary">Agendar Serviço</button>
            </div>
        </form>

        <hr class="my-8">

        <h2 class="text-2xl font-semibold mb-4 text-gray-700">Filtrar Agendamentos</h2>
        <form action="agenda_aplicadores.php" method="GET" class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-8 p-6 bg-white rounded-lg shadow-md">
            <input type="hidden" name="type" value="agenda_aplicadores">
            <div>
                <label for="filter_aplicador" class="block text-sm font-medium text-gray-700 mb-1">Aplicador:</label>
                <select id="filter_aplicador" name="filter_aplicador" class="form-select">
                    <option value="">Todos</option>
                    <?php foreach ($aplicadores_disponiveis as $aplicador_nome): ?>
                        <option value="<?php echo htmlspecialchars($aplicador_nome); ?>" <?php echo ($filter_aplicador == $aplicador_nome) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($aplicador_nome); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label for="filter_status" class="block text-sm font-medium text-gray-700 mb-1">Status:</label>
                <select id="filter_status" name="filter_status" class="form-select">
                    <option value="">Todos</option>
                    <?php foreach ($status_agendamento as $status): ?>
                        <option value="<?php echo htmlspecialchars($status); ?>" <?php echo ($filter_status == $status) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($status); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label for="filter_concessionaria_id" class="block text-sm font-medium text-gray-700 mb-1">Concessionária:</label>
                <select id="filter_concessionaria_id" name="filter_concessionaria_id" class="form-select">
                    <option value="">Todas</option>
                    <?php foreach ($concessionarias_dropdown as $id => $nome): ?>
                        <option value="<?php echo htmlspecialchars($id); ?>" <?php echo ($filter_concessionaria_id == $id) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($nome); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-span-1 md:col-span-3 grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label for="filter_data_inicio" class="block text-sm font-medium text-gray-700 mb-1">Data Início:</label>
                    <input type="date" id="filter_data_inicio" name="filter_data_inicio" class="form-input" value="<?php echo htmlspecialchars($filter_data_inicio); ?>">
                </div>
                <div>
                    <label for="filter_data_fim" class="block text-sm font-medium text-gray-700 mb-1">Data Fim:</label>
                    <input type="date" id="filter_data_fim" name="filter_data_fim" class="form-input" value="<?php echo htmlspecialchars($filter_data_fim); ?>">
                </div>
            </div>
            <div class="md:col-span-3 flex justify-end">
                <button type="submit" class="btn-primary w-full md:w-auto">Aplicar Filtros</button>
            </div>
        </form>

        <h2 class="text-2xl font-semibold mb-4 text-gray-800">Todos os Agendamentos</h2>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="table-header">
                    <tr>
                        <th>ID</th>
                        <th>Data</th>
                        <th>Tipo Película</th>
                        <th>Concessionária</th>
                        <th>Aplicador</th>
                        <th>Serviço Principal</th>
                        <th>Serviços Agregados</th>
                        <th>Status</th>
                        <th>Observações</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    <?php
                    // Obtém a data atual no formato Y-m-d para comparação
                    $today_date = date('Y-m-d');

                    $sql_agendamentos = "SELECT aa.id, aa.data_agendamento, aa.tipo_pelicula, aa.aplicador, aa.servico, aa.servicos_agregados, aa.status, aa.observacoes, c.nome AS concessionaria_nome
                                         FROM agendamentos_aplicadores aa
                                         JOIN concessionarias c ON aa.concessionaria_id = c.id
                                         " . $where_clause . "
                                         ORDER BY aa.data_agendamento ASC, aa.id ASC"; // Ordena por data e ID para consistência

                    $result_agendamentos = mysqli_query($link, $sql_agendamentos);

                    if ($result_agendamentos && mysqli_num_rows($result_agendamentos) > 0) {
                        while ($agendamento = mysqli_fetch_assoc($result_agendamentos)) {
                            // Determina a classe da linha: destaque para hoje, ou vermelho para vencido e não concluído/cancelado
                            $row_class = 'table-row';
                            if ($agendamento['status'] == 'Concluído') {
                                $row_class .= ' completed-appointment'; // Prioridade 1: Concluído (verde)
                            } elseif ($agendamento['data_agendamento'] < $today_date && $agendamento['status'] != 'Concluído' && $agendamento['status'] != 'Cancelado') {
                                $row_class .= ' expired-appointment'; // Prioridade 2: Vencido (vermelho)
                            } elseif ($agendamento['data_agendamento'] == $today_date) {
                                $row_class .= ' highlight-today'; // Prioridade 3: Hoje (amarelo)
                            }
                            // Se nenhuma das anteriores, permanece 'table-row' (fundo padrão/branco)

                            // Decodifica os serviços agregados para exibição
                            $servicos_agregados_exibicao = [];
                            $decoded_services = json_decode($agendamento['servicos_agregados'], true);
                            if (is_array($decoded_services)) {
                                foreach ($decoded_services as $service_key) {
                                    $servicos_agregados_exibicao[] = $servicos_agregados_opcoes[$service_key] ?? $service_key; // Usa o label formatado ou a key original
                                }
                            }
                            $servicos_agregados_str = !empty($servicos_agregados_exibicao) ? implode(', ', $servicos_agregados_exibicao) : '-';

                            echo "<tr class='" . $row_class . "'>";
                            echo "<td>" . htmlspecialchars($agendamento['id']) . "</td>";
                            echo "<td>" . htmlspecialchars(date('d/m/Y', strtotime($agendamento['data_agendamento']))) . "</td>";
                            echo "<td>" . htmlspecialchars($agendamento['tipo_pelicula']) . "</td>";
                            echo "<td>" . htmlspecialchars($agendamento['concessionaria_nome']) . "</td>";
                            echo "<td>" . htmlspecialchars($agendamento['aplicador']) . "</td>";
                            echo "<td>" . htmlspecialchars($agendamento['servico']) . "</td>";
                            echo "<td>" . htmlspecialchars($servicos_agregados_str) . "</td>";
                            echo "<td>" . htmlspecialchars($agendamento['status']) . "</td>";
                            echo "<td>" . htmlspecialchars($agendamento['observacoes']) . "</td>";
                            echo "<td>";
                            // Botões de Ação
                            echo "<a href='editar_agendamento.php?id=" . htmlspecialchars($agendamento['id']) . "' class='text-blue-600 hover:text-blue-900 mr-2'>Editar</a>"; // Botão de Edição
                            if ($agendamento['status'] == 'Agendado' || $agendamento['status'] == 'Reagendado') {
                                echo "<a href='agenda_aplicadores.php?" . http_build_query(array_merge($_GET, ['action' => 'change_status', 'id' => $agendamento['id'], 'new_status' => 'Concluído'])) . "' class='text-green-600 hover:text-green-900 mr-2'>Concluir</a>";
                                echo "<a href='agenda_aplicadores.php?" . http_build_query(array_merge($_GET, ['action' => 'change_status', 'id' => $agendamento['id'], 'new_status' => 'Cancelado'])) . "' class='text-red-600 hover:text-red-900'>Cancelar</a>";
                            } else if ($agendamento['status'] == 'Concluído' || $agendamento['status'] == 'Cancelado') {
                                echo "<span class='text-gray-500'>-</span>"; // Nenhuma ação de status se já estiver concluído/cancelado
                            }
                            echo "</td>";
                            echo "</tr>";
                        }
                        mysqli_free_result($result_agendamentos);
                    } else {
                        echo "<tr><td colspan='10' class='text-center py-4'>Nenhum agendamento encontrado para os filtros selecionados.</td></tr>";
                    }
                    ?>
                </tbody>
            </table>
        </div>
    </div>

    <footer class="app-footer">
        <p>&copy; <?php echo date('Y'); ?> Sistema de Gestão de Estoque (SGE). Todos os direitos reservados.</p>
        <p>Desenvolvido por Dani "Emo" Roger</p>
        <?php
        // Exibe mensagens no rodapé, se houver
        if (isset($_GET['message'])) {
            $message = htmlspecialchars($_GET['message']);
            $type = isset($_GET['type']) ? htmlspecialchars($_GET['type']) : 'success';
            echo "<div class='message-box message-$type'>$message</div>";
        }
        ?>
    </footer>

</body>
</html>