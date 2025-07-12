<?php
// editar_agendamento.php
require_once 'config.php';

// Verifica a conexão com o banco de dados
if (!$link) {
    die("Erro: Conexão com o banco de dados não estabelecida em editar_agendamento.php. Verifique config.php.");
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

$agendamento_id = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT);
$agendamento = null;
$message = '';
$type = '';

if (!$agendamento_id) {
    $message = "ID do agendamento inválido.";
    $type = "error";
    // Redireciona de volta se o ID for inválido
    header('Location: agenda_aplicadores.php?message=' . urlencode($message) . '&type=' . urlencode($type));
    exit();
}

// --- Lógica para buscar o agendamento existente ---
$sql_fetch_agendamento = "SELECT aa.id, aa.data_agendamento, aa.tipo_pelicula, aa.concessionaria_id, aa.aplicador, aa.servico, aa.servicos_agregados, aa.status, aa.observacoes, c.nome AS concessionaria_nome
                          FROM agendamentos_aplicadores aa
                          JOIN concessionarias c ON aa.concessionaria_id = c.id
                          WHERE aa.id = ?";
if ($stmt = mysqli_prepare($link, $sql_fetch_agendamento)) {
    mysqli_stmt_bind_param($stmt, "i", $agendamento_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $agendamento = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);

    if (!$agendamento) {
        $message = "Agendamento não encontrado.";
        $type = "error";
        header('Location: agenda_aplicadores.php?message=' . urlencode($message) . '&type=' . urlencode($type));
        exit();
    }
} else {
    $message = "Erro ao preparar a busca do agendamento: " . mysqli_error($link);
    $type = "error";
    header('Location: agenda_aplicadores.php?message=' . urlencode($message) . '&type=' . urlencode($type));
    exit();
}

// Decodifica os serviços agregados para preencher os checkboxes
$agendamento['servicos_agregados_array'] = json_decode($agendamento['servicos_agregados'], true) ?? [];


// --- Lógica para buscar concessionárias para os dropdowns (necessário para o formulário de edição) ---
$concessionarias_dropdown = [];
$sql_concessionarias = "SELECT id, nome FROM concessionarias ORDER BY nome ASC";
$result_concessionarias = mysqli_query($link, $sql_concessionarias);
if ($result_concessionarias) {
    while ($row = mysqli_fetch_assoc($result_concessionarias)) {
        $concessionarias_dropdown[$row['id']] = $row['nome'];
    }
    mysqli_free_result($result_concessionarias);
}

