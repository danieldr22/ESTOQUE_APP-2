<?php
// relatorio.php
// Esta página exibe diversos relatórios de estoque e movimentação.

require_once 'config.php'; // Inclui a conexão com o banco de dados

// Verifica a conexão com o banco de dados
if (!$link) {
    die("Erro: Conexão com o banco de dados não estabelecida em relatorio.php. Verifique config.php.");
}

// Determina o tipo de relatório a ser exibido
$report_type = $_GET['type'] ?? 'estoque_atual'; // Padrão: estoque_atual

// Captura o mês e ano para o relatório de saídas por concessionária
$selected_month = $_GET['month'] ?? date('m'); // Mês atual por padrão
$selected_year = $_GET['year'] ?? date('Y');   // Ano atual por padrão

// Captura o mês e ano E A CONCESSIONÁRIA para o relatório de saída total por produto
$filter_report_month = $_GET['filter_report_month'] ?? date('Y-m'); //
$filter_report_concessionaria_id = $_GET['filter_report_concessionaria_id'] ?? '';

// Filtro para o Relatório de Serviços Executados
$filter_lavagens_month = $_GET['filter_lavagens_month'] ?? date('Y-m');
$filter_lavagens_concessionaria_id = $_GET['filter_lavagens_concessionaria_id'] ?? ''; // ID da concessionária selecionada


// Mapeamento de números de mês para nomes (para exibição)
$months = [
    '01' => 'Janeiro', '02' => 'Fevereiro', '03' => 'Março', '04' => 'Abril',
    '05' => 'Maio', '06' => 'Junho', '07' => 'Julho', '08' => 'Agosto',
    '09' => 'Setembro', '10' => 'Outubro', '11' => 'Novembro', '12' => 'Dezembro'
];

