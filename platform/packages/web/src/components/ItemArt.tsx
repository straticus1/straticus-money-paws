import { useState } from 'preact/hooks';
import { itemArtwork } from '../lib/art';
import './item-art.css';

/** Decorative thumbnail; the caller supplies the visible item name or button label. */
export function ItemArt({ name, emoji = '▣' }: { name: string; emoji?: string | undefined }) {
  const source = itemArtwork.get(name);
  const [failedSource, setFailedSource] = useState<string>();
  return <span class="item-art" aria-hidden="true">{source && source !== failedSource
    ? <img src={source} alt="" loading="lazy" decoding="async" draggable={false} onError={() => setFailedSource(source)} />
    : emoji}</span>;
}
