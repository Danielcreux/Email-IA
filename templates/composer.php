<?php
// templates/composer.php - Composer mejorado
?>
<div class="email-composer" id="emailComposer">
    <div class="composer-header">
        <div class="title">
            <?php 
            if ($replyMode && $selectedEmail) {
                echo 'Responder a: ' . safeHtml($selectedEmail['from']);
            } else {
                echo 'Nuevo mensaje';
            }
            ?>
        </div>
        <div class="status-badge">IA + Dictado</div>
    </div>
    
    <form method="post" id="mailForm" class="composer-form">
        <input type="hidden" name="ajax" value="0">
        
        <div class="composer-fields">
            <div class="field-row">
                <label>Para</label>
                <div class="field-input">
                    <input type="email" name="to" required 
                           placeholder="destinatario@ejemplo.com"
                           value="<?= safeHtml($prefillTo) ?>"
                           id="emailTo"
                           autocomplete="email">
                </div>
            </div>
            
            <div class="field-row">
                <label>Asunto</label>
                <div class="field-input">
                    <input type="text" id="subjectFinal" 
                           placeholder="Asunto corregido por IA"
                           readonly 
                           value="<?= safeHtml($prefillSubjectFinal) ?>"
                           style="background: #f8f9fa; color: #333;">
                    <input type="hidden" name="final_subject" id="final_subject"
                           value="<?= safeHtml($prefillSubjectFinal) ?>">
                </div>
                <button type="button" class="voice-btn" id="btnDictarSubject" title="Dictar asunto">
                    <span class="status-dot"></span>
                    <span>Asunto</span>
                </button>
            </div>
        </div>
        
        <!-- Campos ocultos para procesamiento -->
        <textarea id="subjectRaw" class="vh" placeholder="Habla para dictar el asunto..."><?= safeHtml($prefillSubjectRaw) ?></textarea>
        <textarea id="texto" class="vh" placeholder="Habla para dictar el cuerpo del mensaje..."></textarea>
        <textarea name="final_body" id="final_body" class="vh"></textarea>
        
        <div class="composer-body">
            <div class="body-editor" id="bodyEditor">
                <div id="bodyResultBox" class="body-placeholder">
                    🎙️ <strong>Dicta el cuerpo del mensaje</strong><br>
                    Usa el botón "🎙️ Cuerpo" para comenzar a dictar.<br>
                    La IA corregirá automáticamente tu texto cada 3 segundos.
                </div>
            </div>
        </div>
        
        <div class="composer-footer">
            <div class="status-info">
                <div class="status-item">
                    <span id="estadoDictado">🎤 Dictado disponible</span>
                </div>
                <div class="status-item">
                    <span>Asunto: </span>
                    <span id="estadoIA_subject" class="<?= $prefillSubjectFinal ? 'status-ok' : 'status-warning' ?>">
                        <?= $prefillSubjectFinal ? '✅ Listo' : '⏳ Pendiente' ?>
                    </span>
                </div>
                <div class="status-item">
                    <span>Cuerpo: </span>
                    <span id="estadoIA_body" class="status-warning">⏳ Pendiente</span>
                </div>
            </div>
            
            <div class="composer-actions">
                <button type="button" class="voice-btn" id="btnDictarBody" title="Dictar cuerpo del mensaje">
                    <span class="status-dot"></span>
                    <span>🎙️ Cuerpo</span>
                </button>
                <button type="button" class="btn-secondary" onclick="cancelCompose()">
                    Cancelar
                </button>
                <button type="submit" class="btn btn-primary" name="send_email" value="1" id="btnSend">
                    <span class="send-icon">📤</span> Enviar
                </button>
            </div>
        </div>
        
        <?php if ($sendMessage !== null): ?>
        <div class="send-status <?= $sendOk ? 'success' : 'error' ?>" id="sendStatus">
            <?= safeHtml($sendMessage) ?>
            <button type="button" onclick="this.parentElement.remove()" 
                    style="margin-left: 10px; background: none; border: none; color: inherit; cursor: pointer;">✕</button>
        </div>
        <?php endif; ?>
    </form>
</div>

