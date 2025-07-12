<?php
// processa_entrada.php
// ESTE É UM ARQUIVO PHP. SALVE-O COMO 'processa_entrada.php' NA MESMA PASTA DOS OUTROS ARQUIVOS.

// Inclui o arquivo de configuração do banco de dados
require_once 'config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 1. Validação e sanitização dos inputs
    $produto_id = filter_var($_POST['produto_id'] ?? null, FILTER_VALIDATE_INT);
    $quantidade = filter_var($_POST['quantidade'] ?? 0, FILTER_VALIDATE_INT);
    $data_entrada = trim($_POST['data_entrada'] ?? '');

    // Verifica se os campos obrigatórios são válidos
    if ($produto_id === false || $produto_id <= 0 || $quantidade === false || $quantidade < 1 || empty($data_entrada)) {
        $message = "Erro: Todos os campos são obrigatórios e a quantidade deve ser um número inteiro positivo.";
        $type = "error";
        header('Location: entrada_produto.php?message=' . urlencode($message) . '&type=' . urlencode($type));
        exit();
    }

    // 2. Verificar se o produto existe e obter sua unidade de medida (apenas para exibição se necessário, mas não é usado para persistência aqui)
    $sql_check_product = "SELECT id, nome, quantidade_estoque FROM produtos WHERE id = ?";
    if ($stmt_check = mysqli_prepare($link, $sql_check_product)) {
        mysqli_stmt_bind_param($stmt_check, "i", $param_produto_id);
        $param_produto_id = $produto_id;
        mysqli_stmt_execute($stmt_check);
        $result_check = mysqli_stmt_get_result($stmt_check);

        if (mysqli_num_rows($result_check) == 0) {
            $message = "Erro: Produto selecionado não existe.";
            $type = "error";
            header('Location: entrada_produto.php?message=' . urlencode($message) . '&type=' . urlencode($type));
            exit();
        }
        $produto_info = mysqli_fetch_assoc($result_check);
        $estoque_atual = $produto_info['quantidade_estoque'];
        mysqli_stmt_close($stmt_check);
    } else {
        $message = "Erro ao verificar o produto: " . mysqli_error($link);
        $type = "error";
        header('Location: entrada_produto.php?message=' . urlencode($message) . '&type=' . urlencode($type));
        exit();
    }

    // 3. Inserir a entrada na tabela 'entradas'
    $sql_insert_entrada = "INSERT INTO entradas (produto_id, quantidade, data_entrada) VALUES (?, ?, ?)";
    if ($stmt_insert = mysqli_prepare($link, $sql_insert_entrada)) {
        mysqli_stmt_bind_param($stmt_insert, "iis", $param_produto_id_entrada, $param_quantidade, $param_data_entrada);

        $param_produto_id_entrada = $produto_id;
        $param_quantidade = $quantidade;
        $param_data_entrada = $data_entrada; // Formato YYYY-MM-DD já é compatível com MySQL DATE

        if (!mysqli_stmt_execute($stmt_insert)) {
            $message = "Erro ao registrar a entrada: " . mysqli_error($link);
            $type = "error";
            mysqli_stmt_close($stmt_insert);
            mysqli_close($link);
            header('Location: entrada_produto.php?message=' . urlencode($message) . '&type=' . urlencode($type));
            exit();
        }
        mysqli_stmt_close($stmt_insert);
    } else {
        $message = "Erro ao preparar a instrução de entrada: " . mysqli_error($link);
        $type = "error";
        mysqli_close($link);
        header('Location: entrada_produto.php?message=' . urlencode($message) . '&type=' . urlencode($type));
        exit();
    }

    // 4. Atualizar a quantidade_estoque na tabela 'produtos'
    $novo_estoque = $estoque_atual + $quantidade;
    $sql_update_estoque = "UPDATE produtos SET quantidade_estoque = ? WHERE id = ?";
    if ($stmt_update = mysqli_prepare($link, $sql_update_estoque)) {
        mysqli_stmt_bind_param($stmt_update, "ii", $param_novo_estoque, $param_produto_id_update);

        $param_novo_estoque = $novo_estoque;
        $param_produto_id_update = $produto_id;

        if (mysqli_stmt_execute($stmt_update)) {
            $message = "Entrada de produto '" . htmlspecialchars($produto_info['nome']) . "' registrada e estoque atualizado para " . $novo_estoque . " com sucesso!";
            $type = "success";
        } else {
            $message = "Erro ao atualizar o estoque: " . mysqli_error($link);
            $type = "error";
        }
        mysqli_stmt_close($stmt_update);
    } else {
        $message = "Erro ao preparar a instrução de atualização de estoque: " . mysqli_error($link);
        $type = "error";
    }

    // Fecha a conexão com o banco de dados
    mysqli_close($link);

    // Redireciona de volta para a página de entrada com a mensagem
    header('Location: entrada_produto.php?message=' . urlencode($message) . '&type=' . urlencode($type));
    exit();

} else {
    // Redireciona se o método não for POST
    header('Location: entrada_produto.php?message=' . urlencode("Método de requisição inválido.") . '&type=' . urlencode("error"));
    exit();
}
?>