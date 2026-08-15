$(document).ready(function() {
    // Initialize breeding interface
    loadRecentOffspring();
    
    // Breeding method selection
    $('.breeding-method').on('click', function() {
        $('.breeding-method').removeClass('selected');
        $(this).addClass('selected');
        
        const method = $(this).data('method');
        $('#breeding-method').val(method);
        
        updateBreedingCost(method);
        updateBreedingStats();
    });
    
    // Set default selection
    $('.breeding-method[data-method="natural"]').addClass('selected');
    
    // Parent selection handlers
    $('#mother-select, #father-select').on('change', function() {
        const motherId = $('#mother-select').val();
        const fatherId = $('#father-select').val();
        
        if (motherId) {
            loadPetPreview(motherId, '#mother-preview');
        }
        if (fatherId) {
            loadPetPreview(fatherId, '#father-preview');
        }
        
        if (motherId && fatherId) {
            analyzeCompatibility(motherId, fatherId);
            updateBreedingStats();
        }
    });
    
    // Enhanced breeding form submission
    $('#breeding-form').on('submit', function(e) {
        e.preventDefault();
        
        const method = $('#breeding-method').val();
        const motherId = $('#mother-select').val();
        const fatherId = $('#father-select').val();
        
        if (!motherId || !fatherId) {
            showAlert('error', 'Please select both parent pets.');
            return;
        }
        
        if (motherId === fatherId) {
            showAlert('error', 'A pet cannot breed with itself.');
            return;
        }
        
        performAdvancedBreeding();
    });
});

function updateBreedingCost(method) {
    const costs = {
        'natural': 0.00,
        'ai_assisted': 2.50,
        'genetic_enhancement': 5.00
    };
    
    const cost = costs[method] || 0.00;
    $('#cost-amount').text('$' + cost.toFixed(2));
    
    if (cost > 0) {
        $('#breeding-cost').show();
    } else {
        $('#breeding-cost').hide();
    }
}

function loadPetPreview(petId, container) {
    $.get(`api/get-pet-details.php?id=${petId}`, function(response) {
        if (response.success) {
            const pet = response.pet;
            const html = `
                <div class="pet-preview-content">
                    <img src="uploads/${pet.filename}" alt="${pet.original_name}" class="pet-preview-img">
                    <div class="pet-info">
                        <h6>${pet.original_name}</h6>
                        <p>Species: ${pet.species || 'Unknown'}</p>
                        <p>Age: ${pet.age_days || 0} days</p>
                        <div class="trait-count">${pet.trait_count || 0} traits</div>
                    </div>
                </div>
            `;
            $(container).html(html);
        }
    }).fail(function() {
        $(container).html('<p class="text-muted">Failed to load pet details</p>');
    });
}

function analyzeCompatibility(motherId, fatherId) {
    $.post('api/analyze-breeding-compatibility.php', {
        mother_id: motherId,
        father_id: fatherId,
        csrf_token: $('input[name="csrf_token"]').val()
    }, function(response) {
        if (response.success) {
            const analysis = response.analysis;
            let html = `
                <div class="compatibility-score">
                    <strong>Compatibility Score:</strong> ${(analysis.compatibility_score * 100).toFixed(1)}%
                </div>
                <div class="genetic-diversity">
                    <strong>Genetic Diversity:</strong> ${(analysis.genetic_diversity * 100).toFixed(1)}%
                </div>
            `;
            
            if (analysis.is_hybrid) {
                html += '<div class="alert alert-warning mt-2">This pairing will produce hybrid offspring!</div>';
            }
            
            $('#compatibility-details').html(html);
            $('#compatibility-analysis').show();
            
            // Update stats display
            $('#genetic-diversity').text((analysis.genetic_diversity * 100).toFixed(1) + '%');
        }
    }, 'json');
}

