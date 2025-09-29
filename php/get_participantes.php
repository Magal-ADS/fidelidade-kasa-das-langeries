<?php
// /php/get_participantes.php (VERSÃO CORRIGIDA E COMPLETA)

session_start();
require_once "db_config.php";
header('Content-Type: application/json');

// Bloco de segurança permanece o mesmo, garantindo que só admins acessem.
if (!isset($_SESSION['usuario_id']) || !isset($_SESSION['cargo']) || $_SESSION['cargo'] != 1) {
    echo json_encode(['status' => 'error', 'message' => 'Acesso não autorizado.', 'participantes' => []]);
    exit;
}

$participantes = [];

// ====================== MUDANÇA: Busca em toda a base ======================
// A cláusula "WHERE s.usuario_id = $1" foi REMOVIDA para que a lista de
// participantes (usada na animação) inclua clientes de todos os usuários,
// refletindo a regra de negócio do Administrador.
$sql = "SELECT DISTINCT c.nome_completo 
        FROM clientes c
        JOIN sorteio s ON c.id = s.cliente_id";

$stmt = pg_prepare($link, "get_participantes_query", $sql);

if ($stmt) {
    // A execução agora é feita com um array vazio, pois não há mais parâmetros na query.
    $result = pg_execute($link, "get_participantes_query", []);

    if ($result) {
        while ($row = pg_fetch_assoc($result)) {
            $participantes[] = $row['nome_completo'];
        }
        
        echo json_encode(['status' => 'success', 'participantes' => $participantes]);

    } else {
        echo json_encode(['status' => 'error', 'message' => 'Erro ao executar a consulta.', 'participantes' => []]);
    }

} else {
    echo json_encode(['status' => 'error', 'message' => 'Erro na preparação da consulta ao banco de dados.', 'participantes' => []]);
}

pg_close($link);
?>