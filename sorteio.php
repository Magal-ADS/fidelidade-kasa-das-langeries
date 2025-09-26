<?php
// /sorteio.php (VERSÃO CORRIGIDA)

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['cargo']) || $_SESSION['cargo'] != 1) {
    header("Location: login.php");
    exit();
}
require_once 'php/db_config.php';

$total_cupons = 0;

// =================== CORREÇÃO CRÍTICA APLICADA AQUI ===================
// A cláusula "WHERE" foi removida para contar TODOS os cupons da urna.
$sql_cupons = "SELECT COUNT(*) as total FROM sorteio";
$result_cupons = pg_query($link, $sql_cupons);

if ($result_cupons) {
    $row = pg_fetch_assoc($result_cupons);
    $total_cupons = $row['total'];
}
// ====================================================================

include 'templates/header.php';
?>

<title>Realizar Sorteio</title>

<title>Realizar Sorteio</title>

<style>
    .page-header h1 { color: var(--cor-dourado) !important; }
    .page-header p { color: var(--cor-branco) !important; opacity: 0.8; }
    .urna-info { background-color: rgba(255, 255, 255, 0.1); color: var(--cor-branco); border-color: rgba(255, 255, 255, 0.2); }
    .urna-info span { color: var(--cor-dourado); }
    .ganhador-card { background: var(--cor-cinza-escuro); border: 1px solid var(--cor-dourado); color: var(--cor-branco); }
    .ganhador-card h2 { color: var(--cor-dourado); }
    .sorteio-container { text-align: center; position: relative; }
    .urna-info { padding: 0.75rem 1.5rem; border-radius: 50px; display: inline-flex; align-items: center; gap: 0.75rem; margin-bottom: 2.5rem; box-shadow: 0 4px 10px rgba(0,0,0,0.05); font-size: 1.1rem; font-weight: 500; }
    #btn-sortear { padding: 1rem 3rem; font-size: 1.2rem; font-weight: 700; }
    .resultado-container { position: relative; z-index: 5; margin-top: 3rem; opacity: 0; transform: translateY(20px); transition: opacity 0.5s ease, transform 0.5s ease; visibility: hidden; }
    .resultado-container.visible { opacity: 1; transform: translateY(0); visibility: visible; }
    .ganhador-card { padding: 2rem; border-radius: 12px; box-shadow: 0 10px 20px rgba(0,0,0, 0.3); max-width: 500px; margin: 0 auto; }
    .ganhador-card .nome { font-size: 2.5rem; font-weight: 700; margin: 0.5rem 0; min-height: 50px; }
    /* A classe .contato não é mais necessária, mas deixamos aqui caso queira usar no futuro */
    .ganhador-card .contato { margin-top: 1.5rem; opacity: 0.9; } 
    .countdown-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.85); display: flex; justify-content: center; align-items: center; z-index: 1000; opacity: 0; visibility: hidden; transition: opacity 0.3s ease; }
    .countdown-overlay.visible { opacity: 1; visibility: visible; }
    #countdown-text { font-size: 15vw; font-weight: 700; color: white; animation: countdown-pop 1s ease-out forwards; }
    @keyframes countdown-pop { 0% { transform: scale(0.5); opacity: 0; } 50% { transform: scale(1.1); opacity: 1; } 100% { transform: scale(1); opacity: 1; } }
    #animation-container { position: fixed; top: 0; left: 0; width: 100%; height: 100%; z-index: 1001; pointer-events: none; }
</style>

<div class="page-container sorteio-container">
    <header class="page-header">
        <h1>Sorteador</h1>
        <p>Clique no botão abaixo para sortear um ganhador de forma aleatória.</p>
    </header>
    
    <div class="urna-info"> 🎟️ Total de números da sorte na urna: <span><?php echo $total_cupons; ?></span> </div>
    
    <div> <button id="btn-sortear" class="btn btn-verde" <?php echo ($total_cupons == 0) ? 'disabled' : ''; ?>> Realizar Sorteio! </button> </div>
    
    <div id="resultado-container" class="resultado-container">
        <div class="ganhador-card">
            <h2>🎉 O Ganhador é... 🎉</h2>
            <p id="ganhador-nome" class="nome">...</p>
            
            </div>
    </div>
