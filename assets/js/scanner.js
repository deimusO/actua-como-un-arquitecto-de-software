/**
 * Controlador de Escáner QR en Vivo con html5-qrcode
 * Liceo Bolivariano "Rossana Melidei"
 * Incluye retroalimentación sonora Web Audio API, resiliencia offline y validación anti-fraude.
 */

class LiceoQRScanner {
    constructor(readerElementId = 'reader') {
        this.readerElementId = readerElementId;
        this.html5QrCode = null;
        this.isScanning = false;
        this.lastScannedToken = null;
        this.lastScanTime = 0;
        this.scanCooldownMs = 3000; // Evita escaneo redundante múltiple consecutivo
        this.audioCtx = null;
        this.selectedHorarioId = null;
        this.selectedMateria = null;

        this.initAudioContext();
    }

    initAudioContext() {
        // Inicializar contexto de audio al primer clic/interacción del usuario
        const unlockAudio = () => {
            if (!this.audioCtx) {
                const AudioContext = window.AudioContext || window.webkitAudioContext;
                if (AudioContext) {
                    this.audioCtx = new AudioContext();
                }
            }
            if (this.audioCtx && this.audioCtx.state === 'suspended') {
                this.audioCtx.resume();
            }
            document.removeEventListener('click', unlockAudio);
            document.removeEventListener('touchstart', unlockAudio);
        };
        document.addEventListener('click', unlockAudio);
        document.addEventListener('touchstart', unlockAudio);
    }

    playTone(frequency, duration, type = 'sine') {
        if (!this.audioCtx) return;
        try {
            const osc = this.audioCtx.createOscillator();
            const gain = this.audioCtx.createGain();

            osc.type = type;
            osc.frequency.setValueAtTime(frequency, this.audioCtx.currentTime);

            gain.gain.setValueAtTime(0.15, this.audioCtx.currentTime);
            gain.gain.exponentialRampToValueAtTime(0.001, this.audioCtx.currentTime + duration);

            osc.connect(gain);
            gain.connect(this.audioCtx.destination);

            osc.start();
            osc.stop(this.audioCtx.currentTime + duration);
        } catch (e) {
            console.warn('Audio tone error:', e);
        }
    }

    playSuccessChime() {
        // Melodía corta agradable ascendente (Presente)
        this.playTone(523.25, 0.12); // C5
        setTimeout(() => this.playTone(659.25, 0.18), 120); // E5
        setTimeout(() => this.playTone(783.99, 0.25), 260); // G5
    }

    playWarningTone() {
        // Tono medio descendente (Retraso)
        this.playTone(493.88, 0.18, 'triangle');
        setTimeout(() => this.playTone(392.00, 0.25, 'triangle'), 160);
    }

    playErrorTone() {
        // Zumbido grave de error / duplicado / no autorizado
        this.playTone(220, 0.35, 'sawtooth');
    }

    setClaseActiva(horarioId, materia) {
        this.selectedHorarioId = horarioId;
        this.selectedMateria = materia;
        const banner = document.getElementById('clase-activa-banner');
        if (banner) {
            banner.innerHTML = `
                <div class="flex items-center gap-2">
                    <span class="chip chip-blue">Clase en Curso</span>
                    <strong class="text-slate-800">${materia}</strong>
                </div>
            `;
        }
    }

    async startCamera() {
        if (!window.Html5Qrcode) {
            console.error('Librería html5-qrcode no encontrada.');
            return;
        }

        if (!this.selectedHorarioId) {
            window.offlineAttendanceManager?.showToast('Seleccione primero una materia / horario en curso antes de encender el escáner.', 'amber');
            return;
        }

        try {
            this.html5QrCode = new Html5Qrcode(this.readerElementId);
            const cameras = await Html5Qrcode.getCameras();

            if (!cameras || cameras.length === 0) {
                alert('No se detectaron cámaras en este dispositivo.');
                return;
            }

            // Preferir cámara trasera en teléfonos móviles
            let cameraId = cameras[0].id;
            for (const cam of cameras) {
                if (cam.label.toLowerCase().includes('back') || cam.label.toLowerCase().includes('trasera')) {
                    cameraId = cam.id;
                    break;
                }
            }

            const config = {
                fps: 15,
                qrbox: { width: 250, height: 250 },
                aspectRatio: 1.0
            };

            await this.html5QrCode.start(
                cameraId,
                config,
                (decodedText) => this.onQrCodeSuccess(decodedText),
                (errorMessage) => {
                    // Silenciar mensajes continuos de frame sin QR
                }
            );

            this.isScanning = true;
            this.updateScannerControlsUI(true);
        } catch (err) {
            console.error('Error al iniciar cámara:', err);
            window.offlineAttendanceManager?.showToast('Error al acceder a la cámara: ' + (err.message || err), 'rose');
        }
    }

