<?php

if (!isset($folder) || empty($folder)) {
    $folder = 'INBOX';
}
?>
<div id="nav" class="scrollbar-custom">
    <i class="fi fi-sr-envelope"></i>
    <div class="nav-header">
        <h3><span>Mail IA</span></h3>
    </div>
    
    <div class="nav-menu">
        <?php foreach ($allowedFolders as $key => $label): 
            $isActive = ($key === $folder);
            $count = isset($folderCounts[$key]) ? (int)$folderCounts[$key] : 0;
            
            // Iconos específicos para IONOS
            $icon = match(true) {
                $key === 'INBOX' => '📥',
                $key === 'Elementos enviados' => '📤',
                $key === 'Borradores' => '✏️',
                $key === 'Papelera' => '🗑️',
                $key === 'Spam' => '🚫',
                default => '📁'
            };
        ?>
        <a href="?folder=<?= urlencode($key) ?>" 
           class="nav-item <?= $isActive ? 'active' : '' ?>"
           onclick="if('<?= $key ?>' === 'INBOX' && !window.location.href.includes('folder=')) { window.location.href='?folder=INBOX'; return false; }">
            <span class="icon"><?= $icon ?></span>
            <span><?= safeHtml($label) ?></span>
            <?php if ($count > 0): ?>
            <span class="badge"><?= $count ?></span>
            <?php endif; ?>
        </a>
        <?php endforeach; ?>
    </div>
    
    <div class="nav-compose">
        <button class="btn-compose" onclick="window.location.href='?folder=<?= urlencode($folder) ?>&compose=1'">
            <span>Redactar</span>
        </button>
    </div>
    
    <div class="nav-info">
        <strong>Email:</strong> Correo empresarial<br>
        <strong>IA:</strong> Corrección automática<br>
        <strong>Dictado:</strong> Español
    </div>
    
    <?php if ($imapError): ?>
    <div class="nav-error">
        <strong>IMAP:</strong> <?= safeHtml($imapError) ?>
    </div>
    <?php endif; ?>
</div>