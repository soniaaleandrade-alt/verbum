import React from 'react'; import { createRoot } from 'react-dom/client'; import { DiagnosticPage } from './pages/DiagnosticPage';
document.querySelectorAll<HTMLElement>('[data-verbum-app]').forEach((element) => createRoot(element).render(<React.StrictMode><DiagnosticPage /></React.StrictMode>));