</div>

<div id="countdown-overlay" class="countdown-overlay"> <span id="countdown-text"></span> </div>
<div id="animation-container"></div>
<script src="https://cdn.jsdelivr.net/npm/canvas-confetti@1.9.3/dist/confetti.browser.min.js"></script>
<script src="js/fireworks.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const animationContainer = document.getElementById('animation-container');
    const btnSortear = document.getElementById('btn-sortear');
    const resultadoContainer = document.getElementById('resultado-container');
    const nomeGanhador = document.getElementById('ganhador-nome');
    // =================== ALTERAÇÃO IMPORTANTE AQUI ===================
    // As constantes para cpfGanhador e whatsappGanhador foram removidas.
    // ===============================================================
    const countdownOverlay = document.getElementById('countdown-overlay');
    const countdownText = document.getElementById('countdown-text');
    let listaDeNomes = [];

    fetch('php/get_participantes.php').then(res => res.json()).then(data => {
        if (data.status === 'success' && data.participantes.length > 0) listaDeNomes = data.participantes;
    });

    // Função startConfetti (inalterada)
    function startConfetti() { const duration = 5 * 1000; const animationEnd = Date.now() + duration; (function frame() { confetti({ particleCount: 5, angle: 60, spread: 55, origin: { x: 0 } }); confetti({ particleCount: 5, angle: 120, spread: 55, origin: { x: 1 } }); if (Date.now() < animationEnd) { requestAnimationFrame(frame); } }()); }

    btnSortear.addEventListener('click', function() {
        if (listaDeNomes.length === 0) { alert("Não há participantes para sortear!"); return; }
        
        btnSortear.disabled = true; btnSortear.textContent = 'Aguarde...'; resultadoContainer.classList.remove('visible');
        let count = 3; countdownOverlay.classList.add('visible'); countdownText.textContent = count;
        
        let countdownInterval = setInterval(() => {
            count--; 
            countdownText.style.animation = 'none'; 
            void countdownText.offsetWidth; 
            countdownText.style.animation = 'countdown-pop 1s ease-out forwards';
            if (count > 0) { 
                countdownText.textContent = count; 
            } else {
                countdownText.textContent = 'SORTEANDO!'; 
                clearInterval(countdownInterval); 
                setTimeout(iniciarSlotMachine, 1000);
            }
        }, 1000);
    
        function iniciarSlotMachine() {
            countdownOverlay.classList.remove('visible'); 
            resultadoContainer.classList.add('visible');
            let animacaoIntervalo = setInterval(() => {
                const nomeAleatorio = listaDeNomes[Math.floor(Math.random() * listaDeNomes.length)];
                nomeGanhador.textContent = nomeAleatorio;
            }, 80);
            setTimeout(() => { clearInterval(animacaoIntervalo); buscarVencedorReal(); }, 3000);
        }

        function buscarVencedorReal() {
            fetch('php/realizar_sorteio.php', { method: 'POST' })
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    nomeGanhador.textContent = data.ganhador.nome_completo;
                    
                    // =================== ALTERAÇÃO IMPORTANTE AQUI ===================
                    // As linhas que preenchiam o CPF e WhatsApp foram removidas.
                    // ===============================================================

                    btnSortear.textContent = 'Sortear Novamente';
                    const fireworks = new Fireworks(animationContainer);
                    fireworks.start();
                    startConfetti();
                    setTimeout(() => fireworks.stop(), 5000);

                } else {
                    alert('Erro: ' + data.message);
                    btnSortear.textContent = 'Tentar Novamente';
                }
                btnSortear.disabled = false;
            });
        }
    });
});
</script>
<?php
include 'templates/footer.php';
?>