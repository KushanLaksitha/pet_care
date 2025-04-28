document.addEventListener('DOMContentLoaded', function() {
    // Initialize any tooltips
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
    if (typeof bootstrap !== 'undefined') {
        tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl)
        });
    }

    // Add fade-out effect to alerts after 5 seconds
    const alerts = document.querySelectorAll('.alert');
    if (alerts.length > 0) {
        setTimeout(function() {
            alerts.forEach(function(alert) {
                alert.classList.add('fade-out');
                setTimeout(function() {
                    alert.style.display = 'none';
                }, 500);
            });
        }, 5000);
    }

    // Confirm deletion of pets
    const deleteButtons = document.querySelectorAll('a[href*="action=delete"]');
    deleteButtons.forEach(function(button) {
        button.addEventListener('click', function(e) {
            if (!confirm('Are you sure you want to delete this pet? This action cannot be undone.')) {
                e.preventDefault();
            }
        });
    });
});

// This function is already defined in your HTML, keeping it here for completeness
function getBreeds(speciesId, selectedBreedId = '') {
    if (!speciesId) {
        document.getElementById('breed_id').innerHTML = '<option value="">Select Species First</option>';
        return;
    }
    
    fetch('ajax/get_breeds.php?species_id=' + speciesId)
        .then(response => response.json())
        .then(data => {
            let options = '<option value="">Select Breed</option>';
            data.forEach(breed => {
                const selected = breed.breed_id == selectedBreedId ? 'selected' : '';
                options += `<option value="${breed.breed_id}" ${selected}>${breed.breed_name}</option>`;
            });
            document.getElementById('breed_id').innerHTML = options;
        })
        .catch(error => console.error('Error fetching breeds:', error));
}