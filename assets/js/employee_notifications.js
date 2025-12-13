// Script réutilisable pour gérer les clics sur les notifications employé
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.notification-item').forEach(function(item) {
        item.addEventListener('click', function() {
            const notificationId = this.getAttribute('data-notification-id');
            const notificationType = this.getAttribute('data-notification-type');
            const demandId = this.getAttribute('data-demand-id');
            const reclamationId = this.getAttribute('data-reclamation-id');
            
            if (notificationId) {
                fetch('../../actions/mark_notification_read_employee.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({id: notificationId, type: notificationType})
                }).then(response => response.json())
                .then(data => {
                    if (data.success) {
                        this.style.opacity = '0.5';
                        if (notificationType === 'reclamation_reply' && reclamationId) {
                            setTimeout(function() {
                                window.location.href = 'mes_reclamations.php?view_id=' + reclamationId;
                            }, 300);
                        } else if (demandId) {
                            setTimeout(function() {
                                window.location.href = 'details_demande.php?id=' + demandId;
                            }, 300);
                        }
                    }
                });
            } else if (notificationType === 'reclamation_reply' && reclamationId) {
                window.location.href = 'mes_reclamations.php?view_id=' + reclamationId;
            } else if (demandId) {
                window.location.href = 'details_demande.php?id=' + demandId;
            }
        });
    });
});

