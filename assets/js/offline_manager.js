/**
 * Módulo de Resiliencia y Contingencia Offline
 * Liceo Bolivariano "Rossana Melidei"
 * Almacena marcas de asistencia en LocalStorage / IndexedDB si falla la red
 * y gestiona la sincronización automática diferida.
 */

class OfflineAttendanceManager {
    constructor() {
        this.storageKey = 'liceo_attendance_offline_queue_v1';
        this.isSyncing = false;
        this.initEventListeners();
    }

    initEventListeners() {
        window.addEventListener('online', () => {
            this.updateNetworkStatusUI(true);
            this.syncQueue();
        });

        window.addEventListener('offline', () => {
            this.updateNetworkStatusUI(false);
        });

        // Verificación periódica de conectividad cada 20 segundos
        setInterval(() => {
            if (navigator.onLine && this.getQueueCount() > 0 && !this.isSyncing) {
                this.syncQueue();
            }
        }, 20000);
    }

    getQueue() {
        try {
            const raw = localStorage.getItem(this.storageKey);
            return raw ? JSON.parse(raw) : [];
        } catch (e) {
            console.error('Error al leer cola offline:', e);
            return [];
        }
    }

    getQueueCount() {
        return this.getQueue().length;
    }

    saveQueue(queue) {
        try {
            localStorage.setItem(this.storageKey, JSON.stringify(queue));
            this.updateBadgeUI();
        } catch (e) {
            console.error('Error al guardar cola offline:', e);
        }
    }

    enqueue(item) {
        const queue = this.getQueue();
        const payload = {
            id: 'OFFLINE_' + Date.now() + '_' + Math.random().toString(36).substr(2, 6),
            token_qr: item.token_qr,
            horario_id: item.horario_id,
            materia: item.materia,
            fecha: item.fecha || new Date().toISOString().split('T')[0],
            hora_escaneo: item.hora_escaneo || new Date().toTimeString().split(' ')[0],
            estudiante_nombre: item.estudiante_nombre || 'Estudiante Escaneado Offline',
            timestamp_local: new Date().toISOString()
        };

        // Evitar duplicados exactos en cola local
        const exists = queue.some(q => q.token_qr === payload.token_qr && q.materia === payload.materia);
        if (!exists) {
            queue.push(payload);
            this.saveQueue(queue);
        }

        return payload;
    }

    async syncQueue(endpoint = 'api/sincronizar_offline.php') {
        const queue = this.getQueue();
        if (queue.length === 0 || this.isSyncing) return { synced: 0, total: 0 };

        this.isSyncing = true;
        this.setSyncingUI(true);

        try {
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

            const response = await fetch(endpoint, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': csrfToken
                },
                body: JSON.stringify({
                    items: queue
                })
            });

            if (!response.ok) {
                throw new Error('Servidor retornó error HTTP ' + response.status);
            }

            const result = await response.json();

            if (result.success) {
                // Limpiar la cola tras sincronización exitosa
                this.saveQueue([]);
                this.showToast(`Sincronización masiva completada: ${result.total_procesados} asistencias enviadas y correos despachados.`, 'mint');
                
                // Recargar tabla de asistencia en vivo si la función existe
                if (typeof window.recargarTablaAsistencia === 'function') {
                    window.recargarTablaAsistencia();
                }

                return { synced: result.total_procesados, total: queue.length };
            } else {
                this.showToast(result.error || 'Error al procesar la cola en el servidor.', 'rose');
            }
        } catch (err) {
            console.warn('[Offline Sync Warning]:', err.message);
            this.showToast('No se pudo sincronizar con el servidor aún. Los datos continúan seguros en la memoria local.', 'amber');
        } finally {
            this.isSyncing = false;
            this.setSyncingUI(false);
            this.updateBadgeUI();
        }
    }

    updateBadgeUI() {
        const count = this.getQueueCount();
        const badges = document.querySelectorAll('.offline-count-badge');
        badges.forEach(el => {
            el.textContent = count;
            if (count > 0) {
                el.classList.remove('hidden');
                el.style.display = 'inline-flex';
            } else {
                el.classList.add('hidden');
                el.style.display = 'none';
            }
        });

        const syncButtons = document.querySelectorAll('.btn-sync-offline');
        syncButtons.forEach(btn => {
            btn.disabled = (count === 0 || this.isSyncing);
        });
    }

    setSyncingUI(syncing) {
        const syncButtons = document.querySelectorAll('.btn-sync-offline');
        syncButtons.forEach(btn => {
            if (syncing) {
                btn.innerHTML = `<span class="spinner-sm"></span> Sincronizando...`;
                btn.disabled = true;
            } else {
                btn.innerHTML = `Sincronizar Cola (${this.getQueueCount()})`;
            }
        });
    }

    updateNetworkStatusUI(online) {
        const statusEl = document.getElementById('network-status-indicator');
        if (statusEl) {
            if (online) {
                statusEl.innerHTML = `
                    <span class="chip chip-mint">
                        <span class="status-dot dot-online"></span> En Línea
                    </span>`;
            } else {
                statusEl.innerHTML = `
                    <span class="chip chip-amber">
                        <span class="status-dot dot-offline"></span> Modo Offline (Contingencia)
                    </span>`;
            }
        }
    }

    showToast(message, type = 'blue') {
        const container = document.getElementById('toast-container') || this.createToastContainer();
        const toast = document.createElement('div');
        toast.className = `toast-card border-l-4 ${type === 'mint' ? 'border-emerald-500' : (type === 'rose' ? 'border-rose-500' : 'border-amber-500')}`;
        toast.innerHTML = `
            <div class="text-sm font-medium text-slate-800">${message}</div>
        `;
        container.appendChild(toast);
        setTimeout(() => toast.remove(), 4500);
    }

    createToastContainer() {
        const c = document.createElement('div');
        c.id = 'toast-container';
        c.className = 'toast-container';
        document.body.appendChild(c);
        return c;
    }
}

// Instancia global
window.offlineAttendanceManager = new OfflineAttendanceManager();