// --- Lógica para buscar concessionárias para os dropdowns e dados ---
$concessionarias_for_report_filter = [];
$concessionaria_name_to_id_map = []; // Mapa reverso (nome maiúsculo da planilha -> ID local)
$sql_concessionarias_report_filter = "SELECT id, nome, local_csv_filename FROM concessionarias ORDER BY nome ASC"; // AGORA PEGA local_csv_filename TAMBÉM
$result_concessionarias_report_filter = mysqli_query($link, $sql_concessionarias_report_filter);
$all_concessionarias_data = []; // Para armazenar todos os dados da concessionária
if ($result_concessionarias_report_filter) {
    while ($row = mysqli_fetch_assoc($result_concessionarias_report_filter)) {
        $concessionarias_for_report_filter[$row['id']] = $row['nome'];
        $concessionaria_name_to_id_map[mb_strtoupper(trim($row['nome']))] = $row['id']; // Mapeia nome MAIÚSCULO para ID
        $all_concessionarias_data[$row['id']] = $row; // Guarda todos os dados por ID para fácil acesso
    }
    mysqli_free_result($result_concessionarias_report_filter);
}
?>
<!DOCTYPE html>
<html lang="pt-BR" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Relatórios de Estoque</title>
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

        html, body { /* Garante que HTML e Body ocupem a altura total */
            height: 100%;
            margin: 0;
            padding: 0;
            overflow-x: hidden; /* Evita scroll horizontal indesejado */
        }

        body {
            font-family: 'Inter', sans-serif;
            color: #333;
            display: flex;
            flex-direction: column;
            min-height: 100vh;
            background-color: #f3f4f6; /* Fallback */
            background-image: url('imagens/background_art.jpg'); /* Substitua pelo caminho da sua imagem */
            background-size: cover;
            background-repeat: no-repeat;
            background-position: center center;
            background-attachment: fixed;
            padding-top: 150px; /* Espaço para o cabeçalho fixo, ajuste se necessário */
        }
        
        .main-header {
            position: fixed; /* Fixa o cabeçalho no topo */
            top: 0;
            left: 0;
            width: 100%;
            z-index: 1000; /* Garante que fique acima de outros elementos */
            background-color: rgba(255, 255, 255, 0.1); /* Fundo sutilmente transparente */
            backdrop-filter: blur(8px); /* Efeito de vidro fosco no cabeçalho */
            -webkit-backdrop-filter: blur(8px); /* Compatibilidade Safari */
            padding: 1rem 0; /* Padding interno do cabeçalho */
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1); /* Sombra para o cabeçalho flutuante */
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
        }

        .container {
            max-width: 960px; /* Alinhado com o index para consistência */
            margin: 0 auto;
            padding: 3rem;
            background-color: #ffffff; /* Fundo branco puro */
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
        h2 { /* Títulos dentro dos containers (ex: "Relatório de Estoque Atual") */
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

        /* Estilos específicos para páginas de relatório */
        .nav-button-reports {
            display: inline-block; /* Para que fiquem lado a lado */
            padding: 0.75rem 1.25rem; /* Padding reduzido para botões de sub-menu de relatório */
            margin: 0.5rem;
            background-color: #a0aec0; /* Cinza mais claro para sub-navegação */
            color: white;
            text-align: center;
            border-radius: 9999px; /* Pílula */
            font-weight: 600;
            font-size: 0.9rem;
            transition: all 0.3s ease-in-out;
            text-decoration: none;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        }
        .nav-button-reports:hover {
            background-color: #718096; /* Cinza mais escuro no hover */
            transform: translateY(-1px);
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.15);
        }
        .nav-button-reports.active {
            background-color: var(--color-primary-indigo); /* Cor primária se ativo */
            box-shadow: 0 3px 8px rgba(0, 0, 0, 0.2);
            transform: translateY(-1px);
        }
        .form-control {
            display: block;
            width: 100%;
            padding: 0.5rem 0.75rem;
            font-size: 1rem;
            line-height: 1.5;
            color: #495057;
            background-color: #fff;
            background-clip: padding-box;
            border: 1px solid #ced4da;
            border-radius: 0.25rem;
            transition: border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out;
        }
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

        <h1 class="text-3xl font-bold text-center mb-8">Relatórios de Estoque</h1> <div class="flex justify-center flex-wrap gap-2 mb-8">
            <a href="relatorio.php?type=estoque_atual" class="nav-button-reports <?php echo ($report_type == 'estoque_atual') ? 'active' : ''; ?>">Estoque Atual</a>
            <a href="relatorio.php?type=saidas_concessionaria" class="nav-button-reports <?php echo ($report_type == 'saidas_concessionaria') ? 'active' : ''; ?>">Saídas por Concessionária (Mensal)</a>
            <a href="relatorio.php?type=entradas_geral" class="nav-button-reports <?php echo ($report_type == 'entradas_geral') ? 'active' : ''; ?>">Entradas Gerais</a>
            <a href="relatorio.php?type=saida_total_por_produto" class="nav-button-reports <?php echo ($report_type == 'saida_total_por_produto') ? 'active' : ''; ?>">Saída Total por Produto (Mensal)</a>
            <a href="relatorio.php?type=relatorio_lavagens" class="nav-button-reports <?php echo ($report_type == 'relatorio_lavagens') ? 'active' : ''; ?>">Serviços Executados</a>
        </div>

        <div class="report-content">
            <?php
            switch ($report_type) {
                case 'estoque_atual':
                    ?>
                    <h2 class="text-2xl font-semibold mb-4 text-gray-700">Relatório de Estoque Atual</h2>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="table-header">
                                <tr>
                                    <th>ID</th>
                                    <th>Nome do Produto</th>
                                    <th>Unidade</th>
                                    <th>Preço Custo (R$)</th>
                                    <th>Estoque Atual</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200">
                                <?php
                                $sql_estoque = "SELECT id, nome, unidade_medida, preco_custo, quantidade_estoque FROM produtos ORDER BY nome ASC";
                                $result_estoque = mysqli_query($link, $sql_estoque);

                                if ($result_estoque && mysqli_num_rows($result_estoque) > 0) {
                                    while ($produto = mysqli_fetch_assoc($result_estoque)) {
                                        echo "<tr class='table-row'>";
                                        echo "<td>" . htmlspecialchars($produto['id']) . "</td>";
                                        echo "<td>" . htmlspecialchars($produto['nome']) . "</td>";
                                        echo "<td>" . htmlspecialchars($produto['unidade_medida']) . "</td>";
                                        echo "<td>R$ " . number_format($produto['preco_custo'], 2, ',', '.') . "</td>";
                                        echo "<td>" . htmlspecialchars($produto['quantidade_estoque']) . "</td>";
                                        echo "</tr>";
                                    }
                                    mysqli_free_result($result_estoque);
                                } else {
                                    echo "<tr><td colspan='5' class='text-center py-4'>Nenhum produto cadastrado ou sem estoque.</td></tr>";
                                }
                                ?>
                            </tbody>
                        </table>
                    </div>
                    <?php
                    break;

                case 'saidas_concessionaria':
                    ?>
                    <h2 class="text-2xl font-semibold mb-4 text-gray-700">Relatório de Saídas por Concessionária (Mensal)</h2>

                    <form method="GET" action="relatorio.php" class="mb-6 bg-gray-50 p-4 rounded-lg shadow-sm">
                        <input type="hidden" name="type" value="saidas_concessionaria">
                        <div class="flex flex-wrap -mx-2 mb-4">
                            <div class="w-full md:w-1/2 px-2 mb-4 md:mb-0">
                                <label for="month" class="block text-sm font-medium text-gray-700 mb-1">Mês:</label>
                                <select id="month" name="month" class="form-control" onchange="this.form.submit()">
                                    <?php
                                    foreach ($months as $num => $name) {
                                        $selected = ($selected_month == $num) ? 'selected' : '';
                                        echo "<option value='{$num}' {$selected}>{$name}</option>";
                                    }
                                    ?>
                                </select>
                            </div>
                            <div class="w-full md:w-1/2 px-2">
                                <label for="year" class="block text-sm font-medium text-gray-700 mb-1">Ano:</label>
                                <select id="year" name="year" class="form-control" onchange="this.form.submit()">
                                    <?php
                                    $current_year_br = (int)date('Y', strtotime('now -3 hours')); // Current year in Fortaleza (GMT-3)
                                    for ($y = (int)date('Y'); $y >= (int)date('Y') - 5; $y--) { // Last 5 years
                                        $selected = ($selected_year == $y) ? 'selected' : '';
                                        echo "<option value='{$y}' {$selected}>{$y}</option>";
                                    }
                                    ?>
                                </select>
                            </div>
                        </div>
                    </form>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="table-header">
                                <tr>
                                    <th>Mês/Ano</th>
                                    <th>Concessionária</th>
                                    <th>Total Itens Saída</th>
                                    <th>Total Custo Mensal (R$)</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200">
                                <?php
                                // SQL para somar o custo total de saídas por concessionária por mês/ano, filtrado pelo mês e ano selecionados
                                $sql_saidas_mensal = "SELECT
                                                          DATE_FORMAT(s.data_saida, '%Y-%m') AS ano_mes,
                                                          DATE_FORMAT(s.data_saida, '%m/%Y') AS mes_ano_formatado,
                                                          c.nome AS concessionaria_nome,
                                                          SUM(s.quantidade) AS total_itens_saida,
                                                          SUM(s.preco_custo_total) AS total_custo_mensal
                                                      FROM
                                                          saidas s
                                                      JOIN
                                                          concessionarias c ON s.concessionaria_id = c.id
                                                      WHERE
                                                          DATE_FORMAT(s.data_saida, '%Y-%m') = ?
                                                      GROUP BY
                                                          ano_mes, c.nome
                                                      ORDER BY
                                                          ano_mes DESC, c.nome ASC";

                                // Prepara a string do ano e mês para a consulta
                                $date_param = $selected_year . '-' . $selected_month;

                                $stmt = mysqli_prepare($link, $sql_saidas_mensal);
                                mysqli_stmt_bind_param($stmt, 's', $date_param);
                                mysqli_stmt_execute($stmt);
                                $result_saidas_mensal = mysqli_stmt_get_result($stmt);

                                if ($result_saidas_mensal && mysqli_num_rows($result_saidas_mensal) > 0) {
                                    $grand_total_custo = 0; // Total geral para o mês/ano selecionado

                                    while ($saida = mysqli_fetch_assoc($result_saidas_mensal)) {
                                        $grand_total_custo += (float)$saida['total_custo_mensal'];

                                        echo "<tr class='table-row'>";
                                        echo "<td>" . htmlspecialchars($saida['mes_ano_formatado']) . "</td>";
                                        echo "<td>" . htmlspecialchars($saida['concessionaria_nome']) . "</td>";
                                        echo "<td>" . htmlspecialchars($saida['total_itens_saida']) . "</td>";
                                        echo "<td>R$ " . number_format($saida['total_custo_mensal'], 2, ',', '.') . "</td>";
                                        echo "</tr>";
                                    }
                                    mysqli_free_result($result_saidas_mensal);
                                    mysqli_stmt_close($stmt);

                                    // Exibe o total geral para o mês selecionado
                                    echo "<tr class='table-row summary-row bg-indigo-100'>";
                                    echo "<td colspan='3' class='text-right pr-4 text-indigo-800'>TOTAL DE CUSTO PARA " . htmlspecialchars($months[$selected_month]) . "/" . htmlspecialchars($selected_year) . ":</td>";
                                    echo "<td class='text-indigo-800'>R$ " . number_format($grand_total_custo, 2, ',', '.') . "</td>";
                                    echo "</tr>";

                                } else {
                                    echo "<tr><td colspan='4' class='text-center py-4'>Nenhuma saída registrada para " . htmlspecialchars($months[$selected_month]) . "/" . htmlspecialchars($selected_year) . ".</td></tr>";
                                }
                                ?>
                            </tbody>
                        </table>
                    </div>
                    <?php
                    break;

                case 'entradas_geral':
                    ?>
                    <h2 class="text-2xl font-semibold mb-4 text-gray-700">Relatório de Entradas Generales</h2>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="table-header">
                                <tr>
                                    <th>ID Entrada</th>
                                    <th>Produto</th>
                                    <th>Quantidade</th>
                                    <th>Data Entrada</th>
                                    <th>Data Registro</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200">
                                <?php
                                $sql_entradas_geral = "SELECT e.id, e.quantidade, e.data_entrada, e.data_registro, p.nome, p.unidade_medida
                                                       FROM entradas e
                                                       JOIN produtos p ON e.produto_id = p.id
                                                       ORDER BY e.data_entrada DESC, e.data_registro DESC";

                                $result_entradas_geral = mysqli_query($link, $sql_entradas_geral);

                                if ($result_entradas_geral && mysqli_num_rows($result_entradas_geral) > 0) {
                                    while ($entrada = mysqli_fetch_assoc($result_entradas_geral)) {
                                        echo "<tr class='table-row'>";
                                        echo "<td>" . htmlspecialchars($entrada['id']) . "</td>";
                                        echo "<td>" . htmlspecialchars($entrada['nome']) . " (" . htmlspecialchars($entrada['unidade_medida']) . ")</td>";
                                        echo "<td>" . htmlspecialchars(date('d/m/Y', strtotime($entrada['data_entrada']))) . "</td>";
                                        echo "<td>" . htmlspecialchars(date('d/m/Y H:i', strtotime($entrada['data_registro']))) . "</td>";
                                        echo "</tr>";
                                    }
                                    mysqli_free_result($result_entradas_geral);
                                } else {
                                    echo "<tr><td colspan='5' class='text-center py-4'>Nenhuma entrada registrada.</td></tr>";
                                }
                                ?>
                            </tbody>
                        </table>
                    </div>
                    <?php
                    break;

                case 'saida_total_por_produto':
                    ?>
                    <h2 class="text-2xl font-semibold mb-4 text-gray-700">Relatório de Saída Total de Produtos por Mês</h2>

                    <form method="GET" action="relatorio.php" class="mb-6 bg-gray-50 p-4 rounded-lg shadow-sm">
                        <input type="hidden" name="type" value="saida_total_por_produto">
                        <div class="flex flex-wrap -mx-2 mb-4">
                            <div class="w-full md:w-1/3 px-2 mb-4 md:mb-0">
                                <label for="report_month_year" class="block text-sm font-medium text-gray-700 mb-1">Mês/Ano:</label>
                                <select id="report_month_year" name="filter_report_month" class="form-control" onchange="this.form.submit()">
                                    <option value="">Todos os meses</option>
                                    <?php
                                    // Pega todos os meses/anos de saída para popular o filtro
                                    $all_months_years = [];
                                    $sql_all_months_years = "SELECT DISTINCT DATE_FORMAT(data_saida, '%Y-%m') AS month_year_raw,
                                                                    DATE_FORMAT(data_saida, '%m/%Y') AS month_year_formatted
                                                            FROM saidas
                                                            ORDER BY month_year_raw DESC";
                                    $result_all_months_years = mysqli_query($link, $sql_all_months_years);
                                    if ($result_all_months_years) {
                                        while ($row = mysqli_fetch_assoc($result_all_months_years)) {
                                            $all_months_years[$row['month_year_raw']] = $row['month_year_formatted'];
                                        }
                                        mysqli_free_result($result_all_months_years);
                                    }
                                    ?>
                                    <?php foreach ($all_months_years as $raw => $formatted): ?>
                                        <option value="<?php echo htmlspecialchars($raw); ?>" <?php echo ($filter_report_month == $raw) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($formatted); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="w-full md:w-1/3 px-2 mb-4 md:mb-0">
                                <label for="filter_report_concessionaria_id" class="block text-sm font-medium text-gray-700 mb-1">Concessionária:</label>
                                <select id="filter_report_concessionaria_id" name="filter_report_concessionaria_id" class="form-control" onchange="this.form.submit()">
                                    <option value="">Todas</option>
                                    <?php foreach ($concessionarias_for_report_filter as $id => $nome): ?>
                                        <option value="<?php echo htmlspecialchars($id); ?>" <?php echo ($filter_report_concessionaria_id == $id) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($nome); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="w-full md:w-1/3 px-2 flex items-end">
                                <button type="submit" class="btn-primary w-full">Aplicar Filtros</button>
                            </div>
                        </div>
                    </form>

                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="table-header">
                                <tr>
                                    <th>Produto</th>
                                    <th>Unidade</th>
                                    <th>Total Saída (Qtd)</th>
                                    <th>Custo Total dos Produtos (R$)</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200">
                                <?php
                                $sql_saida_total_por_produto = "SELECT
                                                                    p.nome AS produto_nome,
                                                                    p.unidade_medida,
                                                                    SUM(s.quantidade) AS total_quantidade_saida,
                                                                    SUM(s.preco_custo_total) AS total_custo_produto
                                                                FROM
                                                                    saidas s
                                                                JOIN
                                                                    produtos p ON s.produto_id = p.id
                                                                WHERE 1=1 "; // Inicia a condição WHERE com 1=1 para facilitar adição de AND

                                // Adiciona filtro de mês/ano
                                if (!empty($filter_report_month)) {
                                    $sql_saida_total_por_produto .= " AND DATE_FORMAT(s.data_saida, '%Y-%m') = '" . mysqli_real_escape_string($link, $filter_report_month) . "'";
                                }

                                // Adiciona filtro de concessionária
                                if (!empty($filter_report_concessionaria_id)) {
                                    $sql_saida_total_por_produto .= " AND s.concessionaria_id = " . intval($filter_report_concessionaria_id);
                                }

                                $sql_saida_total_por_produto .= " GROUP BY
                                                                    p.id, p.nome, p.unidade_medida
                                                                ORDER BY
                                                                    p.nome ASC";

                                $result_saida_total_produto = mysqli_query($link, $sql_saida_total_por_produto);

                                $overall_total_quantity = 0;
                                $overall_total_cost = 0;

                                if ($result_saida_total_produto && mysqli_num_rows($result_saida_total_produto) > 0) {
                                    while ($data = mysqli_fetch_assoc($result_saida_total_produto)) {
                                        $overall_total_quantity += (float)$data['total_quantidade_saida'];
                                        $overall_total_cost += (float)$data['total_custo_produto'];
                                        echo "<tr class='table-row'>";
                                        echo "<td>" . htmlspecialchars($data['produto_nome']) . "</td>";
                                        echo "<td>" . htmlspecialchars($data['unidade_medida']) . "</td>";
                                        echo "<td>" . htmlspecialchars($data['total_quantidade_saida']) . "</td>";
                                        echo "<td>R$ " . number_format($data['total_custo_produto'], 2, ',', '.') . "</td>";
                                        echo "</tr>";
                                    }
                                    mysqli_free_result($result_saida_total_produto);
                                } else {
                                    echo "<tr><td colspan='4' class='text-center py-4'>Nenhuma saída de produto registrada para o período e filtros selecionados.</td></tr>";
                                }

                                if (!empty($overall_total_cost) || !empty($overall_total_quantity)) {
                                    echo "<tr class='table-row summary-row bg-indigo-100'>";
                                    echo "<td colspan='2' class='text-right pr-4 text-indigo-800'>TOTAL GERAL:</td>";
                                    echo "<td class='text-indigo-800'>" . htmlspecialchars($overall_total_quantity) . "</td>";
                                    echo "<td class='text-indigo-800'>R$ " . number_format($overall_total_cost, 2, ',', '.') . "</td>";
                                    echo "</tr>";
                                }
                                ?>
                            </tbody>
                        </table>
                    </div>
                    <?php
                    break;

                // Relatório de Serviços Executados (Dados do Google Docs / LOCAL)
                case 'relatorio_lavagens':
                    ?>
                    <h2 class="text-2xl font-semibold mb-4 text-gray-700">Relatório de Serviços Executados (Dados Locais)</h2>

                    <form method="GET" action="relatorio.php" class="mb-6 bg-gray-50 p-4 rounded-lg shadow-sm">
                        <input type="hidden" name="type" value="relatorio_lavagens">
                        <div class="flex flex-wrap -mx-2 mb-4">
                            <div class="w-full md:w-1/2 px-2 mb-4 md:mb-0">
                                <label for="filter_lavagens_concessionaria_id" class="block text-sm font-medium text-gray-700 mb-1">Concessionária:</label>
                                <select id="filter_lavagens_concessionaria_id" name="filter_lavagens_concessionaria_id" class="form-control" onchange="this.form.submit()">
                                    <option value="">Selecione uma Concessionária</option>
                                    <option value="all" <?php echo ($filter_lavagens_concessionaria_id == 'all') ? 'selected' : ''; ?>>Todas as Concessionárias</option>
                                    <?php foreach ($concessionarias_for_report_filter as $id => $nome): ?>
                                        <option value="<?php echo htmlspecialchars($id); ?>" <?php echo ($filter_lavagens_concessionaria_id == $id) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($nome); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <?php if (!empty($filter_lavagens_concessionaria_id)): ?>
                                <div class="w-full md:w-1/2 px-2 mb-4 md:mb-0">
                                    <label for="filter_lavagens_month" class="block text-sm font-medium text-gray-700 mb-1">Mês/Ano:</label>
                                    <select id="filter_lavagens_month" name="filter_lavagens_month" class="form-control" onchange="this.form.submit()">
                                        <option value="">Todos os meses</option>
                                        <?php
                                        // Popula com meses/anos dos últimos 12 meses, por exemplo
                                        for ($i = 0; $i < 12; $i++) {
                                            $month_year_value = date('Y-m', strtotime("-$i months"));
                                            $month_year_label = date('m/Y', strtotime("-$i months"));
                                            $selected = ($filter_lavagens_month == $month_year_value) ? 'selected' : '';
                                            echo "<option value='" . htmlspecialchars($month_year_value) . "' " . $selected . ">" . htmlspecialchars($month_year_label) . "</option>";
                                        }
                                        ?>
                                    </select>
                                </div>
                            <?php endif; ?>
                            </div>
                    </form>

                    <?php
                    // APENAS EXIBE O RELATÓRIO SE UMA CONCESSIONÁRIA FOR SELECIONADA
                    if (empty($filter_lavagens_concessionaria_id)) {
                        echo "<p class='text-center py-6 text-gray-700 text-lg'>Por favor, selecione uma concessionária (ou 'Todas as Concessionárias') para visualizar o relatório de serviços executados.</p>";
                    } else {
                        $concessionarias_to_display = [];
                        if ($filter_lavagens_concessionaria_id == 'all') {
                            $concessionarias_to_display = $all_concessionarias_data; // Todas as concessionárias
                        } elseif (isset($all_concessionarias_data[$filter_lavagens_concessionaria_id])) {
                            $concessionarias_to_display[$filter_lavagens_concessionaria_id] = $all_concessionarias_data[$filter_lavagens_concessionaria_id]; // Apenas a selecionada
                        }

                        if (empty($concessionarias_to_display)) {
                            echo "<p class='text-center py-6 text-gray-700 text-lg'>Nenhuma concessionária encontrada com o ID selecionado.</p>";
                        } else {
                            $overall_total_servicos_executados = 0; // Totalizador geral para "Todas as Concessionárias"

                            foreach ($concessionarias_to_display as $concessionaria_id_loop => $concessionaria_data) {
                                $concessionaria_nome_atual = htmlspecialchars($concessionaria_data['nome']);
                                $local_csv_filename = $concessionaria_data['local_csv_filename'];

                                echo "<h3 class='text-xl font-semibold mb-2 mt-6 text-gray-700'>Relatório para ".$concessionaria_nome_atual."</h3>";

                                if (empty($local_csv_filename)) {
                                    echo "<p class='text-center py-4 message-error'>Nenhum arquivo CSV local configurado para esta concessionária: ".$concessionaria_nome_atual.".</p>";
                                } else {
                                    $local_csv_filepath = __DIR__ . '/data/lavagens/' . $local_csv_filename;

                                    $data_servicos = []; 
                                    $error_servicos = ''; 
                                    $total_servicos_concessionaria = 0; // Contador por concessionária

                                    if (file_exists($local_csv_filepath) && ($handle = fopen($local_csv_filepath, "r")) !== FALSE) {
                                        $header_row = fgetcsv($handle); // Lê a primeira linha (cabeçalho)

                                        // DEBUG: Exibir cabeçalhos lidos do CSV
                                        // echo "<p style='font-size: 0.8em; color: gray;'>DEBUG: Cabeçalhos lidos (RAW): '" . htmlspecialchars(implode("', '", $header_row)) . "'</p>";

                                        $col_map = []; // Mapeamento dinâmico
                                        if ($header_row) {
                                            foreach ($header_row as $index => $col_name) {
                                                $normalized_col_name = mb_strtoupper(trim($col_name));
                                                
                                                // Mapeamento flexível para nomes de coluna internos
                                                if ($normalized_col_name === 'CARIMBO DE DATA/HORA') {
                                                    $col_map['carimbo_data_hora'] = $index;
                                                } elseif ($normalized_col_name === 'TIPO DE SERVIÇO' || $normalized_col_name === 'TIPO DE SERVICO' || $normalized_col_name === 'TIPO DE SERVI') {
                                                    $col_map['tipo_servico'] = $index;
                                                } elseif ($normalized_col_name === 'CHASSI/PLACA' || $normalized_col_name === 'CHASSI PLACA') {
                                                    $col_map['chassi_placa'] = $index;
                                                } elseif ($normalized_col_name === 'DATA DA REALIZAÇÃO DO SERVIÇO' || $normalized_col_name === 'DATA DA REALIZACAO DO SERVICO' || $normalized_col_name === 'DATA') {
                                                    $col_map['data_realizacao'] = $index;
                                                } elseif ($normalized_col_name === 'OBSERVAÇÕES' || $normalized_col_name === 'OBSERVACOES') {
                                                    $col_map['observacoes'] = $index;
                                                } elseif ($normalized_col_name === 'SETOR') { // SETOR AGORA SEMPRE LIDO
                                                    $col_map['setor'] = $index;
                                                } elseif ($normalized_col_name === 'MÊS' || $normalized_col_name === 'MES') {
                                                    $col_map['mes_planilha'] = $index;
                                                }
                                                // Se houver outras colunas na planilha que você queira exibir, adicione-as aqui.
                                            }
                                        }

                                        // Define as chaves internas que são esperadas no $col_map para validação
                                        $required_internal_cols = [
                                            'carimbo_data_hora', 'tipo_servico', 'chassi_placa',
                                            'data_realizacao', 'observacoes', 'setor' // SETOR é requerido para leitura/exibição
                                        ];
                                        $missing_cols_display = [];
                                        foreach ($required_internal_cols as $req_internal_col) {
                                            // Verifica se a chave interna tem um índice mapeado E se esse índice não é -1
                                            if (!isset($col_map[$req_internal_col]) || $col_map[$req_internal_col] === -1) {
                                                $missing_cols_display[] = $req_internal_col;
                                            }
                                        }


                                        if (!empty($missing_cols_display)) {
                                            $error_servicos = "Erro: Colunas essenciais faltando ou nomeadas incorretamente no arquivo CSV local de '".$concessionaria_nome_atual."': " . implode(', ', $missing_cols_display) . ". Verifique os cabeçalhos EXATOS na sua planilha e no CSV baixado.";
                                            fclose($handle);
                                        } else {
                                            while (($row = fgetcsv($handle)) !== FALSE) {
                                                // Garante que a linha tem dados suficientes para todas as colunas mapeadas
                                                $max_col_index = -1;
                                                foreach ($col_map as $mapped_index) { // Ajuste aqui para iterar sobre valores
                                                    $max_col_index = max($max_col_index, $mapped_index);
                                                }
                                                if (count($row) < $max_col_index + 1) continue; // Pula linhas incompletas/vazias

                                                $data_realizacao_raw = trim($row[$col_map['data_realizacao']] ?? '');
                                                
                                                $data_realizacao_parsed = null;
                                                $formats = ['d/m/Y', 'Y-m-d', 'm/d/Y'];
                                                foreach ($formats as $format) {
                                                    $temp_date = DateTime::createFromFormat($format, $data_realizacao_raw);
                                                    if ($temp_date && $temp_date->format($format) === $data_realizacao_raw) {
                                                        $data_realizacao_parsed = $temp_date;
                                                        break;
                                                    }
                                                }

                                                // Filtra por mês/ano se o filtro estiver aplicado
                                                if ($data_realizacao_parsed && !empty($filter_lavagens_month)) {
                                                    if ($data_realizacao_parsed->format('Y-m') !== $filter_lavagens_month) {
                                                        continue;
                                                    }
                                                }
                                                
                                                // --- REMOVIDA AQUI: Lógica de filtrar pelo SETOR, pois ele deve ser lido independentemente. ---
                                                // A premissa é que o arquivo CSV já é da concessionária selecionada pelo filtro de concessionária principal.
                                                // O SETOR é apenas lido para exibição.
                                                // (DEBUG original removido)
                                                
                                                if ($data_realizacao_parsed) { // Somente adiciona se a data foi parseada com sucesso
                                                    $data_servicos[] = [
                                                        'carimbo' => $row[$col_map['carimbo_data_hora']] ?? '',
                                                        'tipo_servico' => $row[$col_map['tipo_servico']] ?? '',
                                                        'chassi_placa' => $row[$col_map['chassi_placa']] ?? '',
                                                        'data_realizacao' => $data_realizacao_parsed->format('d/m/Y'),
                                                        'setor' => $row[$col_map['setor']] ?? '',
                                                        'observacoes' => $row[$col_map['observacoes']] ?? '',
                                                    ];
                                                    $total_servicos_concessionaria++; // Incrementa o contador para esta concessionária
                                                }
                                            }
                                            fclose($handle); // Fecha o arquivo CSV
                                            $overall_total_servicos_executados += $total_servicos_concessionaria; // Adiciona ao total geral
                                        }
                                    } else {
                                        $error_servicos = "Erro: Não foi possível abrir o arquivo CSV local para '".$concessionaria_nome_atual."'. Caminho: " . htmlspecialchars($local_csv_filepath) . ". Verifique se o arquivo existe e as permissões de leitura. Se a planilha não tem dados, ela pode gerar um CSV vazio/inválido.";
                                    }
                                    
                                    if (!empty($error_servicos)) {
                                        echo "<p class='text-center py-4 message-error'>" . htmlspecialchars($error_servicos) . "</p>";
                                        error_log("Erro no relatório de serviços executados para " . $concessionaria_nome_atual . ": " . $error_servicos);
                                    } elseif (empty($data_servicos)) {
                                        echo "<p class='text-center py-4'>Nenhum registro de serviço executado encontrado para esta concessionária e período no arquivo local.</p>";
                                    } else {
                                        ?>
                                        <div class="overflow-x-auto mb-8">
                                            <table class="min-w-full divide-y divide-gray-200">
                                                <thead class="table-header">
                                                    <tr>
                                                        <th>Carimbo de data/hora</th>
                                                        <th>Tipo de Serviço</th>
                                                        <th>Chassi/Placa</th>
                                                        <th>Data Realização</th>
                                                        <th>Setor</th>
                                                        <th>Observações</th>
                                                    </tr>
                                                </thead>
                                                <tbody class="divide-y divide-gray-200">
                                                    <?php
                                                    foreach ($data_servicos as $servico) {
                                                        echo "<tr class='table-row'>";
                                                        echo "<td>" . htmlspecialchars($servico['carimbo']) . "</td>";
                                                        echo "<td>" . htmlspecialchars($servico['tipo_servico']) . "</td>";
                                                        echo "<td>" . htmlspecialchars($servico['chassi_placa']) . "</td>";
                                                        echo "<td>" . htmlspecialchars($servico['data_realizacao']) . "</td>";
                                                        echo "<td>" . htmlspecialchars($servico['setor']) . "</td>";
                                                        echo "<td>" . htmlspecialchars($servico['observacoes']) . "</td>";
                                                        echo "</tr>";
                                                    }
                                                    ?>
                                                    <tr class='table-row summary-row bg-gray-200'>
                                                        <td colspan='5' class='text-right pr-4 font-bold'>TOTAL DE SERVIÇOS EXIBIDOS PARA <?php echo $concessionaria_nome_atual; ?>:</td>
                                                        <td class='font-bold'><?php echo htmlspecialchars($total_servicos_concessionaria); ?></td>
                                                    </tr>
                                                </tbody>
                                            </table>
                                        </div>
                                        <?php
                                    } // Fim do else de empty($data_servicos)
                                } // Fim do else (concessionária tem local_csv_filename)
                            } // Fim do foreach ($concessionarias_to_display)
                            // Exibe o total GERAL se 'Todas as Concessionárias' foi selecionado
                            if ($filter_lavagens_concessionaria_id == 'all') {
                                echo "<h3 class='text-xl font-semibold mb-2 mt-6 text-gray-700 text-center'>TOTAL GERAL DE SERVIÇOS EXECUTADOS: <span class='text-indigo-600'>" . htmlspecialchars($overall_total_servicos_executados) . "</span></h3>";
                            }
                        }} // Fim do else (se concessionária foi selecionada e existe)
                    ?>
                    <?php
                    break;

                default:
                    echo "<p class='text-center py-4 text-gray-600'>Selecione um tipo de relatório para visualizar.</p>";
                    break;
            }
            ?>
        </div>
    </div>
    <footer class="app-footer">
        <p>&copy; <?php echo date('Y'); ?> Sistema de Gestão de Estoque (SGE). Todos os direitos reservados.</p>
        <p>Desenvolvido por Dani "Emo" Roger</p>
        <?php
        if (isset($_GET['message'])) {
            $message = htmlspecialchars($_GET['message']);
            $type = isset($_GET['type']) ? htmlspecialchars($row['type']) : 'success'; // <<< POSSÍVEL ERRO AQUI: $row['type'] deveria ser $_GET['type']
            echo "<div class='message-box message-$type'>$message</div>";
        }
        ?>
    </footer>
</body>
</html>