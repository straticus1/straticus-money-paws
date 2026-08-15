$(document).ready(function() {
    // Initialize tournament interface
    loadTournamentHistory();
    loadLeaderboards();
    
    // Set minimum dates for tournament creation
    const now = new Date();
    now.setHours(now.getHours() + 1); // Minimum 1 hour from now
    const minDateTime = now.toISOString().slice(0, 16);
    $('#registration-deadline, #start-time').attr('min', minDateTime);
    
    // Create tournament form handler
    $('#create-tournament-form').on('submit', function(e) {
        e.preventDefault();
        createTournament();
    });
    
    // Pet selection handler for registration modal
    $('#reg-pet-select').on('change', function() {
        const petId = $(this).val();
        if (petId) {
            loadPetBattleStats(petId);
        } else {
            $('#pet-battle-stats').hide();
        }
    });
    
    // Tab change handlers
    $('#history-tab').on('click', function() {
        loadTournamentHistory();
    });
    
    $('#leaderboard-tab').on('click', function() {
        loadLeaderboards();
    });
});

function showRegistrationModal(tournamentId) {
    // Load tournament details
    $.get(`api/get-tournament.php?id=${tournamentId}`, function(response) {
        if (response.success) {
            const tournament = response.tournament;
            $('#reg-tournament-id').val(tournamentId);
            $('#modal-entry-fee').text(parseFloat(tournament.entry_fee_usd).toFixed(2));
            $('#modal-prize-pool').text(parseFloat(tournament.prize_pool_usd).toFixed(2));
            $('#registrationModal').modal('show');
        } else {
            showAlert('error', response.message);
        }
    });
}

function registerForTournament() {
    const formData = new FormData($('#registration-form')[0]);
    
    $.ajax({
        url: 'api/register-tournament.php',
        type: 'POST',
        data: formData,
        processData: false,
        contentType: false,
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                $('#registrationModal').modal('hide');
                showAlert('success', 'Successfully registered for tournament!');
                setTimeout(() => location.reload(), 2000);
            } else {
                showAlert('error', response.message);
            }
        },
        error: function() {
            showAlert('error', 'Registration failed. Please try again.');
        }
    });
}

function createTournament() {
    const formData = new FormData($('#create-tournament-form')[0]);
    
    $.ajax({
        url: 'api/create-tournament.php',
        type: 'POST',
        data: formData,
        processData: false,
        contentType: false,
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                showAlert('success', 'Tournament created successfully!');
                $('#create-tournament-form')[0].reset();
                setTimeout(() => {
                    $('#active-tab').click();
                    location.reload();
                }, 2000);
            } else {
                showAlert('error', response.message);
            }
        },
        error: function() {
            showAlert('error', 'Failed to create tournament. Please try again.');
        }
    });
}

function loadPetBattleStats(petId) {
    $.get(`api/get-pet-battle-stats.php?pet_id=${petId}`, function(response) {
        if (response.success) {
            const stats = response.stats;
            $('#pet-elo').text(stats.elo_rating || 1200);
            
            const winRate = stats.total_battles > 0 ? 
                ((stats.wins / stats.total_battles) * 100).toFixed(1) : '0.0';
            $('#pet-winrate').text(winRate + '%');
            
            $('#pet-battle-stats').show();
        }
    });
}

function loadTournamentHistory() {
    $.get('api/get-tournament-history.php', function(response) {
        if (response.success) {
            let html = '';
            
            if (response.tournaments.length === 0) {
                html = `
                    <div class="alert alert-info text-center">
                        <h5>📜 No Tournament History</h5>
                        <p>You haven't participated in any tournaments yet.</p>
                    </div>
                `;
            } else {
                html = '<div class="row">';
                response.tournaments.forEach(tournament => {
                    const statusBadge = getStatusBadge(tournament.status);
                    const placementBadge = tournament.placement ? 
                        `<span class="badge badge-warning">Placed #${tournament.placement}</span>` : '';
                    
                    html += `
                        <div class="col-md-6 mb-3">
                            <div class="card">
                                <div class="card-header d-flex justify-content-between">
                                    <h6>${tournament.tournament_name}</h6>
                                    ${statusBadge}
                                </div>
                                <div class="card-body">
                                    <div class="tournament-info">
                                        <div class="info-row">
                                            <span class="info-label">🎮 Game Mode:</span>
                                            <span>${tournament.game_mode.replace('_', ' ')}</span>
                                        </div>
                                        <div class="info-row">
                                            <span class="info-label">🐾 Pet:</span>
                                            <span>${tournament.pet_name}</span>
                                        </div>
                                        <div class="info-row">
                                            <span class="info-label">📅 Date:</span>
                                            <span>${new Date(tournament.created_at).toLocaleDateString()}</span>
                                        </div>
                                        ${placementBadge}
                                    </div>
                                </div>
                            </div>
                        </div>
                    `;
                });
                html += '</div>';
            }
            
            $('#tournament-history').html(html);
        }
    });
}

function loadLeaderboards() {
    $.get('api/get-tournament-leaderboards.php', function(response) {
        if (response.success) {
            let html = `
                <div class="row">
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h5>🏆 Top Tournament Winners</h5>
                            </div>
                            <div class="card-body">
                                <div class="leaderboard-list">
            `;
            
            response.top_winners.forEach((winner, index) => {
                const medal = index === 0 ? '🥇' : index === 1 ? '🥈' : index === 2 ? '🥉' : `#${index + 1}`;
                html += `
                    <div class="leaderboard-item">
                        <span class="rank">${medal}</span>
                        <span class="name">${winner.name}</span>
                        <span class="score">${winner.tournament_wins} wins</span>
                    </div>
                `;
            });
            
            html += `
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h5>⚡ Highest ELO Ratings</h5>
                            </div>
                            <div class="card-body">
                                <div class="leaderboard-list">
            `;
            
            response.top_elo.forEach((player, index) => {
                const medal = index === 0 ? '🥇' : index === 1 ? '🥈' : index === 2 ? '🥉' : `#${index + 1}`;
                html += `
                    <div class="leaderboard-item">
                        <span class="rank">${medal}</span>
                        <span class="name">${player.pet_name}</span>
                        <span class="score">${player.elo_rating} ELO</span>
                    </div>
                `;
            });
            
            html += `
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            
            $('#tournament-leaderboards').html(html);
        }
    });
}

function viewTournament(tournamentId) {
    window.location.href = `tournament-view.php?id=${tournamentId}`;
}

function getStatusBadge(status) {
    const badges = {
        'registration': '<span class="badge badge-success">Registration Open</span>',
        'in_progress': '<span class="badge badge-warning">In Progress</span>',
        'completed': '<span class="badge badge-primary">Completed</span>',
        'cancelled': '<span class="badge badge-danger">Cancelled</span>'
    };
    return badges[status] || '<span class="badge badge-secondary">Unknown</span>';
}

function showAlert(type, message) {
    const alertClass = type === 'success' ? 'alert-success' : 'alert-danger';
    const alertHtml = `
        <div class="alert ${alertClass} alert-dismissible fade show" role="alert">
            ${message}
            <button type="button" class="close" data-dismiss="alert">
                <span>&times;</span>
            </button>
        </div>
    `;
    
    // Show alert at top of page
    $('body').prepend(alertHtml);
    
    // Auto-dismiss after 5 seconds
    setTimeout(() => {
        $('.alert').fadeOut();
    }, 5000);
}
