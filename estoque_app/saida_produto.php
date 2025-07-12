<?php
// saida_produto.php
require_once 'config.php';

// Verificação de conexão (para depuração, pode ser removido em produção)
if (!$link) {
    die("Erro: Conexão com o banco de dados não estabelecida em saida_produto.php. Verifique config.php.");
}

// --- Lógica para filtros ---
$selected_concessionaria_id = $_GET['filter_concessionaria_id'] ?? '';
$selected_month_year = $_GET['filter_month_year'] ?? ''; // Format:YYYY-MM
// FILTRO: Semana e Ano - O formato RAW é YYYYWW
$selected_week_year_raw = $_GET['filter_week_year'] ?? ''; // Ex: 2024-01, 2024-52


// Prepare filter conditions for SQL
$filter_conditions = [];
if (!empty($selected_concessionaria_id)) {
    $filter_conditions[] = "s.concessionaria_id = " . intval($selected_concessionaria_id);
}

// Para o filtro de mês/ano, ele tem prioridade sobre o filtro de semana, se ambos forem selecionados
if (!empty($selected_month_year)) {
    $filter_conditions[] = "DATE_FORMAT(s.data_saida, '%Y-%m') = '" . mysqli_real_escape_string($link, $selected_month_year) . "'";
} elseif (!empty($selected_week_year_raw)) { // Se não tiver filtro de mês/ano, usa o de semana
    list($year_filter, $week_filter) = explode('-', $selected_week_year_raw); // Corrigido para YYYYWW
    // WEEK(date, 0) para semana começando no domingo. Certifique-se que o modo 0 é o que você quer.
    $filter_conditions[] = "YEAR(s.data_saida) = " . intval($year_filter) . " AND WEEK(s.data_saida, 0) = " . intval($week_filter);
}


$where_clause = '';
if (!empty($filter_conditions)) {
    $where_clause = " WHERE " . implode(' AND ', $filter_conditions);
}

// --- Preencher dropdown de Meses/Anos dinamicamente ---
$months_years = [];
$sql_months_years = "SELECT DISTINCT DATE_FORMAT(data_saida, '%Y-%m') AS month_year_raw,
                                     DATE_FORMAT(data_saida, '%m/%Y') AS month_year_formatted
                      FROM saidas
                      ORDER BY month_year_raw DESC";
$result_months_years = mysqli_query($link, $sql_months_years);
if ($result_months_years) {
    while ($row = mysqli_fetch_assoc($result_months_years)) {
        $months_years[$row['month_year_raw']] = $row['month_year_formatted'];
    }
    mysqli_free_result($result_months_years);
}


// --- Preencher dropdown de Semanas/Anos dinamicamente ---
$weeks_years = [];
$sql_weeks_years = "SELECT DISTINCT YEARWEEK(data_saida, 0) AS year_week_raw,
                                   MIN(data_saida) AS start_of_week_date
                    FROM saidas
                    GROUP BY year_week_raw
                    ORDER BY year_week_raw DESC";
$result_weeks_years = mysqli_query($link, $sql_weeks_years);
if ($result_weeks_years) {
    while ($row = mysqli_fetch_assoc($result_weeks_years)) {
        $start_date_obj = new DateTime($row['start_of_week_date']);
        $day_of_week = (int)$start_date_obj->format('w'); // 0 (para domingo) a 6 (para sábado)
        $start_of_week_display = (clone $start_date_obj)->modify('-' . $day_of_week . ' days');
        $end_of_week_display = (clone $start_of_week_display)->modify('+6 days');

        $formatted_week_label = "Semana " . $start_of_week_display->format('W') . " (" . $start_of_week_display->format('d/m/Y') . " - " . $end_of_week_display->format('d/m/Y') . ")";
        $weeks_years[$row['year_week_raw']] = $formatted_week_label;
    }
    mysqli_free_result($result_weeks_years);
}


// --- Preencher dropdowns de Concessionárias (para o formulário de adição e para o filtro) ---
$concessionarias_for_dropdowns = [];
$sql_concessionarias_all = "SELECT id, nome FROM concessionarias ORDER BY nome ASC";
$result_concessionarias_all = mysqli_query($link, $sql_concessionarias_all);

if ($result_concessionarias_all) {
    while ($row = mysqli_fetch_assoc($result_concessionarias_all)) {
        $concessionarias_for_dropdowns[$row['id']] = $row['nome'];
    }
    mysqli_free_result($result_concessionarias_all);
} else {
    error_log("Erro ao buscar concessionárias: " . mysqli_error($link));
    $concessionarias_for_dropdowns = [];
}