function updateBreedingStats() {
    const motherId = $('#mother-select').val();
    const fatherId = $('#father-select').val();
    const method = $('#breeding-method').val();
    
    if (!motherId || !fatherId) {
        $('#success-rate').text('--');
        $('#rare-trait-chance').text('--');
        return;
    }
    
    $.post('api/calculate-breeding-stats.php', {
        mother_id: motherId,
        father_id: fatherId,
        breeding_method: method,
        csrf_token: $('input[name="csrf_token"]').val()
    }, function(response) {
        if (response.success) {
            const stats = response.stats;
            $('#success-rate').text((stats.success_probability * 100).toFixed(1) + '%');
            $('#rare-trait-chance').text((stats.rare_trait_chance * 100).toFixed(1) + '%');
        }
    }, 'json');
}

function performAdvancedBreeding() {
    const formData = new FormData($('#breeding-form')[0]);
    const $alertContainer = $('#breeding-alert-container');
    const $button = $('#breed-button');
    
    $button.prop('disabled', true).html('🧬 Creating Pet...');
    
    $.ajax({
        url: 'api/advanced-breed-pets.php',
        type: 'POST',
        data: formData,
        processData: false,
        contentType: false,
        dataType: 'json',
        success: function(response) {
            const alertType = response.success ? 'success' : 'danger';
            let message = response.message;
            
            if (response.success && response.breeding_stats) {
                const stats = response.breeding_stats;
                message += `<br><br><strong>Breeding Results:</strong><br>`;
                message += `• Success Rate: ${(stats.success_probability * 100).toFixed(1)}%<br>`;
                message += `• Genetic Diversity: ${(stats.genetic_diversity * 100).toFixed(1)}%<br>`;
                message += `• Mutations: ${stats.mutation_events}<br>`;
                message += `• Traits Inherited: ${stats.traits_inherited}<br>`;
                message += `• Estimated Value: $${stats.estimated_value}`;
            }
            
            const alertHtml = `
                <div class="alert alert-${alertType} alert-dismissible fade show" role="alert">
                    ${message}
                    <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
            `;
            $alertContainer.html(alertHtml);
            
            if (response.success) {
                $('#breeding-form')[0].reset();
                $('.breeding-method').removeClass('selected');
                $('.breeding-method[data-method="natural"]').addClass('selected');
                $('#breeding-method').val('natural');
                updateBreedingCost('natural');
                $('#compatibility-analysis').hide();
                $('#mother-preview, #father-preview').empty();
                loadRecentOffspring();
                
                setTimeout(function() {
                    window.location.href = `pet.php?id=${response.new_pet_id}`;
                }, 3000);
            }
        },
        error: function() {
            const alertHtml = `
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    An unexpected error occurred during breeding. Please try again.
                    <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
            `;
            $alertContainer.html(alertHtml);
        },
        complete: function() {
            $button.prop('disabled', false).html('🧬 Create New Pet');
        }
    });
}

function loadRecentOffspring() {
    $.get('api/get-recent-offspring.php', function(response) {
        if (response.success) {
            let html = '';
            if (response.offspring.length === 0) {
                html = '<p class="text-muted">No recent offspring</p>';
            } else {
                response.offspring.forEach(pet => {
                    html += `
                        <div class="recent-pet">
                            <img src="uploads/${pet.filename}" alt="${pet.original_name}" class="recent-pet-img">
                            <div class="recent-pet-info">
                                <strong>${pet.original_name}</strong><br>
                                <small>${pet.days_ago} days ago</small>
                            </div>
                        </div>
                    `;
                });
            }
            $('#recent-offspring').html(html);
        }
    }, 'json');
}

function showAlert(type, message) {
    const alertClass = type === 'success' ? 'alert-success' : 'alert-danger';
    const alertHtml = `
        <div class="alert ${alertClass} alert-dismissible fade show" role="alert">
            ${message}
            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                <span aria-hidden="true">&times;</span>
            </button>
        </div>
    `;
    $('#breeding-alert-container').html(alertHtml);
}