<script>
// Variables globales para el dictado
let recognition = null;
let isRecordingSubject = false;
let isRecordingBody = false;
let bodyUpdateTimer = null;
let lastFinalResult = ''; // Para evitar repeticiones
let sessionText = ''; // Texto de la sesión actual

// Inicializar el reconocimiento de voz
function initSpeechRecognition() {
    const SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;
    
    if (!SpeechRecognition) {
        alert('El reconocimiento de voz no está soportado en este navegador. Usa Chrome, Edge o Safari.');
        return false;
    }
    
    recognition = new SpeechRecognition();
    recognition.lang = 'es-ES';
    recognition.continuous = false; // Cambiado a false para evitar repeticiones
    recognition.interimResults = true;
    recognition.maxAlternatives = 1;
    
    // Eventos del reconocimiento
    recognition.onstart = function() {
        console.log('Dictado activado');
        sessionText = ''; // Reiniciar texto de sesión
    };
    
    recognition.onresult = function(event) {
        let interimTranscript = '';
        let finalTranscript = '';
        
        for (let i = event.resultIndex; i < event.results.length; i++) {
            const transcript = event.results[i][0].transcript;
            if (event.results[i].isFinal) {
                finalTranscript += transcript;
            } else {
                interimTranscript += transcript;
            }
        }
        
        // Procesar solo si hay texto nuevo
        if (finalTranscript && finalTranscript !== lastFinalResult) {
            lastFinalResult = finalTranscript;
            
            if (isRecordingSubject) {
                const subjectRaw = document.getElementById('subjectRaw');
                // NO concatenar, reemplazar el texto completo
                subjectRaw.value = finalTranscript.trim();
                
                // Procesar inmediatamente con IA
                setTimeout(() => {
                    processTextWithAI('subject');
                }, 500);
                
            } else if (isRecordingBody) {
                const textoField = document.getElementById('texto');
                // Acumular en la sesión actual
                sessionText += (sessionText ? ' ' : '') + finalTranscript.trim();
                textoField.value = sessionText;
                
                // Mostrar texto en el área de vista previa
                const bodyResultBox = document.getElementById('bodyResultBox');
                bodyResultBox.innerHTML = createBodyPreview(sessionText, interimTranscript);
                
                // Reiniciar timer para procesamiento IA
                if (bodyUpdateTimer) {
                    clearTimeout(bodyUpdateTimer);
                }
                
                // Procesar con IA después de pausa
                bodyUpdateTimer = setTimeout(() => {
                    processTextWithAI('body');
                }, 2000); // Reducido a 2 segundos
            }
        } else if (interimTranscript) {
            // Mostrar texto interino
            if (isRecordingBody) {
                const bodyResultBox = document.getElementById('bodyResultBox');
                const displayText = sessionText + (sessionText ? ' ' : '') + interimTranscript;
                bodyResultBox.innerHTML = createBodyPreview(displayText, interimTranscript);
            }
        }
    };
    
    recognition.onerror = function(event) {
        console.error('Error en reconocimiento de voz:', event.error);
        
        // Solo detener si es un error crítico
        if (event.error === 'not-allowed' || event.error === 'service-not-allowed') {
            stopRecording();
            updateRecordingStatus('error', 'Error en dictado: ' + event.error);
        } else {
            // Para otros errores, reiniciar automáticamente
            setTimeout(() => {
                if (isRecordingSubject || isRecordingBody) {
                    try {
                        recognition.start();
                    } catch (e) {
                        console.error('Error al reiniciar:', e);
                    }
                }
            }, 100);
        }
    };
    
    recognition.onend = function() {
        console.log('Dictado finalizado - sesión terminada');
        
        // Reiniciar automáticamente solo si aún estamos en modo grabación
        if (isRecordingSubject || isRecordingBody) {
            setTimeout(() => {
                if (isRecordingSubject || isRecordingBody) {
                    try {
                        // Reiniciar con texto de sesión preservado
                        recognition.start();
                    } catch (e) {
                        console.error('Error al reiniciar dictado:', e);
                        stopRecording();
                    }
                }
            }, 300); // Pequeña pausa antes de reiniciar
        }
    };
    
    return true;
}

