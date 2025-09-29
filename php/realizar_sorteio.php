<?php
// /php/realizar_sorteio.php (VERSÃO CORRIGIDA E COMPLETA)

session_start();
require_once "db_config.php";
header('Content-Type: application/json');

$response = ['status' => 'error', 'message' => 'Acesso não autorizado.'];

// Verificação de segurança: Apenas administradores podem acessar.
if (!isset($_SESSION['usuario_id']) || !isset($_SESSION['cargo']) || $_SESSION['cargo'] != 1) {
    echo json_encode($response);
    exit;
}

$winner_data = null;
$winner_id = null;

// Inicia a transação para garantir a integridade dos dados
pg_query($link, "BEGIN");

try {
    // ====================== MUDANÇA 1: Sorteio em toda a base ======================
    // A cláusula "WHERE usuario_id = $1" foi REMOVIDA para sortear entre todos os
    // cupons existentes, conforme a regra de negócio do Administrador.
    $sql_sorteio = "SELECT cliente_id FROM sorteio ORDER BY RANDOM() LIMIT 1";
    
    $stmt_sorteio = pg_prepare($link, "realizar_sorteio_query", $sql_sorteio);
    if (!$stmt_sorteio) { throw new Exception('Falha ao preparar a consulta do sorteio.'); }
    
    // A execução agora é feita com um array vazio, pois não há mais parâmetros na query.
    $result_sorteio = pg_execute($link, "realizar_sorteio_query", []);
    if (!$result_sorteio) { throw new Exception('Falha ao executar a consulta do sorteio.'); }

    if (pg_num_rows($result_sorteio) > 0) {
        $sorteado = pg_fetch_assoc($result_sorteio);
        $winner_id = $sorteado['cliente_id'];

        // Busca os dados do cliente sorteado (esta parte não precisou de alteração).
        $sql_cliente = "SELECT nome_completo FROM clientes WHERE id = $1";
        $stmt_cliente = pg_prepare($link, "buscar_ganhador_query", $sql_cliente);
        if (!$stmt_cliente) { throw new Exception('Falha ao preparar a busca pelo ganhador.'); }
        
        $result_cliente = pg_execute($link, "buscar_ganhador_query", [$winner_id]);
        if (!$result_cliente) { throw new Exception('Falha ao buscar os dados do ganhador.'); }

        if(pg_num_rows($result_cliente) > 0) {
            $winner_data = pg_fetch_assoc($result_cliente);
        }
        
        if (!$winner_data) {
            throw new Exception('Ganhador sorteado não encontrado na base de clientes.');
        }

        // ====================== MUDANÇA 2: Deleção de todos os cupons do ganhador ======================
        // A cláusula "AND usuario_id = $2" foi REMOVIDA para garantir que TODOS os
        // cupons do cliente sorteado sejam deletados, independente de qual usuário os criou.
        $sql_delete = "DELETE FROM sorteio WHERE cliente_id = $1";
        
        $stmt_delete = pg_prepare($link, "deletar_cupons_query", $sql_delete);
        if (!$stmt_delete) { throw new Exception('Falha ao preparar a deleção de cupons.'); }
        
        // A execução agora passa apenas o ID do ganhador como parâmetro.
        $result_delete = pg_execute($link, "deletar_cupons_query", [$winner_id]);
        if (!$result_delete) { throw new Exception('Falha ao deletar os cupons do ganhador.'); }
        
        // Se todas as operações foram bem-sucedidas, confirma as alterações no banco.
        pg_query($link, "COMMIT");

        $response = [
            'status' => 'success',
            'ganhador' => $winner_data
        ];

    } else {
        // Isso só acontecerá se a tabela 'sorteio' estiver completamente vazia.
        pg_query($link, "ROLLBACK");
        $response['message'] = 'Não há nenhum número da sorte cadastrado para sortear!';
    }
} catch (Exception $exception) {
    // Em caso de qualquer erro no bloco 'try', desfaz todas as alterações.
    pg_query($link, "ROLLBACK");
    $response['message'] = 'Ocorreu um erro durante o sorteio: ' . $exception->getMessage();
}

pg_close($link);
echo json_encode($response);
?>