import { petEmoji } from '../lib/pets';
import type { Companion } from '@paws/core';
import { useState } from 'preact/hooks';
import { petArtwork } from '../lib/art';

const COATS = { honey: '#bd8454', silver: '#a69a9f', cocoa: '#8a624b', cream: '#ede4d0' };
const BANDANAS = { moss: '#315e49', sunflower: '#c39830', berry: '#a05068', midnight: '#485775' };

/** Local, curated artwork only. The classic renderer retains individual coat/marking details. */
export function HomePet({ species, companion, painted = false }: { species: string; companion?: Companion | undefined; painted?: boolean }) {
  const source = petArtwork.get(species);
  const [failedSource, setFailedSource] = useState<string>();
  if (!painted || !source || failedSource === source) return <ClassicHomePet species={species} companion={companion} />;
  return <span class={`home-painted-pet home-painted-pet--${species}`} aria-hidden="true">
    <img src={source} alt="" draggable={false} onError={() => setFailedSource(source)} />
    <svg class="home-painted-bandana" viewBox="0 0 80 35"><path d="M5 3 Q40 13 75 3 L55 30 L40 15 L25 30 Z" fill={BANDANAS[companion?.appearance.bandana ?? 'moss']} /><circle cx="40" cy="13" r="3" fill="#efc76b" /></svg>
  </span>;
}

function ClassicHomePet({ species, companion }: { species: string; companion?: Companion | undefined }) {
  const bandana = BANDANAS[companion?.appearance.bandana ?? 'moss'];
  if (!['dog', 'cat', 'fox', 'rabbit'].includes(species)) {
    return <span class={`home-pet-badge home-pet-badge--${companion?.appearance.coat ?? 'honey'} home-pet-badge--${companion?.appearance.marking ?? 'blaze'}`} aria-hidden="true"><span class="home-pet-emoji">{petEmoji(species)}</span><svg viewBox="0 0 80 35" class="home-badge-bandana"><path d="M5 3 Q40 13 75 3 L55 30 L40 15 L25 30 Z" fill={bandana} /></svg></span>;
  }
  const cat = species === 'cat';
  const rabbit = species === 'rabbit';
  const fox = species === 'fox';
  const coat = companion ? COATS[companion.appearance.coat] : fox ? '#c96936' : cat ? '#a69a86' : rabbit ? '#ede4d0' : '#bd8454';
  return <svg viewBox="0 0 220 210" aria-hidden="true" class="home-pet-art">
    <ellipse cx="110" cy="193" rx="66" ry="10" fill="#263d2924" />
    <g class="home-pet-tail"><path d="M151 153 Q202 108 194 147 Q190 170 155 173" fill={coat} stroke="#674a36" stroke-width="4" /></g>
    <ellipse cx="111" cy="153" rx="51" ry="38" fill={coat} />
    <ellipse cx="111" cy="159" rx="28" ry="29" fill="#f9e8c7" />
    <ellipse cx="76" cy="183" rx="23" ry="12" fill={coat} />
    <ellipse cx="144" cy="183" rx="23" ry="12" fill={coat} />
    {companion?.appearance.marking === 'socks' && <g fill="#faedce"><ellipse cx="76" cy="183" rx="22" ry="10" /><ellipse cx="144" cy="183" rx="22" ry="10" /></g>}
    <g class="home-pet-head">
      {rabbit ? <><ellipse cx="84" cy="50" rx="16" ry="45" fill={coat} transform="rotate(-12 84 50)" /><ellipse cx="139" cy="50" rx="16" ry="45" fill={coat} transform="rotate(12 139 50)" /><ellipse cx="84" cy="48" rx="7" ry="29" fill="#d4a9a0" /><ellipse cx="139" cy="48" rx="7" ry="29" fill="#d4a9a0" /></> : cat || fox ? <><path d="M60 89 L58 28 L99 62 M122 62 L164 28 L162 92" fill={coat} stroke={coat} stroke-width="10" stroke-linejoin="round" /><path d="M66 66 L65 43 L88 64 M135 64 L157 43 L155 69" fill="#d4a9a0" /></> : <><ellipse cx="60" cy="89" rx="24" ry="45" fill="#805339" transform="rotate(16 60 89)" /><ellipse cx="160" cy="89" rx="24" ry="45" fill="#805339" transform="rotate(-16 160 89)" /></>}
      <ellipse cx="110" cy="98" rx="58" ry="49" fill={coat} />
      {companion?.appearance.marking === 'blaze' && <path d="M101 52 Q110 47 119 52 L116 101 L104 101 Z" fill="#faedce" opacity=".85" />}
      {companion?.appearance.marking === 'speckles' && <g fill="#faedce" opacity=".7"><circle cx="69" cy="105" r="6" /><circle cx="79" cy="116" r="4" /><circle cx="151" cy="105" r="6" /><circle cx="141" cy="116" r="4" /><circle cx="101" cy="66" r="5" /></g>}
      <ellipse cx="110" cy="119" rx="32" ry="23" fill="#faedce" />
      <g class="home-pet-eyes" fill="#2f3025"><ellipse cx="86" cy="95" rx="5" ry="7" /><ellipse cx="136" cy="95" rx="5" ry="7" /></g>
      <circle cx="84" cy="93" r="1.5" fill="white" /><circle cx="134" cy="93" r="1.5" fill="white" />
      <path d="M103 111 Q110 106 117 111 Q115 120 110 120 Q105 120 103 111" fill="#493b30" />
      <path d="M110 120 L110 125 M99 126 Q105 132 110 125 Q115 132 122 126" fill="none" stroke="#493b30" stroke-width="2.5" stroke-linecap="round" />
      {companion?.expressions.smile && <path d="M102 130 Q110 146 119 130 Z" fill="#bf6c70" stroke="#493b30" stroke-width="1.5" />}
      {cat && <path d="M55 112 L83 116 M53 122 L81 121 M138 116 L166 112 M140 121 L169 122" stroke="#67594b" stroke-width="2" />}
    </g>
    <path d="M75 138 Q110 153 148 137 L131 161 L109 150 L95 163 Z" fill={bandana} />
    <circle cx="110" cy="151" r="5" fill="#efc76b" />
  </svg>;
}
