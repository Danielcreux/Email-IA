<?php
// templates/email-list.php
?>
<div id="emailList" class="scrollbar-custom">
    <div class="list-header">
        <h3 id="listTitle"><?= safeHtml($allowedFolders[$folder]) ?></h3>
        <div class="list-actions">
            <button class="btn-secondary" onclick="window.location.reload()">↻</button>
        </div>
    </div>
    
    <div class="list-container">
        <?php if (empty($emails)): ?>
        <div class="email-item" style="text-align: center; color: var(--gmail-text-muted); padding: 40px;">
            <div style="font-size: 48px; margin-bottom: 16px;">📭</div>
            <div>No hay mensajes en esta carpeta.</div>
        </div>
        <?php else: ?>
            <?php foreach ($emails as $msg): 
                $isSelected = ($selectedMsgNo && $msg['num'] === $selectedMsgNo);
                // Crear vista previa del remitente
                $preview = safeHtml($msg['from']) . '...';
            ?>
           <a href="?folder=<?= urlencode($folder) ?>&msg=<?= (int)$msg['num'] ?>" 
           class="email-item-link" 
           style="text-decoration: none; display: block;">
            <div class="email-item <?= $isSelected ? 'selected' : '' ?> <?= !$msg['seen'] ? 'unread' : '' ?>"
                 style="cursor: pointer;">
                <div class="email-header">
                    <div class="email-sender">
                        <?php if (!$msg['seen']): ?>
                        <span style="background: var(--gmail-primary); color: white; padding: 2px 6px; border-radius: 10px; font-size: 11px; margin-right: 8px;">NUEVO</span>
                        <?php endif; ?>
                        <?= safeHtml($msg['from']) ?>
                    </div>
                    <div class="email-date"><?= safeHtml($msg['date']) ?></div>
                </div>
                <div class="email-subject">
                    <?= safeHtml($msg['subject']) ?>
                </div>
                <div class="email-preview">
                    <?= $preview ?>
                </div>
            </div>
        </a>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>