    async stopCamera() {
        if (this.html5QrCode && this.isScanning) {
            await this.html5QrCode.stop();
            this.isScanning = false;
            this.updateScannerControlsUI(false);
        }
    }

    updateScannerControlsUI(active) {
        const btnStart = document.getElementById('btn-start-scanner');
        const btnStop = document.getElementById('btn-stop-scanner');
        const scanOverlay = document.getElementById('scanner-overlay');

        if (btnStart) btnStart.style.display = active ? 'none' : 'inline-flex';
        if (btnStop) btnStop.style.display = active ? 'inline-flex' : 'none';
        if (scanOverlay) scanOverlay.style.display = active ? 'flex' : 'none';
    }

    async onQrCodeSuccess(tokenText) {
        const now = Date.now();
        if (this.lastScannedToken === tokenText && (now - this.lastScanTime) < this.scanCooldownMs) {
            return; // Esperar cooldown para evitar lecturas continuas
        }

        this.lastScannedToken = tokenText;
        this.lastScanTime = now;

        await this.procesarTokenAsistencia(tokenText);
    }

    async procesarTokenAsistencia(tokenText) {
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
        const payload = {
            token_qr: tokenText.trim(),
            horario_id: this.selectedHorarioId,
            materia: this.selectedMateria
        };

        // Mostrar indicador visual de procesamiento
        this.showProcessingBadge();

        // Si el navegador está offline, encolar de inmediato en contingencia
        if (!navigator.onLine) {
            this.procesarOfflineContingencia(payload);
            return;
        }

        try {
            const response = await fetch('api/procesar_asistencia.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': csrfToken
                },
                body: JSON.stringify(payload)
            });

            const data = await response.json();

            if (response.status === 403) {
                // IP No autorizada (Anti-Fraude)
                this.playErrorTone();
                this.renderModalResultado({
                    tipo: 'error',
                    titulo: 'Bloqueo Anti-Fraude',
                    mensaje: data.error || 'Dispositivo fuera de la subred autorizada del liceo.',
                    chip: 'chip-rose'
                });
                return;
            }

