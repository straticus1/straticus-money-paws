document.addEventListener('DOMContentLoaded', function() {
    const deleteForms = document.querySelectorAll('.delete-confirmation');

    deleteForms.forEach(form => {
        form.addEventListener('submit', function(event) {
            const confirmation = confirm('Are you sure you want to delete this item? This action cannot be undone.');
            if (!confirmation) {
                event.preventDefault();
            }
        });
    });
});
