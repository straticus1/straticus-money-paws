<?php
/**
 * Money Paws - Tournament Engine
 * Developed and Designed by Ryan Coleman. <coleman.ryan@gmail.com>
 */

require_once 'functions.php';

/**
 * Tournament management and matchmaking system
 */
class TournamentEngine {
    
    private $pdo;
    
    public function __construct() {
        $this->pdo = get_db();
    }
    
    /**
     * Create a new tournament
     */
    public function createTournament($creator_user_id, $tournament_data) {
        $stmt = $this->pdo->prepare("
            INSERT INTO tournaments (
                tournament_name, tournament_type, game_mode, entry_fee_usd, 
                max_participants, registration_deadline, start_time, created_by_user_id
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $tournament_data['name'],
            $tournament_data['type'],
            $tournament_data['game_mode'],
            $tournament_data['entry_fee'],
            $tournament_data['max_participants'],
            $tournament_data['registration_deadline'],
            $tournament_data['start_time'],
            $creator_user_id
        ]);
        
        return $this->pdo->lastInsertId();
    }
    
    /**
     * Register a pet for tournament
     */
    public function registerForTournament($tournament_id, $user_id, $pet_id, $payment_data) {
        // Check if tournament is open for registration
        $tournament = $this->getTournament($tournament_id);
        if (!$tournament || $tournament['status'] !== 'registration') {
            throw new Exception('Tournament is not open for registration');
        }
        
        // Check if user already registered
        $stmt = $this->pdo->prepare("
            SELECT id FROM tournament_participants 
            WHERE tournament_id = ? AND user_id = ?
        ");
        $stmt->execute([$tournament_id, $user_id]);
        if ($stmt->fetch()) {
            throw new Exception('Already registered for this tournament');
        }
        
        // Check if tournament is full
        if ($tournament['current_participants'] >= $tournament['max_participants']) {
            throw new Exception('Tournament is full');
        }
        
        // Verify pet ownership and battle readiness
        $pet = $this->getPetForTournament($pet_id, $user_id);
        if (!$pet) {
            throw new Exception('Pet not found or not eligible');
        }
        
        // Process payment and register
        $this->pdo->beginTransaction();
        try {
            // Register participant
            $stmt = $this->pdo->prepare("
                INSERT INTO tournament_participants (
                    tournament_id, user_id, pet_id, entry_fee_paid, 
                    crypto_type, crypto_amount
                ) VALUES (?, ?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $tournament_id,
                $user_id,
                $pet_id,
                $payment_data['usd_amount'],
                $payment_data['crypto_type'],
                $payment_data['crypto_amount']
            ]);
            
            $participant_id = $this->pdo->lastInsertId();
            
            // Update tournament participant count and prize pool
            $stmt = $this->pdo->prepare("
                UPDATE tournaments 
                SET current_participants = current_participants + 1,
                    prize_pool_usd = prize_pool_usd + ?
                WHERE id = ?
            ");
            $stmt->execute([$payment_data['usd_amount'], $tournament_id]);
            
            // Initialize battle stats if needed
            $this->initializeBattleStats($pet_id);
            
            $this->pdo->commit();
            return $participant_id;
            
        } catch (Exception $e) {
            $this->pdo->rollback();
            throw $e;
        }
    }
    
    /**
     * Start tournament and generate bracket
     */
    public function startTournament($tournament_id) {
        $tournament = $this->getTournament($tournament_id);
        if (!$tournament || $tournament['status'] !== 'registration') {
            throw new Exception('Tournament cannot be started');
        }
        
        // Get all participants
        $participants = $this->getTournamentParticipants($tournament_id);
        if (count($participants) < 2) {
            throw new Exception('Not enough participants to start tournament');
        }
        
        $this->pdo->beginTransaction();
        try {
            // Update tournament status
            $stmt = $this->pdo->prepare("
                UPDATE tournaments 
                SET status = 'in_progress', start_time = NOW() 
                WHERE id = ?
            ");
            $stmt->execute([$tournament_id]);
            
            // Generate bracket based on tournament type
            switch ($tournament['tournament_type']) {
                case 'single_elimination':
                    $this->generateSingleEliminationBracket($tournament_id, $participants);
                    break;
                case 'round_robin':
                    $this->generateRoundRobinMatches($tournament_id, $participants);
                    break;
                default:
                    throw new Exception('Tournament type not supported yet');
            }
            
            $this->pdo->commit();
            
            // Log tournament start event
            $this->logTournamentEvent($tournament_id, 'tournament_start', [
                'participant_count' => count($participants),
                'prize_pool' => $tournament['prize_pool_usd']
            ]);
            
        } catch (Exception $e) {
            $this->pdo->rollback();
            throw $e;
        }
    }
    
    /**
     * Generate single elimination bracket
     */
    private function generateSingleEliminationBracket($tournament_id, $participants) {
        // Seed participants by ELO rating
        usort($participants, function($a, $b) {
            return $b['elo_rating'] <=> $a['elo_rating'];
        });
        
        // Assign seed numbers
        foreach ($participants as $index => $participant) {
            $stmt = $this->pdo->prepare("
                UPDATE tournament_participants 
                SET seed_number = ? 
                WHERE id = ?
            ");
            $stmt->execute([$index + 1, $participant['id']]);
        }
        
        // Generate first round matches
        $round = 1;
        $match_number = 1;
        
        for ($i = 0; $i < count($participants); $i += 2) {
            if (isset($participants[$i + 1])) {
                $this->createMatch(
                    $tournament_id,
                    $round,
                    $match_number++,
                    $participants[$i]['id'],
                    $participants[$i + 1]['id']
                );
            } else {
                // Bye - participant advances automatically
                $stmt = $this->pdo->prepare("
                    UPDATE tournament_participants 
                    SET current_round = 2 
                    WHERE id = ?
                ");
                $stmt->execute([$participants[$i]['id']]);
            }
        }
    }
    
    /**
     * Generate round robin matches
     */
    private function generateRoundRobinMatches($tournament_id, $participants) {
        $round = 1;
        $match_number = 1;
        
        // Every participant plays every other participant once
        for ($i = 0; $i < count($participants); $i++) {
            for ($j = $i + 1; $j < count($participants); $j++) {
                $this->createMatch(
                    $tournament_id,
                    $round,
                    $match_number++,
                    $participants[$i]['id'],
                    $participants[$j]['id']
                );
            }
        }
    }
    
    /**
     * Create a tournament match
     */
    private function createMatch($tournament_id, $round, $match_number, $participant_a_id, $participant_b_id) {
        $stmt = $this->pdo->prepare("
            INSERT INTO tournament_matches (
                tournament_id, round_number, match_number, 
                participant_a_id, participant_b_id, match_status
            ) VALUES (?, ?, ?, ?, ?, 'scheduled')
        ");
        
        $stmt->execute([
            $tournament_id,
            $round,
            $match_number,
            $participant_a_id,
            $participant_b_id
        ]);
        
        return $this->pdo->lastInsertId();
    }
    
    /**
     * Simulate a battle between two pets
     */
    public function simulateBattle($pet_a_id, $pet_b_id, $game_mode = 'pet_battle') {
        $pet_a_stats = $this->getBattleStats($pet_a_id);
        $pet_b_stats = $this->getBattleStats($pet_b_id);
        
        switch ($game_mode) {
            case 'pet_battle':
                return $this->simulatePetBattle($pet_a_stats, $pet_b_stats);
            case 'racing':
                return $this->simulateRace($pet_a_stats, $pet_b_stats);
            case 'agility':
                return $this->simulateAgility($pet_a_stats, $pet_b_stats);
            case 'beauty_contest':
                return $this->simulateBeautyContest($pet_a_stats, $pet_b_stats);
            case 'intelligence':
                return $this->simulateIntelligence($pet_a_stats, $pet_b_stats);
            default:
                return $this->simulatePetBattle($pet_a_stats, $pet_b_stats);
        }
    }
    
    /**
     * Simulate pet battle
     */
    private function simulatePetBattle($pet_a, $pet_b) {
        $rounds = 0;
        $max_rounds = 10;
        
        $hp_a = 100;
        $hp_b = 100;
        
        $battle_log = [];
        
        while ($hp_a > 0 && $hp_b > 0 && $rounds < $max_rounds) {
            $rounds++;
            
            // Pet A attacks
            $damage_a = max(1, $pet_a['attack_power'] - $pet_b['defense_power'] + rand(-10, 10));
            $hp_b -= $damage_a;
            $battle_log[] = "Round $rounds: {$pet_a['pet_name']} deals $damage_a damage";
            
            if ($hp_b <= 0) break;
            
            // Pet B attacks
            $damage_b = max(1, $pet_b['attack_power'] - $pet_a['defense_power'] + rand(-10, 10));
            $hp_a -= $damage_b;
            $battle_log[] = "Round $rounds: {$pet_b['pet_name']} deals $damage_b damage";
        }
        
        $winner = $hp_a > $hp_b ? 'a' : 'b';
        $score_a = max(0, $hp_a);
        $score_b = max(0, $hp_b);
        
        return [
            'winner' => $winner,
            'score_a' => $score_a,
            'score_b' => $score_b,
            'battle_log' => $battle_log,
            'rounds' => $rounds
        ];
    }
    
    /**
     * Simulate race
     */
    private function simulateRace($pet_a, $pet_b) {
        $speed_a = $pet_a['speed'] + $pet_a['agility'] + rand(-15, 15);
        $speed_b = $pet_b['speed'] + $pet_b['agility'] + rand(-15, 15);
        
        $winner = $speed_a > $speed_b ? 'a' : 'b';
        
        return [
            'winner' => $winner,
            'score_a' => $speed_a,
            'score_b' => $speed_b,
            'battle_log' => [
                "{$pet_a['pet_name']} speed: $speed_a",
                "{$pet_b['pet_name']} speed: $speed_b"
            ]
        ];
    }
    
    /**
     * Process match result
     */
    public function processMatchResult($match_id, $result) {
        $match = $this->getMatch($match_id);
        if (!$match || $match['match_status'] !== 'scheduled') {
            throw new Exception('Match cannot be processed');
        }
        
        $this->pdo->beginTransaction();
        try {
            // Update match result
            $winner_id = $result['winner'] === 'a' ? $match['participant_a_id'] : $match['participant_b_id'];
            $loser_id = $result['winner'] === 'a' ? $match['participant_b_id'] : $match['participant_a_id'];
            
            $stmt = $this->pdo->prepare("
                UPDATE tournament_matches 
                SET winner_participant_id = ?, loser_participant_id = ?, 
                    score_a = ?, score_b = ?, match_status = 'completed',
                    match_data = ?, completed_at = NOW()
                WHERE id = ?
            ");
            
            $stmt->execute([
                $winner_id,
                $loser_id,
                $result['score_a'],
                $result['score_b'],
                json_encode($result),
                $match_id
            ]);
            
            // Update participant records
            $stmt = $this->pdo->prepare("
                UPDATE tournament_participants 
                SET total_wins = total_wins + 1 
                WHERE id = ?
            ");
            $stmt->execute([$winner_id]);
            
            $stmt = $this->pdo->prepare("
                UPDATE tournament_participants 
                SET total_losses = total_losses + 1 
                WHERE id = ?
            ");
            $stmt->execute([$loser_id]);
            
            // Update ELO ratings
            $this->updateEloRatings($match['participant_a_id'], $match['participant_b_id'], $result['winner']);
            
            // Check if tournament round is complete
            $this->checkRoundCompletion($match['tournament_id'], $match['round_number']);
            
            $this->pdo->commit();
            
        } catch (Exception $e) {
            $this->pdo->rollback();
            throw $e;
        }
    }
    
    /**
     * Update ELO ratings after match
     */
    private function updateEloRatings($participant_a_id, $participant_b_id, $winner) {
        $participant_a = $this->getTournamentParticipant($participant_a_id);
        $participant_b = $this->getTournamentParticipant($participant_b_id);
        
        $elo_a = $this->getBattleStats($participant_a['pet_id'])['elo_rating'];
        $elo_b = $this->getBattleStats($participant_b['pet_id'])['elo_rating'];
        
        $expected_a = 1 / (1 + pow(10, ($elo_b - $elo_a) / 400));
        $expected_b = 1 / (1 + pow(10, ($elo_a - $elo_b) / 400));
        
        $score_a = $winner === 'a' ? 1 : 0;
        $score_b = $winner === 'b' ? 1 : 0;
        
        $k_factor = 32; // Standard ELO K-factor
        
        $new_elo_a = round($elo_a + $k_factor * ($score_a - $expected_a));
        $new_elo_b = round($elo_b + $k_factor * ($score_b - $expected_b));
        
        // Update ELO ratings
        $stmt = $this->pdo->prepare("
            UPDATE pet_battle_stats 
            SET elo_rating = ?, last_battle_at = NOW(),
                total_battles = total_battles + 1,
                wins = wins + ?,
                losses = losses + ?
            WHERE pet_id = ?
        ");
        
        $stmt->execute([$new_elo_a, $score_a, 1 - $score_a, $participant_a['pet_id']]);
        $stmt->execute([$new_elo_b, $score_b, 1 - $score_b, $participant_b['pet_id']]);
    }
    
    /**
     * Distribute tournament prizes
     */
    public function distributePrizes($tournament_id) {
        $tournament = $this->getTournament($tournament_id);
        if ($tournament['status'] !== 'completed') {
            throw new Exception('Tournament not completed');
        }
        
        $participants = $this->getFinalStandings($tournament_id);
        $total_prize_pool = $tournament['prize_pool_usd'];
        
        // Prize distribution percentages
        $prize_distribution = [
            1 => 0.50, // 50% to winner
            2 => 0.25, // 25% to second place
            3 => 0.15, // 15% to third place
            4 => 0.10  // 10% to fourth place
        ];
        
        foreach ($participants as $index => $participant) {
            $placement = $index + 1;
            if (isset($prize_distribution[$placement])) {
                $prize_amount = $total_prize_pool * $prize_distribution[$placement];
                
                $stmt = $this->pdo->prepare("
                    INSERT INTO tournament_prizes (
                        tournament_id, placement, participant_id, 
                        prize_amount_usd, crypto_type, crypto_amount
                    ) VALUES (?, ?, ?, ?, 'USDC', ?)
                ");
                
                $stmt->execute([
                    $tournament_id,
                    $placement,
                    $participant['id'],
                    $prize_amount,
                    $prize_amount // 1:1 USDC conversion for simplicity
                ]);
                
                // Update user balance
                $this->updateUserBalance($participant['user_id'], 'USDC', $prize_amount);
            }
        }
    }
    
    /**
     * Get tournament details
     */
    public function getTournament($tournament_id) {
        $stmt = $this->pdo->prepare("SELECT * FROM tournaments WHERE id = ?");
        $stmt->execute([$tournament_id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get tournament participants
     */
    public function getTournamentParticipants($tournament_id) {
        $stmt = $this->pdo->prepare("
            SELECT tp.*, p.original_name as pet_name, pbs.elo_rating
            FROM tournament_participants tp
            JOIN pets p ON tp.pet_id = p.id
            LEFT JOIN pet_battle_stats pbs ON p.id = pbs.pet_id
            WHERE tp.tournament_id = ?
            ORDER BY tp.seed_number
        ");
        $stmt->execute([$tournament_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get battle stats for a pet
     */
    private function getBattleStats($pet_id) {
        $stmt = $this->pdo->prepare("
            SELECT pbs.*, p.original_name as pet_name
            FROM pet_battle_stats pbs
            JOIN pets p ON pbs.pet_id = p.id
            WHERE pbs.pet_id = ?
        ");
        $stmt->execute([$pet_id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * Initialize battle stats for a pet
     */
    private function initializeBattleStats($pet_id) {
        $stmt = $this->pdo->prepare("
            INSERT IGNORE INTO pet_battle_stats (pet_id) VALUES (?)
        ");
        $stmt->execute([$pet_id]);
    }
    
    /**
     * Log tournament event
     */
    private function logTournamentEvent($tournament_id, $event_type, $data) {
        $stmt = $this->pdo->prepare("
            INSERT INTO tournament_events (tournament_id, event_type, event_data)
            VALUES (?, ?, ?)
        ");
        $stmt->execute([$tournament_id, $event_type, json_encode($data)]);
    }
    
    // Additional helper methods...
    private function getMatch($match_id) {
        $stmt = $this->pdo->prepare("SELECT * FROM tournament_matches WHERE id = ?");
        $stmt->execute([$match_id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    private function getTournamentParticipant($participant_id) {
        $stmt = $this->pdo->prepare("SELECT * FROM tournament_participants WHERE id = ?");
        $stmt->execute([$participant_id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    private function getPetForTournament($pet_id, $user_id) {
        $stmt = $this->pdo->prepare("
            SELECT * FROM pets 
            WHERE id = ? AND user_id = ? AND life_status = 'alive'
        ");
        $stmt->execute([$pet_id, $user_id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    private function checkRoundCompletion($tournament_id, $round_number) {
        // Implementation for checking if round is complete and advancing tournament
        // This would handle bracket progression logic
    }
    
    private function getFinalStandings($tournament_id) {
        // Implementation for getting final tournament standings
        $stmt = $this->pdo->prepare("
            SELECT * FROM tournament_participants 
            WHERE tournament_id = ? 
            ORDER BY total_wins DESC, total_losses ASC
        ");
        $stmt->execute([$tournament_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    private function updateUserBalance($user_id, $crypto_type, $amount) {
        $stmt = $this->pdo->prepare("
            INSERT INTO user_balances (user_id, crypto_type, balance)
            VALUES (?, ?, ?)
            ON DUPLICATE KEY UPDATE balance = balance + VALUES(balance)
        ");
        $stmt->execute([$user_id, $crypto_type, $amount]);
    }
}
?>
