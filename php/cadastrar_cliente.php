<?php
// /php/cadastrar_cliente.php (VERSÃO FINAL E CORRIGIDA)

session_start();
require_once "db_config.php";
header('Content-Type: application/json');

$response = ['status' => 'error', 'message' => 'Ocorreu um erro desconhecido.'];

// O ID do usuário/loja que está cadastrando o cliente, no seu caso sempre 1.
$usuario_id = 1;

// Pega os dados enviados pelo formulário
$cpf_da_sessao = $_SESSION['cpf_digitado'] ?? '';
$nome = trim($_POST['nome'] ?? '');
$whatsapp_sujo = trim($_POST['whatsapp'] ?? '');
$nascimento_br = trim($_POST['nascimento'] ?? '');

// Limpa os dados para evitar problemas de formatação
$cpf_limpo = preg_replace('/[^0-9]/', '', $cpf_da_sessao);
$whatsapp_limpo = preg_replace('/[^0-9]/', '', $whatsapp_sujo);

// Validação dos campos
if (empty($cpf_limpo) || empty($nome) || empty($whatsapp_limpo) || empty($nascimento_br)) {
    $response['message'] = 'Todos os campos são obrigatórios.';
    echo json_encode($response);
    exit;
}

// Converte a data do formato brasileiro (DD/MM/AAAA) para o formato do banco (AAAA-MM-DD)
$date_obj = DateTime::createFromFormat('d/m/Y', $nascimento_br);
if (!$date_obj || $date_obj->format('d/m/Y') !== $nascimento_br) {
    $response['message'] = 'Data de nascimento inválida. Use o formato DD/MM/AAAA.';
    echo json_encode($response);
    exit;
}
$nascimento_para_banco = $date_obj->format('Y-m-d');

// SQL explícito para garantir a inserção correta e o retorno do ID
$sql = "INSERT INTO clientes (cpf, nome_completo, whatsapp, data_nascimento, data_cadastro, usuario_id) VALUES ($1, $2, $3, $4, NOW(), $5) RETURNING id";

// Usamos um nome de query único para evitar problemas de cache do PostgreSQL
$stmt = pg_prepare($link, "cadastrar_cliente_query_final", $sql);

if ($stmt) {
    // A @ na frente do pg_execute suprime o Warning padrão do PHP, pois vamos tratar o erro manualmente logo abaixo
    $result = @pg_execute($link, "cadastrar_cliente_query_final", array($cpf_limpo, $nome, $whatsapp_limpo, $nascimento_para_banco, $usuario_id));

    if ($result && pg_num_rows($result) > 0) {
        // Bloco de sucesso: Cliente foi cadastrado
        $row = pg_fetch_assoc($result);

        // Guarda o ID e nome do cliente na sessão para a próxima página
        $_SESSION['cliente_id'] = $row['id'];
        $_SESSION['cliente_nome'] = $nome;

        // Limpa o CPF que não será mais necessário na sessão
        unset($_SESSION['cpf_digitado']);

        // Prepara a resposta de sucesso com o redirecionamento CORRETO
        $response = ['status' => 'success', 'message' => 'Cliente cadastrado com sucesso!', 'redirect' => 'confirmacao_cliente.php'];

    } else {
        // Bloco de erro: A inserção falhou
        $error_message = pg_last_error($link);

        // Verifica se o erro foi de CPF duplicado (código 23505)
        if (strpos($error_message, '23505') !== false) {
            $response['message'] = 'Este CPF já está cadastrado.';
        } else {
            $response['message'] = 'Erro ao cadastrar o cliente no banco de dados.';
        }
    }
} else {
    $response['message'] = 'Erro na preparação da consulta.';
}

pg_close($link);
echo json_encode($response);
?>