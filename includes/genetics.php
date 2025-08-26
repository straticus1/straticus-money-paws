<?php
/**
 * Money Paws - Pet Genetics Engine
 * Developed and Designed by Ryan Coleman. <coleman.ryan@gmail.com>
 */

const DNA_LENGTH = 50;
const MUTATION_RATE = 0.01; // 1% chance of mutation per gene

/**
 * Generates a random DNA string for a new pet.
 * @return string
 */
function generate_dna(): string {
    $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $dna = '';
    for ($i = 0; $i < DNA_LENGTH; $i++) {
        $dna .= $characters[rand(0, strlen($characters) - 1)];
    }
    return $dna;
}

/**
 * Combines the DNA of two parents to create an offspring's DNA.
 * @param string $dna1 Mother's DNA
 * @param string $dna2 Father's DNA
 * @return string The offspring's DNA
 */
function combine_dna(string $dna1, string $dna2): string {
    $offspring_dna = '';
    for ($i = 0; $i < DNA_LENGTH; $i++) {
        // Randomly pick the gene from one of the parents
        $offspring_dna .= (rand(0, 1) === 0) ? $dna1[$i] : $dna2[$i];
    }
    return $offspring_dna;
}

/**
 * Applies random mutations to a DNA string.
 * @param string $dna The original DNA string
 * @return string The mutated DNA string
 */
function mutate_dna(string $dna): string {
    $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    for ($i = 0; $i < DNA_LENGTH; $i++) {
        if ((mt_rand() / mt_getrandmax()) < MUTATION_RATE) {
            // Mutate this gene
            $dna[$i] = $characters[rand(0, strlen($characters) - 1)];
        }
    }
    return $dna;
}

/**
 * Breeds two pets to create a new one.
 * @param string $mother_dna
 * @param string $father_dna
 * @return string The new pet's DNA
 */
function breed_pets(string $mother_dna, string $father_dna): string {
    $offspring_dna = combine_dna($mother_dna, $father_dna);
    $mutated_dna = mutate_dna($offspring_dna);
    return $mutated_dna;
}
