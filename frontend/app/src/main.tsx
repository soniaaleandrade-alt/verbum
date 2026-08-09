import React from 'react';
import { createRoot } from 'react-dom/client';
import { VerbumApp } from './pages/VerbumApp';

document.querySelectorAll<HTMLElement>('[data-verbum-app]').forEach((element) => {
  createRoot(element).render(
    <React.StrictMode>
      <VerbumApp />
    </React.StrictMode>,
  );
});
