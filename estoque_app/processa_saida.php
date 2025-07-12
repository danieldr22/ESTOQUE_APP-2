<?php
// processa_saida.php
// Este script processa o formulário de saída de produto, dá baixa no estoque
// e registra a saída com o custo total para a concessionária.

require_once 'config.php'; // Inclui a conexão com o banco de dados

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 1. Validação e sanitização dos inputs
    $produto_id = filter_var($_POST['produto_id'] ?? null, FILTER_VALIDATE_INT);
    $quantidade = filter_var($_POST['quantidade'] ?? 0, FILTER_VALIDATE_INT);
    $concessionaria_id = filter_var($_POST['concessionaria_id'] ?? null, FILTER_VALIDATE_INT);
    $data_saida = trim($_POST['data_saida'] ?? '');

    // Verifica se os campos obrigatórios são válidos
    if ($produto_id === false || $produto_id <= 0 ||
        $quantidade === false || $quantidade < 1 ||
        $concessionaria_id === false || $concessionaria_id <= 0 ||
        empty($data_saida)) {
        $message = "Erro: Todos os campos são obrigatórios e a quantidade deve ser um número inteiro positivo.";
        $type = "error";
        header('Location: saida_produto.php?message=' . urlencode($message) . '&type=' . urlencode($type));
        exit();
    }

    // Inicia uma transação para garantir atomicidade das operações
    mysqli_autocommit($link, false); // Desabilita o autocommit
    $transaction_successful = true;

    // 2. Verificar se o produto existe, obter seu estoque atual e preço de custo
    $sql_get_product_info = "SELECT id, nome, quantidade_estoque, preco_custo FROM produtos WHERE id = ?";
    if ($stmt_product = mysqli_prepare($link, $sql_get_product_info)) {
        mysqli_stmt_bind_param($stmt_product, "i", $param_produto_id);
        $param_produto_id = $produto_id;
        mysqli_stmt_execute($stmt_product);
        $result_product = mysqli_stmt_get_result($stmt_product);

        if (mysqli_num_rows($result_product) == 0) {
            $message = "Erro: Produto selecionado não existe.";
            $type = "error";
            $transaction_successful = false;
        } else {
            $produto_info = mysqli_fetch_assoc($result_product);
            $estoque_atual = $produto_info['quantidade_estoque'];
            $preco_custo_unitario = $produto_info['preco_custo'];

            // 2.1. Verificar se há estoque suficiente
            if ($quantidade > $estoque_atual) {
                $message = "Erro: Quantidade solicitada (" . $quantidade . ") excede o estoque disponível (" . $estoque_atual . ") para o produto '" . htmlspecialchars($produto_info['nome']) . "'.";
                $type = "error";
                $transaction_successful = false;
            }
        }
        mysqli_stmt_close($stmt_product);
    } else {
        $message = "Erro ao preparar a verificação do produto: " . mysqli_error($link);
        $type = "error";
        $transaction_successful = false;
    }

    // Se houver algum erro até aqui, aborta a transação e redireciona
    if (!$transaction_successful) {
        mysqli_rollback($link); // Desfaz quaisquer operações pendentes
        mysqli_close($link);
        header('Location: saida_produto.php?message=' . urlencode($message) . '&type=' . urlencode($type));
        exit();
    }

    // 3. Verificar se a concessionária existe
    $sql_check_concessionaria = "SELECT id FROM concessionarias WHERE id = ?";
    if ($stmt_concessionaria = mysqli_prepare($link, $sql_check_concessionaria)) {
        mysqli_stmt_bind_param($stmt_concessionaria, "i", $param_concessionaria_id);
        $param_concessionaria_id = $concessionaria_id;
        mysqli_stmt_execute($stmt_concessionaria);
        $result_concessionaria = mysqli_stmt_get_result($stmt_concessionaria);

        if (mysqli_num_rows($result_concessionaria) == 0) {
            $message = "Erro: Concessionária selecionada não existe.";
            $type = "error";
            $transaction_successful = false;
        }
        mysqli_stmt_close($stmt_concessionaria);
    } else {
        $message = "Erro ao preparar a verificação da concessionária: " . mysqli_error($link);
        $type = "error";
        $transaction_successful = false;
    }

    // Se houver erro na concessionária, aborta a transação e redireciona
    if (!$transaction_successful) {
        mysqli_rollback($link);
        mysqli_close($link);
        header('Location: saida_produto.php?message=' . urlencode($message) . '&type=' . urlencode($type));
        exit();
    }

    // Calcular o preço de custo total da saída
    $preco_custo_total = $quantidade * $preco_custo_unitario;

    // 4. Inserir a saída na tabela 'saidas'
    $sql_insert_saida = "INSERT INTO saidas (produto_id, quantidade, concessionaria_id, preco_custo_total, data_saida) VALUES (?, ?, ?, ?, ?)";
    if ($stmt_insert_saida = mysqli_prepare($link, $sql_insert_saida)) {
        mysqli_stmt_bind_param($stmt_insert_saida, "iiids", // i=int, i=int, i=int, d=decimal, s=string
                                $param_produto_id_saida,
                                $param_quantidade_saida,
                                $param_concessionaria_id,
                                $param_preco_custo_total,
                                $param_data_saida);

        $param_produto_id_saida = $produto_id;
        $param_quantidade_saida = $quantidade;
        $param_concessionaria_id = $concessionaria_id;
        $param_preco_custo_total = $preco_custo_total;
        $param_data_saida = $data_saida;

        if (!mysqli_stmt_execute($stmt_insert_saida)) {
            $message = "Erro ao registrar a saída: " . mysqli_error($link);
            $type = "error";
            $transaction_successful = false;
        }
        mysqli_stmt_close($stmt_insert_saida);
    } else {
        $message = "Erro ao preparar a instrução de saída: " . mysqli_error($link);
        $type = "error";
        $transaction_successful = false;
    }

    // Se houver erro na inserção da saída, aborta a transação e redireciona
    if (!$transaction_successful) {
        mysqli_rollback($link);
        mysqli_close($link);
        header('Location: saida_produto.php?message=' . urlencode($message) . '&type=' . urlencode($type));
        exit();
    }

    // 5. Atualizar a quantidade_estoque na tabela 'produtos'
    $novo_estoque = $estoque_atual - $quantidade;
    $sql_update_estoque = "UPDATE produtos SET quantidade_estoque = ? WHERE id = ?";
    if ($stmt_update_estoque = mysqli_prepare($link, $sql_update_estoque)) {
        mysqli_stmt_bind_param($stmt_update_estoque, "ii", $param_novo_estoque, $param_produto_id_update);

        $param_novo_estoque = $novo_estoque;
        $param_produto_id_update = $produto_id;

        if (!mysqli_stmt_execute($stmt_update_estoque)) {
            $message = "Erro ao atualizar o estoque: " . mysqli_error($link);
            $type = "error";
            $transaction_successful = false;
        }
        mysqli_stmt_close($stmt_update_estoque);
    } else {
        $message = "Erro ao preparar a atualização de estoque: " . mysqli_error($link);
        $type = "error";
        $transaction_successful = false;
    }

    // Final da transação: Commit ou Rollback
    if ($transaction_successful) {
        mysqli_commit($link); // Confirma todas as operações
        $message = "Saída de produto '" . htmlspecialchars($produto_info['nome']) . "' registrada para " . htmlspecialchars($quantidade) . " unidades. Novo estoque: " . $novo_estoque . ". Custo total para a concessionária: R$ " . number_format($preco_custo_total, 2, ',', '.') . ".";
        $type = "success";
    } else {
        mysqli_rollback($link); // Desfaz tudo se algo deu errado
        // A mensagem e tipo já foram definidos acima em caso de erro
    }

    mysqli_close($link); // Fecha a conexão
    header('Location: saida_produto.php?message=' . urlencode($message) . '&type=' . urlencode($type));
    exit();

} else {
    // Redireciona se o método não for POST
    $message = "Método de requisição inválido.";
    $type = "error";
    header('Location: saida_produto.php?message=' . urlencode($message) . '&type=' . urlencode($type));
    exit();
}
?>