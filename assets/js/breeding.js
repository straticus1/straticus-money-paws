$(document).ready(function() {
    $('#breeding-form').on('submit', function(e) {
        e.preventDefault();

        var formData = new FormData(this);
        var $alertContainer = $('#breeding-alert-container');

        $.ajax({
            url: 'api/breed-pets.php',
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            dataType: 'json',
            success: function(response) {
                var alertType = response.success ? 'success' : 'danger';
                var alertHtml = '<div class="alert alert-' + alertType + ' alert-dismissible fade show" role="alert">' +
                                response.message +
                                '<button type="button" class="close" data-dismiss="alert" aria-label="Close">' +
                                '<span aria-hidden="true">&times;</span>' +
                                '</button>' +
                                '</div>';
                $alertContainer.html(alertHtml);

                if (response.success) {
                    $('#breeding-form')[0].reset();
                    // Optionally, redirect to the new pet's page or the user's profile
                    setTimeout(function() {
                        window.location.href = 'profile.php'; // Redirect to profile to see the new pet
                    }, 3000);
                }
            },
            error: function() {
                var alertHtml = '<div class="alert alert-danger alert-dismissible fade show" role="alert">' +
                                'An unexpected error occurred. Please try again.' +
                                '<button type="button" class="close" data-dismiss="alert" aria-label="Close">' +
                                '<span aria-hidden="true">&times;</span>' +
                                '</button>' +
                                '</div>';
                $alertContainer.html(alertHtml);
            }
        });
    });
});
