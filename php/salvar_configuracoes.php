<?php
// /php/salvar_configuracoes.php (VERSÃO FINAL COM LÓGICA UPSERT)

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once "db_config.php";
header('Content-Type: application/json');

$response = ['status' => 'error', 'message' => 'Acesso não autorizado.'];

if (!isset($_SESSION['usuario_id']) || !isset($_SESSION['cargo']) || $_SESSION['cargo'] != 1) {
    echo json_encode($response);
    exit;
}

$novas_configuracoes = $_POST;

pg_query($link, "BEGIN");

try {
    // =================== LÓGICA CORRIGIDA: UPSERT (INSERT or UPDATE) ===================
    // Esta query tenta INSERIR uma nova configuração.
    // Se a 'chave' já existir (ON CONFLICT), ele executa um UPDATE no 'valor'.
    // Isso garante que funcione tanto na primeira vez (INSERT) quanto nas próximas (UPDATE).
    // NOTA: A coluna 'chave' na sua tabela 'configuracoes' deve ser uma CHAVE PRIMÁRIA ou ter uma restrição UNIQUE.
    $sql = "INSERT INTO configuracoes (chave, valor) 
            VALUES ($1, $2)
            ON CONFLICT (chave) 
            DO UPDATE SET valor = EXCLUDED.valor";
            
    $stmt = pg_prepare($link, "upsert_config_query", $sql);

    if (!$stmt) {
        throw new Exception('Falha ao preparar a consulta de atualização.');
    }

    foreach ($novas_configuracoes as $chave => $valor) {
        if (!empty($chave) && isset($valor)) {
            // A ordem dos parâmetros no pg_execute foi ajustada para [$chave, $valor] para corresponder a VALUES ($1, $2)
            $result = pg_execute($link, "upsert_config_query", [$chave, $valor]);

            if (!$result) {
                throw new Exception("Erro ao tentar salvar a configuração '{$chave}'.");
            }
        }
    }
    
    pg_query($link, "COMMIT");

    $response = ['status' => 'success', 'message' => 'Configurações salvas com sucesso!'];

} catch (Exception $exception) {
    pg_query($link, "ROLLBACK");
    // Mensagem de erro foi melhorada para incluir o erro real do banco de dados (útil para depuração)
    $response['message'] = 'Erro ao salvar: ' . $exception->getMessage();
}

pg_close($link);
echo json_encode($response);
?>