// --- Lógica para processar as ações POST (edição ou exclusão) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // MODIFICAÇÃO PARA EXCLUSÃO: Processa a exclusão primeiro se o botão 'excluir_agendamento' foi clicado
    if (isset($_POST['excluir_agendamento']) && $_POST['excluir_agendamento'] === 'Sim') {
        $sql_delete = "DELETE FROM agendamentos_aplicadores WHERE id = ?";
        if ($stmt = mysqli_prepare($link, $sql_delete)) {
            mysqli_stmt_bind_param($stmt, "i", $agendamento_id);
            if (mysqli_stmt_execute($stmt)) {
                $message = "Agendamento ID " . $agendamento_id . " excluído com sucesso!";
                $type = "success";
                header('Location: agenda_aplicadores.php?message=' . urlencode($message) . '&type=' . urlencode($type));
                exit();
            } else {
                $message = "Erro ao excluir agendamento: " . mysqli_error($link);
                $type = "error";
            }
            mysqli_stmt_close($stmt);
        } else {
            $message = "Erro ao preparar a exclusão do agendamento: " . mysqli_error($link);
            $type = "error";
        }
    }
    // MODIFICAÇÃO PARA EDIÇÃO: Processa a edição se o botão de exclusão NÃO foi clicado
    else { // Apenas processa a edição se não for uma requisição de exclusão
        $data_agendamento = trim($_POST['data_agendamento'] ?? '');
        $tipo_pelicula = trim($_POST['tipo_pelicula'] ?? '');
        $concessionaria_id_new = filter_var($_POST['concessionaria_id'] ?? null, FILTER_VALIDATE_INT);
        $aplicador = trim($_POST['aplicador'] ?? '');
        $servico = trim($_POST['servico'] ?? '');
        $servicos_agregados_array_new = $_POST['servicos_agregados'] ?? [];
        $status_new = trim($_POST['status'] ?? ''); // Permite editar o status também
        $observacoes = trim($_POST['observacoes'] ?? '');

        // Codifica os serviços agregados para JSON para armazenar no banco de dados
        $servicos_agregados_json_new = json_encode($servicos_agregados_array_new);
        if ($servicos_agregados_json_new === false) {
            $servicos_agregados_json_new = '[]';
        }

        if (empty($data_agendamento) || $concessionaria_id_new === false || $concessionaria_id_new <= 0 || empty($aplicador) || empty($servico) || !in_array($aplicador, $aplicadores_disponiveis) || !in_array($status_new, $status_agendamento)) {
            $message = "Erro: Por favor, preencha todos os campos obrigatórios corretamente e selecione opções válidas.";
            $type = "error";
        } else {
            $sql_update = "UPDATE agendamentos_aplicadores SET data_agendamento = ?, tipo_pelicula = ?, concessionaria_id = ?, aplicador = ?, servico = ?, servicos_agregados = ?, status = ?, observacoes = ? WHERE id = ?";
            if ($stmt = mysqli_prepare($link, $sql_update)) {
                mysqli_stmt_bind_param($stmt, "ssisssssi", $data_agendamento, $tipo_pelicula, $concessionaria_id_new, $aplicador, $servico, $servicos_agregados_json_new, $status_new, $observacoes, $agendamento_id);
                if (mysqli_stmt_execute($stmt)) {
                    $message = "Agendamento ID " . $agendamento_id . " atualizado com sucesso!";
                    $type = "success";
                    // Atualiza o objeto $agendamento para refletir as mudanças no formulário após o POST
                    $agendamento['data_agendamento'] = $data_agendamento;
                    $agendamento['tipo_pelicula'] = $tipo_pelicula;
                    $agendamento['concessionaria_id'] = $concessionaria_id_new;
                    $agendamento['aplicador'] = $aplicador;
                    $agendamento['servico'] = $servico;
                    $agendamento['servicos_agregados'] = $servicos_agregados_json_new;
                    $agendamento['servicos_agregados_array'] = $servicos_agregados_array_new;
                    $agendamento['status'] = $status_new;
                    $agendamento['observacoes'] = $observacoes;

                } else {
                    $message = "Erro ao atualizar agendamento: " . mysqli_error($link);
                    $type = "error";
                }
                mysqli_stmt_close($stmt);
            } else {
                $message = "Erro ao preparar a atualização do agendamento: " . mysqli_error($link);
                $type = "error";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Agendamento</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; background-color: #f3f4f6; color: #333; display: flex; flex-direction: column; min-height: 100vh; }
        .container { max-width: 900px; margin: 2rem auto; padding: 1.5rem; background-color: #ffffff; border-radius: 0.75rem; box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1); flex-grow: 1; }
        .form-input, .form-select, .form-textarea { width: 100%; padding: 0.75rem; border: 1px solid #d1d5db; border-radius: 0.5rem; font-size: 1rem; background-color: #f9fafb; }
        .btn-primary { background-color: #4f46e5; color: white; padding: 0.75rem 1.5rem; border-radius: 0.5rem; font-weight: 600; transition: background-color 0.2s ease-in-out; }
        .btn-primary:hover { background-color: #4338ca; }
        .btn-secondary { background-color: #6b7280; color: white; padding: 0.75rem 1.5rem; border-radius: 0.5rem; font-weight: 600; transition: background-color 0.2s ease-in-out; text-decoration: none; }
        .btn-secondary:hover { background-color: #4b5563; }
        .btn-danger { background-color: #dc2626; color: white; padding: 0.75rem 1.5rem; border-radius: 0.5rem; font-weight: 600; transition: background-color 0.2s ease-in-out; text-decoration: none; }
        .btn-danger:hover { background-color: #b91c1c; }
        .message-box {
            padding: 0.75rem;
            border-radius: 0.5rem;
            margin-bottom: 1rem;
            font-weight: 500;
        }
        .message-success { background-color: #d1fae5; color: #065f46; border: 1px solid #34d399; }
        .message-error { background-color: #fee2e2; color: #991b1b; border: 1px solid #ef4444; }
        .app-footer { margin-top: 2rem; padding: 1rem; background-color: #374151; color: white; text-align: center; font-size: 0.9rem; }
    </style>
</head>
<body>
    <div class="container">
        <h1 class="text-3xl font-bold text-center mb-6 text-gray-800">Editar Agendamento #<?php echo htmlspecialchars($agendamento['id']); ?></h1>

        <?php
        if (!empty($message)) {
            echo "<div class='message-box message-$type'>$message</div>";
        }
        ?>

        <form action="editar_agendamento.php?id=<?php echo htmlspecialchars($agendamento['id']); ?>" method="POST" class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-8 p-6 bg-white rounded-lg shadow-md">
            <div>
                <label for="data_agendamento" class="block text-sm font-medium text-gray-700 mb-1">Data:</label>
                <input type="date" id="data_agendamento" name="data_agendamento" class="form-input" value="<?php echo htmlspecialchars($agendamento['data_agendamento']); ?>" required>
            </div>
            <div>
                <label for="tipo_pelicula" class="block text-sm font-medium text-gray-700 mb-1">Tipo de Película:</label>
                <input type="text" id="tipo_pelicula" name="tipo_pelicula" class="form-input" value="<?php echo htmlspecialchars($agendamento['tipo_pelicula']); ?>" placeholder="Ex: G5, G20, Window Blue" required>
            </div>
            <div>
                <label for="concessionaria_id" class="block text-sm font-medium text-gray-700 mb-1">Concessionária:</label>
                <select id="concessionaria_id" name="concessionaria_id" class="form-select" required>
                    <option value="">Selecione uma concessionária</option>
                    <?php foreach ($concessionarias_dropdown as $id => $nome): ?>
                        <option value="<?php echo htmlspecialchars($id); ?>" <?php echo ($agendamento['concessionaria_id'] == $id) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($nome); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label for="aplicador" class="block text-sm font-medium text-gray-700 mb-1">Aplicador:</label>
                <select id="aplicador" name="aplicador" class="form-select" required>
                    <option value="">Selecione um aplicador</option>
                    <?php foreach ($aplicadores_disponiveis as $aplicador_nome): ?>
                        <option value="<?php echo htmlspecialchars($aplicador_nome); ?>" <?php echo ($agendamento['aplicador'] == $aplicador_nome) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($aplicador_nome); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="md:col-span-2">
                <label for="servico" class="block text-sm font-medium text-gray-700 mb-1">Serviço Principal:</label>
                <input type="text" id="servico" name="servico" class="form-input" value="<?php echo htmlspecialchars($agendamento['servico']); ?>" placeholder="Ex: Instalação de Insulfilm" required>
            </div>

            <div class="md:col-span-2">
                <label class="block text-sm font-medium text-gray-700 mb-2">Serviços Agregados:</label>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                    <?php foreach ($servicos_agregados_opcoes as $key => $label): ?>
                        <div class="flex items-center">
                            <input type="checkbox" id="servico_<?php echo $key; ?>" name="servicos_agregados[]" value="<?php echo htmlspecialchars($key); ?>"
                                <?php echo in_array($key, $agendamento['servicos_agregados_array']) ? 'checked' : ''; ?>
                                class="h-4 w-4 text-indigo-600 border-gray-300 rounded focus:ring-indigo-500">
                            <label for="servico_<?php echo $key; ?>" class="ml-2 block text-sm text-gray-900"><?php echo htmlspecialchars($label); ?></label>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div>
                <label for="status" class="block text-sm font-medium text-gray-700 mb-1">Status:</label>
                <select id="status" name="status" class="form-select" required>
                    <?php foreach ($status_agendamento as $status_option): ?>
                        <option value="<?php echo htmlspecialchars($status_option); ?>" <?php echo ($agendamento['status'] == $status_option) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($status_option); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="md:col-span-2">
                <label for="observacoes" class="block text-sm font-medium text-gray-700 mb-1">Observações (Opcional):</label>
                <textarea id="observacoes" name="observacoes" class="form-textarea" rows="3" placeholder="Detalhes adicionais..."><?php echo htmlspecialchars($agendamento['observacoes']); ?></textarea>
            </div>
            <div class="md:col-span-2 flex justify-end space-x-4">
                <a href="agenda_aplicadores.php" class="btn-secondary">Voltar para Agenda</a>
                <button type="submit" class="btn-primary">Salvar Alterações</button>
                
                <button type="submit" name="excluir_agendamento" value="Sim" class="btn-danger" onclick="return confirm('Tem certeza que deseja excluir este agendamento? Esta ação não pode ser desfeita.');">Excluir Serviço</button>
            </div>
        </form>
    </div>

    <footer class="app-footer">
        <p>&copy; <?php echo date('Y'); ?> Sistema de Gestão de Estoque (SGE). Todos os direitos reservados.</p>
        <p>Desenvolvido por Dani "Emo" Roger</p>
    </footer>

</body>
</html>
<?php
// Fecha a conexão com o banco de dados
if (isset($link) && is_object($link)) {
    mysqli_close($link);
}
?>