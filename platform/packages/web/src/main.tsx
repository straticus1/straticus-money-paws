import { render } from 'preact';
import { App } from './app';
import './style.css';

const root = document.getElementById('app');
if (root !== null) {
  render(<App />, root);
}