// Crear vista previa del cuerpo
function createBodyPreview(finalText, interimText = '') {
    if (!finalText.trim() && !interimText.trim()) {
        return '🎙️ <strong>Dicta el cuerpo del mensaje</strong><br>' +
               'Usa el botón "🎙️ Cuerpo" para comenzar a dictar.<br>' +
               'La IA corregirá automáticamente tu texto cuando pauses.';
    }
    
    const displayFinal = finalText || '';
    const displayInterim = interimText || '';
    const fullText = displayFinal + (displayFinal && displayInterim ? ' ' : '') + displayInterim;
    
    // Limitar a 1500 caracteres para vista previa
    const previewText = fullText.length > 1500 ? 
        fullText.substring(0, 1500) + '...' : 
        fullText;
    
    let html = `
        <div style="padding: 10px; background: #e3f2fd; border-radius: 4px; margin-bottom: 10px;">
            <strong>🎤 Dictando... (habla claramente)</strong>
        </div>
        <div style="white-space: pre-wrap; font-family: inherit; line-height: 1.6; padding: 10px; background: white; border-radius: 4px; min-height: 100px;">
    `;
    
    // Mostrar texto final en negro
    if (displayFinal) {
        html += `<span style="color: #202124;">${displayFinal.replace(/\n/g, '<br>')}</span>`;
    }
    
    // Mostrar texto interino en gris
    if (displayInterim) {
        if (displayFinal) html += ' ';
        html += `<span style="color: #5f6368; font-style: italic;">${displayInterim.replace(/\n/g, '<br>')}</span>`;
    }
    
    html += `</div>`;
    
    // Contador de palabras
    const wordCount = previewText.trim().split(/\s+/).filter(word => word.length > 0).length;
    html += `
        <div style="margin-top: 10px; padding: 8px; background: #f8f9fa; border-radius: 4px; font-size: 12px; display: flex; justify-content: space-between;">
            <div>
                ${displayInterim ? '⏳ <strong>Escuchando...</strong>' : '✅ <strong>Pausa detectada</strong>'}
            </div>
            <div>
                📊 <strong>${wordCount}</strong> palabras
            </div>
        </div>
    `;
    
    return html;
}

// Iniciar grabación para asunto
function startRecordingSubject() {
    if (!recognition) {
        if (!initSpeechRecognition()) {
            return;
        }
    }
    
    stopRecording(); // Detener cualquier grabación en curso
    lastFinalResult = ''; // Reiniciar
    sessionText = ''; // Reiniciar
    
    isRecordingSubject = true;
    isRecordingBody = false;
    
    // Limpiar campos previos
    document.getElementById('subjectRaw').value = '';
    document.getElementById('subjectFinal').value = '';
    document.getElementById('final_subject').value = '';
    
    try {
        recognition.start();
        updateRecordingStatus('subject', 'grabando');
    } catch (e) {
        console.error('Error al iniciar grabación:', e);
        updateRecordingStatus('error', 'Error al iniciar dictado');
    }
}

// Iniciar grabación para cuerpo
function startRecordingBody() {
    if (!recognition) {
        if (!initSpeechRecognition()) {
            return;
        }
    }
    
    stopRecording(); // Detener cualquier grabación en curso
    lastFinalResult = ''; // Reiniciar
    sessionText = ''; // Reiniciar
    
    isRecordingBody = true;
    isRecordingSubject = false;
    
    // Limpiar campo previo
    document.getElementById('texto').value = '';
    
    try {
        recognition.start();
        updateRecordingStatus('body', 'grabando');
    } catch (e) {
        console.error('Error al iniciar grabación:', e);
        updateRecordingStatus('error', 'Error al iniciar dictado');
    }
}

// Detener grabación
function stopRecording() {
    const wasRecording = isRecordingSubject || isRecordingBody;
    
    isRecordingSubject = false;
    isRecordingBody = false;
    
    if (recognition) {
        try {
            recognition.stop();
        } catch (e) {
            // Ignorar errores al detener
        }
    }
    
    updateRecordingStatus('idle', '');
    
    if (bodyUpdateTimer) {
        clearTimeout(bodyUpdateTimer);
        bodyUpdateTimer = null;
    }
    
    // Procesar texto final si se estaba grabando
    if (wasRecording) {
        const textoField = document.getElementById('texto');
        if (textoField && textoField.value.trim()) {
            // Procesar inmediatamente al detener
            processTextWithAI('body');
        }
    }
}

