<?php
// index.php
// Página principal do Sistema de Gestão de Estoque

require_once 'config.php'; // Inclui a conexão com o banco de dados

// Verificação de conexão (para depuração, pode ser removido em produção)
if (!$link) {
    die("Erro: Conexão com o banco de dados não estabelecida em index.php. Verifique config.php.");
}

// Lógica para buscar dados de gasto mensal para o gráfico
$monthly_spending_data = [];
$sql_monthly_spending = "SELECT
                            DATE_FORMAT(data_saida, '%Y-%m') AS month_year,
                            SUM(preco_custo_total) AS total_cost
                           FROM
                            saidas
                           GROUP BY
                            month_year
                           ORDER BY
                            month_year ASC";

$result_monthly_spending = mysqli_query($link, $sql_monthly_spending);

if ($result_monthly_spending) {
    while ($row = mysqli_fetch_assoc($result_monthly_spending)) {
        $monthly_spending_data[] = [
            'month_year' => date('M/Y', strtotime($row['month_year'] . '-01')), // Format as Jan/2023
            'total_cost' => (float)$row['total_cost']
        ];
    }
    mysqli_free_result($result_monthly_spending);
} else {
    // Handle error if query fails
    error_log("Erro ao buscar dados de gasto mensal: " . mysqli_error($link) . " SQL: " . $sql_monthly_spending);
}

// Fecha a conexão com o banco de dados
if (isset($link) && is_object($link)) {
    mysqli_close($link);
}

// Codifica os dados para JSON para uso no JavaScript
$json_monthly_spending = json_encode($monthly_spending_data);
?>
<!DOCTYPE html>
<html lang="pt-BR" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sistema de Gestão de Estoque</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
            background-color: #f3f4f6; /* Fallback */
            background-image: url('imagens/background_art.jpg');
            background-size: cover;
            background-repeat: no-repeat;
            background-position: center center;
            background-attachment: fixed;
            padding-top: 150px; /* Espaço para o cabeçalho fixo, ajuste se necessário */
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
            max-width: 1000px;
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
        h1 {
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
        
        /* Estilos para o novo menu flutuante */
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
        .chart-container {
            width: 100%;
            max-height: 500px;
            margin-top: 0; /* Margem controlada pelo gap do pai */
            padding: 2.5rem;
            background-color: #fff;
            border-radius: 1.5rem;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.15);
            border: 1px solid rgba(224, 224, 224, 0.6);
        }
        /* Estilo para o rodapé */
        .app-footer {
            margin-top: 4rem;
            padding: 2rem;
            background-color: var(--color-footer-bg);
            color: var(--color-text-light);
            text-align: center;
            font-size: 1rem;
            box-shadow: 0 -8px 20px rgba(0, 0, 0, 0.3);
            border-top: 1px solid rgba(255, 255, 255, 0.1);
        }
        .app-footer p {
            margin-bottom: 0.5rem;
            line-height: 1.5;
        }
        .app-footer p:last-child {
            margin-bottom: 0;
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

        <div class="main-content-area">
            <div class="chart-container">
                <h2 class="text-2xl font-semibold mb-4 text-gray-700 text-center">Gasto Total Mensal (Saídas)</h2>
                <canvas id="monthlySpendingChart"></canvas>
            </div>
        </div>
    </div>

    <footer class="app-footer">
        <p>&copy; <?php echo date('Y'); ?> Sistema de Gestão de Estoque (SGE). Todos os direitos reservados.</p>
        <p>Desenvolvido por Dani "Emo" Roger</p>
        <?php
        // Mensagem específica para o rodapé ou duplicada do cabeçalho
        if (isset($_GET['message']) && isset($_GET['display_in_footer']) && $_GET['display_in_footer'] == 'true') {
            $message = htmlspecialchars($_GET['message']);
            $type = isset($_GET['type']) ? htmlspecialchars($_GET['type']) : 'success';
            echo "<div class='message-box message-$type'>$message</div>";
        } else if (isset($_GET['message']) && !isset($_GET['display_in_footer'])) {
             $message = htmlspecialchars($_GET['message']);
             $type = isset($_GET['type']) ? htmlspecialchars($_GET['type']) : 'success';
             echo "<div class='message-box message-$type'>$message</div>";
        }
        ?>
    </footer>

    <script>
        // Dados do PHP para o JavaScript
        const monthlySpendingData = <?php echo $json_monthly_spending; ?>;

        // Preparar os rótulos (meses/anos) e os dados (custos) para o Chart.js
        const labels = monthlySpendingData.map(item => item.month_year);
        const data = monthlySpendingData.map(item => item.total_cost);

        const ctx = document.getElementById('monthlySpendingChart').getContext('2d');
        new Chart(ctx, {
            type: 'bar', // Tipo de gráfico: barra
            data: {
                labels: labels,
                datasets: [{
                    label: 'Custo Total de Saídas (R$)',
                    data: data,
                    backgroundColor: 'rgba(79, 70, 229, 0.6)', // Cor azul índigo
                    borderColor: 'rgba(79, 70, 229, 1)',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false, // Permite controlar a altura com max-height no CSS
                scales: {
                    y: {
                    beginAtZero: true,
                        title: {
                            display: true,
                            text: 'Custo (R$)'
                        },
                        ticks: {
                            callback: function(value, index, ticks) {
                                return 'R$ ' + value.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                            }
                        }
                    },
                    x: {
                        title: {
                            display: true,
                            text: 'Mês/Ano'
                        }
                    }
                },
                plugins: {
                    legend: {
                        display: true,
                        position: 'top',
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                let label = context.dataset.label || '';
                                if (label) {
                                    label += ': ';
                                }
                                if (context.parsed.y !== null) {
                                    label += 'R$ ' + context.parsed.y.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                                }
                                return label;
                            }
                        }
                    }
                }
            }
        });
    </script>
</body>
</html>