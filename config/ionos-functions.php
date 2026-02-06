<?php
// Funciones específicas para manejar nombres de carpetas IONOS

function normalizeIonosFolderName(string $folder): string
{
    // Mapeo de nombres de carpetas IONOS
    $ionosMapping = [
        'INBOX'              => 'INBOX',
        'Elementos enviados' => 'Elementos enviados',
        'Borradores'         => 'Borradores',
        'Papelera'           => 'Papelera',
        'Spam'               => 'Spam',
        'Sent'               => 'Elementos enviados',
        'Drafts'             => 'Borradores',
        'Trash'              => 'Papelera'
    ];
    
    return $ionosMapping[$folder] ?? $folder;
}

function getIonosFolderDisplayName(string $folderName): string
{
    $displayNames = [
        'INBOX'              => 'Recibidos',
        'Elementos enviados' => 'Enviados',
        'Borradores'         => 'Borradores',
        'Papelera'           => 'Papelera',
        'Spam'               => 'Spam'
    ];
    
    return $displayNames[$folderName] ?? $folderName;
}