?>
<!DOCTYPE html>
<html lang="pt-BR" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Saída de Produto</title>
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
        h1 { /* Este H1 é o do cabeçalho fixo */
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
            gap: 1rem;
            width: 100%;
            max-width: 800px;
            padding: 0 1rem;
            flex-wrap: wrap;
        }
        .nav-category {
            position: relative;
            flex-grow: 1;
            flex-basis: 150px;
            text-align: center;
        }
        .nav-category:hover .submenu {
            opacity: 1;
            visibility: visible;
            transform: translateY(0);
        }

        .nav-button {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 100%;
            padding: 0.8rem 1rem;
            background-color: var(--color-primary-indigo);
            color: white;
            text-align: center;
            border-radius: 9999px;
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
            white-space: nowrap;
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
            top: calc(100% + 5px);
            left: 50%;
            transform: translateX(-50%) translateY(10px);
            min-width: 200px;
            background-color: rgba(255, 255, 255, 0.95);
            border-radius: 0.75rem;
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.2);
            padding: 0.75rem;
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s cubic-bezier(0.25, 0.8, 0.25, 1);
            z-index: 1005;
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
            align-items: stretch;
            border: 1px solid rgba(224, 224, 224, 0.6);
        }

        .submenu-item {
            font-size: 0.9rem;
            padding: 0.6rem 0.8rem;
            box-shadow: none;
            transition: background-color 0.2s ease-in-out, color 0.2s ease-in-out;
            background-color: transparent;
            color: #333;
            border: 1px solid transparent;
        }
        .submenu-item:hover {
            background-color: #f0f0f0;
            color: var(--color-primary-indigo);
            transform: translateY(0);
            box-shadow: none;
            border-color: #ddd;
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

        <h1 class="text-3xl font-bold text-center mb-8">Registrar Saída de Produto</h1> <form action="processa_saida.php" method="POST" class="grid grid-cols-1 gap-4 mb-8 p-6 bg-white rounded-lg shadow-md">
            <div>
                <label for="produto_id" class="block text-sm font-medium text-gray-700 mb-1">Produto:</label>
                <select id="produto_id" name="produto_id" class="form-select" required>
                    <option value="">Selecione um produto</option>
                    <?php
                    // PHP para carregar produtos do banco de dados
                    $sql_products = "SELECT id, nome, unidade_medida, quantidade_estoque FROM produtos ORDER BY nome ASC";
                    $result_products = mysqli_query($link, $sql_products);

                    if ($result_products && mysqli_num_rows($result_products) > 0) {
                        while ($product = mysqli_fetch_assoc($result_products)) {
                            // Only show products with stock
                            if ($product['quantidade_estoque'] > 0) {
                                echo "<option value='" . htmlspecialchars($product['id']) . "'>"
                                   . htmlspecialchars($product['nome']) . " (" . htmlspecialchars($product['unidade_medida']) . ") - Estoque: " . htmlspecialchars($product['quantidade_estoque'])
                                   . "</option>";
                            }
                        }
                        mysqli_free_result($result_products);
                    } else {
                        echo "<option value=''>Nenhum produto com estoque disponível</option>";
                    }
                    ?>
                </select>
            </div>
            <div>
                <label for="quantidade" class="block text-sm font-medium text-gray-700 mb-1">Quantidade:</label>
                <input type="number" id="quantidade" name="quantidade" class="form-input" min="1" required>
            </div>
            <div>
                <label for="concessionaria_id" class="block text-sm font-medium text-gray-700 mb-1">Concessionária:</label>
                <select id="concessionaria_id" name="concessionaria_id" class="form-select" required>
                    <option value="">Selecione uma concessionária</option>
                    <?php
                    // PHP para carregar concessionárias do formulário de adição
                    // Assumindo que $concessionarias_for_dropdowns está definido no topo se necessário
                    // ou re-consultar se esta página não precisar de outras.
                    // Para evitar duplicação ou dependência não declarada, vamos garantir a consulta aqui:
                    $concessionarias_for_dropdowns = []; // Redefine para evitar usar de outro lugar
                    $sql_concessionarias_form = "SELECT id, nome FROM concessionarias ORDER BY nome ASC";
                    $result_concessionarias_form = mysqli_query($link, $sql_concessionarias_form);

                    if ($result_concessionarias_form && mysqli_num_rows($result_concessionarias_form) > 0) {
                        while ($concessionaria = mysqli_fetch_assoc($result_concessionarias_form)) {
                            $concessionarias_for_dropdowns[$concessionaria['id']] = $concessionaria['nome']; // Popula para uso local
                            echo "<option value='" . htmlspecialchars($concessionaria['id']) . "'>" . htmlspecialchars($concessionaria['nome']) . "</option>";
                        }
                        mysqli_free_result($result_concessionarias_form);
                    } else {
                        echo "<option value=''>Nenhuma concessionária cadastrada</option>";
                    }
                    ?>
                </select>
            </div>
            <div>
                <label for="data_saida" class="block text-sm font-medium text-gray-700 mb-1">Data da Saída:</label>
                <input type="date" id="data_saida" name="data_saida" class="form-input" value="<?php echo date('Y-m-d'); ?>" required>
            </div>
            <div class="flex justify-end space-x-4">
                <button type="submit" class="btn-primary">Registrar Saída</button>
            </div>
        </form>

        <hr class="my-8">
        <h2 class="text-2xl font-semibold mb-4 text-gray-700">Filtrar Saídas Registradas</h2>
        <form action="saida_produto.php" method="GET" class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-8 p-6 bg-white rounded-lg shadow-md">
            <div>
                <label for="filter_month_year" class="block text-sm font-medium text-gray-700 mb-1">Mês/Ano:</label>
                <select id="filter_month_year" name="filter_month_year" class="form-select" onchange="this.form.submit()">
                    <option value="">Todos</option>
                    <?php foreach ($months_years as $raw => $formatted): ?>
                        <option value="<?php echo htmlspecialchars($raw); ?>" <?php echo ($selected_month_year == $raw) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($formatted); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label for="filter_week_year" class="block text-sm font-medium text-gray-700 mb-1">Semana/Ano:</label>
                <select id="filter_week_year" name="filter_week_year" class="form-select" onchange="this.form.submit()">
                    <option value="">Todas</option>
                    <?php foreach ($weeks_years as $raw => $formatted): ?>
                        <option value="<?php echo htmlspecialchars($raw); ?>" <?php echo ($selected_week_year_raw == $raw) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($formatted); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label for="filter_concessionaria_id" class="block text-sm font-medium text-gray-700 mb-1">Concessionária:</label>
                <select id="filter_concessionaria_id" name="filter_concessionaria_id" class="form-select" onchange="this.form.submit()">
                    <option value="">Todas</option>
                    <?php
                    // PHP para carregar concessionárias do banco de dados (usando a variável já populada, mas localmente)
                    foreach ($concessionarias_for_dropdowns as $id => $nome): // <-- Aqui vai a variável populada no início da página
                        echo "<option value='" . htmlspecialchars($id) . "' " . (($selected_concessionaria_id == $id) ? 'selected' : '') . ">" . htmlspecialchars($nome) . "</option>";
                    endforeach;
                    ?>
                </select>
            </div>
            <div class="flex items-end md:col-span-3">
                <button type="submit" class="btn-primary w-full md:w-auto">Aplicar Filtros</button>
            </div>
        </form>

        <h2 class="text-2xl font-semibold mb-4 text-gray-700">Saídas Registradas</h2>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="table-header">
                    <tr>
                        <th>ID Saída</th>
                        <th>Produto</th>
                        <th>Quantidade</th>
                        <th>Preço Custo Total (R$)</th>
                        <th>Concessionária</th>
                        <th>Data Saída</th>
                        <th>Data Registro</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    <?php
                    // PHP para listar saídas registradas do banco de dados com filtros
                    $sql_saidas = "SELECT s.id, s.quantidade, s.preco_custo_total, s.data_saida, s.data_registro,
                                          p.nome AS produto_nome, p.unidade_medida, c.nome AS concessionaria_nome
                                   FROM saidas s
                                   JOIN produtos p ON s.produto_id = p.id
                                   JOIN concessionarias c ON s.concessionaria_id = c.id
                                   " . $where_clause . "
                                   ORDER BY s.data_registro DESC";

                    $result_saidas = mysqli_query($link, $sql_saidas);

                    if ($result_saidas && mysqli_num_rows($result_saidas) > 0) {
                        while ($saida = mysqli_fetch_assoc($result_saidas)) {
                            echo "<tr class='table-row'>";
                            echo "<td>" . htmlspecialchars($saida['id']) . "</td>";
                            echo "<td>" . htmlspecialchars($saida['produto_nome']) . " (" . htmlspecialchars($saida['unidade_medida']) . ")</td>";
                            echo "<td>" . htmlspecialchars($saida['quantidade']) . "</td>";
                            echo "<td>R$ " . number_format($saida['preco_custo_total'], 2, ',', '.') . "</td>";
                            echo "<td>" . htmlspecialchars($saida['concessionaria_nome']) . "</td>";
                            echo "<td>" . htmlspecialchars(date('d/m/Y', strtotime($saida['data_saida']))) . "</td>";
                            echo "<td>" . htmlspecialchars(date('d/m/Y H:i', strtotime($saida['data_registro']))) . "</td>";
                            echo "</tr>";
                        }
                        mysqli_free_result($result_saidas);
                    } else {
                        echo "<tr><td colspan='7' class='text-center py-4'>Nenhuma saída registrada para os filtros selecionados.</td></tr>";
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
        if (isset($_GET['message'])) {
            $message = htmlspecialchars($_GET['message']);
            $type = isset($_GET['type']) ? htmlspecialchars($_GET['type']) : 'success';
            echo "<div class='message-box message-$type'>$message</div>";
        }
        ?>
    </footer>
</body>
</html>