import { StrictMode } from 'react';
import { createRoot } from 'react-dom/client';
import App from './App';
import { initLegaturi } from './lib/legaturi';
import { initPlayer } from './lib/player';
import './styles.css';

void initPlayer();
void initLegaturi();

createRoot(document.getElementById('root')!).render(
  <StrictMode>
    <App />
  </StrictMode>,
);
