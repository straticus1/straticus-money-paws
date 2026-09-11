// Curated build-time assets: never accept image URLs from inventory or user input.
import squeakyMoon from '../assets/home/squeaky-moon-cutout-v1.webp';
import rollingAcorn from '../assets/home/rolling-acorn-cutout-v1.webp';
import cloverCrunch from '../assets/home/clover-crunch-cutout-v1.webp';
import dog from '../assets/home/dog-cutout-v1.webp';
import cat from '../assets/home/cat-cutout-v1.webp';
import fox from '../assets/home/fox-cutout-v1.webp';
import rabbit from '../assets/home/rabbit-cutout-v1.webp';
// Only alpha-validated sprites belong in this map. Unavailable species use classic art.
export const petArtwork = new Map<string, string>([['dog', dog], ['cat', cat], ['fox', fox], ['rabbit', rabbit]]);
export const petArtDescriptions = new Map<string, string>([
  ['dog', 'The painted puppy has golden fur, floppy ears, dark eyes and a gently tilted head.'],
  ['cat', 'The painted cat has silver tabby stripes, a cream chest, green eyes and a fluffy curled tail.'],
  ['fox', 'The painted fox has rusty-orange fur, upright ears, dark paws and a large cream-tipped tail.'],
  ['rabbit', 'The painted rabbit has soft cocoa fur, long drooping ears, a cream chest and tiny front paws.'],
]);

export const itemArtwork = new Map<string, string>([
  ['Squeaky Moon', squeakyMoon],
  ['Rolling Acorn', rollingAcorn],
  ['Clover Crunch', cloverCrunch],
]);
