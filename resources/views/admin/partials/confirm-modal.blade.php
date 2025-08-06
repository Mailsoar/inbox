{{-- Modal de confirmation réutilisable --}}
<div class="modal fade" id="confirmModal" tabindex="-1" aria-labelledby="confirmModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="confirmModalLabel">
                    <i class="fas fa-exclamation-triangle text-warning me-2"></i>
                    Confirmation
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="confirmModalBody">
                Êtes-vous sûr de vouloir effectuer cette action ?
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="fas fa-times me-1"></i> Annuler
                </button>
                <button type="button" class="btn btn-danger" id="confirmModalButton">
                    <i class="fas fa-check me-1"></i> Confirmer
                </button>
            </div>
        </div>
    </div>
</div>

<script>
// Variable globale pour stocker l'instance de la modal
let confirmModalInstance = null;

// Fonction globale pour afficher la modal de confirmation
function showConfirmModal(message, callback, buttonText = 'Confirmer', buttonClass = 'btn-danger') {
    const modalElement = document.getElementById('confirmModal');
    const modalBody = document.getElementById('confirmModalBody');
    const confirmButton = document.getElementById('confirmModalButton');
    
    // Nettoyer toute instance précédente
    if (confirmModalInstance) {
        confirmModalInstance.dispose();
        confirmModalInstance = null;
    }
    
    // Nettoyer les backdrops orphelins
    document.querySelectorAll('.modal-backdrop').forEach(backdrop => backdrop.remove());
    document.body.classList.remove('modal-open');
    document.body.style.removeProperty('padding-right');
    document.body.style.removeProperty('overflow');
    
    // Créer une nouvelle instance de modal
    confirmModalInstance = new bootstrap.Modal(modalElement, {
        backdrop: 'static',
        keyboard: true
    });
    
    // Définir le message
    modalBody.innerHTML = message;
    
    // Définir le texte et la classe du bouton
    confirmButton.innerHTML = '<i class="fas fa-check me-1"></i> ' + buttonText;
    confirmButton.className = 'btn ' + buttonClass;
    
    // Supprimer les anciens event listeners
    const newButton = confirmButton.cloneNode(true);
    confirmButton.parentNode.replaceChild(newButton, confirmButton);
    
    // Ajouter le nouveau listener pour confirmer
    newButton.addEventListener('click', function() {
        confirmModalInstance.hide();
        // Attendre que la modal soit complètement fermée avant d'exécuter le callback
        modalElement.addEventListener('hidden.bs.modal', function onHidden() {
            modalElement.removeEventListener('hidden.bs.modal', onHidden);
            callback();
        }, { once: true });
    });
    
    // Nettoyer proprement quand la modal se ferme
    modalElement.addEventListener('hidden.bs.modal', function cleanup() {
        // Nettoyer les backdrops
        document.querySelectorAll('.modal-backdrop').forEach(backdrop => backdrop.remove());
        document.body.classList.remove('modal-open');
        document.body.style.removeProperty('padding-right');
        document.body.style.removeProperty('overflow');
        modalElement.removeEventListener('hidden.bs.modal', cleanup);
    });
    
    // Afficher la modal
    confirmModalInstance.show();
}

// Fonction pour gérer les formulaires de suppression
function confirmDelete(form, message = null) {
    const defaultMessage = 'Êtes-vous sûr de vouloir supprimer cet élément ? Cette action est irréversible.';
    showConfirmModal(
        message || defaultMessage,
        function() {
            form.submit();
        },
        'Supprimer',
        'btn-danger'
    );
}

// Initialiser les confirmations de suppression au chargement
document.addEventListener('DOMContentLoaded', function() {
    // Nettoyer les backdrops au chargement de la page (au cas où)
    document.querySelectorAll('.modal-backdrop').forEach(backdrop => backdrop.remove());
    document.body.classList.remove('modal-open');
    document.body.style.removeProperty('padding-right');
    document.body.style.removeProperty('overflow');
    
    // Intercepter tous les formulaires avec data-confirm
    document.querySelectorAll('form[data-confirm]').forEach(function(form) {
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            const message = form.getAttribute('data-confirm');
            const action = form.getAttribute('data-action') || 'Confirmer';
            const btnClass = form.getAttribute('data-btn-class') || 'btn-danger';
            
            showConfirmModal(
                message,
                function() {
                    form.submit();
                },
                action,
                btnClass
            );
        });
    });
    
    // Intercepter les boutons/liens avec data-confirm
    document.querySelectorAll('a[data-confirm], button[data-confirm]').forEach(function(element) {
        element.addEventListener('click', function(e) {
            e.preventDefault();
            const message = element.getAttribute('data-confirm');
            const href = element.getAttribute('href');
            const action = element.getAttribute('data-action') || 'Confirmer';
            const btnClass = element.getAttribute('data-btn-class') || 'btn-primary';
            
            showConfirmModal(
                message,
                function() {
                    if (href) {
                        // Cas spécial pour la déconnexion
                        if (href.includes('logout')) {
                            document.getElementById('logout-form').submit();
                        } else {
                            window.location.href = href;
                        }
                    } else if (element.form) {
                        element.form.submit();
                    }
                },
                action,
                btnClass
            );
        });
    });
});

// Nettoyer les backdrops quand on navigue avec le bouton retour
window.addEventListener('pageshow', function(event) {
    document.querySelectorAll('.modal-backdrop').forEach(backdrop => backdrop.remove());
    document.body.classList.remove('modal-open');
    document.body.style.removeProperty('padding-right');
    document.body.style.removeProperty('overflow');
});
</script>