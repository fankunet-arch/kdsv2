/**
 * TopTea KDS - Modal Error Handling System
 *
 * Replaces system alert/confirm boxes with Bootstrap modals
 * Required for APK deployment (system dialogs don't work in WebView)
 *
 * @author TopTea Engineering Team
 * @version 1.0.0
 * @date 2026-01-03
 */

const KDSModal = {
    /**
     * Initialize modal container (call on page load)
     */
    init() {
        if (document.getElementById('kds-modal-container')) {
            return; // Already initialized
        }

        const container = document.createElement('div');
        container.id = 'kds-modal-container';
        container.innerHTML = `
            <!-- Error Modal -->
            <div class="modal fade" id="kdsErrorModal" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content border-danger">
                        <div class="modal-header bg-danger text-white">
                            <h5 class="modal-title">
                                <i class="bi bi-exclamation-triangle-fill me-2"></i>
                                <span id="kdsErrorTitle">错误 / Error</span>
                            </h5>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <p id="kdsErrorMessage"></p>
                            <div id="kdsErrorDebug" class="alert alert-warning mt-3" style="display: none;">
                                <strong>Debug:</strong>
                                <pre class="mb-0" id="kdsErrorDebugContent"></pre>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">关闭 / Close</button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Success Modal -->
            <div class="modal fade" id="kdsSuccessModal" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content border-success">
                        <div class="modal-header bg-success text-white">
                            <h5 class="modal-title">
                                <i class="bi bi-check-circle-fill me-2"></i>
                                <span id="kdsSuccessTitle">成功 / Success</span>
                            </h5>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <p id="kdsSuccessMessage"></p>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-success" data-bs-dismiss="modal">确定 / OK</button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Confirm Modal -->
            <div class="modal fade" id="kdsConfirmModal" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content">
                        <div class="modal-header bg-warning">
                            <h5 class="modal-title">
                                <i class="bi bi-question-circle-fill me-2"></i>
                                <span id="kdsConfirmTitle">确认 / Confirm</span>
                            </h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <p id="kdsConfirmMessage"></p>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" id="kdsConfirmCancel">
                                取消 / Cancel
                            </button>
                            <button type="button" class="btn btn-primary" id="kdsConfirmOk">
                                确定 / Confirm
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        `;
        document.body.appendChild(container);
    },

    /**
     * Show error modal
     * @param {string} message - Error message
     * @param {string} title - Modal title (optional)
     * @param {string} debug - Debug information (optional)
     */
    error(message, title = null, debug = null) {
        this.init();

        const modal = new bootstrap.Modal(document.getElementById('kdsErrorModal'));
        const titleEl = document.getElementById('kdsErrorTitle');
        const messageEl = document.getElementById('kdsErrorMessage');
        const debugEl = document.getElementById('kdsErrorDebug');
        const debugContentEl = document.getElementById('kdsErrorDebugContent');

        if (title) titleEl.textContent = title;
        else titleEl.textContent = '错误 / Error';

        messageEl.textContent = message;

        if (debug) {
            debugContentEl.textContent = debug;
            debugEl.style.display = 'block';
        } else {
            debugEl.style.display = 'none';
        }

        modal.show();
    },

    /**
     * Show success modal
     * @param {string} message - Success message
     * @param {string} title - Modal title (optional)
     * @param {function} callback - Callback after modal closes (optional)
     */
    success(message, title = null, callback = null) {
        this.init();

        const modal = new bootstrap.Modal(document.getElementById('kdsSuccessModal'));
        const titleEl = document.getElementById('kdsSuccessTitle');
        const messageEl = document.getElementById('kdsSuccessMessage');

        if (title) titleEl.textContent = title;
        else titleEl.textContent = '成功 / Success';

        messageEl.textContent = message;

        if (callback) {
            const modalEl = document.getElementById('kdsSuccessModal');
            modalEl.addEventListener('hidden.bs.modal', callback, { once: true });
        }

        modal.show();
    },

    /**
     * Show confirm dialog
     * @param {string} message - Confirmation message
     * @param {function} onConfirm - Callback if user confirms
     * @param {function} onCancel - Callback if user cancels (optional)
     * @param {string} title - Modal title (optional)
     */
    confirm(message, onConfirm, onCancel = null, title = null) {
        this.init();

        const modal = new bootstrap.Modal(document.getElementById('kdsConfirmModal'));
        const titleEl = document.getElementById('kdsConfirmTitle');
        const messageEl = document.getElementById('kdsConfirmMessage');
        const okBtn = document.getElementById('kdsConfirmOk');
        const cancelBtn = document.getElementById('kdsConfirmCancel');

        if (title) titleEl.textContent = title;
        else titleEl.textContent = '确认 / Confirm';

        messageEl.textContent = message;

        // Remove old event listeners by cloning
        const newOkBtn = okBtn.cloneNode(true);
        const newCancelBtn = cancelBtn.cloneNode(true);
        okBtn.parentNode.replaceChild(newOkBtn, okBtn);
        cancelBtn.parentNode.replaceChild(newCancelBtn, cancelBtn);

        // Add new listeners
        newOkBtn.addEventListener('click', () => {
            modal.hide();
            if (onConfirm) onConfirm();
        });

        newCancelBtn.addEventListener('click', () => {
            modal.hide();
            if (onCancel) onCancel();
        });

        modal.show();
    },

    /**
     * Handle AJAX error response
     * @param {object} xhr - XMLHttpRequest object
     */
    handleAjaxError(xhr) {
        let message = 'An error occurred';
        let debug = null;

        try {
            const response = JSON.parse(xhr.responseText);
            message = response.message || message;
            debug = response.debug || null;
        } catch (e) {
            message = `HTTP ${xhr.status}: ${xhr.statusText}`;
            debug = xhr.responseText;
        }

        this.error(message, 'Request Failed', debug);
    }
};

// Auto-initialize on DOM ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => KDSModal.init());
} else {
    KDSModal.init();
}

// Override window.alert (DEPRECATED - use KDSModal.error instead)
window.alert = function(message) {
    console.warn('window.alert() is deprecated. Use KDSModal.error() instead.');
    KDSModal.error(String(message));
};

// Override window.confirm (DEPRECATED - use KDSModal.confirm instead)
window.confirm = function(message) {
    console.warn('window.confirm() is deprecated. Use KDSModal.confirm() instead.');
    // Cannot truly override confirm synchronously, so just show error
    KDSModal.error('Please use KDSModal.confirm() instead of window.confirm()');
    return false;
};