// Actualizar estado visual del dictado
function updateRecordingStatus(type, status) {
    const estadoDictado = document.getElementById('estadoDictado');
    const btnSubject = document.getElementById('btnDictarSubject');
    const btnBody = document.getElementById('btnDictarBody');
    
    // Resetear todos los botones
    btnSubject.classList.remove('active');
    btnBody.classList.remove('active');
    
    switch(type) {
        case 'subject':
            btnSubject.classList.add('active');
            estadoDictado.innerHTML = '<span class="recording-indicator"></span> Dictando asunto...';
            estadoDictado.style.color = '#d93025';
            estadoDictado.style.fontWeight = 'bold';
            break;
            
        case 'body':
            btnBody.classList.add('active');
            estadoDictado.innerHTML = '<span class="recording-indicator"></span> Dictando cuerpo...';
            estadoDictado.style.color = '#d93025';
            estadoDictado.style.fontWeight = 'bold';
            break;
            
        case 'idle':
            estadoDictado.textContent = '🎤 Dictado disponible (haz clic para comenzar)';
            estadoDictado.style.color = '';
            estadoDictado.style.fontWeight = 'normal';
            break;
            
        case 'error':
            estadoDictado.textContent = '❌ ' + status;
            estadoDictado.style.color = '#d93025';
            break;
    }
}

// Procesar texto con IA
function processTextWithAI(kind) {
    let textField, resultField, statusField;
    
    if (kind === 'subject') {
        textField = document.getElementById('subjectRaw');
        resultField = document.getElementById('subjectFinal');
        statusField = document.getElementById('estadoIA_subject');
    } else {
        textField = document.getElementById('texto');
        resultField = document.getElementById('final_body');
        statusField = document.getElementById('estadoIA_body');
    }
    
    const textoOriginal = textField.value.trim();
    
    if (!textoOriginal) {
        if (kind === 'body') {
            const bodyResultBox = document.getElementById('bodyResultBox');
            bodyResultBox.innerHTML = '🎙️ <strong>Dicta el cuerpo del mensaje</strong><br>' +
                                    'Usa el botón "🎙️ Cuerpo" para comenzar a dictar.<br>' +
                                    'La IA corregirá automáticamente tu texto cuando pauses.';
        }
        return;
    }
    
    // Mostrar estado de procesamiento
    statusField.textContent = '⏳ Procesando con IA...';
    statusField.className = 'status-warning';
    
    // Mostrar mensaje de procesamiento en el área de vista previa
    if (kind === 'body') {
        const bodyResultBox = document.getElementById('bodyResultBox');
        bodyResultBox.innerHTML = `
            <div style="padding: 15px; background: #fff3cd; border-radius: 4px; text-align: center;">
                <div style="font-size: 24px; margin-bottom: 10px;">🤖</div>
                <strong>Procesando con Inteligencia Artificial...</strong><br>
                <small>Corrigiendo gramática y mejorando redacción</small>
                <div style="margin-top: 10px; font-size: 12px; color: #856404;">
                    <div class="loading" style="display: inline-block; animation: spin 1s linear infinite;">⟳</div>
                    Espera un momento...
                </div>
            </div>
        `;
    }
    
    // Enviar a AJAX
    const formData = new FormData();
    formData.append('ajax', '1');
    formData.append('kind', kind);
    formData.append('texto', textoOriginal);
    
    fetch('ajax-handler.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.ok) {
            // Actualizar campo correspondiente
            if (kind === 'subject') {
                resultField.value = data.texto;
                document.getElementById('final_subject').value = data.texto;
                statusField.textContent = '✅ Asunto listo';
                statusField.className = 'status-ok';
                
                // También mostrar en el campo visible
                const subjectFinalInput = document.getElementById('subjectFinal');
                if (subjectFinalInput) {
                    subjectFinalInput.value = data.texto;
                }
            } else {
                resultField.value = data.texto;
                statusField.textContent = '✅ Cuerpo listo';
                statusField.className = 'status-ok';
                
                // Mostrar el texto procesado en el área de vista previa
                const bodyResultBox = document.getElementById('bodyResultBox');
                bodyResultBox.innerHTML = `
                    <div style="padding: 10px; background: #d4edda; border-radius: 4px; margin-bottom: 10px;">
                        <strong>✅ Texto corregido por IA:</strong>
                    </div>
                    <div style="white-space: pre-wrap; font-family: inherit; line-height: 1.6; padding: 15px; background: white; border-radius: 4px;">
                        ${data.texto.replace(/\n/g, '<br>')}
                    </div>
                    <div style="margin-top: 10px; padding: 8px; background: #f8f9fa; border-radius: 4px; font-size: 12px; text-align: center;">
                        ✨ <strong>Texto optimizado y listo para enviar</strong>
                    </div>
                `;
            }
        } else {
            statusField.textContent = '❌ Error en IA';
            statusField.className = 'status-error';
            console.error('Error en IA:', data.error);
            
            // Restaurar texto original si hay error
            if (kind === 'body') {
                const bodyResultBox = document.getElementById('bodyResultBox');
                bodyResultBox.innerHTML = createBodyPreview(textoOriginal);
            }
        }
    })
    .catch(error => {
        statusField.textContent = '❌ Error de conexión';
        statusField.className = 'status-error';
        console.error('Error en fetch:', error);
    });
}

