<?php
// templates/content.php
?>
<div id="content">
    <div class="content-header">
        <h3 id="contentTitle">
            <?php
            if ($selectedEmail) {
                echo '📨 ' . safeHtml($selectedEmail['subject']);
            } else {
                echo '📭 ' . safeHtml($allowedFolders[$folder]);
            }
            ?>
        </h3>
        <div class="content-toolbar">
            <?php if ($selectedEmail): ?>
            <button class="btn-secondary" id="btnReadEmail" title="Leer correo en voz alta">
                 Leer
            </button>
            <button class="btn-secondary" onclick="window.location.href='?folder=<?= urlencode($folder) ?>&msg=<?= (int)$selectedEmail['num'] ?>&reply=1'">
                 Responder
            </button>
            <?php endif; ?>
            <button class="btn-primary" onclick="window.location.href='?folder=<?= urlencode($folder) ?>&compose=1'">
                 Nuevo mensaje
            </button>
        </div>
    </div>
    
    <div class="content-body">
        <!-- SECCIÓN 1: LECTOR DE CORREOS (SIEMPRE VISIBLE) -->
        <div class="content-section email-reader-section">
            <div class="section-title">
                <h4 style="color: var(--gmail-text); font-weight: 500; font-size: 16px;">
                    <?= $selectedEmail ? ' Mensaje seleccionado' : ' Vista previa' ?>
                </h4>
            </div>
            
            <?php if ($selectedEmail): ?>
            <div class="email-reader scrollbar-custom">
                <div class="email-meta">
                    <div class="email-subject"><?= safeHtml($selectedEmail['subject']) ?></div>
                    <div class="meta-row">
                        <span class="label">De:</span>
                        <span><?= safeHtml($selectedEmail['from']) ?></span>
                    </div>
                    <div class="meta-row">
                        <span class="label">Para:</span>
                        <span>Joshué Freire</span>
                    </div>
                    <div class="meta-row">
                        <span class="label">Fecha:</span>
                        <span><?= safeHtml($selectedEmail['date']) ?></span>
                    </div>
                </div>
                
                <?php if ($selectedSummary): ?>
                <div class="email-summary">
                    <div class="summary-title">Resumen automático por IA</div>
                    <div><?= nl2br(safeHtml($selectedSummary)) ?></div>
                </div>
                <textarea id="emailSummaryText" class="vh"><?= safeHtml($selectedSummary) ?></textarea>

                <?php endif; ?>
                
                <div class="email-body">
                    <?= nl2br(safeHtml($selectedBody)) ?>
                </div>
                <textarea id="emailSubjectText" class="vh"><?= safeHtml($selectedEmail['subject']) ?></textarea>
                <textarea id="emailBodyText" class="vh"><?= safeHtml($selectedBody) ?></textarea>
            </div>
            <?php else: ?>
            <div class="email-reader-placeholder scrollbar-custom">
                <div style="text-align: center; padding: 60px; color: var(--gmail-text-muted);">
                    <div style="font-size: 72px; margin-bottom: 20px; opacity: 0.5;"></div>
                    <h3 style="font-weight: 400; margin-bottom: 10px;">
                        <?php if (empty($emails)): ?>
                        No hay mensajes en <?= safeHtml($allowedFolders[$folder]) ?>
                        <?php else: ?>
                        Selecciona un mensaje para leerlo
                        <?php endif; ?>
                    </h3>
                    <p>Haz clic en cualquier mensaje de la lista izquierda.</p>
                </div>
            </div>
            <?php endif; ?>
        </div>
        
        <!-- SECCIÓN 2: COMPOSITOR DE CORREOS (SIEMPRE VISIBLE) -->
        <div class="content-section composer-section">
            <div class="section-title" style="display: flex; justify-content: space-between; align-items: center;">
                <h4 style="color: var(--gmail-text); font-weight: 500; font-size: 16px;">
                    <?= ($replyMode && $selectedEmail) ? 'Responder a este mensaje' : 'Redactar nuevo mensaje' ?>
                </h4>
                <span class="status-badge" style="background: var(--gmail-primary); color: white; padding: 4px 12px; border-radius: 12px; font-size: 12px; font-weight: 500;">
                    IA + Dictado
                </span>
            </div>
            
            <!-- EL COMPOSITOR SIEMPRE SE MUESTRA -->
            <?php include 'templates/composer.php'; ?>
        </div>
    </div>
</div>
<script>
let speechUtterance = null;
let isReading = false;

function readEmailAloud() {
    const subject = document.getElementById('emailSubjectText')?.value;
    const summary = document.getElementById('emailSummaryText')?.value;
    const body = summary || document.getElementById('emailBodyText')?.value;
    const btn = document.getElementById('btnReadEmail');

    if (!subject || !body) {
        alert('No hay ningún correo para leer.');
        return;
    }

    // Si ya está leyendo → detener
    if (isReading) {
        window.speechSynthesis.cancel();
        isReading = false;
        btn.textContent = '🔊 Leer';
        return;
    }

    // Texto a leer
    const textToRead =
        'Asunto. ' + subject + '. ' +
        'Mensaje. ' + body;

    speechUtterance = new SpeechSynthesisUtterance(textToRead);
    speechUtterance.lang = 'es-ES';
    speechUtterance.rate = 1;
    speechUtterance.pitch = 1;
    speechUtterance.volume = 1;

    speechUtterance.onstart = () => {
        isReading = true;
        btn.textContent = '⏹️ Detener';
    };

    speechUtterance.onend = () => {
        isReading = false;
        btn.textContent = '🔊 Leer';
    };

    speechUtterance.onerror = () => {
        isReading = false;
        btn.textContent = '🔊 Leer';
    };

    window.speechSynthesis.cancel(); // limpiar cola
    window.speechSynthesis.speak(speechUtterance);
}

// Vincular botón
document.addEventListener('DOMContentLoaded', () => {
    const btn = document.getElementById('btnReadEmail');
    if (btn) {
        btn.addEventListener('click', readEmailAloud);
    }
});
</script>