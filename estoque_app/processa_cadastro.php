<?php
// Inclui o arquivo de configuração do banco de dados
require_once 'config.php';

// Verifica se os dados do formulário foram enviados via POST
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Valida e sanitiza os inputs
    $nome_produto = trim($_POST["nome_produto"]);
    $unidade_medida = trim($_POST["unidade_medida"]);
    $preco_custo = filter_input(INPUT_POST, 'preco_custo', FILTER_VALIDATE_FLOAT);

    // Verifica se os campos obrigatórios não estão vazios e se o preço é um número válido
    if (empty($nome_produto) || empty($unidade_medida) || $preco_custo === false || $preco_custo < 0) {
        $message = "Por favor, preencha todos os campos corretamente.";
        $type = "error";
    } else {
        // Prepara a instrução SQL de inserção
        $sql = "INSERT INTO produtos (nome, unidade_medida, preco_custo) VALUES (?, ?, ?)";

        if ($stmt = mysqli_prepare($link, $sql)) {
            // Vincula as variáveis aos parâmetros da instrução preparada como strings
            mysqli_stmt_bind_param($stmt, "ssd", $param_nome, $param_unidade, $param_preco);

            // Define os parâmetros
            $param_nome = $nome_produto;
            $param_unidade = $unidade_medida;
            $param_preco = $preco_custo;

            // Tenta executar a instrução preparada
            if (mysqli_stmt_execute($stmt)) {
                $message = "Produto '" . htmlspecialchars($nome_produto) . "' cadastrado com sucesso!";
                $type = "success";
            } else {
                $message = "Erro ao cadastrar produto: " . mysqli_error($link);
                $type = "error";
            }

            // Fecha a instrução
            mysqli_stmt_close($stmt);
        } else {
            $message = "Erro ao preparar a consulta: " . mysqli_error($link);
            $type = "error";
        }
    }

    // Fecha a conexão com o banco de dados
    mysqli_close($link);

    // Redireciona de volta para a página de cadastro com a mensagem
    header("location: cadastro_produto.php?message=" . urlencode($message) . "&type=" . urlencode($type));
    exit();
} else {
    // Se não for uma requisição POST, redireciona para a página principal ou exibe um erro
    header("location: cadastro_produto.php");
    exit();
}
?>