            if (data.success) {
                if (data.estado === 'Presente') {
                    this.playSuccessChime();
                } else {
                    this.playWarningTone();
                }

                this.renderModalResultado({
                    tipo: 'success',
                    titulo: data.estado === 'Presente' ? '¡Asistencia Registrada!' : 'Asistencia con Retardo',
                    estudiante: data.estudiante,
                    estado: data.estado,
                    hora: data.hora,
                    notificado: data.email_enviado,
                    chip: data.estado === 'Presente' ? 'chip-mint' : 'chip-amber'
                });

                // Notificar refresco de tabla
                if (typeof window.recargarTablaAsistencia === 'function') {
                    window.recargarTablaAsistencia();
                }
            } else {
                this.playErrorTone();
                this.renderModalResultado({
                    tipo: 'warning',
                    titulo: 'Registro No Procesado',
                    mensaje: data.error || 'No fue posible registrar la marca.',
                    chip: 'chip-amber'
                });
            }
        } catch (netErr) {
            // Falla de red imprevista durante la petición -> Delegar a modo contingencia
            console.warn('[Falla de red en escaneo]: Almacenando en LocalStorage');
            this.procesarOfflineContingencia(payload);
        }
    }

    procesarOfflineContingencia(payload) {
        this.playSuccessChime();
        const queued = window.offlineAttendanceManager.enqueue(payload);
        this.renderModalResultado({
            tipo: 'offline',
            titulo: 'Guardado en Contingencia (Offline)',
            mensaje: 'Sin conexión de red en este momento. La marca de asistencia se guardó localmente y se sincronizará automáticamente al volver el enlace.',
            token: payload.token_qr,
            hora: new Date().toLocaleTimeString(),
            chip: 'chip-amber'
        });
    }

    showProcessingBadge() {
        const badge = document.getElementById('scanner-feedback-badge');
        if (badge) {
            badge.style.display = 'block';
            badge.innerHTML = `<span class="chip chip-blue">Verificando Credencial...</span>`;
        }
    }

    renderModalResultado(datos) {
        const modalContainer = document.getElementById('modal-scan-result');
        if (!modalContainer) {
            window.offlineAttendanceManager?.showToast(datos.titulo + ': ' + (datos.mensaje || datos.estudiante?.nombres || ''), datos.chip === 'chip-mint' ? 'mint' : 'rose');
            return;
        }

        const modalBody = modalContainer.querySelector('.modal-content-body');
        if (modalBody) {
            let htmlContent = '';
            if (datos.tipo === 'success') {
                htmlContent = `
                    <div class="text-center p-4">
                        <span class="chip ${datos.chip} mb-3">${datos.estado}</span>
                        <h3 class="text-xl font-bold text-slate-800 mb-1">${datos.estudiante.nombres} ${datos.estudiante.apellidos}</h3>
                        <p class="text-xs text-slate-500 font-mono mb-4">C.I. ${datos.estudiante.cedula} &bull; ${datos.estudiante.ano_instruccion} "${datos.estudiante.seccion}"</p>
                        
                        <div class="glass-card-subtle p-3 rounded-xl mb-4 text-left text-xs">
                            <div class="flex justify-between py-1 border-b border-slate-200/60">
                                <span class="text-slate-500">Hora de Registro:</span>
                                <span class="font-bold text-slate-800">${datos.hora}</span>
                            </div>
                            <div class="flex justify-between py-1">
                                <span class="text-slate-500">Notificación al Representante:</span>
                                <span class="font-bold text-emerald-600">${datos.notificado ? '✓ Despachada vía Correo' : 'En cola de envío'}</span>
                            </div>
                        </div>
                        <button type="button" class="btn btn-primary w-full" onclick="document.getElementById('modal-scan-result').classList.add('hidden')">Continuar Escaneando</button>
                    </div>
                `;
            } else if (datos.tipo === 'offline') {
                htmlContent = `
                    <div class="text-center p-4">
                        <span class="chip chip-amber mb-3">Modo Resiliencia Activo</span>
                        <h3 class="text-lg font-bold text-slate-800 mb-2">${datos.titulo}</h3>
                        <p class="text-xs text-slate-600 mb-4 leading-relaxed">${datos.mensaje}</p>
                        <div class="glass-card-subtle p-3 rounded-xl mb-4 text-xs font-mono text-slate-700">
                            Token: ${datos.token}<br>Hora Local: ${datos.hora}
                        </div>
                        <button type="button" class="btn btn-mint w-full" onclick="document.getElementById('modal-scan-result').classList.add('hidden')">Aceptar y Continuar</button>
                    </div>
                `;
            } else {
                htmlContent = `
                    <div class="text-center p-4">
                        <span class="chip ${datos.chip} mb-3">Aviso</span>
                        <h3 class="text-lg font-bold text-slate-800 mb-2">${datos.titulo}</h3>
                        <p class="text-xs text-slate-600 mb-4">${datos.mensaje}</p>
                        <button type="button" class="btn btn-subtle w-full" onclick="document.getElementById('modal-scan-result').classList.add('hidden')">Cerrar</button>
                    </div>
                `;
            }
            modalBody.innerHTML = htmlContent;
            modalContainer.classList.remove('hidden');
        }
    }
}

// Instancia global
window.liceoQRScanner = new LiceoQRScanner('reader');