// Inicializar cuando se carga la página
document.addEventListener('DOMContentLoaded', function() {
    // Asignar eventos a los botones de dictado
    const btnDictarSubject = document.getElementById('btnDictarSubject');
    const btnDictarBody = document.getElementById('btnDictarBody');
    
    if (btnDictarSubject) {
        btnDictarSubject.addEventListener('click', function() {
            if (isRecordingSubject) {
                stopRecording();
            } else {
                startRecordingSubject();
            }
        });
        
        // Tooltip
        btnDictarSubject.title = "Dictar asunto (Ctrl+Shift+S)\nHaz clic para comenzar/pausar";
    }
    
    if (btnDictarBody) {
        btnDictarBody.addEventListener('click', function() {
            if (isRecordingBody) {
                stopRecording();
            } else {
                startRecordingBody();
            }
        });
        
        // Tooltip
        btnDictarBody.title = "Dictar cuerpo (Ctrl+Shift+D)\nHaz clic para comenzar/pausar";
    }
    
    // Agregar atajos de teclado
    document.addEventListener('keydown', function(e) {
        // Ctrl+Shift+S para dictar asunto
        if (e.ctrlKey && e.shiftKey && e.key === 'S') {
            e.preventDefault();
            if (!isRecordingSubject) {
                startRecordingSubject();
            }
        }
        
        // Ctrl+Shift+D para dictar cuerpo
        if (e.ctrlKey && e.shiftKey && e.key === 'D') {
            e.preventDefault();
            if (!isRecordingBody) {
                startRecordingBody();
            }
        }
        
        // Espacio para pausar/continuar
        if (e.key === ' ' && (isRecordingSubject || isRecordingBody)) {
            e.preventDefault();
            // El espacio naturalmente crea pausas en el dictado
        }
        
        // Esc para detener dictado
        if (e.key === 'Escape' && (isRecordingSubject || isRecordingBody)) {
            e.preventDefault();
            stopRecording();
        }
    });
    
    // Detectar clics fuera para detener grabación
    document.addEventListener('click', function(e) {
        if ((isRecordingSubject || isRecordingBody) && 
            !e.target.closest('.voice-btn') && 
            !e.target.closest('#bodyResultBox')) {
            // No detener automáticamente, permitir dictado continuo
        }
    });
    
    // Inicializar reconocimiento de voz
    initSpeechRecognition();
    
    // Agregar animación CSS para el indicador de grabación
    const style = document.createElement('style');
    style.textContent = `
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        .recording-indicator {
            display: inline-block;
            width: 12px;
            height: 12px;
            background-color: #d93025;
            border-radius: 50%;
            margin-right: 8px;
            animation: blink 1s infinite;
            vertical-align: middle;
        }
        @keyframes blink {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.3; }
        }
        .voice-btn.active {
            background: #ffeaea;
            border-color: #d93025;
            color: #d93025;
        }
    `;
    document.head.appendChild(style);
});
</script>