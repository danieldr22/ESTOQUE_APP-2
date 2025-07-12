<?php
// Configurações do banco de dados
define('DB_SERVER', 'localhost'); // Geralmente 'localhost' para ambiente local
define('DB_USERNAME', 'root');   // Seu nome de usuário do MySQL
define('DB_PASSWORD', '');       // Sua senha do MySQL (deixe em branco se não tiver)
define('DB_NAME', 'estoque_db'); // Nome do banco de dados que você criou

// Tentativa de conexão com o banco de dados
$link = mysqli_connect(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);

// Checar conexão
if($link === false){
    die("ERRO: Não foi possível conectar ao banco de dados. " . mysqli_connect_error());
}
?>