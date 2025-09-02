<?php
/**
 * Money Paws - Advanced AI Pet Genetics Engine
 * Developed and Designed by Ryan Coleman. <coleman.ryan@gmail.com>
 */

require_once 'functions.php';

/**
 * Advanced genetic algorithm for trait inheritance
 */
class AdvancedGeneticsEngine {
    
    private $pdo;
    
    public function __construct() {
        $this->pdo = get_db();
    }
    
    /**
     * Generate traits for a new AI-created pet
     */
    public function generateAITraits($species, $rarity_boost = 0) {
        $traits = [];
        $trait_count = rand(3, 7); // Random number of traits
        
        // Get available traits for this species
        $stmt = $this->pdo->prepare("
            SELECT * FROM pet_traits 
            WHERE trait_category IN ('physical', 'behavioral', 'special') 
            ORDER BY RAND() 
            LIMIT ?
        ");
        $stmt->execute([$trait_count * 2]); // Get more than needed for selection
        $available_traits = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($available_traits as $trait) {
            if (count($traits) >= $trait_count) break;
            
            // Calculate probability based on rarity
            $base_probability = $this->getRarityProbability($trait['rarity']);
            $final_probability = min(0.95, $base_probability + ($rarity_boost / 100));
            
            if (mt_rand() / mt_getrandmax() < $final_probability) {
                $traits[] = [
                    'trait_id' => $trait['id'],
                    'expression_strength' => $this->getRandomExpression(),
                    'inherited_from' => 'ai_generated'
                ];
            }
        }
        
        return $traits;
    }
    
    /**
     * Advanced breeding algorithm with trait inheritance
     */
    public function breedPetsAdvanced($mother_id, $father_id, $enhancement_level = 'natural') {
        // Get parent traits
        $mother_traits = $this->getPetTraits($mother_id);
        $father_traits = $this->getPetTraits($father_id);
        
        // Calculate genetic diversity
        $diversity_score = $this->calculateGeneticDiversity($mother_traits, $father_traits);
        
        // Determine breeding success probability
        $success_probability = $this->calculateBreedingSuccess($mother_id, $father_id, $diversity_score, $enhancement_level);
        
        // Generate offspring traits
        $offspring_traits = $this->inheritTraits($mother_traits, $father_traits, $enhancement_level);
        
        // Apply mutations
        $offspring_traits = $this->applyMutations($offspring_traits, $enhancement_level);
        
        return [
            'traits' => $offspring_traits,
            'success_probability' => $success_probability,
            'diversity_score' => $diversity_score,
            'mutation_events' => $this->mutation_count ?? 0
        ];
    }
    
    /**
     * Calculate genetic diversity between two parents
     */
    private function calculateGeneticDiversity($mother_traits, $father_traits) {
        $mother_trait_ids = array_column($mother_traits, 'trait_id');
        $father_trait_ids = array_column($father_traits, 'trait_id');
        
        $common_traits = array_intersect($mother_trait_ids, $father_trait_ids);
        $total_unique_traits = count(array_unique(array_merge($mother_trait_ids, $father_trait_ids)));
        
        if ($total_unique_traits == 0) return 0.5;
        
        $diversity = 1 - (count($common_traits) / $total_unique_traits);
        return round($diversity, 2);
    }
    
    /**
     * Calculate breeding success probability
     */
    private function calculateBreedingSuccess($mother_id, $father_id, $diversity_score, $enhancement_level) {
        $base_success = 0.75; // 75% base success rate
        
        // Diversity bonus (more diverse = higher success)
        $diversity_bonus = $diversity_score * 0.20;
        
        // Enhancement bonuses
        $enhancement_bonus = 0;
        switch ($enhancement_level) {
            case 'ai_assisted':
                $enhancement_bonus = 0.15;
                break;
            case 'genetic_enhancement':
                $enhancement_bonus = 0.25;
                break;
        }
        
        // Pet health and happiness factors
        $mother_stats = $this->getPetStats($mother_id);
        $father_stats = $this->getPetStats($father_id);
        
        $health_bonus = (($mother_stats['happiness_level'] + $father_stats['happiness_level']) / 200) * 0.10;
        
        $final_probability = min(0.95, $base_success + $diversity_bonus + $enhancement_bonus + $health_bonus);
        return round($final_probability, 2);
    }
    
    /**
     * Inherit traits from parents using genetic algorithms
     */
    private function inheritTraits($mother_traits, $father_traits, $enhancement_level) {
        $offspring_traits = [];
        $all_parent_traits = array_merge($mother_traits, $father_traits);
        
        // Group traits by trait_id to handle inheritance
        $trait_groups = [];
        foreach ($all_parent_traits as $trait) {
            $trait_groups[$trait['trait_id']][] = $trait;
        }
        
        foreach ($trait_groups as $trait_id => $parent_versions) {
            $trait_info = $this->getTraitInfo($trait_id);
            
            // Determine if trait is inherited based on dominance
            $inherit_probability = $this->calculateInheritanceProbability($trait_info, $parent_versions, $enhancement_level);
            
            if (mt_rand() / mt_getrandmax() < $inherit_probability) {
                // Determine expression strength
                $expression = $this->determineExpression($parent_versions, $trait_info);
                
                $offspring_traits[] = [
                    'trait_id' => $trait_id,
                    'expression_strength' => $expression['strength'],
                    'inherited_from' => $expression['source']
                ];
            }
        }
        
        // Add chance for completely new traits (rare)
        if ($enhancement_level === 'genetic_enhancement' && mt_rand() / mt_getrandmax() < 0.15) {
            $new_trait = $this->generateRandomTrait('rare');
            if ($new_trait) {
                $offspring_traits[] = $new_trait;
            }
        }
        
        return $offspring_traits;
    }
    
    /**
     * Apply genetic mutations
     */
    private function applyMutations($traits, $enhancement_level) {
        $this->mutation_count = 0;
        $mutation_rate = 0.05; // 5% base mutation rate
        
        if ($enhancement_level === 'genetic_enhancement') {
            $mutation_rate = 0.12; // Higher mutation rate with enhancement
        }
        
        foreach ($traits as &$trait) {
            if (mt_rand() / mt_getrandmax() < $mutation_rate) {
                // Mutate expression strength
                $strengths = ['weak', 'moderate', 'strong'];
                $current_index = array_search($trait['expression_strength'], $strengths);
                
                // 50% chance to go up or down
                if (mt_rand() / mt_getrandmax() < 0.5 && $current_index < 2) {
                    $trait['expression_strength'] = $strengths[$current_index + 1];
                } elseif ($current_index > 0) {
                    $trait['expression_strength'] = $strengths[$current_index - 1];
                }
                
                $trait['inherited_from'] = 'mutation';
                $this->mutation_count++;
            }
        }
        
        // Chance for completely new mutated trait
        if (mt_rand() / mt_getrandmax() < ($mutation_rate / 2)) {
            $new_trait = $this->generateRandomTrait('uncommon');
            if ($new_trait) {
                $new_trait['inherited_from'] = 'mutation';
                $traits[] = $new_trait;
                $this->mutation_count++;
            }
        }
        
        return $traits;
    }
    
    /**
     * Get pet traits from database
     */
    private function getPetTraits($pet_id) {
        $stmt = $this->pdo->prepare("
            SELECT pta.*, pt.trait_name, pt.rarity, pt.genetic_dominance 
            FROM pet_trait_assignments pta
            JOIN pet_traits pt ON pta.trait_id = pt.id
            WHERE pta.pet_id = ?
        ");
        $stmt->execute([$pet_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get trait information
     */
    private function getTraitInfo($trait_id) {
        $stmt = $this->pdo->prepare("SELECT * FROM pet_traits WHERE id = ?");
        $stmt->execute([$trait_id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get pet stats for breeding calculations
     */
    private function getPetStats($pet_id) {
        $stmt = $this->pdo->prepare("SELECT * FROM pet_stats WHERE pet_id = ?");
        $stmt->execute([$pet_id]);
        $stats = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$stats) {
            return ['happiness_level' => 50, 'hunger_level' => 50];
        }
        
        return $stats;
    }
    
    /**
     * Calculate inheritance probability based on genetic dominance
     */
    private function calculateInheritanceProbability($trait_info, $parent_versions, $enhancement_level) {
        $base_probability = 0.5; // 50% base chance
        
        // Adjust based on genetic dominance
        switch ($trait_info['genetic_dominance']) {
            case 'dominant':
                $base_probability = 0.75;
                break;
            case 'recessive':
                $base_probability = count($parent_versions) > 1 ? 0.25 : 0.10; // Need both parents for recessive
                break;
            case 'codominant':
                $base_probability = 0.60;
                break;
        }
        
        // Enhancement bonus
        if ($enhancement_level === 'ai_assisted') {
            $base_probability += 0.10;
        } elseif ($enhancement_level === 'genetic_enhancement') {
            $base_probability += 0.20;
        }
        
        return min(0.95, $base_probability);
    }
    
    /**
     * Determine expression strength and source
     */
    private function determineExpression($parent_versions, $trait_info) {
        if (count($parent_versions) === 1) {
            // Only one parent has this trait
            return [
                'strength' => $parent_versions[0]['expression_strength'],
                'source' => $parent_versions[0]['inherited_from'] === 'mother' ? 'mother' : 'father'
            ];
        }
        
        // Both parents have this trait - blend or choose dominant
        $strengths = ['weak' => 1, 'moderate' => 2, 'strong' => 3];
        $avg_strength = 0;
        $source = mt_rand() / mt_getrandmax() < 0.5 ? 'mother' : 'father';
        
        foreach ($parent_versions as $version) {
            $avg_strength += $strengths[$version['expression_strength']];
        }
        
        $avg_strength = round($avg_strength / count($parent_versions));
        $strength_names = array_flip($strengths);
        
        return [
            'strength' => $strength_names[$avg_strength],
            'source' => $source
        ];
    }
    
    /**
     * Generate a random trait for mutations
     */
    private function generateRandomTrait($min_rarity = 'common') {
        $rarity_order = ['common', 'uncommon', 'rare', 'epic', 'legendary'];
        $min_index = array_search($min_rarity, $rarity_order);
        
        $stmt = $this->pdo->prepare("
            SELECT * FROM pet_traits 
            WHERE rarity IN ('" . implode("','", array_slice($rarity_order, $min_index)) . "')
            ORDER BY RAND() 
            LIMIT 1
        ");
        $stmt->execute();
        $trait = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$trait) return null;
        
        return [
            'trait_id' => $trait['id'],
            'expression_strength' => $this->getRandomExpression(),
            'inherited_from' => 'mutation'
        ];
    }
    
    /**
     * Get random expression strength
     */
    private function getRandomExpression() {
        $expressions = ['weak', 'moderate', 'strong'];
        $weights = [0.5, 0.35, 0.15]; // Weighted towards weaker expressions
        
        $rand = mt_rand() / mt_getrandmax();
        $cumulative = 0;
        
        for ($i = 0; $i < count($expressions); $i++) {
            $cumulative += $weights[$i];
            if ($rand <= $cumulative) {
                return $expressions[$i];
            }
        }
        
        return 'moderate';
    }
    
    /**
     * Get rarity probability for AI generation
     */
    private function getRarityProbability($rarity) {
        switch ($rarity) {
            case 'common': return 0.60;
            case 'uncommon': return 0.25;
            case 'rare': return 0.10;
            case 'epic': return 0.04;
            case 'legendary': return 0.01;
            default: return 0.30;
        }
    }
    
    /**
     * Assign traits to a pet
     */
    public function assignTraitsToPet($pet_id, $traits) {
        foreach ($traits as $trait) {
            $stmt = $this->pdo->prepare("
                INSERT INTO pet_trait_assignments (pet_id, trait_id, expression_strength, inherited_from)
                VALUES (?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE 
                expression_strength = VALUES(expression_strength),
                inherited_from = VALUES(inherited_from)
            ");
            
            $stmt->execute([
                $pet_id,
                $trait['trait_id'],
                $trait['expression_strength'],
                $trait['inherited_from']
            ]);
        }
    }
    
    /**
     * Calculate pet market value based on traits
     */
    public function calculatePetValue($pet_id) {
        $stmt = $this->pdo->prepare("
            SELECT pt.market_value_multiplier, pta.expression_strength
            FROM pet_trait_assignments pta
            JOIN pet_traits pt ON pta.trait_id = pt.id
            WHERE pta.pet_id = ?
        ");
        $stmt->execute([$pet_id]);
        $traits = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $base_value = 10.00; // $10 base value
        $total_multiplier = 1.0;
        
        foreach ($traits as $trait) {
            $expression_bonus = 1.0;
            switch ($trait['expression_strength']) {
                case 'weak': $expression_bonus = 0.8; break;
                case 'moderate': $expression_bonus = 1.0; break;
                case 'strong': $expression_bonus = 1.3; break;
            }
            
            $total_multiplier *= ($trait['market_value_multiplier'] * $expression_bonus);
        }
        
        return round($base_value * $total_multiplier, 2);
    }
}
?>
